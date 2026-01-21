<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Utils\UUID;
use App\Services\AvailabilityService;

/**
 * Modèle de gestion des réservations
 */
class Booking
{
    // Statuts de réservation
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_SHOW = 'no_show';

    // Types de durée
    public const TYPE_DISCOVERY = 'discovery';
    public const TYPE_REGULAR = 'regular';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
        self::STATUS_NO_SHOW
    ];

    public const TYPES = [
        self::TYPE_DISCOVERY,
        self::TYPE_REGULAR
    ];

    // Client types (mirror from User model)
    public const CLIENT_TYPE_PERSONAL = 'personal';
    public const CLIENT_TYPE_ASSOCIATION = 'association';

    public const LABELS = [
        'status' => [
            'pending' => 'En attente de confirmation',
            'confirmed' => 'Confirmé',
            'cancelled' => 'Annulé',
            'completed' => 'Effectué',
            'no_show' => 'Absent'
        ],
        'duration_type' => [
            'discovery' => 'Séance découverte (1h15)',
            'regular' => 'Séance classique (45min)'
        ],
        'client_type' => [
            'personal' => 'Particulier',
            'association' => 'Association'
        ]
    ];

    /**
     * Trouve une réservation par son ID
     */
    public static function findById(string $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT b.*,
                   p.first_name as linked_person_first_name,
                   p.last_name as linked_person_last_name,
                   u.email as user_email,
                   u.first_name as user_first_name,
                   u.last_name as user_last_name,
                   s.id as linked_session_id
            FROM bookings b
            LEFT JOIN persons p ON b.person_id = p.id
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN sessions s ON b.session_id = s.id
            WHERE b.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $booking = $stmt->fetch();

        return $booking ?: null;
    }

    /**
     * Trouve une réservation par son token de confirmation
     */
    public static function findByToken(string $token): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT b.*,
                   p.first_name as linked_person_first_name,
                   p.last_name as linked_person_last_name
            FROM bookings b
            LEFT JOIN persons p ON b.person_id = p.id
            WHERE b.confirmation_token = :token
        ');
        $stmt->execute(['token' => $token]);
        $booking = $stmt->fetch();

        return $booking ?: null;
    }

    /**
     * Trouve les réservations par email client
     */
    public static function findByEmail(string $email): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT b.*,
                   p.first_name as linked_person_first_name,
                   p.last_name as linked_person_last_name
            FROM bookings b
            LEFT JOIN persons p ON b.person_id = p.id
            WHERE b.client_email = :email
            ORDER BY b.session_date DESC
        ');
        $stmt->execute(['email' => strtolower($email)]);

        return $stmt->fetchAll();
    }

    /**
     * Trouve les personnes distinctes associées à un email
     */
    public static function findPersonsByEmail(string $email): array
    {
        $db = Database::getInstance();

        // Chercher dans les réservations les personnes distinctes
        $stmt = $db->prepare('
            SELECT DISTINCT
                b.person_id,
                COALESCE(p.first_name, b.person_first_name) as first_name,
                COALESCE(p.last_name, b.person_last_name) as last_name,
                p.id as linked_person_id
            FROM bookings b
            LEFT JOIN persons p ON b.person_id = p.id
            WHERE b.client_email = :email
            AND (b.status = :confirmed OR b.status = :completed)
            ORDER BY b.created_at DESC
        ');
        $stmt->execute([
            'email' => strtolower($email),
            'confirmed' => self::STATUS_CONFIRMED,
            'completed' => self::STATUS_COMPLETED
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Vérifie si un email existe dans les réservations
     */
    public static function emailExists(string $email): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM bookings
            WHERE client_email = :email
            AND (status = :confirmed OR status = :completed)
        ');
        $stmt->execute([
            'email' => strtolower($email),
            'confirmed' => self::STATUS_CONFIRMED,
            'completed' => self::STATUS_COMPLETED
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Trouve toutes les réservations avec filtres
     */
    public static function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $db = Database::getInstance();

        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'b.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'b.session_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'b.session_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['duration_type'])) {
            $where[] = 'b.duration_type = :duration_type';
            $params['duration_type'] = $filters['duration_type'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $db->prepare("
            SELECT b.*,
                   p.first_name as linked_person_first_name,
                   p.last_name as linked_person_last_name,
                   u.email as user_email,
                   s.id as linked_session_id
            FROM bookings b
            LEFT JOIN persons p ON b.person_id = p.id
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN sessions s ON b.session_id = s.id
            {$whereClause}
            ORDER BY b.session_date DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Compte les réservations avec filtres
     */
    public static function count(array $filters = []): int
    {
        $db = Database::getInstance();

        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'session_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'session_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings {$whereClause}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Crée une nouvelle réservation
     */
    public static function create(array $data): string
    {
        $db = Database::getInstance();
        $id = UUID::generate();
        $token = self::generateConfirmationToken();

        $durations = AvailabilityService::getDurations($data['duration_type']);

        $stmt = $db->prepare('
            INSERT INTO bookings (
                id, user_id, person_id, session_date, duration_type,
                duration_display_minutes, duration_blocked_minutes,
                status, client_email, client_phone, client_first_name, client_last_name,
                person_first_name, person_last_name, confirmation_token,
                gdpr_consent, gdpr_consent_at, client_type, company_name, siret,
                ip_address, user_agent
            ) VALUES (
                :id, :user_id, :person_id, :session_date, :duration_type,
                :duration_display_minutes, :duration_blocked_minutes,
                :status, :client_email, :client_phone, :client_first_name, :client_last_name,
                :person_first_name, :person_last_name, :confirmation_token,
                :gdpr_consent, :gdpr_consent_at, :client_type, :company_name, :siret,
                :ip_address, :user_agent
            )
        ');

        // Clean SIRET (remove spaces)
        $siret = isset($data['siret']) ? preg_replace('/\s+/', '', $data['siret']) : null;

        $stmt->execute([
            'id' => $id,
            'user_id' => $data['user_id'] ?? null,
            'person_id' => $data['person_id'] ?? null,
            'session_date' => $data['session_date'],
            'duration_type' => $data['duration_type'],
            'duration_display_minutes' => $durations['display'],
            'duration_blocked_minutes' => $durations['blocked'],
            'status' => self::STATUS_PENDING,
            'client_email' => strtolower($data['client_email']),
            'client_phone' => $data['client_phone'] ?? null,
            'client_first_name' => $data['client_first_name'],
            'client_last_name' => $data['client_last_name'],
            'person_first_name' => $data['person_first_name'],
            'person_last_name' => $data['person_last_name'],
            'confirmation_token' => $token,
            'gdpr_consent' => $data['gdpr_consent'] ? 1 : 0,
            'gdpr_consent_at' => $data['gdpr_consent'] ? (new \DateTime())->format('Y-m-d H:i:s') : null,
            'client_type' => $data['client_type'] ?? self::CLIENT_TYPE_PERSONAL,
            'company_name' => $data['company_name'] ?? null,
            'siret' => $siret,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null
        ]);

        return $id;
    }

    /**
     * Met à jour une réservation
     */
    public static function update(string $id, array $data): bool
    {
        $db = Database::getInstance();

        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'user_id', 'person_id', 'session_id', 'session_date', 'duration_type',
            'status', 'client_phone', 'admin_notes', 'confirmed_at',
            'reminder_sms_sent_at', 'reminder_email_sent_at',
            'client_type', 'company_name', 'siret'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $value = $data[$field];

                // Clean SIRET (remove spaces)
                if ($field === 'siret' && $value !== null) {
                    $value = preg_replace('/\s+/', '', (string) $value);
                }

                $params[$field] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE bookings SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Confirme une réservation
     */
    public static function confirm(string $id): bool
    {
        return self::update($id, [
            'status' => self::STATUS_CONFIRMED,
            'confirmed_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Annule une réservation
     */
    public static function cancel(string $id): bool
    {
        return self::update($id, [
            'status' => self::STATUS_CANCELLED
        ]);
    }

    /**
     * Marque une réservation comme effectuée et lie à une session
     */
    public static function complete(string $id, string $sessionId): bool
    {
        return self::update($id, [
            'status' => self::STATUS_COMPLETED,
            'session_id' => $sessionId
        ]);
    }

    /**
     * Marque une réservation comme no-show
     */
    public static function markNoShow(string $id): bool
    {
        return self::update($id, [
            'status' => self::STATUS_NO_SHOW
        ]);
    }

    /**
     * Supprime une réservation
     */
    public static function delete(string $id): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM bookings WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Récupère les réservations pour une date donnée (pour calcul de disponibilité)
     */
    public static function getBookingsForDate(\DateTime $date): array
    {
        $db = Database::getInstance();
        $dateStr = $date->format('Y-m-d');

        $stmt = $db->prepare('
            SELECT id, session_date, duration_blocked_minutes, status
            FROM bookings
            WHERE DATE(session_date) = :date
            AND (status = :pending OR status = :confirmed)
            ORDER BY session_date
        ');
        $stmt->execute([
            'date' => $dateStr,
            'pending' => self::STATUS_PENDING,
            'confirmed' => self::STATUS_CONFIRMED
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Vérifie si un créneau est déjà réservé
     */
    public static function isSlotBooked(\DateTime $start, \DateTime $end): bool
    {
        $db = Database::getInstance();

        // On vérifie les réservations pending ou confirmed qui chevauchent le créneau
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM bookings
            WHERE (status = :pending OR status = :confirmed)
            AND (
                (session_date < :end_time AND DATE_ADD(session_date, INTERVAL duration_blocked_minutes MINUTE) > :start_time)
            )
        ');
        $stmt->execute([
            'pending' => self::STATUS_PENDING,
            'confirmed' => self::STATUS_CONFIRMED,
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $end->format('Y-m-d H:i:s')
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Récupère les réservations confirmées pour une date (pour générer les Sessions)
     */
    public static function getConfirmedForDate(\DateTime $date): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT b.*,
                   p.first_name as linked_person_first_name,
                   p.last_name as linked_person_last_name,
                   u.id as linked_user_id
            FROM bookings b
            LEFT JOIN persons p ON b.person_id = p.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE DATE(b.session_date) = :date
            AND b.status = :confirmed
            AND b.session_id IS NULL
            ORDER BY b.session_date
        ');
        $stmt->execute([
            'date' => $date->format('Y-m-d'),
            'confirmed' => self::STATUS_CONFIRMED
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Récupère les réservations nécessitant un rappel
     * (confirmées, pour demain, rappel pas encore envoyé)
     */
    public static function getPendingReminders(): array
    {
        $db = Database::getInstance();
        $tomorrow = (new \DateTime('tomorrow'))->format('Y-m-d');

        $stmt = $db->prepare('
            SELECT b.*,
                   p.first_name as linked_person_first_name,
                   p.last_name as linked_person_last_name
            FROM bookings b
            LEFT JOIN persons p ON b.person_id = p.id
            WHERE DATE(b.session_date) = :tomorrow
            AND b.status = :confirmed
            AND b.reminder_sms_sent_at IS NULL
            AND b.client_phone IS NOT NULL
            ORDER BY b.session_date
        ');
        $stmt->execute([
            'tomorrow' => $tomorrow,
            'confirmed' => self::STATUS_CONFIRMED
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Génère un token de confirmation sécurisé
     */
    public static function generateConfirmationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Compte les réservations à venir par IP
     */
    public static function countUpcomingByIp(string $ip): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM bookings
            WHERE ip_address = :ip
            AND session_date >= NOW()
            AND status IN (:pending, :confirmed)
        ');
        $stmt->execute([
            'ip' => $ip,
            'pending' => self::STATUS_PENDING,
            'confirmed' => self::STATUS_CONFIRMED
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Compte les réservations à venir par email
     */
    public static function countUpcomingByEmail(string $email): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM bookings
            WHERE client_email = :email
            AND session_date >= NOW()
            AND status IN (:pending, :confirmed)
        ');
        $stmt->execute([
            'email' => strtolower($email),
            'pending' => self::STATUS_PENDING,
            'confirmed' => self::STATUS_CONFIRMED
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Récupère les données de calendrier pour un mois (nombre de réservations par jour)
     */
    public static function getCalendarData(int $year, int $month): array
    {
        $db = Database::getInstance();

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-01', $month == 12 ? $year + 1 : $year, $month == 12 ? 1 : $month + 1);

        $stmt = $db->prepare('
            SELECT DATE(session_date) as date, COUNT(*) as count
            FROM bookings
            WHERE session_date >= :start_date
            AND session_date < :end_date
            AND (status = :pending OR status = :confirmed)
            GROUP BY DATE(session_date)
        ');
        $stmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pending' => self::STATUS_PENDING,
            'confirmed' => self::STATUS_CONFIRMED
        ]);

        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['date']] = (int) $row['count'];
        }

        return $result;
    }

    /**
     * Statistiques des réservations
     */
    public static function getStats(): array
    {
        $db = Database::getInstance();

        // Réservations à venir
        $stmt = $db->query("
            SELECT COUNT(*) FROM bookings
            WHERE session_date >= NOW()
            AND (status = 'pending' OR status = 'confirmed')
        ");
        $upcoming = (int) $stmt->fetchColumn();

        // Réservations aujourd'hui
        $stmt = $db->query("
            SELECT COUNT(*) FROM bookings
            WHERE DATE(session_date) = CURDATE()
            AND (status = 'pending' OR status = 'confirmed')
        ");
        $today = (int) $stmt->fetchColumn();

        // En attente de confirmation
        $stmt = $db->query("
            SELECT COUNT(*) FROM bookings
            WHERE status = 'pending'
        ");
        $pending = (int) $stmt->fetchColumn();

        // Par statut ce mois
        $stmt = $db->query("
            SELECT status, COUNT(*) as count
            FROM bookings
            WHERE session_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            GROUP BY status
        ");
        $byStatus = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        return [
            'upcoming' => $upcoming,
            'today' => $today,
            'pending' => $pending,
            'by_status_this_month' => $byStatus
        ];
    }
}
