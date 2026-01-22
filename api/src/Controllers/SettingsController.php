<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Setting;
use App\Services\SMSService;
use App\Utils\Response;

class SettingsController
{
    /**
     * GET /settings - Get all settings grouped by category
     */
    public function index(): void
    {
        $settings = Setting::getAllGrouped();

        // Add category labels
        $categoryLabels = [
            'pricing' => 'Tarifs',
            'scheduling' => 'Horaires & Durées',
            'booking' => 'Réservations',
            'calendar' => 'Calendrier',
            'security' => 'Sécurité',
            'sms' => 'SMS',
            'general' => 'Général'
        ];

        // Define category order
        $categoryOrder = ['pricing', 'scheduling', 'booking', 'calendar', 'sms', 'security', 'general'];

        // Build response in defined order
        $response = [];
        foreach ($categoryOrder as $category) {
            if (isset($settings[$category])) {
                $response[] = [
                    'category' => $category,
                    'label' => $categoryLabels[$category] ?? ucfirst($category),
                    'settings' => $settings[$category]
                ];
            }
        }

        // Add any remaining categories not in the order list
        foreach ($settings as $category => $items) {
            if (!in_array($category, $categoryOrder)) {
                $response[] = [
                    'category' => $category,
                    'label' => $categoryLabels[$category] ?? ucfirst($category),
                    'settings' => $items
                ];
            }
        }

        Response::success($response);
    }

    /**
     * GET /settings/category/{category} - Get settings for a specific category
     */
    public function getByCategory(string $category): void
    {
        $settings = Setting::getAll($category);

        if (empty($settings)) {
            Response::notFound('Catégorie non trouvée');
            return;
        }

        Response::success([
            'category' => $category,
            'settings' => array_map(function ($setting) {
                return [
                    'key' => $setting['key'],
                    'value' => Setting::get($setting['key']),
                    'type' => $setting['type'],
                    'label' => $setting['label'],
                    'description' => $setting['description']
                ];
            }, $settings)
        ]);
    }

    /**
     * PUT /settings - Update multiple settings
     */
    public function update(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $userId = $_SERVER['AUTH_USER_ID'] ?? null;

        if (!isset($data['settings']) || !is_array($data['settings'])) {
            Response::validationError(['settings' => 'Format invalide, attendu: { settings: { key: value, ... } }']);
            return;
        }

        // Validate all keys exist before updating
        $errors = [];
        foreach ($data['settings'] as $key => $value) {
            if (!Setting::exists($key)) {
                $errors[$key] = "Paramètre inconnu";
            }
        }

        if (!empty($errors)) {
            Response::validationError($errors);
            return;
        }

        $updated = Setting::updateMultiple($data['settings'], $userId);

        Response::success([
            'message' => "{$updated} paramètre(s) mis à jour",
            'updated' => $updated
        ]);
    }

    /**
     * GET /settings/sms-credits - Get OVH SMS remaining credits
     */
    public function getSmsCredits(): void
    {
        if (!SMSService::isConfigured()) {
            Response::success([
                'configured' => false,
                'message' => 'Service SMS non configuré'
            ]);
            return;
        }

        try {
            $credits = SMSService::getRemainingCredits();

            Response::success([
                'configured' => true,
                'credits' => $credits['credits'] ?? 0,
                'credits_left' => $credits['creditsLeft'] ?? 0,
                'service_name' => $credits['serviceName'] ?? null,
                'cached_at' => $credits['cached_at'] ?? null
            ]);
        } catch (\Exception $e) {
            Response::error('Impossible de récupérer les crédits SMS: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /settings/sms-credits/refresh - Force refresh SMS credits cache
     */
    public function refreshSmsCredits(): void
    {
        if (!SMSService::isConfigured()) {
            Response::success([
                'configured' => false,
                'message' => 'Service SMS non configuré'
            ]);
            return;
        }

        try {
            // Vider le cache et forcer le rafraîchissement
            SMSService::clearCreditsCache();
            $credits = SMSService::getRemainingCredits(true);

            Response::success([
                'configured' => true,
                'credits' => $credits['credits'] ?? 0,
                'credits_left' => $credits['creditsLeft'] ?? 0,
                'service_name' => $credits['serviceName'] ?? null,
                'cached_at' => $credits['cached_at'] ?? null,
                'message' => 'Cache SMS rafraîchi'
            ]);
        } catch (\Exception $e) {
            Response::error('Impossible de rafraîchir les crédits SMS: ' . $e->getMessage(), 500);
        }
    }
}
