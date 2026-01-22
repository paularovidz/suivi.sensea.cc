<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PromoCode;
use App\Models\Session;
use App\Models\User;
use App\Utils\Response;
use App\Utils\Validator;

/**
 * Contrôleur pour les endpoints publics de codes promo (sans authentification)
 */
class PublicPromoCodeController
{
    /**
     * Résout les informations utilisateur à partir de l'email et du client_type
     */
    private function resolveUserContext(?string $email, ?string $providedClientType): array
    {
        $userId = null;
        $clientType = null;

        if ($email) {
            $user = User::findByEmail($email);
            if ($user) {
                $userId = $user['id'];
                $clientType = $user['client_type'] ?? User::CLIENT_TYPE_PERSONAL;
            }
        }

        // Le client_type fourni a priorité (nouveau client)
        if ($providedClientType && in_array($providedClientType, User::CLIENT_TYPES)) {
            $clientType = $providedClientType;
        }

        return ['userId' => $userId, 'clientType' => $clientType];
    }

    /**
     * Formate les données d'un code promo pour la réponse API
     */
    private function formatPromoResponse(array $promo, bool $includeCode = true): array
    {
        $response = [
            'id' => $promo['id'],
            'name' => $promo['name'],
            'discount_type' => $promo['discount_type'],
            'discount_value' => $promo['discount_value'],
            'discount_label' => PromoCode::getDiscountLabel($promo)
        ];

        if ($includeCode && !empty($promo['code'])) {
            $response['code'] = $promo['code'];
        }

        return $response;
    }

    /**
     * POST /public/promo-codes/validate
     * Valide un code promo pour une réservation
     */
    public function validate(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($data);
        $validator
            ->required('code')
            ->required('duration_type')
            ->inArray('duration_type', Session::TYPES);

        $errors = $validator->validate();

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $code = strtoupper(trim($data['code']));
        $durationType = $data['duration_type'];
        $email = isset($data['email']) ? strtolower(trim($data['email'])) : null;

        $context = $this->resolveUserContext($email, $data['client_type'] ?? null);

        $validation = PromoCode::validate($code, $durationType, $context['userId'], $context['clientType']);

        if (!$validation['valid']) {
            Response::error($validation['error'], 400);
            return;
        }

        $promo = $validation['promo'];
        $originalPrice = Session::getPriceForType($durationType);
        $discount = PromoCode::calculateDiscount($promo, $originalPrice);

        Response::success([
            'valid' => true,
            'promo' => $this->formatPromoResponse($promo, true),
            'pricing' => $discount
        ]);
    }

    /**
     * GET /public/promo-codes/has-manual-codes
     * Vérifie si des codes promo manuels actifs existent
     */
    public function hasManualCodes(): void
    {
        Response::success([
            'has_manual_codes' => PromoCode::hasActiveManualCodes()
        ]);
    }

    /**
     * GET /public/promo-codes/automatic
     * Récupère la promo automatique applicable (si elle existe)
     */
    public function getAutomatic(): void
    {
        $durationType = $_GET['duration_type'] ?? Session::TYPE_REGULAR;
        $email = isset($_GET['email']) ? strtolower(trim($_GET['email'])) : null;

        if (!in_array($durationType, Session::TYPES)) {
            Response::validationError(['duration_type' => 'Type de séance invalide']);
        }

        $context = $this->resolveUserContext($email, $_GET['client_type'] ?? null);

        $promo = PromoCode::findApplicableAutomatic($durationType, $context['userId'], $context['clientType']);

        if (!$promo) {
            Response::success([
                'has_automatic_promo' => false,
                'promo' => null,
                'pricing' => null
            ]);
            return;
        }

        $originalPrice = Session::getPriceForType($durationType);
        $discount = PromoCode::calculateDiscount($promo, $originalPrice);

        Response::success([
            'has_automatic_promo' => true,
            'promo' => $this->formatPromoResponse($promo, false),
            'pricing' => $discount
        ]);
    }
}
