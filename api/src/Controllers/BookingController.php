<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Session;
use App\Models\Person;
use App\Models\User;
use App\Models\LoyaltyCard;
use App\Models\Setting;
use App\Models\PromoCode;
use App\Services\AuditService;
use App\Services\AvailabilityService;
use App\Services\BookingMailService;
use App\Services\ICSGeneratorService;
use App\Services\SMSService;
use App\Services\MailService;
use App\Utils\Response;
use App\Utils\Validator;

/**
 * Contrôleur pour la gestion des réservations (authentifié - admin)
 */
class BookingController
{
    /**
     * GET /bookings
     * Liste toutes les réservations (admin uniquement)
     */
    public function index(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $filters = [];

        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }

        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }

        if (!empty($_GET['duration_type'])) {
            $filters['duration_type'] = $_GET['duration_type'];
        }

        if (!empty($_GET['person_id'])) {
            $filters['person_id'] = $_GET['person_id'];
        }

        if (!empty($_GET['no_session'])) {
            $filters['no_session'] = true;
        }

        if (!empty($_GET['upcoming'])) {
            $filters['upcoming'] = true;
        }

        $bookings = Session::findAll($limit, $offset, null, $filters);
        $total = Session::count(null, $filters);

        Response::success([
            'bookings' => $bookings,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * GET /bookings/stats
     * Statistiques des réservations (admin uniquement)
     */
    public function stats(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();

        $stats = Session::getBookingStats();
        $smsStats = SMSService::getStats();

        Response::success([
            'bookings' => $stats,
            'sms' => $smsStats,
            'labels' => Session::LABELS
        ]);
    }

    /**
     * GET /bookings/calendar
     * Données de calendrier des réservations (admin uniquement)
     */
    public function calendar(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();

        $year = (int) ($_GET['year'] ?? date('Y'));
        $month = (int) ($_GET['month'] ?? date('n'));

        if ($month < 1 || $month > 12) {
            Response::validationError(['month' => 'Mois invalide']);
        }

        $calendar = Session::getCalendarData($year, $month);

        Response::success([
            'year' => $year,
            'month' => $month,
            'calendar' => $calendar
        ]);
    }

    /**
     * GET /bookings/{id}
     * Détail d'une réservation (admin uniquement)
     */
    public function show(string $id): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();

        $booking = Session::findById($id);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        Response::success([
            'booking' => $booking,
            'labels' => Session::LABELS
        ]);
    }

    /**
     * PUT /bookings/{id}
     * Met à jour une réservation (admin uniquement)
     */
    public function update(string $id): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();
        $currentUser = AuthMiddleware::getCurrentUser();

        $booking = Session::findById($id);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $allowedUpdates = ['admin_notes', 'price'];
        $updateData = array_intersect_key($data, array_flip($allowedUpdates));

        if (empty($updateData)) {
            Response::validationError(['error' => 'Aucune donnée à mettre à jour']);
        }

        // Validate price if provided
        if (isset($updateData['price'])) {
            $updateData['price'] = max(0, (float) $updateData['price']);
        }

        $oldValues = array_intersect_key($booking, $updateData);

        Session::update($id, $updateData);

        AuditService::log(
            $currentUser['id'],
            'booking_updated',
            'booking',
            $id,
            $oldValues,
            $updateData
        );

        $updatedBooking = Session::findById($id);
        Response::success(['booking' => $updatedBooking], 'Réservation mise à jour');
    }

    /**
     * PATCH /bookings/{id}/status
     * Change le statut d'une réservation (admin uniquement)
     */
    public function updateStatus(string $id): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();
        $currentUser = AuthMiddleware::getCurrentUser();

        $booking = Session::findById($id);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($data);
        $validator->required('status')->inArray('status', Session::STATUSES);
        $errors = $validator->validate();

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $newStatus = $data['status'];
        $oldStatus = $booking['status'];

        if ($newStatus === $oldStatus) {
            Response::success(['booking' => $booking], 'Aucun changement');
            return;
        }

        // Traitement selon le nouveau statut
        switch ($newStatus) {
            case Session::STATUS_CONFIRMED:
                Session::confirm($id);
                $booking = Session::findById($id);
                $mailService = new BookingMailService();
                $mailService->sendBookingConfirmedEmail($booking);
                break;

            case Session::STATUS_CANCELLED:
                Session::cancel($id);
                $booking = Session::findById($id);
                $mailService = new BookingMailService();
                $mailService->sendCancellationEmail($booking);
                break;

            case Session::STATUS_NO_SHOW:
                Session::markNoShow($id);
                break;

            case Session::STATUS_COMPLETED:
                // On ne peut pas passer en completed manuellement sans session
                if (empty($booking['session_id'])) {
                    Response::error('Une session doit être liée pour marquer comme complété', 400);
                }
                Session::update($id, ['status' => Session::STATUS_COMPLETED]);
                break;

            default:
                Session::update($id, ['status' => $newStatus]);
        }

        AuditService::log(
            $currentUser['id'],
            'booking_status_changed',
            'booking',
            $id,
            ['status' => $oldStatus],
            ['status' => $newStatus]
        );

        $updatedBooking = Session::findById($id);
        Response::success(['booking' => $updatedBooking], 'Statut mis à jour');
    }

    /**
     * DELETE /bookings/{id}
     * Supprime une réservation (admin uniquement)
     * Envoie un email d'annulation au client si la réservation était confirmée
     */
    public function destroy(string $id): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();
        $currentUser = AuthMiddleware::getCurrentUser();

        $booking = Session::findById($id);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        // Ne pas permettre la suppression si une session est liée
        if (!empty($booking['session_id'])) {
            Response::error('Impossible de supprimer une réservation liée à une séance', 400);
        }

        // Envoyer un email d'annulation si la réservation n'était pas déjà annulée
        $emailSent = false;
        if ($booking['status'] !== Session::STATUS_CANCELLED && !empty($booking['client_email'])) {
            $mailService = new BookingMailService();
            $emailSent = $mailService->sendCancellationEmail($booking);
        }

        Session::delete($id);

        AuditService::log(
            $currentUser['id'],
            'booking_deleted',
            'booking',
            $id,
            $booking,
            ['email_sent' => $emailSent]
        );

        Response::success(['email_sent' => $emailSent], 'Réservation supprimée');
    }

    /**
     * GET /bookings/export/calendar
     * Exporte les réservations confirmées en fichier ICS (admin uniquement)
     */
    public function exportCalendar(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();

        $dateFrom = $_GET['date_from'] ?? (new \DateTime())->format('Y-m-d');
        $dateTo = $_GET['date_to'] ?? (new \DateTime('+3 months'))->format('Y-m-d');

        $bookings = Session::findAll([
            'status' => Session::STATUS_CONFIRMED,
            'date_from' => $dateFrom,
            'date_to' => $dateTo . ' 23:59:59'
        ], 1000, 0);

        if (empty($bookings)) {
            Response::success(['message' => 'Aucune réservation à exporter']);
            return;
        }

        $icsContent = ICSGeneratorService::generateCalendarFile($bookings);
        $headers = ICSGeneratorService::getDownloadHeaders('sensea-reservations.ics');

        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $icsContent;
        exit;
    }

    /**
     * POST /bookings/{id}/reminder
     * Envoie manuellement un rappel (admin uniquement)
     */
    public function sendReminder(string $id): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();
        $currentUser = AuthMiddleware::getCurrentUser();

        $booking = Session::findById($id);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        if ($booking['status'] !== Session::STATUS_CONFIRMED) {
            Response::error('Seules les réservations confirmées peuvent recevoir un rappel', 400);
        }

        $results = [
            'email' => false,
            'sms' => false
        ];

        // Envoyer email
        $mailService = new BookingMailService();
        $results['email'] = $mailService->sendReminderEmail($booking);

        // Envoyer SMS si configuré et téléphone disponible
        if (SMSService::isConfigured() && !empty($booking['client_phone'])) {
            $results['sms'] = SMSService::sendReminder($booking);
        }

        // Mettre à jour la date d'envoi du rappel
        if ($results['email']) {
            Session::update($id, [
                'reminder_email_sent_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        }

        if ($results['sms']) {
            Session::update($id, [
                'reminder_sms_sent_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        }

        AuditService::log(
            $currentUser['id'],
            'booking_reminder_sent',
            'booking',
            $id,
            null,
            $results
        );

        Response::success([
            'results' => $results
        ], 'Rappel envoyé');
    }

    /**
     * POST /bookings/{id}/complete
     * Marque une réservation comme effectuée (admin uniquement)
     * Permet ensuite de remplir les détails de la séance
     */
    public function completeSession(string $id): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();
        $currentUser = AuthMiddleware::getCurrentUser();

        $session = Session::findById($id);

        if (!$session) {
            Response::notFound('Réservation non trouvée');
        }

        if ($session['status'] !== Session::STATUS_CONFIRMED) {
            Response::error('Seules les réservations confirmées peuvent être marquées comme effectuées', 400);
        }

        if ($session['status'] === Session::STATUS_COMPLETED) {
            Response::error('Cette séance est déjà marquée comme effectuée', 400);
        }

        // Marquer la session comme complétée
        Session::complete($id);

        // Gérer la carte de fidélité
        $loyaltyPromoGenerated = null;

        // Vérifier si c'est une séance gratuite (via code promo free_session)
        $isFreeSession = false;
        if (!empty($session['promo_code_id'])) {
            $isFreeSession = PromoCode::isFreeSession($session['promo_code_id']);
        }

        // Incrémenter la fidélité seulement si ce n'est PAS une séance gratuite
        if (!$isFreeSession && !empty($session['user_id'])) {
            $user = User::findById($session['user_id']);

            if ($user && User::isPersonalClient($session['user_id'])) {
                $sessionsRequired = Setting::getInteger('loyalty_sessions_required', 9);
                $loyaltyResult = LoyaltyCard::incrementSessions($session['user_id'], $sessionsRequired);

                // Si la carte vient d'être complétée, générer un code promo
                if ($loyaltyResult['just_completed']) {
                    $userName = $user['first_name'] . ' ' . $user['last_name'];
                    $promoData = PromoCode::generateLoyaltyCode($session['user_id'], $userName);

                    // Envoyer l'email avec le code promo
                    if (!empty($user['email'])) {
                        $mailService = new MailService();
                        $mailService->sendLoyaltyPromoCode(
                            $user['email'],
                            $user['first_name'],
                            $promoData['code']
                        );
                    }

                    $loyaltyPromoGenerated = [
                        'user_id' => $session['user_id'],
                        'user_name' => $userName,
                        'promo_code' => $promoData['code'],
                        'message' => "Code promo fidélité généré et envoyé par email : {$promoData['code']}"
                    ];
                }
            }
        }

        AuditService::log(
            $currentUser['id'],
            'session_completed',
            'session',
            $id,
            ['status' => Session::STATUS_CONFIRMED],
            ['status' => Session::STATUS_COMPLETED]
        );

        $session = Session::findById($id);
        $response = ['session' => $session];

        if ($loyaltyPromoGenerated) {
            $response['loyalty_promo_generated'] = $loyaltyPromoGenerated;
        }

        Response::success($response, 'Séance marquée comme effectuée');
    }

    /**
     * GET /bookings/pending-sessions
     * Liste les réservations confirmées prêtes à devenir des sessions (admin uniquement)
     */
    public function pendingSessions(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();

        $today = new \DateTime();
        $bookings = Session::getConfirmedForDate($today);

        Response::success([
            'bookings' => $bookings,
            'date' => $today->format('Y-m-d')
        ]);
    }

}
