<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Service de vérification captcha (hCaptcha ou reCAPTCHA)
 */
class CaptchaService
{
    private const HCAPTCHA_VERIFY_URL = 'https://hcaptcha.com/siteverify';
    private const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * Vérifie si le captcha est activé et configuré
     */
    public static function isEnabled(): bool
    {
        if (!Setting::getBoolean('captcha_enabled', false)) {
            return false;
        }

        $secretKey = Setting::getString('captcha_secret_key', '');
        return !empty($secretKey);
    }

    /**
     * Récupère la configuration publique du captcha (pour le frontend)
     */
    public static function getPublicConfig(): array
    {
        return [
            'enabled' => self::isEnabled(),
            'provider' => Setting::getString('captcha_provider', 'hcaptcha'),
            'site_key' => Setting::getString('captcha_site_key', '')
        ];
    }

    /**
     * Vérifie un token captcha
     */
    public static function verify(string $token, ?string $remoteIp = null): bool
    {
        if (!self::isEnabled()) {
            return true; // Si captcha désactivé, toujours valide
        }

        if (empty($token)) {
            return false;
        }

        $provider = Setting::getString('captcha_provider', 'hcaptcha');
        $secretKey = Setting::getString('captcha_secret_key', '');

        if (empty($secretKey)) {
            error_log('CaptchaService: Clé secrète non configurée');
            return true; // Fail open si mal configuré
        }

        $verifyUrl = $provider === 'recaptcha'
            ? self::RECAPTCHA_VERIFY_URL
            : self::HCAPTCHA_VERIFY_URL;

        return self::verifyWithProvider($verifyUrl, $secretKey, $token, $remoteIp);
    }

    /**
     * Effectue la vérification auprès du fournisseur
     */
    private static function verifyWithProvider(
        string $url,
        string $secretKey,
        string $token,
        ?string $remoteIp
    ): bool {
        $data = [
            'secret' => $secretKey,
            'response' => $token
        ];

        if ($remoteIp) {
            $data['remoteip'] = $remoteIp;
        }

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];

        $context = stream_context_create($options);

        try {
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                error_log('CaptchaService: Erreur de communication avec le serveur captcha');
                return true; // Fail open en cas d'erreur réseau
            }

            $result = json_decode($response, true);

            if (!is_array($result)) {
                error_log('CaptchaService: Réponse invalide du serveur captcha');
                return true;
            }

            $success = $result['success'] ?? false;

            if (!$success && isset($result['error-codes'])) {
                error_log('CaptchaService: Erreurs captcha: ' . implode(', ', $result['error-codes']));
            }

            return $success;

        } catch (\Exception $e) {
            error_log('CaptchaService: Exception: ' . $e->getMessage());
            return true; // Fail open en cas d'exception
        }
    }
}
