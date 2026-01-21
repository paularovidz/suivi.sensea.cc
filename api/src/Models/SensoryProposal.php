<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Utils\UUID;

class SensoryProposal
{
    public const TYPES = ['tactile', 'visual', 'olfactory', 'gustatory', 'auditory', 'proprioceptive', 'vestibular'];

    public const TYPE_LABELS = [
        'tactile' => 'Tactile',
        'visual' => 'Visuelle',
        'olfactory' => 'Olfactive',
        'gustatory' => 'Gustative',
        'auditory' => 'Auditive',
        'proprioceptive' => 'Proprioceptive',
        'vestibular' => 'Vestibulaire'
    ];

    public static function findById(string $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT sp.*, u.first_name as creator_first_name, u.last_name as creator_last_name
            FROM sensory_proposals sp
            LEFT JOIN users u ON sp.created_by = u.id
            WHERE sp.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $proposal = $stmt->fetch();

        return $proposal ?: null;
    }

    public static function findAll(int $limit = 100, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT sp.*, u.first_name as creator_first_name, u.last_name as creator_last_name
            FROM sensory_proposals sp
            LEFT JOIN users u ON sp.created_by = u.id
            ORDER BY sp.type, sp.title
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function search(string $query, ?string $type = null, int $limit = 20): array
    {
        $db = Database::getInstance();

        $searchPattern = '%' . $query . '%';

        $sql = '
            SELECT sp.*, u.first_name as creator_first_name, u.last_name as creator_last_name
            FROM sensory_proposals sp
            LEFT JOIN users u ON sp.created_by = u.id
            WHERE (sp.title LIKE :query1 OR sp.description LIKE :query2)
        ';

        $params = [
            'query1' => $searchPattern,
            'query2' => $searchPattern
        ];

        if ($type && in_array($type, self::TYPES, true)) {
            $sql .= ' AND sp.type = :type';
            $params['type'] = $type;
        }

        $sql .= ' ORDER BY sp.title LIMIT :limit';

        $stmt = $db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function findByType(string $type, int $limit = 100): array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT sp.*, u.first_name as creator_first_name, u.last_name as creator_last_name
            FROM sensory_proposals sp
            LEFT JOIN users u ON sp.created_by = u.id
            WHERE sp.type = :type
            ORDER BY sp.title
            LIMIT :limit
        ');

        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function count(): int
    {
        $db = Database::getInstance();
        $stmt = $db->query('SELECT COUNT(*) FROM sensory_proposals');
        return (int)$stmt->fetchColumn();
    }

    public static function create(array $data): string
    {
        $db = Database::getInstance();
        $id = UUID::generate();

        $stmt = $db->prepare('
            INSERT INTO sensory_proposals (id, title, type, description, created_by)
            VALUES (:id, :title, :type, :description, :created_by)
        ');

        $stmt->execute([
            'id' => $id,
            'title' => trim($data['title']),
            'type' => $data['type'],
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'created_by' => $data['created_by']
        ]);

        return $id;
    }

    public static function update(string $id, array $data): bool
    {
        $db = Database::getInstance();

        $fields = [];
        $params = ['id' => $id];

        $allowedFields = ['title', 'type', 'description'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $value = $data[$field];

                if (is_string($value)) {
                    $value = trim($value);
                }

                $params[$field] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE sensory_proposals SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);

        return $stmt->execute($params);
    }

    public static function delete(string $id): bool
    {
        $db = Database::getInstance();

        // Check if used in sessions
        $stmt = $db->prepare('SELECT COUNT(*) FROM session_proposals WHERE sensory_proposal_id = :id');
        $stmt->execute(['id' => $id]);

        if ((int)$stmt->fetchColumn() > 0) {
            return false; // Cannot delete if used
        }

        $stmt = $db->prepare('DELETE FROM sensory_proposals WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public static function canModify(string $id, string $userId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        $proposal = self::findById($id);
        if (!$proposal) {
            return false;
        }

        return $proposal['created_by'] === $userId;
    }

    public static function isUsedInSessions(string $id): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM session_proposals WHERE sensory_proposal_id = :id');
        $stmt->execute(['id' => $id]);

        return (int)$stmt->fetchColumn() > 0;
    }
}
