<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Utils\UUID;

/**
 * Service d'envoi de SMS via OVH
 * Documentation: https://docs.ovh.com/fr/sms/
 */
class SMSService
{
    private const API_ENDPOINT = 'https://eu.api.ovh.com/1.0';

    private static function env(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Vérifie si le service SMS est configuré
     */
    public static function isConfigured(): bool
    {
        return !empty(self::env('OVH_SMS_APP_KEY'))
            && !empty(self::env('OVH_SMS_APP_SECRET'))
            && !empty(self::env('OVH_SMS_CONSUMER_KEY'))
            && !empty(self::env('OVH_SMS_SERVICE_NAME'));
    }

    /**
     * Envoie un SMS de rappel pour une réservation
     */
    public static function sendReminder(array $booking): bool
    {
        if (!self::isConfigured()) {
            error_log('SMSService: Service non configuré');
            return false;
        }

        if (empty($booking['client_phone'])) {
            return false;
        }

        $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m à H:i');
        $message = "Rappel sensëa: Votre séance pour {$booking['person_first_name']} est prévue demain {$dateFormatted}. A bientôt !";

        return self::send(
            $booking['client_phone'],
            $message,
            'reminder',
            $booking['id'] ?? null
        );
    }

    /**
     * Envoie un SMS de confirmation
     */
    public static function sendConfirmation(array $booking): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        if (empty($booking['client_phone'])) {
            return false;
        }

        $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y à H:i');
        $message = "sensëa: Votre RDV du {$dateFormatted} pour {$booking['person_first_name']} est confirmé. A bientôt !";

        return self::send(
            $booking['client_phone'],
            $message,
            'confirmation',
            $booking['id'] ?? null
        );
    }

    /**
     * Envoie un SMS d'annulation
     */
    public static function sendCancellation(array $booking): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        if (empty($booking['client_phone'])) {
            return false;
        }

        $dateFormatted = (new \DateTime($booking['session_date']))->format('d/m/Y');
        $message = "sensëa: Votre RDV du {$dateFormatted} a été annulé. N'hésitez pas à reprendre RDV sur notre site.";

