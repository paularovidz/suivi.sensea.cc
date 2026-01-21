<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Utils\UUID;
use App\Utils\Encryption;

class Session
{
    public const BEHAVIORS_START = ['calm', 'agitated', 'defensive', 'anxious', 'passive'];
    public const BEHAVIORS_END = ['calm', 'agitated', 'tired', 'defensive', 'anxious', 'passive'];
    public const PROPOSAL_ORIGINS = ['person', 'relative'];
    public const ATTITUDES_START = ['accepts', 'indifferent', 'refuses'];
    public const POSITIONS = ['standing', 'lying', 'sitting', 'moving'];
    public const COMMUNICATIONS = ['body', 'verbal', 'vocal'];
    public const SESSION_ENDS = ['accepts', 'refuses', 'interrupts'];
    public const APPRECIATIONS = ['negative', 'neutral', 'positive'];

    public const LABELS = [
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

    public static function findById(string $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*,
                   p.first_name as person_first_name,
                   p.last_name as person_last_name,
                   p.birth_date as person_birth_date,
                   u.first_name as creator_first_name,
                   u.last_name as creator_last_name
            FROM sessions s
            INNER JOIN persons p ON s.person_id = p.id
            INNER JOIN users u ON s.created_by = u.id
            WHERE s.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $session = $stmt->fetch();

        if ($session) {
            $session = self::decryptFields($session);
            $session['proposals'] = self::getProposals($id);
        }

        return $session ?: null;
    }

    public static function findByPerson(string $personId, int $limit = 50, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*,
                   u.first_name as creator_first_name,
                   u.last_name as creator_last_name
            FROM sessions s
            INNER JOIN users u ON s.created_by = u.id
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

    /**
     * Récupère les séances pour une date donnée (pour la vérification de disponibilité)
     */
    public static function getSessionsForDate(\DateTime $date): array
    {
        $db = Database::getInstance();
        $dateStr = $date->format('Y-m-d');

        $stmt = $db->prepare('
            SELECT id, session_date, duration_minutes
            FROM sessions
            WHERE DATE(session_date) = :date
            ORDER BY session_date
        ');
        $stmt->execute(['date' => $dateStr]);

        return $stmt->fetchAll();
    }

    public static function findByUser(string $userId, int $limit = 50, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*,
                   p.first_name as person_first_name,
                   p.last_name as person_last_name,
                   p.birth_date as person_birth_date
            FROM sessions s
            INNER JOIN persons p ON s.person_id = p.id
            INNER JOIN user_persons up ON p.id = up.person_id
            WHERE up.user_id = :user_id
            ORDER BY s.session_date DESC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $sessions = $stmt->fetchAll();

        foreach ($sessions as &$session) {
            $session = self::decryptFields($session);
        }

        return $sessions;
    }

    public static function findAll(int $limit = 50, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT s.*,
                   p.first_name as person_first_name,
                   p.last_name as person_last_name,
                   p.birth_date as person_birth_date,
                   u.first_name as creator_first_name,
                   u.last_name as creator_last_name
            FROM sessions s
            INNER JOIN persons p ON s.person_id = p.id
            INNER JOIN users u ON s.created_by = u.id
            ORDER BY s.session_date DESC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $sessions = $stmt->fetchAll();

        foreach ($sessions as &$session) {
            $session = self::decryptFields($session);
        }

        return $sessions;
    }

    public static function count(): int
    {
        $db = Database::getInstance();
        $stmt = $db->query('SELECT COUNT(*) FROM sessions');
        return (int)$stmt->fetchColumn();
    }

    public static function countByPerson(string $personId): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM sessions WHERE person_id = :person_id');
        $stmt->execute(['person_id' => $personId]);
        return (int)$stmt->fetchColumn();
    }

    public static function countByUser(string $userId): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM sessions s
            INNER JOIN user_persons up ON s.person_id = up.person_id
            WHERE up.user_id = :user_id
        ');
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function create(array $data): string
    {
        $db = Database::getInstance();
        $id = UUID::generate();

        $communication = $data['communication'] ?? null;
        if (is_array($communication)) {
            $communication = json_encode($communication);
        }

        $stmt = $db->prepare('
            INSERT INTO sessions (
                id, person_id, created_by, session_date, duration_minutes, sessions_per_month,
                behavior_start, proposal_origin, attitude_start,
                position, communication,
                session_end, behavior_end, wants_to_return,
                professional_notes, person_expression, next_session_proposals,
                is_invoiced, is_paid, is_free_session
            ) VALUES (
                :id, :person_id, :created_by, :session_date, :duration_minutes, :sessions_per_month,
                :behavior_start, :proposal_origin, :attitude_start,
                :position, :communication,
                :session_end, :behavior_end, :wants_to_return,
                :professional_notes, :person_expression, :next_session_proposals,
                :is_invoiced, :is_paid, :is_free_session
            )
        ');

        // Convert wants_to_return to integer or null
        $wantsToReturn = $data['wants_to_return'] ?? null;
        if ($wantsToReturn !== null && $wantsToReturn !== '') {
            $wantsToReturn = $wantsToReturn ? 1 : 0;
        } else {
            $wantsToReturn = null;
        }

        $stmt->execute([
            'id' => $id,
            'person_id' => $data['person_id'],
            'created_by' => $data['created_by'],
            'session_date' => $data['session_date'],
            'duration_minutes' => $data['duration_minutes'],
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

        // Add proposals if provided
        if (!empty($data['proposals'])) {
            self::setProposals($id, $data['proposals']);
        }

        return $id;
    }

    public static function update(string $id, array $data): bool
    {
        $db = Database::getInstance();

        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'session_date', 'duration_minutes', 'sessions_per_month',
            'behavior_start', 'proposal_origin', 'attitude_start',
            'position', 'communication',
            'session_end', 'behavior_end', 'wants_to_return',
            'professional_notes', 'person_expression', 'next_session_proposals',
            'is_invoiced', 'is_paid', 'is_free_session'
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
                    // Convert to integer or null for MySQL TINYINT
                    if ($value !== null && $value !== '') {
                        $value = $value ? 1 : 0;
                    } else {
                        $value = null;
                    }
                } elseif (in_array($field, ['behavior_start', 'proposal_origin', 'attitude_start', 'position', 'session_end', 'behavior_end'])) {
                    // Convert empty strings to null for ENUM fields
                    $value = !empty($value) ? $value : null;
                } elseif (in_array($field, ['is_invoiced', 'is_paid', 'is_free_session'])) {
                    // Convert to integer for MySQL TINYINT
                    $value = $value ? 1 : 0;
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

        // Update proposals if provided
        if (array_key_exists('proposals', $data)) {
            self::setProposals($id, $data['proposals']);
        }

        return $result;
    }

    public static function delete(string $id): bool
    {
        $db = Database::getInstance();

        // Delete proposals first (cascade should handle this, but be explicit)
        $stmt = $db->prepare('DELETE FROM session_proposals WHERE session_id = :id');
        $stmt->execute(['id' => $id]);

        $stmt = $db->prepare('DELETE FROM sessions WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

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

        // Delete existing
        $stmt = $db->prepare('DELETE FROM session_proposals WHERE session_id = :session_id');
        $stmt->execute(['session_id' => $sessionId]);

        // Insert new
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

    public static function getStats(string $personId): array
    {
        $db = Database::getInstance();

        // Total sessions
        $stmt = $db->prepare('SELECT COUNT(*) FROM sessions WHERE person_id = :person_id');
        $stmt->execute(['person_id' => $personId]);
        $totalSessions = (int)$stmt->fetchColumn();

        // Average duration
        $stmt = $db->prepare('SELECT AVG(duration_minutes) FROM sessions WHERE person_id = :person_id');
        $stmt->execute(['person_id' => $personId]);
        $avgDuration = round((float)$stmt->fetchColumn(), 1);

        // Last session date
        $stmt = $db->prepare('SELECT MAX(session_date) FROM sessions WHERE person_id = :person_id');
        $stmt->execute(['person_id' => $personId]);
        $lastSession = $stmt->fetchColumn();

        // Behavior distribution (end)
        $stmt = $db->prepare('
            SELECT behavior_end, COUNT(*) as count
            FROM sessions
            WHERE person_id = :person_id AND behavior_end IS NOT NULL
            GROUP BY behavior_end
        ');
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

        // Sessions this month
        $stmt = $db->query("
            SELECT COUNT(*) FROM sessions
            WHERE session_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ");
        $sessionsThisMonth = (int)$stmt->fetchColumn();

        // Sessions today
        $stmt = $db->query("
            SELECT COUNT(*) FROM sessions
            WHERE DATE(session_date) = CURDATE()
        ");
        $sessionsToday = (int)$stmt->fetchColumn();

        // Total sessions
        $stmt = $db->query('SELECT COUNT(*) FROM sessions');
        $totalSessions = (int)$stmt->fetchColumn();

        return [
            'sessions_this_month' => $sessionsThisMonth,
            'sessions_today' => $sessionsToday,
            'total_sessions' => $totalSessions
        ];
    }

    public static function getCalendarData(int $year, int $month, ?string $userId = null): array
    {
        $db = Database::getInstance();

        if ($userId === null) {
            // Admin: all sessions
            $stmt = $db->prepare("
                SELECT DATE(session_date) as date, COUNT(*) as count
                FROM sessions
                WHERE YEAR(session_date) = :year AND MONTH(session_date) = :month
                GROUP BY DATE(session_date)
            ");
            $stmt->execute(['year' => $year, 'month' => $month]);
        } else {
            // User: only sessions for assigned persons
            $stmt = $db->prepare("
                SELECT DATE(s.session_date) as date, COUNT(*) as count
                FROM sessions s
                INNER JOIN user_persons up ON s.person_id = up.person_id
                WHERE up.user_id = :user_id
                  AND YEAR(s.session_date) = :year
                  AND MONTH(s.session_date) = :month
                GROUP BY DATE(s.session_date)
            ");
            $stmt->execute(['user_id' => $userId, 'year' => $year, 'month' => $month]);
        }

        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public static function getPersonStats(string $personId): array
    {
        $db = Database::getInstance();

        // Session dates for calendar
        $stmt = $db->prepare("
            SELECT DATE(session_date) as date, COUNT(*) as count
            FROM sessions
            WHERE person_id = :person_id
            GROUP BY DATE(session_date)
            ORDER BY date DESC
            LIMIT 365
        ");
        $stmt->execute(['person_id' => $personId]);
        $sessionDates = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Session end distribution
        $stmt = $db->prepare("
            SELECT session_end, COUNT(*) as count
            FROM sessions
            WHERE person_id = :person_id AND session_end IS NOT NULL
            GROUP BY session_end
        ");
        $stmt->execute(['person_id' => $personId]);
        $sessionEndStats = $stmt->fetchAll();

        // Behavior end distribution
        $stmt = $db->prepare("
            SELECT behavior_end, COUNT(*) as count
            FROM sessions
            WHERE person_id = :person_id AND behavior_end IS NOT NULL
            GROUP BY behavior_end
        ");
        $stmt->execute(['person_id' => $personId]);
        $behaviorEndStats = $stmt->fetchAll();

        // Communication distribution
        $stmt = $db->prepare("
            SELECT communication
            FROM sessions
            WHERE person_id = :person_id AND communication IS NOT NULL
        ");
        $stmt->execute(['person_id' => $personId]);
        $communications = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Aggregate communication counts
        $commCounts = ['body' => 0, 'verbal' => 0, 'vocal' => 0];
        foreach ($communications as $commJson) {
            $comms = json_decode($commJson, true) ?: [];
            foreach ($comms as $comm) {
                if (isset($commCounts[$comm])) {
                    $commCounts[$comm]++;
                }
            }
        }

        // Sensory appreciation by type (for radar chart)
        $stmt = $db->prepare("
            SELECT sp.type,
                   SUM(CASE WHEN spl.appreciation = 'positive' THEN 1 ELSE 0 END) as positive,
                   SUM(CASE WHEN spl.appreciation = 'neutral' THEN 1 ELSE 0 END) as neutral,
                   SUM(CASE WHEN spl.appreciation = 'negative' THEN 1 ELSE 0 END) as negative,
                   COUNT(*) as total
            FROM session_proposals spl
            INNER JOIN sessions s ON spl.session_id = s.id
            INNER JOIN sensory_proposals sp ON spl.sensory_proposal_id = sp.id
            WHERE s.person_id = :person_id
            GROUP BY sp.type
        ");
        $stmt->execute(['person_id' => $personId]);
        $sensoryStats = $stmt->fetchAll();

        return [
            'session_dates' => $sessionDates,
            'session_end_distribution' => $sessionEndStats,
            'behavior_end_distribution' => $behaviorEndStats,
            'communication_distribution' => $commCounts,
            'sensory_appreciation' => $sensoryStats
        ];
    }
}
