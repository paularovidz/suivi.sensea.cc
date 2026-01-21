<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Session;
use App\Models\Person;
use App\Models\SensoryProposal;
use App\Models\User;
use App\Models\LoyaltyCard;
use App\Models\Setting;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Utils\Response;
use App\Utils\Validator;

class SessionController
{
    public function index(): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        if ($isAdmin) {
            $sessions = Session::findAll($limit, $offset);
            $total = Session::count();
        } else {
            $sessions = Session::findByUser($currentUser['id'], $limit, $offset);
            $total = Session::countByUser($currentUser['id']);
        }

        Response::success([
            'sessions' => $sessions,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    public function show(string $id): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        $session = Session::findById($id);

        if (!$session) {
            Response::notFound('Séance non trouvée');
        }

        // Check access
        if (!Session::canAccess($id, $currentUser['id'], $isAdmin)) {
            Response::forbidden('Accès non autorisé');
        }

        Response::success($session);
    }

    public function store(): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Validate required fields
        $validator = new Validator($data);
        $validator
            ->required('person_id')->uuid('person_id')
            ->required('session_date')->datetime('session_date')
            ->required('duration_minutes')->integer('duration_minutes');

        // Validate optional enum fields (only if non-empty)
        if (!empty($data['behavior_start'])) {
            $validator->inArray('behavior_start', Session::BEHAVIORS_START);
        }
        if (!empty($data['proposal_origin'])) {
            $validator->inArray('proposal_origin', Session::PROPOSAL_ORIGINS);
        }
        if (!empty($data['attitude_start'])) {
            $validator->inArray('attitude_start', Session::ATTITUDES_START);
        }
        if (!empty($data['position'])) {
            $validator->inArray('position', Session::POSITIONS);
        }
        if (!empty($data['session_end'])) {
            $validator->inArray('session_end', Session::SESSION_ENDS);
        }
        if (!empty($data['behavior_end'])) {
            $validator->inArray('behavior_end', Session::BEHAVIORS_END);
        }
        if (!empty($data['sessions_per_month'])) {
            $validator->integer('sessions_per_month');
        }

        $validator->validate();

        // Validate communication array
        if (isset($data['communication']) && is_array($data['communication'])) {
            foreach ($data['communication'] as $comm) {
                if (!in_array($comm, Session::COMMUNICATIONS, true)) {
                    Response::validationError(['communication' => 'Valeur de communication invalide: ' . $comm]);
                }
            }
        }

        // Check person exists and access
        $person = Person::findById($data['person_id']);
        if (!$person) {
            Response::notFound('Personne non trouvée');
        }

        if (!$isAdmin && !Person::isAssignedToUser($data['person_id'], $currentUser['id'])) {
            Response::forbidden('Vous n\'êtes pas autorisé à créer une séance pour cette personne');
        }

        // Validate proposals if provided
        if (!empty($data['proposals']) && is_array($data['proposals'])) {
            foreach ($data['proposals'] as $index => $proposal) {
                if (empty($proposal['sensory_proposal_id'])) {
                    Response::validationError(["proposals.{$index}.sensory_proposal_id" => 'ID de proposition requis']);
                }

                // Verify proposal exists and is accessible
                if (!SensoryProposal::canAccess($proposal['sensory_proposal_id'], $currentUser['id'], $isAdmin)) {
                    Response::validationError(["proposals.{$index}.sensory_proposal_id" => 'Proposition non trouvée ou non accessible']);
                }

                if (isset($proposal['appreciation']) && !in_array($proposal['appreciation'], Session::APPRECIATIONS, true)) {
                    Response::validationError(["proposals.{$index}.appreciation" => 'Valeur d\'appréciation invalide']);
                }
            }
        }

        $data['created_by'] = $currentUser['id'];

        // Vérifier la carte de fidélité des utilisateurs assignés à cette personne
        $loyaltyWarning = null;
        $assignedUsers = Person::getAssignedUsers($data['person_id']);
        $sessionsRequired = Setting::getInteger('loyalty_sessions_required', 9);

        foreach ($assignedUsers as $assignedUser) {
            // Seuls les particuliers sont éligibles
            if (User::isPersonalClient($assignedUser['id'])) {
                $loyaltyInfo = LoyaltyCard::getWithProgress($assignedUser['id'], $sessionsRequired);
                if ($loyaltyInfo['eligible'] && $loyaltyInfo['free_session_available']) {
                    $loyaltyWarning = [
                        'user_id' => $assignedUser['id'],
                        'user_name' => $assignedUser['first_name'] . ' ' . $assignedUser['last_name'],
                        'message' => 'Ce client a une séance gratuite disponible sur sa carte de fidélité!'
                    ];
                    break;
                }
            }
        }

        $sessionId = Session::create($data);
        $session = Session::findById($sessionId);

        // Mettre à jour la carte de fidélité si ce n'est pas une séance gratuite
        $isFreeSession = $data['is_free_session'] ?? false;

        foreach ($assignedUsers as $assignedUser) {
            if (User::isPersonalClient($assignedUser['id'])) {
                if ($isFreeSession) {
                    // Marquer la séance gratuite comme utilisée
                    LoyaltyCard::markFreeSessionUsed($assignedUser['id']);
                } else {
                    // Incrémenter le compteur de séances
                    LoyaltyCard::incrementSessions($assignedUser['id'], $sessionsRequired);
                }
            }
        }

        AuditService::log(
            $currentUser['id'],
            'session_created',
            'session',
            $sessionId,
            null,
            ['person_id' => $data['person_id'], 'session_date' => $data['session_date']]
        );

        // Inclure l'alerte de fidélité dans la réponse si applicable
        $response = $session;
        if ($loyaltyWarning) {
            $response['loyalty_warning'] = $loyaltyWarning;
        }

        Response::success($response, 'Séance créée avec succès', 201);
    }

