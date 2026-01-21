<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\Person;
use App\Models\LoyaltyCard;
use App\Models\Setting;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Utils\Response;
use App\Utils\Validator;

class UserController
{
    public function index(): void
    {
        AuthMiddleware::requireAdmin();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $users = User::findAll($limit, $offset);
        $total = User::count();

        Response::success([
            'users' => array_map([User::class, 'toPublic'], $users),
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

        // Members can only view their own profile
        if ($currentUser['role'] !== 'admin' && $currentUser['id'] !== $id) {
            Response::forbidden('Accès non autorisé');
        }

        $user = User::findById($id);

        if (!$user) {
            Response::notFound('Utilisateur non trouvé');
        }

        $userData = User::toPublic($user);

        // Add assigned persons
        $userData['persons'] = Person::findByUser($id);

        Response::success($userData);
    }

    public function me(): void
    {
        AuthMiddleware::handle();

        $user = AuthMiddleware::getCurrentUser();
        $userData = User::toPublic($user);

        // Add assigned persons
        $userData['persons'] = Person::findByUser($user['id']);

        Response::success($userData);
    }

    public function store(): void
    {
        AuthMiddleware::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($data);
        $validator
            ->required('email')->email('email')
            ->required('login')->minLength('login', 3)->maxLength('login', 100)
            ->required('first_name')->minLength('first_name', 1)->maxLength('first_name', 100)
            ->required('last_name')->minLength('last_name', 1)->maxLength('last_name', 100)
            ->phone('phone')
            ->inArray('role', ['member', 'admin']);
        $validator->validate();

        // Check uniqueness
        if (User::emailExists($data['email'])) {
            Response::validationError(['email' => 'Cette adresse email est déjà utilisée']);
        }

        if (User::loginExists($data['login'])) {
            Response::validationError(['login' => 'Ce login est déjà utilisé']);
        }

        $userId = User::create($data);
        $user = User::findById($userId);

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'user_created',
            'user',
            $userId,
            null,
            User::toPublic($user)
        );

        Response::success(User::toPublic($user), 'Utilisateur créé avec succès', 201);
    }

    public function update(string $id): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();

        // Members can only update their own profile (limited fields)
        if ($currentUser['role'] !== 'admin' && $currentUser['id'] !== $id) {
            Response::forbidden('Accès non autorisé');
        }

        $user = User::findById($id);

        if (!$user) {
            Response::notFound('Utilisateur non trouvé');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Members can only update limited fields
        if ($currentUser['role'] !== 'admin') {
            $allowedFields = ['first_name', 'last_name', 'phone'];
            $data = array_intersect_key($data, array_flip($allowedFields));
        }

        $validator = new Validator($data);

        if (isset($data['email'])) {
            $validator->email('email');
            if (User::emailExists($data['email'], $id)) {
                Response::validationError(['email' => 'Cette adresse email est déjà utilisée']);
            }
        }

        if (isset($data['login'])) {
            $validator->minLength('login', 3)->maxLength('login', 100);
            if (User::loginExists($data['login'], $id)) {
                Response::validationError(['login' => 'Ce login est déjà utilisé']);
            }
        }

        if (isset($data['first_name'])) {
            $validator->minLength('first_name', 1)->maxLength('first_name', 100);
        }

        if (isset($data['last_name'])) {
            $validator->minLength('last_name', 1)->maxLength('last_name', 100);
        }

        if (isset($data['phone'])) {
            $validator->phone('phone');
        }

        if (isset($data['role'])) {
            $validator->inArray('role', ['member', 'admin']);
        }

        $validator->validate();

        $oldValues = User::toPublic($user);
        User::update($id, $data);
        $updatedUser = User::findById($id);

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'user_updated',
            'user',
            $id,
            $oldValues,
            User::toPublic($updatedUser)
        );

        Response::success(User::toPublic($updatedUser), 'Utilisateur mis à jour');
    }

    public function destroy(string $id): void
    {
        AuthMiddleware::requireAdmin();

        $currentUser = AuthMiddleware::getCurrentUser();

        // Cannot delete yourself
        if ($currentUser['id'] === $id) {
            Response::error('Vous ne pouvez pas supprimer votre propre compte', 400);
        }

        $user = User::findById($id);

        if (!$user) {
            Response::notFound('Utilisateur non trouvé');
        }

        $oldValues = User::toPublic($user);
        User::delete($id); // Soft delete

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'user_deactivated',
            'user',
            $id,
            $oldValues,
            ['is_active' => false]
        );

        Response::success(null, 'Utilisateur désactivé');
    }

    public function assignPerson(string $userId, string $personId): void
    {
        AuthMiddleware::requireAdmin();

        $user = User::findById($userId);
        if (!$user) {
            Response::notFound('Utilisateur non trouvé');
        }

        $person = Person::findById($personId);
        if (!$person) {
            Response::notFound('Personne non trouvée');
        }

        Person::assignToUser($personId, $userId);

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'person_assigned',
            'user_person',
            null,
            null,
            ['user_id' => $userId, 'person_id' => $personId]
        );

        Response::success(null, 'Personne assignée avec succès');
    }

    public function unassignPerson(string $userId, string $personId): void
    {
        AuthMiddleware::requireAdmin();

        Person::unassignFromUser($personId, $userId);

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'person_unassigned',
            'user_person',
            null,
            ['user_id' => $userId, 'person_id' => $personId],
            null
        );

        Response::success(null, 'Assignation retirée');
    }

    /**
     * Récupère la carte de fidélité d'un utilisateur
     */
    public function getLoyaltyCard(string $id): void
    {
        AuthMiddleware::handle();

        $currentUser = AuthMiddleware::getCurrentUser();
        $isAdmin = AuthMiddleware::isAdmin();

        // Les membres ne peuvent voir que leur propre carte de fidélité
        if (!$isAdmin && $currentUser['id'] !== $id) {
            Response::forbidden('Accès non autorisé');
        }

        $user = User::findById($id);
        if (!$user) {
            Response::notFound('Utilisateur non trouvé');
        }

        // Seuls les particuliers sont éligibles
        if (!User::isPersonalClient($id)) {
            Response::success([
                'eligible' => false,
                'reason' => 'Les associations ne sont pas éligibles au programme de fidélité'
            ]);
            return;
        }

        $sessionsRequired = Setting::getInteger('loyalty_sessions_required', 9);
        $loyaltyInfo = LoyaltyCard::getWithProgress($id, $sessionsRequired);

        Response::success($loyaltyInfo);
    }
}
