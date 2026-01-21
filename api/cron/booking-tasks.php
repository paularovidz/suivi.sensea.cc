#!/usr/bin/env php
<?php
/**
 * Tâches planifiées pour le système de réservation
 *
 * À exécuter via cron, par exemple :
 * */15 * * * * php /path/to/api/cron/booking-tasks.php >> /var/log/sensea-booking.log 2>&1
 *
 * Ou pour des tâches spécifiques :
 * # Création des sessions (tous les matins à 6h)
 * 0 6 * * * php /path/to/api/cron/booking-tasks.php create-sessions
 *
 * # Envoi des rappels (tous les jours à 18h)
 * 0 18 * * * php /path/to/api/cron/booking-tasks.php send-reminders
 *
 * # Rafraîchissement du cache calendrier (toutes les 5 minutes)
 * */5 * * * * php /path/to/api/cron/booking-tasks.php refresh-calendar
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Models\Booking;
use App\Models\Session;
use App\Models\Person;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\BookingMailService;
use App\Services\SMSService;
use App\Utils\UUID;

// Load environment variables
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (Exception $e) {
    // Continue without .env file (Docker env vars)
}

// Determine task to run
$task = $argv[1] ?? 'all';

echo "[" . date('Y-m-d H:i:s') . "] Starting booking tasks: {$task}\n";

