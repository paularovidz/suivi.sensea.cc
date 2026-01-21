<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Booking;
use App\Models\Session;
use App\Models\Person;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AvailabilityService;
use App\Services\BookingMailService;
use App\Services\ICSGeneratorService;
use App\Services\SMSService;
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

        $bookings = Booking::findAll($filters, $limit, $offset);
        $total = Booking::count($filters);

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

        $stats = Booking::getStats();
        $smsStats = SMSService::getStats();

        Response::success([
            'bookings' => $stats,
            'sms' => $smsStats,
            'labels' => Booking::LABELS
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

        $calendar = Booking::getCalendarData($year, $month);

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

        $booking = Booking::findById($id);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        Response::success([
            'booking' => $booking,
            'labels' => Booking::LABELS
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

        $booking = Booking::findById($id);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $allowedUpdates = ['admin_notes', 'client_phone'];
        $updateData = array_intersect_key($data, array_flip($allowedUpdates));

        if (empty($updateData)) {
            Response::validationError(['error' => 'Aucune donnée à mettre à jour']);
        }

        $oldValues = array_intersect_key($booking, $updateData);

        Booking::update($id, $updateData);

        AuditService::log(
            $currentUser['id'],
            'booking_updated',
            'booking',
            $id,
            $oldValues,
            $updateData
        );

        $updatedBooking = Booking::findById($id);
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

        $booking = Booking::findById($id);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($data);
        $validator->required('status')->inArray('status', Booking::STATUSES);
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
            case Booking::STATUS_CONFIRMED:
                Booking::confirm($id);
                $booking = Booking::findById($id);
                $mailService = new BookingMailService();
                $mailService->sendBookingConfirmedEmail($booking);
                break;

            case Booking::STATUS_CANCELLED:
                Booking::cancel($id);
                $booking = Booking::findById($id);
                $mailService = new BookingMailService();
                $mailService->sendCancellationEmail($booking);
                break;

            case Booking::STATUS_NO_SHOW:
                Booking::markNoShow($id);
                break;

            case Booking::STATUS_COMPLETED:
                // On ne peut pas passer en completed manuellement sans session
                if (empty($booking['session_id'])) {
                    Response::error('Une session doit être liée pour marquer comme complété', 400);
                }
                Booking::update($id, ['status' => Booking::STATUS_COMPLETED]);
                break;

            default:
                Booking::update($id, ['status' => $newStatus]);
        }

        AuditService::log(
            $currentUser['id'],
            'booking_status_changed',
            'booking',
            $id,
            ['status' => $oldStatus],
            ['status' => $newStatus]
        );

        $updatedBooking = Booking::findById($id);
        Response::success(['booking' => $updatedBooking], 'Statut mis à jour');
    }

    /**
     * DELETE /bookings/{id}
     * Supprime une réservation (admin uniquement)
     */
    public function destroy(string $id): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();
        $currentUser = AuthMiddleware::getCurrentUser();

        $booking = Booking::findById($id);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        // Ne pas permettre la suppression si une session est liée
        if (!empty($booking['session_id'])) {
            Response::error('Impossible de supprimer une réservation liée à une séance', 400);
        }

        Booking::delete($id);

        AuditService::log(
            $currentUser['id'],
            'booking_deleted',
            'booking',
            $id,
            $booking,
            null
        );

        Response::success(null, 'Réservation supprimée');
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

        $bookings = Booking::findAll([
            'status' => Booking::STATUS_CONFIRMED,
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

        $booking = Booking::findById($id);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        if ($booking['status'] !== Booking::STATUS_CONFIRMED) {
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
            Booking::update($id, [
                'reminder_email_sent_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        }

        if ($results['sms']) {
            Booking::update($id, [
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
     * POST /bookings/{id}/create-session
     * Crée une session à partir d'une réservation (admin uniquement)
     */
    public function createSession(string $id): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireAdmin();
        $currentUser = AuthMiddleware::getCurrentUser();

        $booking = Booking::findById($id);

        if (!$booking) {
            Response::notFound('Réservation non trouvée');
        }

        if ($booking['status'] !== Booking::STATUS_CONFIRMED) {
            Response::error('Seules les réservations confirmées peuvent générer une session', 400);
        }

        if (!empty($booking['session_id'])) {
            Response::error('Une session existe déjà pour cette réservation', 400);
        }

        // Créer ou récupérer l'utilisateur
        $userId = $booking['user_id'];
        if (!$userId) {
            // Créer un compte utilisateur inactif
            $userId = User::create([
                'email' => $booking['client_email'],
                'login' => $this->generateLoginFromEmail($booking['client_email']),
                'first_name' => $booking['client_first_name'],
                'last_name' => $booking['client_last_name'],
                'phone' => $booking['client_phone'],
                'role' => 'member',
                'is_active' => false
            ]);

            Booking::update($id, ['user_id' => $userId]);
        }

        // Créer ou récupérer la personne
        $personId = $booking['person_id'];
        if (!$personId) {
            $personId = Person::create([
                'first_name' => $booking['person_first_name'],
                'last_name' => $booking['person_last_name']
            ]);

            // Lier la personne à l'utilisateur
            Person::assignToUser($personId, $userId);

            Booking::update($id, ['person_id' => $personId]);
        }

        // Créer la session
        $sessionData = [
            'person_id' => $personId,
            'created_by' => $currentUser['id'],
            'session_date' => $booking['session_date'],
            'duration_minutes' => $booking['duration_display_minutes'],
            'booking_id' => $id
        ];

        $sessionId = Session::create($sessionData);

        // Marquer la réservation comme complétée
        Booking::complete($id, $sessionId);

        AuditService::log(
            $currentUser['id'],
            'session_created_from_booking',
            'session',
            $sessionId,
            null,
            ['booking_id' => $id]
        );

        $session = Session::findById($sessionId);
        Response::success([
            'session' => $session
        ], 'Session créée', 201);
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
        $bookings = Booking::getConfirmedForDate($today);

        Response::success([
            'bookings' => $bookings,
            'date' => $today->format('Y-m-d')
        ]);
    }

    /**
     * Génère un login unique à partir de l'email
     */
    private function generateLoginFromEmail(string $email): string
    {
        $base = explode('@', $email)[0];
        $base = preg_replace('/[^a-zA-Z0-9]/', '', $base);
        $base = strtolower(substr($base, 0, 20));

        $login = $base;
        $counter = 1;

        while (User::findByLogin($login)) {
            $login = $base . $counter;
            $counter++;
        }

        return $login;
    }
}
