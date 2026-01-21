<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Person;
use App\Models\Session;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Utils\Response;
use App\Utils\Validator;

class PersonController
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
            $persons = Person::findAll($limit, $offset);
            $total = Person::count();
        } else {
            $persons = Person::findByUser($currentUser['id'], $limit, $offset);
            $total = Person::countByUser($currentUser['id']);
        }

        // Add age and stats to each person
        $persons = array_map(function($person) {
            $person = Person::withAge($person);
            $person['stats'] = Session::getStats($person['id']);
            return $person;
        }, $persons);

        Response::success([
            'persons' => $persons,
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

        $person = Person::findById($id);

        if (!$person) {
            Response::notFound('Personne non trouvée');
        }

        // Check access
        if (!$isAdmin && !Person::isAssignedToUser($id, $currentUser['id'])) {
            Response::forbidden('Accès non autorisé');
        }

        $person = Person::withAge($person);
        $person['assigned_users'] = Person::getAssignedUsers($id);
        $person['stats'] = Session::getStats($id);

        Response::success($person);
    }

    public function store(): void
    {
        AuthMiddleware::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($data);
        $validator
            ->required('first_name')->minLength('first_name', 1)->maxLength('first_name', 100)
            ->required('last_name')->minLength('last_name', 1)->maxLength('last_name', 100)
            ->date('birth_date');
        $validator->validate();

        $personId = Person::create($data);
        $person = Person::findById($personId);

        // Assign to users if provided
        if (!empty($data['assign_to_users']) && is_array($data['assign_to_users'])) {
            foreach ($data['assign_to_users'] as $userId) {
                Person::assignToUser($personId, $userId);
            }
        }

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'person_created',
            'person',
            $personId,
            null,
            $person
        );

        Response::success(Person::withAge($person), 'Personne créée avec succès', 201);
    }

    public function update(string $id): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        $person = Person::findById($id);

        if (!$person) {
            Response::notFound('Personne non trouvée');
        }

        // Check access
        if (!$isAdmin && !Person::isAssignedToUser($id, $currentUser['id'])) {
            Response::forbidden('Accès non autorisé');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($data);

        if (isset($data['first_name'])) {
            $validator->minLength('first_name', 1)->maxLength('first_name', 100);
        }

        if (isset($data['last_name'])) {
            $validator->minLength('last_name', 1)->maxLength('last_name', 100);
        }

        if (isset($data['birth_date'])) {
            $validator->date('birth_date');
        }

        $validator->validate();

        $oldValues = $person;
        Person::update($id, $data);
        $updatedPerson = Person::findById($id);

        // Handle user assignments (admin only)
        if ($isAdmin && isset($data['assign_to_users'])) {
            $currentUsers = array_column(Person::getAssignedUsers($id), 'id');
            $newUsers = $data['assign_to_users'];

            // Unassign removed users
            foreach (array_diff($currentUsers, $newUsers) as $userId) {
                Person::unassignFromUser($id, $userId);
            }

            // Assign new users
            foreach (array_diff($newUsers, $currentUsers) as $userId) {
                Person::assignToUser($id, $userId);
            }
        }

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'person_updated',
            'person',
            $id,
            $oldValues,
            $updatedPerson
        );

        Response::success(Person::withAge($updatedPerson), 'Personne mise à jour');
    }

    public function destroy(string $id): void
    {
        AuthMiddleware::requireAdmin();

        $person = Person::findById($id);

        if (!$person) {
            Response::notFound('Personne non trouvée');
        }

        // Check if person has sessions
        $sessionCount = Session::countByPerson($id);
        if ($sessionCount > 0) {
            Response::error("Cette personne a {$sessionCount} séance(s) enregistrée(s). Supprimez d'abord les séances.", 400);
        }

        $oldValues = $person;
        Person::delete($id);

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'person_deleted',
            'person',
            $id,
            $oldValues,
            null
        );

        Response::success(null, 'Personne supprimée');
    }

    public function sessions(string $id): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        $person = Person::findById($id);

        if (!$person) {
            Response::notFound('Personne non trouvée');
        }

        // Check access
        if (!$isAdmin && !Person::isAssignedToUser($id, $currentUser['id'])) {
            Response::forbidden('Accès non autorisé');
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $sessions = Session::findByPerson($id, $limit, $offset);
        $total = Session::countByPerson($id);

        Response::success([
            'person' => Person::withAge($person),
            'sessions' => $sessions,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
}