switch ($task) {
    case 'create-sessions':
        createSessionsFromBookings();
        break;

    case 'send-reminders':
        sendReminders();
        break;

    case 'refresh-calendar':
        refreshCalendarCache();
        break;

    case 'cleanup-expired':
        cleanupExpiredBookings();
        break;

    case 'all':
        refreshCalendarCache();
        createSessionsFromBookings();
        sendReminders();
        cleanupExpiredBookings();
        break;

    default:
        echo "Unknown task: {$task}\n";
        echo "Available tasks: create-sessions, send-reminders, refresh-calendar, cleanup-expired, all\n";
        exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Tasks completed.\n";

// ========================================
// TASK FUNCTIONS
// ========================================

/**
 * Crée automatiquement des Sessions à partir des Bookings confirmés du jour
 */
function createSessionsFromBookings(): void
{
    echo "  -> Creating sessions from today's bookings...\n";

    $today = new DateTime();
    $bookings = Booking::getConfirmedForDate($today);

    if (empty($bookings)) {
        echo "     No bookings to process.\n";
        return;
    }

    $created = 0;
    $errors = 0;

    foreach ($bookings as $booking) {
        try {
            // Vérifier qu'une session n'existe pas déjà
            if (!empty($booking['session_id'])) {
                continue;
            }

            // Créer ou récupérer l'utilisateur
            $userId = $booking['user_id'];
            if (!$userId) {
                $userId = getOrCreateUser($booking);
                Booking::update($booking['id'], ['user_id' => $userId]);
            }

            // Créer ou récupérer la personne
            $personId = $booking['person_id'];
            if (!$personId) {
                $personId = getOrCreatePerson($booking, $userId);
                Booking::update($booking['id'], ['person_id' => $personId]);
            }

            // Trouver un admin pour créer la session
            $adminId = getFirstAdminId();
            if (!$adminId) {
                echo "     ERROR: No admin found to create session.\n";
                $errors++;
                continue;
            }

            // Créer la session
            $sessionData = [
                'person_id' => $personId,
                'created_by' => $adminId,
                'session_date' => $booking['session_date'],
                'duration_minutes' => $booking['duration_display_minutes'],
                'booking_id' => $booking['id']
            ];

            $sessionId = Session::create($sessionData);

            // Marquer le booking comme complété
            Booking::complete($booking['id'], $sessionId);

            echo "     Created session {$sessionId} from booking {$booking['id']}\n";
            $created++;

        } catch (Exception $e) {
            echo "     ERROR processing booking {$booking['id']}: {$e->getMessage()}\n";
            $errors++;
        }
    }

    echo "     Sessions created: {$created}, Errors: {$errors}\n";
}

/**
 * Envoie les rappels SMS et email pour les réservations de demain
 */
function sendReminders(): void
{
    echo "  -> Sending reminders for tomorrow's bookings...\n";

    $bookings = Booking::getPendingReminders();

    if (empty($bookings)) {
        echo "     No reminders to send.\n";
        return;
    }

    $smsSent = 0;
    $emailSent = 0;
    $errors = 0;

    $mailService = new BookingMailService();

    foreach ($bookings as $booking) {
        try {
            // Envoyer SMS si configuré et téléphone disponible
            if (SMSService::isConfigured() && !empty($booking['client_phone'])) {
                if (SMSService::sendReminder($booking)) {
                    Booking::update($booking['id'], [
                        'reminder_sms_sent_at' => (new DateTime())->format('Y-m-d H:i:s')
                    ]);
                    $smsSent++;
                    echo "     SMS sent to {$booking['client_phone']}\n";
                }
            }

            // Envoyer email de rappel
            if ($mailService->sendReminderEmail($booking)) {
                Booking::update($booking['id'], [
                    'reminder_email_sent_at' => (new DateTime())->format('Y-m-d H:i:s')
                ]);
                $emailSent++;
                echo "     Email sent to {$booking['client_email']}\n";
            }

        } catch (Exception $e) {
            echo "     ERROR sending reminder for booking {$booking['id']}: {$e->getMessage()}\n";
            $errors++;
        }
    }

    echo "     SMS sent: {$smsSent}, Emails sent: {$emailSent}, Errors: {$errors}\n";
}

/**
 * Rafraîchit le cache du calendrier Google
 */
function refreshCalendarCache(): void
{
    echo "  -> Refreshing calendar cache...\n";

    try {
        if (CalendarService::refreshCache()) {
            echo "     Calendar cache refreshed successfully.\n";
        } else {
            echo "     WARNING: Calendar cache refresh returned false.\n";
        }
    } catch (Exception $e) {
        echo "     ERROR refreshing calendar cache: {$e->getMessage()}\n";
    }
}

/**
 * Nettoie les réservations expirées (pending depuis plus de 24h)
 */
function cleanupExpiredBookings(): void
{
    echo "  -> Cleaning up expired pending bookings...\n";

    try {
        $db = \App\Config\Database::getInstance();

        // Trouver les bookings pending créés il y a plus de 24h
        $stmt = $db->prepare('
            SELECT id, client_email
            FROM bookings
            WHERE status = :pending
            AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ');
        $stmt->execute(['pending' => Booking::STATUS_PENDING]);
        $expiredBookings = $stmt->fetchAll();

        if (empty($expiredBookings)) {
            echo "     No expired bookings to cleanup.\n";
            return;
        }

        $cancelled = 0;
        foreach ($expiredBookings as $booking) {
            Booking::cancel($booking['id']);
            echo "     Cancelled expired booking {$booking['id']} ({$booking['client_email']})\n";
            $cancelled++;
        }

        echo "     Cancelled {$cancelled} expired bookings.\n";

    } catch (Exception $e) {
        echo "     ERROR cleaning up expired bookings: {$e->getMessage()}\n";
    }
}

// ========================================
// HELPER FUNCTIONS
// ========================================

/**
 * Crée ou récupère un utilisateur à partir des données de booking
 */
function getOrCreateUser(array $booking): string
{
    $existingUser = User::findByEmail($booking['client_email']);
    if ($existingUser) {
        return $existingUser['id'];
    }

    // Générer un login unique
    $base = explode('@', $booking['client_email'])[0];
    $base = preg_replace('/[^a-zA-Z0-9]/', '', $base);
    $base = strtolower(substr($base, 0, 20));
    $login = $base;
    $counter = 1;

    while (User::findByLogin($login)) {
        $login = $base . $counter;
        $counter++;
    }

    return User::create([
        'email' => $booking['client_email'],
        'login' => $login,
        'first_name' => $booking['client_first_name'],
        'last_name' => $booking['client_last_name'],
        'phone' => $booking['client_phone'],
        'role' => 'member',
        'is_active' => false
    ]);
}

/**
 * Crée ou récupère une personne à partir des données de booking
 */
function getOrCreatePerson(array $booking, string $userId): string
{
    $personId = Person::create([
        'first_name' => $booking['person_first_name'],
        'last_name' => $booking['person_last_name']
    ]);

    // Lier la personne à l'utilisateur
    Person::assignToUser($personId, $userId);

    return $personId;
}

/**
 * Récupère l'ID du premier admin trouvé
 */
function getFirstAdminId(): ?string
{
    $db = \App\Config\Database::getInstance();
    $stmt = $db->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
    $result = $stmt->fetch();
    return $result ? $result['id'] : null;
}
