<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SensoryProposal;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Utils\Response;
use App\Utils\Validator;

class SensoryProposalController
{
    public function index(): void
    {
        AuthMiddleware::handle();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $type = $_GET['type'] ?? null;

        if ($type) {
            if (!in_array($type, SensoryProposal::TYPES, true)) {
                Response::validationError(['type' => 'Type de proposition invalide']);
            }
            $proposals = SensoryProposal::findByType($type, $limit);
            $total = count($proposals);
        } else {
            $proposals = SensoryProposal::findAll($limit, $offset);
            $total = SensoryProposal::count();
        }

        Response::success([
            'proposals' => $proposals,
            'types' => SensoryProposal::TYPE_LABELS,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => max(1, ceil($total / $limit))
            ]
        ]);
    }

    public function show(string $id): void
    {
        AuthMiddleware::handle();

        $proposal = SensoryProposal::findById($id);

        if (!$proposal) {
            Response::notFound('Proposition non trouvée');
        }

        Response::success($proposal);
    }

    public function search(): void
    {
        AuthMiddleware::handle();

        $query = $_GET['q'] ?? '';
        $type = $_GET['type'] ?? null;
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));

        if (strlen($query) < 2) {
            Response::validationError(['q' => 'La recherche doit contenir au moins 2 caractères']);
        }

        if ($type && !in_array($type, SensoryProposal::TYPES, true)) {
            Response::validationError(['type' => 'Type de proposition invalide']);
        }

        $proposals = SensoryProposal::search($query, $type, $limit);

        Response::success([
            'proposals' => $proposals,
            'query' => $query,
            'type' => $type
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($data);
        $validator
            ->required('title')->minLength('title', 3)->maxLength('title', 255)
            ->required('type')->inArray('type', SensoryProposal::TYPES)
            ->maxLength('description', 2000);
        $validator->validate();

        $data['created_by'] = $currentUser['id'];

        $proposalId = SensoryProposal::create($data);
        $proposal = SensoryProposal::findById($proposalId);

        AuditService::log(
            $currentUser['id'],
            'sensory_proposal_created',
            'sensory_proposal',
            $proposalId,
            null,
            $proposal
        );

        Response::success($proposal, 'Proposition sensorielle créée avec succès', 201);
    }

    public function update(string $id): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        $proposal = SensoryProposal::findById($id);

        if (!$proposal) {
            Response::notFound('Proposition non trouvée');
        }

        // Check modify access
        if (!SensoryProposal::canModify($id, $currentUser['id'], $isAdmin)) {
            Response::forbidden('Vous ne pouvez pas modifier cette proposition');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($data);

        if (isset($data['title'])) {
            $validator->minLength('title', 3)->maxLength('title', 255);
        }
        if (isset($data['type'])) {
            $validator->inArray('type', SensoryProposal::TYPES);
        }
        if (isset($data['description'])) {
            $validator->maxLength('description', 2000);
        }

        $validator->validate();

        // Don't allow changing created_by
        unset($data['created_by']);

        $oldValues = $proposal;
        SensoryProposal::update($id, $data);
        $updatedProposal = SensoryProposal::findById($id);

        AuditService::log(
            $currentUser['id'],
            'sensory_proposal_updated',
            'sensory_proposal',
            $id,
            $oldValues,
            $updatedProposal
        );

        Response::success($updatedProposal, 'Proposition mise à jour');
    }

    public function destroy(string $id): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        $proposal = SensoryProposal::findById($id);

        if (!$proposal) {
            Response::notFound('Proposition non trouvée');
        }

        // Check modify access
        if (!SensoryProposal::canModify($id, $currentUser['id'], $isAdmin)) {
            Response::forbidden('Vous ne pouvez pas supprimer cette proposition');
        }

        // Check if used in sessions
        if (SensoryProposal::isUsedInSessions($id)) {
            Response::error('Cette proposition est utilisée dans des séances et ne peut pas être supprimée', 400);
        }

        $oldValues = $proposal;
        SensoryProposal::delete($id);

        AuditService::log(
            $currentUser['id'],
            'sensory_proposal_deleted',
            'sensory_proposal',
            $id,
            $oldValues,
            null
        );

        Response::success(null, 'Proposition supprimée');
    }

    public function getTypes(): void
    {
        // Public endpoint for types list
        Response::success([
            'types' => SensoryProposal::TYPES,
            'labels' => SensoryProposal::TYPE_LABELS
        ]);
    }
}