    public function update(string $id): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        $session = Session::findById($id);

        if (!$session) {
            Response::notFound('Séance non trouvée');
        }

        // Check access
        if (!Session::canAccess($id, $currentUser['id'], $isAdmin)) {
            Response::forbidden('Accès non autorisé');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($data);

        if (isset($data['session_date'])) {
            $validator->datetime('session_date');
        }
        if (isset($data['duration_minutes'])) {
            $validator->integer('duration_minutes');
        }
        // Validate optional enum fields (only if non-empty)
        if (!empty($data['behavior_start'])) {
            $validator->inArray('behavior_start', Session::BEHAVIORS_START);
        }
        if (!empty($data['proposal_origin'])) {
            $validator->inArray('proposal_origin', Session::PROPOSAL_ORIGINS);
        }
        if (!empty($data['attitude_start'])) {
            $validator->inArray('attitude_start', Session::ATTITUDES_START);
        }
        if (!empty($data['position'])) {
            $validator->inArray('position', Session::POSITIONS);
        }
        if (!empty($data['session_end'])) {
            $validator->inArray('session_end', Session::SESSION_ENDS);
        }
        if (!empty($data['behavior_end'])) {
            $validator->inArray('behavior_end', Session::BEHAVIORS_END);
        }
        if (!empty($data['sessions_per_month'])) {
            $validator->integer('sessions_per_month');
        }

        $validator->validate();

        // Validate communication array
        if (isset($data['communication']) && is_array($data['communication'])) {
            foreach ($data['communication'] as $comm) {
                if (!in_array($comm, Session::COMMUNICATIONS, true)) {
                    Response::validationError(['communication' => 'Valeur de communication invalide: ' . $comm]);
                }
            }
        }

        // Validate proposals if provided
        if (isset($data['proposals']) && is_array($data['proposals'])) {
            foreach ($data['proposals'] as $index => $proposal) {
                if (empty($proposal['sensory_proposal_id'])) {
                    Response::validationError(["proposals.{$index}.sensory_proposal_id" => 'ID de proposition requis']);
                }

                if (!SensoryProposal::canAccess($proposal['sensory_proposal_id'], $currentUser['id'], $isAdmin)) {
                    Response::validationError(["proposals.{$index}.sensory_proposal_id" => 'Proposition non trouvée ou non accessible']);
                }

                if (isset($proposal['appreciation']) && !in_array($proposal['appreciation'], Session::APPRECIATIONS, true)) {
                    Response::validationError(["proposals.{$index}.appreciation" => 'Valeur d\'appréciation invalide']);
                }
            }
        }

        // Don't allow changing person_id
        unset($data['person_id'], $data['created_by']);

        Session::update($id, $data);
        $updatedSession = Session::findById($id);

        AuditService::log(
            $currentUser['id'],
            'session_updated',
            'session',
            $id
        );

        Response::success($updatedSession, 'Séance mise à jour');
    }

    public function destroy(string $id): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        $session = Session::findById($id);

        if (!$session) {
            Response::notFound('Séance non trouvée');
        }

        // Check access
        if (!Session::canAccess($id, $currentUser['id'], $isAdmin)) {
            Response::forbidden('Accès non autorisé');
        }

        Session::delete($id);

        AuditService::log(
            $currentUser['id'],
            'session_deleted',
            'session',
            $id,
            ['person_id' => $session['person_id'], 'session_date' => $session['session_date']],
            null
        );

        Response::success(null, 'Séance supprimée');
    }

    public function getLabels(): void
    {
        // Public endpoint for form labels
        Response::success(Session::LABELS);
    }

    public function stats(): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('n'));

        // Admin sees all sessions, users see only their assigned persons' sessions
        $userId = $isAdmin ? null : $currentUser['id'];
        $calendarData = Session::getCalendarData($year, $month, $userId);

        $response = [
            'calendar' => $calendarData,
            'year' => $year,
            'month' => $month
        ];

        // Only admin gets global stats
        if ($isAdmin) {
            $response['stats'] = Session::getGlobalStats();
        }

        Response::success($response);
    }

    public function personStats(string $personId): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        // Check access to person
        $person = Person::findById($personId);
        if (!$person) {
            Response::notFound('Personne non trouvée');
        }

        if (!$isAdmin && !Person::isAssignedToUser($personId, $currentUser['id'])) {
            Response::forbidden('Accès non autorisé');
        }

        $stats = Session::getPersonStats($personId);
        $basicStats = Session::getStats($personId);

        Response::success(array_merge($basicStats, $stats));
    }
}
