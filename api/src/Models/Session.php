<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Utils\UUID;
use App\Utils\Encryption;
use App\Utils\Validator;
use App\Services\AvailabilityService;

/**
 * Modèle unifié Session
 *
 * Une session représente tout le cycle de vie d'un rendez-vous :
 * - Réservation (status: pending, confirmed)
 * - Séance effectuée (status: completed) avec détails cliniques
 * - Annulation (status: cancelled, no_show)
 */
class Session
{
    // Statuts du cycle de vie
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_NO_SHOW
    ];

    // Types de durée
    public const TYPE_DISCOVERY = 'discovery';
    public const TYPE_REGULAR = 'regular';

    public const TYPES = [
        self::TYPE_DISCOVERY,
        self::TYPE_REGULAR
    ];

    // Enums pour les détails de séance
    public const BEHAVIORS_START = ['calm', 'agitated', 'defensive', 'anxious', 'passive'];
    public const BEHAVIORS_END = ['calm', 'agitated', 'tired', 'defensive', 'anxious', 'passive'];
    public const PROPOSAL_ORIGINS = ['person', 'relative'];
    public const ATTITUDES_START = ['accepts', 'indifferent', 'refuses'];
    public const POSITIONS = ['standing', 'lying', 'sitting', 'moving'];
    public const COMMUNICATIONS = ['body', 'verbal', 'vocal'];
    public const SESSION_ENDS = ['accepts', 'refuses', 'interrupts'];
    public const APPRECIATIONS = ['negative', 'neutral', 'positive'];

    public const LABELS = [
        'status' => [
            'pending' => 'En attente de confirmation',
            'confirmed' => 'Confirmé',
            'completed' => 'Effectué',
            'cancelled' => 'Annulé',
            'no_show' => 'Absent'
        ],
        'duration_type' => [
            'discovery' => 'Séance découverte (1h15)',
            'regular' => 'Séance classique (45min)'
        ],
        'client_type' => [
            'personal' => 'Particulier',
            'association' => 'Association',
            'friends_family' => 'Friends & Family'
        ],
        'behavior' => [
            'calm' => 'Calme',
            'agitated' => 'Agité',
            'tired' => 'Fatigué',
            'defensive' => 'Défensif',
            'anxious' => 'Inquiet',
            'passive' => 'Passif (apathique)'
        ],
        'proposal_origin' => [
            'person' => 'La personne elle-même',
            'relative' => 'Un proche'
        ],
        'attitude_start' => [
            'accepts' => 'Accepte la séance',
            'indifferent' => 'Indifférente',
            'refuses' => 'Refuse'
        ],
        'position' => [
            'standing' => 'Debout',
            'lying' => 'Allongée',
            'sitting' => 'Assise',
            'moving' => 'Se déplace'
        ],
        'communication' => [
            'body' => 'Corporelle',
            'verbal' => 'Verbale',
            'vocal' => 'Vocale'
        ],
        'session_end' => [
            'accepts' => 'Accepte',
            'refuses' => 'Refuse',
            'interrupts' => 'Interrompt la séance'
        ],
        'appreciation' => [
            'negative' => 'Apprécié négativement',
            'neutral' => 'Neutralité',
            'positive' => 'Apprécié positivement'
        ]
    ];

    // =========================================================================
    // RECHERCHE ET LECTURE
    // =========================================================================

    public static function findById(string $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*,
                   s.duration_minutes as duration_display_minutes,
                   p.first_name as person_first_name,
                   p.last_name as person_last_name,
                   p.birth_date as person_birth_date,
                   u.first_name as creator_first_name,
                   u.last_name as creator_last_name,
                   client.email as client_email,
                   client.phone as client_phone,
                   client.first_name as client_first_name,
                   client.last_name as client_last_name,
                   client.client_type,
                   client.company_name,
                   client.siret,
                   pc.code as promo_code,
                   pc.name as promo_code_name,
                   pc.discount_type as promo_discount_type,
                   pc.discount_value as promo_discount_value
            FROM sessions s
            INNER JOIN persons p ON s.person_id = p.id
            LEFT JOIN users u ON s.created_by = u.id
            LEFT JOIN users client ON s.user_id = client.id
            LEFT JOIN promo_codes pc ON s.promo_code_id = pc.id
            WHERE s.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $session = $stmt->fetch();

        if ($session) {
            $session = self::decryptFields($session);
            $session['proposals'] = self::getProposals($id);

            // Format promo code info
            if (!empty($session['promo_code_id'])) {
                $session['promo_code'] = [
                    'id' => $session['promo_code_id'],
                    'code' => $session['promo_code'],
                    'name' => $session['promo_code_name'],
                    'discount_type' => $session['promo_discount_type'],
                    'discount_value' => $session['promo_discount_value'],
                    'discount_label' => \App\Models\PromoCode::getDiscountLabel([
                        'discount_type' => $session['promo_discount_type'],
                        'discount_value' => $session['promo_discount_value']
                    ])
                ];
            }
        }

        return $session ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*,
                   s.duration_minutes as duration_display_minutes,
                   p.first_name as person_first_name,
                   p.last_name as person_last_name,
                   client.email as client_email,
                   client.phone as client_phone,
                   client.first_name as client_first_name,
                   client.last_name as client_last_name,
                   client.client_type,
                   client.company_name,
                   client.siret
            FROM sessions s
            LEFT JOIN persons p ON s.person_id = p.id
            LEFT JOIN users client ON s.user_id = client.id
            WHERE s.confirmation_token = :token
        ');
        $stmt->execute(['token' => $token]);
        $session = $stmt->fetch();

        if ($session) {
            $session = self::decryptFields($session);
        }

        return $session ?: null;
    }

    public static function findByPerson(string $personId, int $limit = 50, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*,
                   s.duration_minutes as duration_display_minutes,
                   u.first_name as creator_first_name,
                   u.last_name as creator_last_name
            FROM sessions s
            LEFT JOIN users u ON s.created_by = u.id
            WHERE s.person_id = :person_id
            ORDER BY s.session_date DESC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':person_id', $personId);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $sessions = $stmt->fetchAll();
        foreach ($sessions as &$session) {
            $session = self::decryptFields($session);
        }

        return $sessions;
    }

    public static function findByEmail(string $email): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*,
                   s.duration_minutes as duration_display_minutes,
                   p.first_name as person_first_name,
                   p.last_name as person_last_name,
                   client.email as client_email,
                   client.first_name as client_first_name,
                   client.last_name as client_last_name
            FROM sessions s
            JOIN users client ON s.user_id = client.id
            LEFT JOIN persons p ON s.person_id = p.id
            WHERE client.email = :email
            ORDER BY s.session_date DESC
        ');
        $stmt->execute(['email' => strtolower($email)]);

        $sessions = $stmt->fetchAll();
        foreach ($sessions as &$session) {
            $session = self::decryptFields($session);
        }

        return $sessions;
    }

    public static function findByUser(string $userId, int $limit = 50, int $offset = 0, ?string $search = null, array $filters = []): array
    {
        $db = Database::getInstance();

        $where = ['up.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($search !== null && $search !== '') {
            $where[] = '(
                p.first_name LIKE :s1
                OR p.last_name LIKE :s2
                OR CONCAT(p.first_name, " ", p.last_name) LIKE :s3
                OR DATE_FORMAT(s.session_date, "%d/%m/%Y") LIKE :s4
            )';
            $params['s1'] = '%' . $search . '%';
            $params['s2'] = '%' . $search . '%';
            $params['s3'] = '%' . $search . '%';
            $params['s4'] = '%' . $search . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 's.session_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 's.session_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT s.*,
                   s.duration_minutes as duration_display_minutes,
                   p.first_name as person_first_name,
                   p.last_name as person_last_name,
                   p.birth_date as person_birth_date
            FROM sessions s
            INNER JOIN persons p ON s.person_id = p.id
            INNER JOIN user_persons up ON p.id = up.person_id
            {$whereClause}
            ORDER BY s.session_date DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $sessions = $stmt->fetchAll();
        foreach ($sessions as &$session) {
            $session = self::decryptFields($session);
        }

        return $sessions;
    }

    public static function findAll(int $limit = 50, int $offset = 0, ?string $search = null, array $filters = []): array
    {
        $db = Database::getInstance();

        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(
                p.first_name LIKE :s1
                OR p.last_name LIKE :s2
                OR CONCAT(p.first_name, " ", p.last_name) LIKE :s3
                OR DATE_FORMAT(s.session_date, "%d/%m/%Y") LIKE :s4
            )';
            $params['s1'] = '%' . $search . '%';
            $params['s2'] = '%' . $search . '%';
            $params['s3'] = '%' . $search . '%';
            $params['s4'] = '%' . $search . '%';
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = [];
                foreach ($filters['status'] as $i => $status) {
                    $key = 'status_' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $status;
                }
                $where[] = 's.status IN (' . implode(', ', $placeholders) . ')';
            } else {
                $where[] = 's.status = :status';
                $params['status'] = $filters['status'];
            }
        }

        if (!empty($filters['date_from'])) {
            $where[] = 's.session_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 's.session_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['duration_type'])) {
            $where[] = 's.duration_type = :duration_type';
            $params['duration_type'] = $filters['duration_type'];
        }

        if (!empty($filters['person_id'])) {
            $where[] = 's.person_id = :person_id';
            $params['person_id'] = $filters['person_id'];
        }

        if (!empty($filters['upcoming'])) {
            $where[] = 's.session_date >= NOW()';
        }

        if (!empty($filters['pending_or_confirmed'])) {
            $where[] = "s.status IN ('pending', 'confirmed')";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT s.*,
                   s.duration_minutes as duration_display_minutes,
                   p.first_name as person_first_name,
                   p.last_name as person_last_name,
                   p.birth_date as person_birth_date,
                   u.first_name as creator_first_name,
                   u.last_name as creator_last_name,
                   client.email as client_email,
                   client.first_name as client_first_name,
                   client.last_name as client_last_name,
                   client.client_type,
                   client.phone as client_phone,
                   client.company_name,
                   CASE WHEN s.status = 'completed' THEN s.id ELSE NULL END as linked_session_id
            FROM sessions s
            INNER JOIN persons p ON s.person_id = p.id
            LEFT JOIN users u ON s.created_by = u.id
            LEFT JOIN users client ON s.user_id = client.id
            {$whereClause}
            ORDER BY s.session_date DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $sessions = $stmt->fetchAll();
        foreach ($sessions as &$session) {
            $session = self::decryptFields($session);
        }

        return $sessions;
    }

    // =========================================================================
    // COMPTAGE
    // =========================================================================

    public static function count(?string $search = null, array $filters = []): int
    {
        $db = Database::getInstance();

        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(
                p.first_name LIKE :s1
                OR p.last_name LIKE :s2
                OR CONCAT(p.first_name, " ", p.last_name) LIKE :s3
                OR DATE_FORMAT(s.session_date, "%d/%m/%Y") LIKE :s4
            )';
            $params['s1'] = '%' . $search . '%';
            $params['s2'] = '%' . $search . '%';
            $params['s3'] = '%' . $search . '%';
            $params['s4'] = '%' . $search . '%';
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = [];
                foreach ($filters['status'] as $i => $status) {
                    $key = 'status_' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $status;
                }
                $where[] = 's.status IN (' . implode(', ', $placeholders) . ')';
            } else {
                $where[] = 's.status = :status';
                $params['status'] = $filters['status'];
            }
        }

        if (!empty($filters['person_id'])) {
            $where[] = 's.person_id = :person_id';
            $params['person_id'] = $filters['person_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 's.session_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 's.session_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['upcoming'])) {
            $where[] = 's.session_date >= NOW()';
        }

        if (!empty($filters['pending_or_confirmed'])) {
            $where[] = "s.status IN ('pending', 'confirmed')";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT COUNT(*) FROM sessions s
            INNER JOIN persons p ON s.person_id = p.id
            {$whereClause}
        ";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public static function countByPerson(string $personId): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM sessions WHERE person_id = :person_id AND status = :status');
        $stmt->execute(['person_id' => $personId, 'status' => self::STATUS_COMPLETED]);
        return (int)$stmt->fetchColumn();
    }

    public static function countByUser(string $userId, ?string $search = null, array $filters = []): int
    {
        $db = Database::getInstance();

        $where = ['up.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($search !== null && $search !== '') {
            $where[] = '(
                p.first_name LIKE :s1
                OR p.last_name LIKE :s2
                OR CONCAT(p.first_name, " ", p.last_name) LIKE :s3
                OR DATE_FORMAT(s.session_date, "%d/%m/%Y") LIKE :s4
            )';
            $params['s1'] = '%' . $search . '%';
            $params['s2'] = '%' . $search . '%';
            $params['s3'] = '%' . $search . '%';
            $params['s4'] = '%' . $search . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 's.session_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 's.session_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT COUNT(*) FROM sessions s
            INNER JOIN persons p ON s.person_id = p.id
            INNER JOIN user_persons up ON s.person_id = up.person_id
            {$whereClause}
        ";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public static function countUpcomingByIp(string $ip): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM sessions
            WHERE ip_address = :ip
            AND session_date >= NOW()
            AND status IN ('pending', 'confirmed')
        ");
        $stmt->execute(['ip' => $ip]);
        return (int)$stmt->fetchColumn();
    }

    public static function countUpcomingByEmail(string $email): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE u.email = :email
            AND s.session_date >= NOW()
            AND s.status IN ('pending', 'confirmed')
        ");
        $stmt->execute(['email' => strtolower($email)]);
        return (int)$stmt->fetchColumn();
    }

    public static function countUpcomingByPerson(string $personId): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM sessions
            WHERE person_id = :person_id
            AND session_date >= NOW()
            AND status IN ('pending', 'confirmed')
        ");
        $stmt->execute(['person_id' => $personId]);
        return (int)$stmt->fetchColumn();
    }

    // =========================================================================
    // CRÉATION
    // =========================================================================

    /**
     * Crée une réservation (status pending/confirmed)
     */
    public static function createReservation(array $data): string
    {
        $db = Database::getInstance();
        $id = UUID::generate();
        $token = self::generateConfirmationToken();

        $durations = AvailabilityService::getDurations($data['duration_type']);

        $stmt = $db->prepare('
            INSERT INTO sessions (
                id, user_id, person_id, created_by, session_date,
                duration_minutes, duration_type, duration_blocked_minutes,
                price, status, confirmation_token,
                gdpr_consent, gdpr_consent_at,
                ip_address, user_agent,
                promo_code_id, original_price, discount_amount
            ) VALUES (
                :id, :user_id, :person_id, :created_by, :session_date,
                :duration_minutes, :duration_type, :duration_blocked_minutes,
                :price, :status, :confirmation_token,
                :gdpr_consent, :gdpr_consent_at,
                :ip_address, :user_agent,
                :promo_code_id, :original_price, :discount_amount
            )
        ');

        $stmt->execute([
            'id' => $id,
            'user_id' => $data['user_id'],
            'person_id' => $data['person_id'],
            'created_by' => $data['user_id'], // Le client est le créateur pour les réservations
            'session_date' => $data['session_date'],
            'duration_minutes' => $durations['display'],
            'duration_type' => $data['duration_type'],
            'duration_blocked_minutes' => $durations['blocked'],
            'price' => $data['price'] ?? null,
            'status' => self::STATUS_PENDING,
            'confirmation_token' => $token,
            'gdpr_consent' => $data['gdpr_consent'] ? 1 : 0,
            'gdpr_consent_at' => $data['gdpr_consent'] ? (new \DateTime())->format('Y-m-d H:i:s') : null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'promo_code_id' => $data['promo_code_id'] ?? null,
            'original_price' => $data['original_price'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? null
        ]);

        return $id;
    }

    /**
     * Crée une session complète (admin - status completed)
     */
    public static function create(array $data): string
    {
        $db = Database::getInstance();
        $id = UUID::generate();

        $communication = $data['communication'] ?? null;
        if (is_array($communication)) {
            $communication = json_encode($communication);
        }

        // Générer un token même pour les sessions créées manuellement
        $token = self::generateConfirmationToken();

        $stmt = $db->prepare('
            INSERT INTO sessions (
                id, user_id, person_id, created_by, session_date,
                duration_minutes, duration_type, duration_blocked_minutes, price,
                status, confirmation_token, confirmed_at,
                sessions_per_month, behavior_start, proposal_origin, attitude_start,
                position, communication, session_end, behavior_end, wants_to_return,
                professional_notes, person_expression, next_session_proposals,
                is_invoiced, is_paid, is_free_session
            ) VALUES (
                :id, :user_id, :person_id, :created_by, :session_date,
                :duration_minutes, :duration_type, :duration_blocked_minutes, :price,
                :status, :confirmation_token, :confirmed_at,
                :sessions_per_month, :behavior_start, :proposal_origin, :attitude_start,
                :position, :communication, :session_end, :behavior_end, :wants_to_return,
                :professional_notes, :person_expression, :next_session_proposals,
                :is_invoiced, :is_paid, :is_free_session
            )
        ');

        $wantsToReturn = $data['wants_to_return'] ?? null;
        if ($wantsToReturn !== null && $wantsToReturn !== '') {
            $wantsToReturn = $wantsToReturn ? 1 : 0;
        } else {
            $wantsToReturn = null;
        }

        // Déterminer le type de durée basé sur la durée
        $durationMinutes = $data['duration_minutes'] ?? 45;
        $durationType = $durationMinutes >= 75 ? self::TYPE_DISCOVERY : self::TYPE_REGULAR;

        $stmt->execute([
            'id' => $id,
            'user_id' => $data['user_id'] ?? null,
            'person_id' => $data['person_id'],
            'created_by' => $data['created_by'],
            'session_date' => $data['session_date'],
            'duration_minutes' => $durationMinutes,
            'duration_type' => $data['duration_type'] ?? $durationType,
            'duration_blocked_minutes' => $data['duration_blocked_minutes'] ?? null,
            'price' => $data['price'] ?? null,
            'status' => $data['status'] ?? self::STATUS_COMPLETED,
            'confirmation_token' => $token,
            'confirmed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'sessions_per_month' => $data['sessions_per_month'] ?? null,
            'behavior_start' => !empty($data['behavior_start']) ? $data['behavior_start'] : null,
            'proposal_origin' => !empty($data['proposal_origin']) ? $data['proposal_origin'] : null,
            'attitude_start' => !empty($data['attitude_start']) ? $data['attitude_start'] : null,
            'position' => !empty($data['position']) ? $data['position'] : null,
            'communication' => $communication,
            'session_end' => !empty($data['session_end']) ? $data['session_end'] : null,
            'behavior_end' => !empty($data['behavior_end']) ? $data['behavior_end'] : null,
            'wants_to_return' => $wantsToReturn,
            'professional_notes' => Encryption::encrypt($data['professional_notes'] ?? null),
            'person_expression' => Encryption::encrypt($data['person_expression'] ?? null),
            'next_session_proposals' => Encryption::encrypt($data['next_session_proposals'] ?? null),
            'is_invoiced' => ($data['is_invoiced'] ?? false) ? 1 : 0,
            'is_paid' => ($data['is_paid'] ?? false) ? 1 : 0,
            'is_free_session' => ($data['is_free_session'] ?? false) ? 1 : 0
        ]);

        if (!empty($data['proposals'])) {
            self::setProposals($id, $data['proposals']);
        }

        return $id;
    }

    // =========================================================================
    // MISE À JOUR
    // =========================================================================

    public static function update(string $id, array $data): bool
    {
        $db = Database::getInstance();

        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'session_date', 'duration_minutes', 'duration_type', 'duration_blocked_minutes',
            'price', 'status', 'confirmed_at', 'admin_notes',
            'sessions_per_month', 'behavior_start', 'proposal_origin', 'attitude_start',
            'position', 'communication', 'session_end', 'behavior_end', 'wants_to_return',
            'professional_notes', 'person_expression', 'next_session_proposals',
            'is_invoiced', 'is_paid', 'is_free_session',
            'reminder_sms_sent_at', 'reminder_email_sent_at',
            'promo_code_id', 'original_price', 'discount_amount'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $value = $data[$field];

                if ($field === 'communication' && is_array($value)) {
                    $value = json_encode($value);
                } elseif (in_array($field, ['professional_notes', 'person_expression', 'next_session_proposals'])) {
                    $value = Encryption::encrypt($value);
                } elseif ($field === 'wants_to_return') {
                    if ($value !== null && $value !== '') {
                        $value = $value ? 1 : 0;
                    } else {
                        $value = null;
                    }
                } elseif (in_array($field, ['behavior_start', 'proposal_origin', 'attitude_start', 'position', 'session_end', 'behavior_end'])) {
                    $value = !empty($value) ? $value : null;
                } elseif (in_array($field, ['is_invoiced', 'is_paid', 'is_free_session'])) {
                    $value = $value ? 1 : 0;
                } elseif ($field === 'price' && $value !== null) {
                    $value = max(0, (float) $value);
                }

                $params[$field] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE sessions SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);

        if (array_key_exists('proposals', $data)) {
            self::setProposals($id, $data['proposals']);
        }

        return $result;
    }

    // =========================================================================
    // GESTION DU STATUT
    // =========================================================================

    public static function confirm(string $id): bool
    {
        return self::update($id, [
            'status' => self::STATUS_CONFIRMED,
            'confirmed_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    public static function cancel(string $id): bool
    {
        return self::update($id, [
            'status' => self::STATUS_CANCELLED
        ]);
    }

    public static function complete(string $id): bool
    {
        return self::update($id, [
            'status' => self::STATUS_COMPLETED
        ]);
    }

    public static function markNoShow(string $id): bool
    {
        return self::update($id, [
            'status' => self::STATUS_NO_SHOW
        ]);
    }

    // =========================================================================
    // SUPPRESSION
    // =========================================================================

    public static function delete(string $id): bool
    {
        $db = Database::getInstance();

        $stmt = $db->prepare('DELETE FROM session_proposals WHERE session_id = :id');
        $stmt->execute(['id' => $id]);

        $stmt = $db->prepare('DELETE FROM sessions WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    // =========================================================================
    // PROPOSITIONS SENSORIELLES
    // =========================================================================

    public static function getProposals(string $sessionId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT sp_link.id as link_id, sp_link.appreciation, sp_link.order,
                   sp.id, sp.title, sp.type, sp.description
            FROM session_proposals sp_link
            INNER JOIN sensory_proposals sp ON sp_link.sensory_proposal_id = sp.id
            WHERE sp_link.session_id = :session_id
            ORDER BY sp_link.order
        ');
        $stmt->execute(['session_id' => $sessionId]);

        return $stmt->fetchAll();
    }

    public static function setProposals(string $sessionId, array $proposals): void
    {
        $db = Database::getInstance();

        $stmt = $db->prepare('DELETE FROM session_proposals WHERE session_id = :session_id');
        $stmt->execute(['session_id' => $sessionId]);

        $stmt = $db->prepare('
            INSERT INTO session_proposals (id, session_id, sensory_proposal_id, appreciation, `order`)
            VALUES (:id, :session_id, :sensory_proposal_id, :appreciation, :order)
        ');

        foreach ($proposals as $index => $proposal) {
            $stmt->execute([
                'id' => UUID::generate(),
                'session_id' => $sessionId,
                'sensory_proposal_id' => $proposal['sensory_proposal_id'],
                'appreciation' => $proposal['appreciation'] ?? null,
                'order' => $proposal['order'] ?? $index
            ]);
        }
    }

    // =========================================================================
    // DISPONIBILITÉ ET CALENDRIER
    // =========================================================================

    public static function getSessionsForDate(\DateTime $date): array
    {
        $db = Database::getInstance();
        $dateStr = $date->format('Y-m-d');

        $stmt = $db->prepare("
            SELECT id, session_date, duration_minutes, duration_blocked_minutes, status
            FROM sessions
            WHERE DATE(session_date) = :date
            AND status IN ('pending', 'confirmed', 'completed')
            ORDER BY session_date
        ");
        $stmt->execute(['date' => $dateStr]);

        return $stmt->fetchAll();
    }

    public static function isSlotBooked(\DateTime $start, \DateTime $end): bool
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT COUNT(*) FROM sessions
            WHERE status IN ('pending', 'confirmed')
            AND (
                session_date < :end_time
                AND DATE_ADD(session_date, INTERVAL COALESCE(duration_blocked_minutes, duration_minutes) MINUTE) > :start_time
            )
        ");
        $stmt->execute([
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $end->format('Y-m-d H:i:s')
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public static function getConfirmedForDate(\DateTime $date): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT s.*,
                   s.duration_minutes as duration_display_minutes,
                   p.first_name as person_first_name,
                   p.last_name as person_last_name,
                   client.email as client_email,
                   client.phone as client_phone,
                   client.first_name as client_first_name,
                   client.last_name as client_last_name
            FROM sessions s
            LEFT JOIN persons p ON s.person_id = p.id
            LEFT JOIN users client ON s.user_id = client.id
            WHERE DATE(s.session_date) = :date
            AND s.status = 'confirmed'
            ORDER BY s.session_date
        ");
        $stmt->execute(['date' => $date->format('Y-m-d')]);

        $sessions = $stmt->fetchAll();
        foreach ($sessions as &$session) {
            $session = self::decryptFields($session);
        }

        return $sessions;
    }

    public static function getPendingReminders(): array
    {
        $db = Database::getInstance();
        $tomorrow = (new \DateTime('tomorrow'))->format('Y-m-d');

        $stmt = $db->prepare("
            SELECT s.*,
                   s.duration_minutes as duration_display_minutes,
                   p.first_name as person_first_name,
                   p.last_name as person_last_name,
                   client.email as client_email,
                   client.phone as client_phone,
                   client.first_name as client_first_name,
                   client.last_name as client_last_name
            FROM sessions s
            JOIN users client ON s.user_id = client.id
            LEFT JOIN persons p ON s.person_id = p.id
            WHERE DATE(s.session_date) = :tomorrow
            AND s.status = 'confirmed'
            AND s.reminder_sms_sent_at IS NULL
            AND client.phone IS NOT NULL
            ORDER BY s.session_date
        ");
        $stmt->execute(['tomorrow' => $tomorrow]);

        $sessions = $stmt->fetchAll();
        foreach ($sessions as &$session) {
            $session = self::decryptFields($session);
        }

        return $sessions;
    }

    public static function getCalendarData(int $year, int $month, ?string $userId = null): array
    {
        $db = Database::getInstance();

        if ($userId === null) {
            $stmt = $db->prepare("
                SELECT DATE(session_date) as date, COUNT(*) as count
                FROM sessions
                WHERE YEAR(session_date) = :year AND MONTH(session_date) = :month
                AND status IN ('pending', 'confirmed', 'completed')
                GROUP BY DATE(session_date)
            ");
            $stmt->execute(['year' => $year, 'month' => $month]);
        } else {
            $stmt = $db->prepare("
                SELECT DATE(s.session_date) as date, COUNT(*) as count
                FROM sessions s
                INNER JOIN user_persons up ON s.person_id = up.person_id
                WHERE up.user_id = :user_id
                  AND YEAR(s.session_date) = :year
                  AND MONTH(s.session_date) = :month
                  AND s.status IN ('pending', 'confirmed', 'completed')
                GROUP BY DATE(s.session_date)
            ");
            $stmt->execute(['user_id' => $userId, 'year' => $year, 'month' => $month]);
        }

        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    // =========================================================================
    // UTILITAIRES
    // =========================================================================

    public static function generateConfirmationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function getPriceForType(string $durationType, string $clientType = 'personal'): int
    {
        $isAssociation = $clientType === 'association';

        if ($durationType === self::TYPE_DISCOVERY) {
            return $isAssociation
                ? Setting::getInteger('session_discovery_price_association', 50)
                : Setting::getInteger('session_discovery_price', 55);
        }

        return $isAssociation
            ? Setting::getInteger('session_regular_price_association', 40)
            : Setting::getInteger('session_regular_price', 45);
    }

    public static function canAccess(string $sessionId, string $userId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        $session = self::findById($sessionId);
        if (!$session) {
            return false;
        }

        return Person::isAssignedToUser($session['person_id'], $userId);
    }

    private static function decryptFields(array $session): array
    {
        $session['professional_notes'] = Encryption::decrypt($session['professional_notes'] ?? null);
        $session['person_expression'] = Encryption::decrypt($session['person_expression'] ?? null);
        $session['next_session_proposals'] = Encryption::decrypt($session['next_session_proposals'] ?? null);

        if (isset($session['communication']) && is_string($session['communication'])) {
            $session['communication'] = json_decode($session['communication'], true);
        }

        return $session;
    }

    // =========================================================================
    // STATISTIQUES
    // =========================================================================

    public static function getStats(string $personId): array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT COUNT(*) FROM sessions WHERE person_id = :person_id AND status = 'completed'");
        $stmt->execute(['person_id' => $personId]);
        $totalSessions = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT AVG(duration_minutes) FROM sessions WHERE person_id = :person_id AND status = 'completed'");
        $stmt->execute(['person_id' => $personId]);
        $avgDuration = round((float)$stmt->fetchColumn(), 1);

        $stmt = $db->prepare("SELECT MAX(session_date) FROM sessions WHERE person_id = :person_id AND status = 'completed'");
        $stmt->execute(['person_id' => $personId]);
        $lastSession = $stmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT behavior_end, COUNT(*) as count
            FROM sessions
            WHERE person_id = :person_id AND behavior_end IS NOT NULL AND status = 'completed'
            GROUP BY behavior_end
        ");
        $stmt->execute(['person_id' => $personId]);
        $behaviorStats = $stmt->fetchAll();

        return [
            'total_sessions' => $totalSessions,
            'average_duration' => $avgDuration,
            'last_session' => $lastSession,
            'behavior_distribution' => $behaviorStats
        ];
    }

    public static function getGlobalStats(): array
    {
        $db = Database::getInstance();

        $stmt = $db->query("
            SELECT COUNT(*) FROM sessions
            WHERE session_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            AND status = 'completed'
        ");
        $sessionsThisMonth = (int)$stmt->fetchColumn();

        $stmt = $db->query("
            SELECT COUNT(*) FROM sessions
            WHERE DATE(session_date) = CURDATE()
            AND status IN ('confirmed', 'completed')
        ");
        $sessionsToday = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM sessions WHERE status = 'completed'");
        $totalSessions = (int)$stmt->fetchColumn();

        $stmt = $db->query("
            SELECT COUNT(*) FROM sessions
            WHERE session_date >= NOW()
            AND status IN ('pending', 'confirmed')
        ");
        $upcoming = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM sessions WHERE status = 'pending'");
        $pending = (int)$stmt->fetchColumn();

        return [
            'sessions_this_month' => $sessionsThisMonth,
            'sessions_today' => $sessionsToday,
            'total_sessions' => $totalSessions,
            'upcoming' => $upcoming,
            'pending' => $pending
        ];
    }

    public static function getBookingStats(): array
    {
        $db = Database::getInstance();

        $stmt = $db->query("
            SELECT COUNT(*) FROM sessions
            WHERE session_date >= NOW()
            AND status IN ('pending', 'confirmed')
        ");
        $upcoming = (int)$stmt->fetchColumn();

        $stmt = $db->query("
            SELECT COUNT(*) FROM sessions
            WHERE DATE(session_date) = CURDATE()
            AND status IN ('pending', 'confirmed', 'completed')
        ");
        $today = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM sessions WHERE status = 'pending'");
        $pending = (int)$stmt->fetchColumn();

        $stmt = $db->query("
            SELECT status, COUNT(*) as count
            FROM sessions
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

    public static function getPersonStats(string $personId): array
    {
        $db = Database::getInstance();

        // Completed sessions (past)
        $stmt = $db->prepare("
            SELECT DATE(session_date) as date, COUNT(*) as count
            FROM sessions
            WHERE person_id = :person_id AND status = 'completed'
            GROUP BY DATE(session_date)
            ORDER BY date DESC
            LIMIT 365
        ");
        $stmt->execute(['person_id' => $personId]);
        $sessionDates = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Upcoming bookings (future sessions pending or confirmed)
        $stmt = $db->prepare("
            SELECT DATE(session_date) as date, COUNT(*) as count
            FROM sessions
            WHERE person_id = :person_id
            AND status IN ('pending', 'confirmed')
            AND DATE(session_date) >= CURDATE()
            GROUP BY DATE(session_date)
            ORDER BY date ASC
            LIMIT 365
        ");
        $stmt->execute(['person_id' => $personId]);
        $bookingDates = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $stmt = $db->prepare("
            SELECT session_end, COUNT(*) as count
            FROM sessions
            WHERE person_id = :person_id AND session_end IS NOT NULL AND status = 'completed'
            GROUP BY session_end
        ");
        $stmt->execute(['person_id' => $personId]);
        $sessionEndStats = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT behavior_end, COUNT(*) as count
            FROM sessions
            WHERE person_id = :person_id AND behavior_end IS NOT NULL AND status = 'completed'
            GROUP BY behavior_end
        ");
        $stmt->execute(['person_id' => $personId]);
        $behaviorEndStats = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT communication
            FROM sessions
            WHERE person_id = :person_id AND communication IS NOT NULL AND status = 'completed'
        ");
        $stmt->execute(['person_id' => $personId]);
        $communications = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $commCounts = ['body' => 0, 'verbal' => 0, 'vocal' => 0];
        foreach ($communications as $commJson) {
            $comms = json_decode($commJson, true) ?: [];
            foreach ($comms as $comm) {
                if (isset($commCounts[$comm])) {
                    $commCounts[$comm]++;
                }
            }
        }

        $stmt = $db->prepare("
            SELECT sp.type,
                   SUM(CASE WHEN spl.appreciation = 'positive' THEN 1 ELSE 0 END) as positive,
                   SUM(CASE WHEN spl.appreciation = 'neutral' THEN 1 ELSE 0 END) as neutral,
                   SUM(CASE WHEN spl.appreciation = 'negative' THEN 1 ELSE 0 END) as negative,
                   COUNT(*) as total
            FROM session_proposals spl
            INNER JOIN sessions s ON spl.session_id = s.id
            INNER JOIN sensory_proposals sp ON spl.sensory_proposal_id = sp.id
            WHERE s.person_id = :person_id AND s.status = 'completed'
            GROUP BY sp.type
        ");
        $stmt->execute(['person_id' => $personId]);
        $sensoryStats = $stmt->fetchAll();

        return [
            'session_dates' => $sessionDates,
            'booking_dates' => $bookingDates,
            'session_end_distribution' => $sessionEndStats,
            'behavior_end_distribution' => $behaviorEndStats,
            'communication_distribution' => $commCounts,
            'sensory_appreciation' => $sensoryStats
        ];
    }

    /**
     * Vérifie si un email existe (a des sessions confirmées/complétées)
     */
    public static function emailExists(string $email): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE u.email = :email
            AND s.status IN ('confirmed', 'completed')
        ");
        $stmt->execute(['email' => strtolower($email)]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Trouve les personnes distinctes associées à un email
     */
    public static function findPersonsByEmail(string $email): array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT DISTINCT
                s.person_id,
                p.first_name,
                p.last_name,
                p.id as linked_person_id
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            JOIN persons p ON s.person_id = p.id
            WHERE u.email = :email
            AND s.status IN ('confirmed', 'completed')
            ORDER BY s.created_at DESC
        ");
        $stmt->execute(['email' => strtolower($email)]);

        return $stmt->fetchAll();
    }
}
