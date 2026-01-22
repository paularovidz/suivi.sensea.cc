<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Utils\UUID;
use App\Utils\Validator;

class User
{
    // Client types
    public const CLIENT_TYPE_PERSONAL = 'personal';
    public const CLIENT_TYPE_ASSOCIATION = 'association';
    public const CLIENT_TYPE_FRIENDS_FAMILY = 'friends_family';

    public const CLIENT_TYPES = [
        self::CLIENT_TYPE_PERSONAL,
        self::CLIENT_TYPE_ASSOCIATION,
        self::CLIENT_TYPE_FRIENDS_FAMILY
    ];

    public const CLIENT_TYPE_LABELS = [
        'personal' => 'Particulier',
        'association' => 'Association',
        'friends_family' => 'Friends & Family'
    ];

    // Types de clients qui sont traités comme des particuliers (pour rate limiting, fidélité, etc.)
    public const PERSONAL_LIKE_TYPES = [
        self::CLIENT_TYPE_PERSONAL,
        self::CLIENT_TYPE_FRIENDS_FAMILY
    ];

    /**
     * Vérifie si un utilisateur est traité comme un particulier (éligible au programme fidélité)
     * Inclut les types: personal, friends_family
     */
    public static function isPersonalClient(string $userId): bool
    {
        $user = self::findById($userId);
        $clientType = $user['client_type'] ?? self::CLIENT_TYPE_PERSONAL;
        return $user && in_array($clientType, self::PERSONAL_LIKE_TYPES, true);
    }

    /**
     * Vérifie si un utilisateur est un Friends & Family
     */
    public static function isFriendsFamily(string $userId): bool
    {
        $user = self::findById($userId);
        return $user && ($user['client_type'] ?? self::CLIENT_TYPE_PERSONAL) === self::CLIENT_TYPE_FRIENDS_FAMILY;
    }

    /**
     * Vérifie si un utilisateur est une association
     */
    public static function isAssociation(string $userId): bool
    {
        $user = self::findById($userId);
        return $user && ($user['client_type'] ?? self::CLIENT_TYPE_PERSONAL) === self::CLIENT_TYPE_ASSOCIATION;
    }

    public static function findById(string $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findAll(int $limit = 100, int $offset = 0, ?string $search = null): array
    {
        $db = Database::getInstance();

        $sql = '
            SELECT id, email, first_name, last_name, phone, role, is_active,
                   client_type, company_name, siret, created_at, updated_at
            FROM users
        ';

        if ($search !== null && $search !== '') {
            $sql .= ' WHERE (
                first_name LIKE :s1
                OR last_name LIKE :s2
                OR email LIKE :s3
                OR phone LIKE :s4
                OR CONCAT(first_name, " ", last_name) LIKE :s5
            )';
        }

        $sql .= ' ORDER BY last_name, first_name LIMIT :limit OFFSET :offset';

        $stmt = $db->prepare($sql);
        if ($search !== null && $search !== '') {
            $searchPattern = '%' . $search . '%';
            $stmt->bindValue(':s1', $searchPattern);
            $stmt->bindValue(':s2', $searchPattern);
            $stmt->bindValue(':s3', $searchPattern);
            $stmt->bindValue(':s4', $searchPattern);
            $stmt->bindValue(':s5', $searchPattern);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function count(?string $search = null): int
    {
        $db = Database::getInstance();

        $sql = 'SELECT COUNT(*) FROM users';

        if ($search !== null && $search !== '') {
            $sql .= ' WHERE (
                first_name LIKE :s1
                OR last_name LIKE :s2
                OR email LIKE :s3
                OR phone LIKE :s4
                OR CONCAT(first_name, " ", last_name) LIKE :s5
            )';
        }

        $stmt = $db->prepare($sql);
        if ($search !== null && $search !== '') {
            $searchPattern = '%' . $search . '%';
            $stmt->bindValue(':s1', $searchPattern);
            $stmt->bindValue(':s2', $searchPattern);
            $stmt->bindValue(':s3', $searchPattern);
            $stmt->bindValue(':s4', $searchPattern);
            $stmt->bindValue(':s5', $searchPattern);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public static function create(array $data): string
    {
        $db = Database::getInstance();
        $id = UUID::generate();

        $stmt = $db->prepare('
            INSERT INTO users (id, email, first_name, last_name, phone, role, is_active, client_type, company_name, siret)
            VALUES (:id, :email, :first_name, :last_name, :phone, :role, :is_active, :client_type, :company_name, :siret)
        ');

        $stmt->execute([
            'id' => $id,
            'email' => strtolower(trim($data['email'])),
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'phone' => Validator::normalizePhone($data['phone'] ?? null),
            'role' => $data['role'] ?? 'member',
            'is_active' => ($data['is_active'] ?? true) ? 1 : 0,
            'client_type' => $data['client_type'] ?? self::CLIENT_TYPE_PERSONAL,
            'company_name' => isset($data['company_name']) ? trim($data['company_name']) : null,
            'siret' => isset($data['siret']) ? preg_replace('/\s+/', '', $data['siret']) : null
        ]);

        return $id;
    }

    public static function update(string $id, array $data): bool
    {
        $db = Database::getInstance();

        $fields = [];
        $params = ['id' => $id];

        $allowedFields = ['email', 'first_name', 'last_name', 'phone', 'role', 'is_active', 'client_type', 'company_name', 'siret'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $value = $data[$field];

                if ($field === 'email') {
                    $value = strtolower(trim((string)$value));
                } elseif ($field === 'phone') {
                    $value = Validator::normalizePhone($value);
                } elseif ($field === 'is_active') {
                    // Convert to integer for MySQL TINYINT
                    $value = $value ? 1 : 0;
                } elseif ($field === 'siret' && $value !== null) {
                    // Remove spaces from SIRET
                    $value = preg_replace('/\s+/', '', (string)$value);
                } elseif (is_string($value)) {
                    $value = trim($value);
                }

                $params[$field] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);

        return $stmt->execute($params);
    }

    public static function delete(string $id): bool
    {
        // Soft delete - just deactivate
        return self::update($id, ['is_active' => false]);
    }

    public static function hardDelete(string $id): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public static function emailExists(string $email, ?string $excludeId = null): bool
    {
        $db = Database::getInstance();

        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => strtolower(trim($email))];

        if ($excludeId) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public static function getPersons(string $userId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT p.*
            FROM persons p
            INNER JOIN user_persons up ON p.id = up.person_id
            WHERE up.user_id = :user_id
            ORDER BY p.last_name, p.first_name
        ');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function toPublic(array $user): array
    {
        unset($user['password_hash']);
        // Ensure is_active is a boolean
        if (isset($user['is_active'])) {
            $user['is_active'] = (bool) $user['is_active'];
        }
        return $user;
    }
}