        return self::send(
            $booking['client_phone'],
            $message,
            'cancellation',
            $booking['id'] ?? null
        );
    }

    /**
     * Envoie un SMS personnalisé
     */
    public static function sendCustom(string $phone, string $message, ?string $bookingId = null): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        return self::send($phone, $message, 'custom', $bookingId);
    }

    /**
     * Envoie un SMS via l'API OVH
     */
    private static function send(string $phone, string $message, string $type, ?string $bookingId = null): bool
    {
        $formattedPhone = self::formatPhoneNumber($phone);
        if (!$formattedPhone) {
            error_log("SMSService: Numéro de téléphone invalide: {$phone}");
            self::logSMS($bookingId, $type, $phone, $message, 'failed', null, 'Numéro invalide');
            return false;
        }

        try {
            $serviceName = self::env('OVH_SMS_SERVICE_NAME');
            $endpoint = "/sms/{$serviceName}/jobs";

            $body = [
                'message' => $message,
                'receivers' => [$formattedPhone],
                'sender' => 'sensëa', // Nom de l'expéditeur (max 11 caractères)
                'noStopClause' => true, // Pas de mention STOP (pour SMS transactionnels)
                'priority' => 'high',
                'validityPeriod' => 2880 // 48h de validité
            ];

            $response = self::callOvhApi('POST', $endpoint, $body);

            if (isset($response['ids']) && !empty($response['ids'])) {
                self::logSMS(
                    $bookingId,
                    $type,
                    $formattedPhone,
                    $message,
                    'sent',
                    $response,
                    null,
                    $response['ids'][0] ?? null
                );
                return true;
            }

            self::logSMS($bookingId, $type, $formattedPhone, $message, 'failed', $response, 'Réponse inattendue');
            return false;

        } catch (\Exception $e) {
            error_log('SMSService: Erreur API OVH: ' . $e->getMessage());
            self::logSMS($bookingId, $type, $formattedPhone, $message, 'failed', null, $e->getMessage());
            return false;
        }
    }

    /**
     * Appelle l'API OVH avec signature
     */
    private static function callOvhApi(string $method, string $endpoint, ?array $body = null): array
    {
        $appKey = self::env('OVH_SMS_APP_KEY');
        $appSecret = self::env('OVH_SMS_APP_SECRET');
        $consumerKey = self::env('OVH_SMS_CONSUMER_KEY');

        $url = self::API_ENDPOINT . $endpoint;
        $timestamp = time();
        $bodyJson = $body ? json_encode($body) : '';

        // Calcul de la signature OVH
        // $1$timestamp$signature
        $toSign = $appSecret . '+' . $consumerKey . '+' . $method . '+' . $url . '+' . $bodyJson . '+' . $timestamp;
        $signature = '$1$' . sha1($toSign);

        $headers = [
            'Content-Type: application/json',
            'X-Ovh-Application: ' . $appKey,
            'X-Ovh-Timestamp: ' . $timestamp,
            'X-Ovh-Signature: ' . $signature,
            'X-Ovh-Consumer: ' . $consumerKey
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method
        ]);

        if ($method === 'POST' && $body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("CURL error: {$error}");
        }

        if ($httpCode >= 400) {
            throw new \Exception("API error (HTTP {$httpCode}): {$response}");
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Formate un numéro de téléphone français au format international
     */
    private static function formatPhoneNumber(string $phone): ?string
    {
        // Nettoyer le numéro
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Si déjà au format international
        if (preg_match('/^\+33[0-9]{9}$/', $phone)) {
            return $phone;
        }

        // Format français 06/07 -> +336/+337
        if (preg_match('/^0([67][0-9]{8})$/', $phone, $matches)) {
            return '+33' . $matches[1];
        }

        // Format sans le 0 initial
        if (preg_match('/^33([67][0-9]{8})$/', $phone, $matches)) {
            return '+33' . $matches[1];
        }

        // Format avec 0033
        if (preg_match('/^0033([67][0-9]{8})$/', $phone, $matches)) {
            return '+33' . $matches[1];
        }

        return null;
    }

    /**
     * Enregistre un log SMS en base de données
     */
    private static function logSMS(
        ?string $bookingId,
        string $type,
        string $phone,
        string $message,
        string $status,
        ?array $response,
        ?string $error = null,
        ?string $messageId = null
    ): void {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare('
                INSERT INTO sms_logs (
                    id, booking_id, phone_number, message_type, message_content,
                    provider, provider_message_id, provider_response, status,
                    sent_at, error_message
                ) VALUES (
                    :id, :booking_id, :phone_number, :message_type, :message_content,
                    :provider, :provider_message_id, :provider_response, :status,
                    :sent_at, :error_message
                )
            ');

            $stmt->execute([
                'id' => UUID::generate(),
                'booking_id' => $bookingId,
                'phone_number' => $phone,
                'message_type' => $type,
                'message_content' => $message,
                'provider' => 'ovh',
                'provider_message_id' => $messageId,
                'provider_response' => $response ? json_encode($response) : null,
                'status' => $status,
                'sent_at' => $status === 'sent' ? (new \DateTime())->format('Y-m-d H:i:s') : null,
                'error_message' => $error
            ]);
        } catch (\Exception $e) {
            error_log('SMSService: Erreur lors du log: ' . $e->getMessage());
        }
    }

    /**
     * Récupère les crédits SMS restants
     */
    public static function getRemainingCredits(): array
    {
        if (!self::isConfigured()) {
            return ['configured' => false];
        }

        try {
            $serviceName = self::env('OVH_SMS_SERVICE_NAME');
            $response = self::callOvhApi('GET', "/sms/{$serviceName}");
            return [
                'configured' => true,
                'serviceName' => $serviceName,
                'creditsLeft' => $response['creditsLeft'] ?? 0,
                'credits' => $response['credits'] ?? 0,
                'description' => $response['description'] ?? null
            ];
        } catch (\Exception $e) {
            error_log('SMSService: Erreur récupération crédits: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupère les statistiques d'envoi
     */
    public static function getStats(): array
    {
        $db = Database::getInstance();

        $stmt = $db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered
            FROM sms_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        $stats = $stmt->fetch();

        return [
            'total_30_days' => (int) ($stats['total'] ?? 0),
            'sent' => (int) ($stats['sent'] ?? 0),
            'failed' => (int) ($stats['failed'] ?? 0),
            'delivered' => (int) ($stats['delivered'] ?? 0),
            'credits_remaining' => self::getRemainingCredits()['creditsLeft'] ?? null,
            'configured' => self::isConfigured()
        ];
    }
}
