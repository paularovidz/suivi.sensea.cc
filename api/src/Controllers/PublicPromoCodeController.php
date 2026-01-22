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

        // Récupérer l'utilisateur si email fourni
        $userId = null;
        $clientType = null;
        if ($email) {
            $user = User::findByEmail($email);
            if ($user) {
                $userId = $user['id'];
                $clientType = $user['client_type'] ?? User::CLIENT_TYPE_PERSONAL;
            }
        }

        // Si un client_type est fourni (nouveau client), l'utiliser
        if (isset($data['client_type']) && in_array($data['client_type'], User::CLIENT_TYPES)) {
            $clientType = $data['client_type'];
        }

        // Valider le code
        $validation = PromoCode::validate($code, $durationType, $userId, $clientType);

        if (!$validation['valid']) {
            Response::error($validation['error'], 400);
            return;
        }

        $promo = $validation['promo'];

        // Calculer la remise
        $originalPrice = Session::getPriceForType($durationType);
        $discount = PromoCode::calculateDiscount($promo, $originalPrice);

        Response::success([
            'valid' => true,
            'promo' => [
                'id' => $promo['id'],
                'code' => $promo['code'],
                'name' => $promo['name'],
                'discount_type' => $promo['discount_type'],
                'discount_value' => $promo['discount_value'],
                'discount_label' => PromoCode::getDiscountLabel($promo)
            ],
            'pricing' => $discount
        ]);
    }

    /**
     * GET /public/promo-codes/has-manual-codes
     * Vérifie si des codes promo manuels actifs existent
     */
    public function hasManualCodes(): void
    {
        $hasManualCodes = PromoCode::hasActiveManualCodes();

        Response::success([
            'has_manual_codes' => $hasManualCodes
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

        // Récupérer l'utilisateur si email fourni
        $userId = null;
        $clientType = null;
        if ($email) {
            $user = User::findByEmail($email);
            if ($user) {
                $userId = $user['id'];
                $clientType = $user['client_type'] ?? User::CLIENT_TYPE_PERSONAL;
            }
        }

        // Si un client_type est fourni (nouveau client), l'utiliser
        if (isset($_GET['client_type']) && in_array($_GET['client_type'], User::CLIENT_TYPES)) {
            $clientType = $_GET['client_type'];
        }

        // Chercher une promo automatique applicable
        $promo = PromoCode::findApplicableAutomatic($durationType, $userId, $clientType);

        if (!$promo) {
            Response::success([
                'has_automatic_promo' => false,
                'promo' => null,
                'pricing' => null
            ]);
            return;
        }

        // Calculer la remise
        $originalPrice = Session::getPriceForType($durationType);
        $discount = PromoCode::calculateDiscount($promo, $originalPrice);

        Response::success([
            'has_automatic_promo' => true,
            'promo' => [
                'id' => $promo['id'],
                'name' => $promo['name'],
                'discount_type' => $promo['discount_type'],
                'discount_value' => $promo['discount_value'],
                'discount_label' => PromoCode::getDiscountLabel($promo)
            ],
            'pricing' => $discount
        ]);
    }
}
