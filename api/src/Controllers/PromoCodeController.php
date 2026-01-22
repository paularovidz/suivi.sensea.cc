<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PromoCode;
use App\Models\User;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Utils\Response;
use App\Utils\Validator;

/**
 * Contrôleur admin pour la gestion des codes promo
 */
class PromoCodeController
{
    /**
     * GET /promo-codes
     * Liste tous les codes promo
     */
    public function index(): void
    {
        AuthMiddleware::requireAdmin();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = isset($_GET['search']) && $_GET['search'] !== '' ? trim($_GET['search']) : null;

        $filters = [];
        if (isset($_GET['is_active'])) {
            $filters['is_active'] = $_GET['is_active'] === 'true' || $_GET['is_active'] === '1';
        }
        if (!empty($_GET['application_mode'])) {
            $filters['application_mode'] = $_GET['application_mode'];
        }
        if (!empty($_GET['discount_type'])) {
            $filters['discount_type'] = $_GET['discount_type'];
        }

        $promoCodes = PromoCode::findAll($limit, $offset, $search, $filters);
        $total = PromoCode::count($search, $filters);

        Response::success([
            'promo_codes' => $promoCodes,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit)
            ],
            'labels' => [
                'discount_types' => PromoCode::DISCOUNT_TYPE_LABELS,
                'application_modes' => PromoCode::APPLICATION_MODE_LABELS
            ]
        ]);
    }

    /**
     * GET /promo-codes/{id}
     * Détail d'un code promo avec stats
     */
    public function show(string $id): void
    {
        AuthMiddleware::requireAdmin();

        $promoCode = PromoCode::findById($id);

        if (!$promoCode) {
            Response::notFound('Code promo non trouvé');
        }

        // Ajouter les statistiques
        $stats = PromoCode::getStats($id);

        Response::success([
            'promo_code' => $promoCode,
            'stats' => $stats,
            'labels' => [
                'discount_types' => PromoCode::DISCOUNT_TYPE_LABELS,
                'application_modes' => PromoCode::APPLICATION_MODE_LABELS
            ]
        ]);
    }

    /**
     * GET /promo-codes/{id}/usages
     * Historique d'utilisation d'un code promo
     */
    public function getUsages(string $id): void
    {
        AuthMiddleware::requireAdmin();

        $promoCode = PromoCode::findById($id);

        if (!$promoCode) {
            Response::notFound('Code promo non trouvé');
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $usages = PromoCode::getUsages($id, $limit, $offset);
        $total = PromoCode::getUsageCount($id);

        Response::success([
            'usages' => $usages,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit)
            ]
        ]);
    }

    /**
     * POST /promo-codes
     * Créer un code promo
     */
    public function store(): void
    {
        AuthMiddleware::requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($data);
        $validator
            ->required('name')
            ->minLength('name', 2)
            ->maxLength('name', 255)
            ->required('discount_type')
            ->inArray('discount_type', PromoCode::DISCOUNT_TYPES)
            ->required('discount_value')
            ->numeric('discount_value')
            ->inArray('application_mode', PromoCode::APPLICATION_MODES);

        $errors = $validator->validate();

        // Validation supplémentaire pour le discount_value
        if (empty($errors)) {
            $discountValue = (float)$data['discount_value'];
            if ($discountValue < 0) {
                $errors['discount_value'] = 'La valeur de remise doit être positive';
            }
            if ($data['discount_type'] === PromoCode::DISCOUNT_TYPE_PERCENTAGE && $discountValue > 100) {
                $errors['discount_value'] = 'Le pourcentage ne peut pas dépasser 100%';
            }
        }

        // Validation du code pour les promos manuelles
        if (($data['application_mode'] ?? PromoCode::MODE_MANUAL) === PromoCode::MODE_MANUAL) {
            if (empty($data['code'])) {
                $errors['code'] = 'Le code est requis pour les promotions manuelles';
            } elseif (PromoCode::codeExists($data['code'])) {
                $errors['code'] = 'Ce code existe déjà';
            }
        }

        // Validation du target_user_id s'il est fourni
        if (!empty($data['target_user_id'])) {
            $targetUser = User::findById($data['target_user_id']);
            if (!$targetUser) {
                $errors['target_user_id'] = 'Utilisateur cible non trouvé';
            }
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Ajouter le créateur
        $data['created_by'] = AuthMiddleware::getCurrentUserId();

        $promoId = PromoCode::create($data);
        $promoCode = PromoCode::findById($promoId);

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'promo_code_created',
            'promo_code',
            $promoId,
            null,
            ['name' => $promoCode['name'], 'code' => $promoCode['code']]
        );

        Response::success($promoCode, 'Code promo créé avec succès', 201);
    }

    /**
     * PUT /promo-codes/{id}
     * Mettre à jour un code promo
     */
    public function update(string $id): void
    {
        AuthMiddleware::requireAdmin();

        $promoCode = PromoCode::findById($id);

        if (!$promoCode) {
            Response::notFound('Code promo non trouvé');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = [];

        $validator = new Validator($data);

        if (isset($data['name'])) {
            $validator->minLength('name', 2)->maxLength('name', 255);
        }

        if (isset($data['discount_type'])) {
            $validator->inArray('discount_type', PromoCode::DISCOUNT_TYPES);
        }

        if (isset($data['discount_value'])) {
            $validator->numeric('discount_value');
            $discountValue = (float)$data['discount_value'];
            if ($discountValue < 0) {
                $errors['discount_value'] = 'La valeur de remise doit être positive';
            }
            $discountType = $data['discount_type'] ?? $promoCode['discount_type'];
            if ($discountType === PromoCode::DISCOUNT_TYPE_PERCENTAGE && $discountValue > 100) {
                $errors['discount_value'] = 'Le pourcentage ne peut pas dépasser 100%';
            }
        }

        if (isset($data['application_mode'])) {
            $validator->inArray('application_mode', PromoCode::APPLICATION_MODES);
        }

        $validationErrors = $validator->validate();
        $errors = array_merge($errors, $validationErrors);

        // Vérifier l'unicité du code si modifié
        if (isset($data['code']) && !empty($data['code'])) {
            if (PromoCode::codeExists($data['code'], $id)) {
                $errors['code'] = 'Ce code existe déjà';
            }
        }

        // Validation du target_user_id s'il est fourni
        if (!empty($data['target_user_id'])) {
            $targetUser = User::findById($data['target_user_id']);
            if (!$targetUser) {
                $errors['target_user_id'] = 'Utilisateur cible non trouvé';
            }
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $oldData = ['name' => $promoCode['name'], 'code' => $promoCode['code']];

        PromoCode::update($id, $data);

        $updatedPromo = PromoCode::findById($id);

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'promo_code_updated',
            'promo_code',
            $id,
            $oldData,
            ['name' => $updatedPromo['name'], 'code' => $updatedPromo['code']]
        );

        Response::success($updatedPromo, 'Code promo mis à jour');
    }

    /**
     * DELETE /promo-codes/{id}
     * Supprimer un code promo
     */
    public function destroy(string $id): void
    {
        AuthMiddleware::requireAdmin();

        $promoCode = PromoCode::findById($id);

        if (!$promoCode) {
            Response::notFound('Code promo non trouvé');
        }

        // Vérifier s'il a été utilisé
        $usageCount = PromoCode::getUsageCount($id);
        if ($usageCount > 0) {
            // Désactiver plutôt que supprimer si déjà utilisé
            PromoCode::update($id, ['is_active' => false]);

            AuditService::log(
                AuthMiddleware::getCurrentUserId(),
                'promo_code_deactivated',
                'promo_code',
                $id,
                ['is_active' => true],
                ['is_active' => false]
            );

            Response::success(
                ['deactivated' => true, 'deleted' => false],
                'Code promo désactivé (déjà utilisé ' . $usageCount . ' fois)'
            );
            return;
        }

        PromoCode::delete($id);

        AuditService::log(
            AuthMiddleware::getCurrentUserId(),
            'promo_code_deleted',
            'promo_code',
            $id,
            ['name' => $promoCode['name'], 'code' => $promoCode['code']],
            null
        );

        Response::success(['deleted' => true], 'Code promo supprimé');
    }

    /**
     * GET /promo-codes/generate-code
     * Génère un code aléatoire unique
     */
    public function generateCode(): void
    {
        AuthMiddleware::requireAdmin();

        $length = (int)($_GET['length'] ?? 8);
        $length = max(4, min(16, $length));

        $code = PromoCode::generateRandomCode($length);

        Response::success(['code' => $code]);
    }
}
