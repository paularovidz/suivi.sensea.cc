<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Utils\UUID;

/**
 * Modèle de gestion des cartes de fidélité
 */
class LoyaltyCard
{
    /**
     * Trouve une carte par son ID
     */
    public static function findById(string $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM loyalty_cards WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $card = $stmt->fetch();

        return $card ? self::formatCard($card) : null;
    }

    /**
     * Trouve une carte par l'ID de l'utilisateur
     */
    public static function findByUserId(string $userId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM loyalty_cards WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $card = $stmt->fetch();

        return $card ? self::formatCard($card) : null;
    }

    /**
     * Récupère ou crée une carte pour un utilisateur
     */
    public static function getOrCreate(string $userId): array
    {
        $card = self::findByUserId($userId);

        if (!$card) {
            $id = UUID::generate();
            $db = Database::getInstance();
            $stmt = $db->prepare('INSERT INTO loyalty_cards (id, user_id) VALUES (:id, :user_id)');
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            $card = self::findById($id);
        }

        return $card;
    }

    /**
     * Incrémente le compteur de séances
     */
    public static function incrementSessions(string $userId, int $sessionsRequired): bool
    {
        $db = Database::getInstance();

        // Récupérer ou créer la carte
        $card = self::getOrCreate($userId);

        // Ne pas incrémenter si la carte est déjà complétée et la séance gratuite pas encore utilisée
        if ($card['is_completed'] && !$card['free_session_used_at']) {
            return true; // La carte est en attente d'utilisation de la séance gratuite
        }

        // Si la carte était complétée et la séance gratuite utilisée, on repart à zéro
        if ($card['is_completed'] && $card['free_session_used_at']) {
            self::resetCard($userId);
            $card = self::findByUserId($userId);
        }

        $newCount = $card['sessions_count'] + 1;
        $isCompleted = $newCount >= $sessionsRequired;

        $stmt = $db->prepare('
            UPDATE loyalty_cards
            SET sessions_count = :count,
                is_completed = :completed,
                completed_at = CASE WHEN :completed2 = 1 AND completed_at IS NULL THEN NOW() ELSE completed_at END
            WHERE user_id = :user_id
        ');

        return $stmt->execute([
            'count' => $newCount,
            'completed' => $isCompleted ? 1 : 0,
            'completed2' => $isCompleted ? 1 : 0,
            'user_id' => $userId
        ]);
    }

    /**
     * Marque la séance gratuite comme utilisée
     */
    public static function markFreeSessionUsed(string $userId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE loyalty_cards
            SET free_session_used_at = NOW()
            WHERE user_id = :user_id AND is_completed = 1 AND free_session_used_at IS NULL
        ');
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Réinitialise une carte (après utilisation de la séance gratuite)
     */
    public static function resetCard(string $userId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE loyalty_cards
            SET sessions_count = 0,
                is_completed = 0,
                completed_at = NULL,
                free_session_used_at = NULL
            WHERE user_id = :user_id
        ');
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Récupère les informations de progression d'une carte
     */
    public static function getWithProgress(string $userId, int $sessionsRequired): array
    {
        $user = User::findById($userId);

        // Seuls les particuliers sont éligibles
        if (!$user || ($user['client_type'] ?? User::CLIENT_TYPE_PERSONAL) !== User::CLIENT_TYPE_PERSONAL) {
            return [
                'eligible' => false,
                'reason' => 'Les associations ne sont pas éligibles au programme de fidélité'
            ];
        }

        $card = self::getOrCreate($userId);

        $freeSessionAvailable = $card['is_completed'] && !$card['free_session_used_at'];
        $progressPercent = min(100, round(($card['sessions_count'] / $sessionsRequired) * 100));

        return [
            'eligible' => true,
            'card' => $card,
            'sessions_count' => $card['sessions_count'],
            'sessions_required' => $sessionsRequired,
            'progress_percent' => $progressPercent,
            'is_completed' => (bool) $card['is_completed'],
            'free_session_available' => $freeSessionAvailable,
            'completed_at' => $card['completed_at'],
            'free_session_used_at' => $card['free_session_used_at']
        ];
    }

    /**
     * Vérifie si un utilisateur a une séance gratuite disponible
     */
    public static function hasFreeSessionAvailable(string $userId): bool
    {
        $card = self::findByUserId($userId);

        if (!$card) {
            return false;
        }

        return $card['is_completed'] && !$card['free_session_used_at'];
    }

    /**
     * Compte le nombre total de séances d'un utilisateur (via les personnes qui lui sont assignées)
     */
    public static function countUserSessions(string $userId): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM sessions s
            INNER JOIN user_persons up ON s.person_id = up.person_id
            WHERE up.user_id = :user_id
        ');
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Formate les champs d'une carte
     */
    private static function formatCard(array $card): array
    {
        $card['sessions_count'] = (int) $card['sessions_count'];
        $card['is_completed'] = (bool) $card['is_completed'];
        return $card;
    }
}
