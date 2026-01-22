<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Utils\UUID;

class PromoCode
{
    // Types de remise
    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
    public const DISCOUNT_TYPE_FIXED_AMOUNT = 'fixed_amount';
    public const DISCOUNT_TYPE_FREE_SESSION = 'free_session';

    public const DISCOUNT_TYPES = [
        self::DISCOUNT_TYPE_PERCENTAGE,
        self::DISCOUNT_TYPE_FIXED_AMOUNT,
        self::DISCOUNT_TYPE_FREE_SESSION
    ];

    public const DISCOUNT_TYPE_LABELS = [
        'percentage' => 'Pourcentage',
        'fixed_amount' => 'Montant fixe',
        'free_session' => 'Séance gratuite'
    ];

    // Modes d'application
    public const MODE_MANUAL = 'manual';
    public const MODE_AUTOMATIC = 'automatic';

    public const APPLICATION_MODES = [
        self::MODE_MANUAL,
        self::MODE_AUTOMATIC
    ];

    public const APPLICATION_MODE_LABELS = [
        'manual' => 'Code à saisir',
        'automatic' => 'Automatique'
    ];

    // =========================================================================
    // RECHERCHE
    // =========================================================================

    public static function findById(string $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT pc.*,
                   u.first_name as creator_first_name,
                   u.last_name as creator_last_name,
                   tu.first_name as target_first_name,
                   tu.last_name as target_last_name,
                   tu.email as target_email
            FROM promo_codes pc
            LEFT JOIN users u ON pc.created_by = u.id
            LEFT JOIN users tu ON pc.target_user_id = tu.id
            WHERE pc.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $promo = $stmt->fetch();

        if ($promo) {
            $promo = self::castBooleans($promo);
            $promo['usage_count'] = self::getUsageCount($id);
        }

        return $promo ?: null;
    }

    public static function findByCode(string $code): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT pc.*,
                   tu.first_name as target_first_name,
                   tu.last_name as target_last_name,
                   tu.email as target_email
            FROM promo_codes pc
            LEFT JOIN users tu ON pc.target_user_id = tu.id
            WHERE pc.code = :code
        ');
        $stmt->execute(['code' => strtoupper(trim($code))]);
        $promo = $stmt->fetch();

        if ($promo) {
            $promo = self::castBooleans($promo);
            $promo['usage_count'] = self::getUsageCount($promo['id']);
        }

        return $promo ?: null;
    }

    public static function findAll(int $limit = 100, int $offset = 0, ?string $search = null, array $filters = []): array
    {
        $db = Database::getInstance();

        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(pc.code LIKE :s1 OR pc.name LIKE :s2 OR pc.description LIKE :s3)';
            $params['s1'] = '%' . $search . '%';
            $params['s2'] = '%' . $search . '%';
            $params['s3'] = '%' . $search . '%';
        }

        if (isset($filters['is_active'])) {
            $where[] = 'pc.is_active = :is_active';
            $params['is_active'] = $filters['is_active'] ? 1 : 0;
        }

        if (!empty($filters['application_mode'])) {
            $where[] = 'pc.application_mode = :application_mode';
            $params['application_mode'] = $filters['application_mode'];
        }

        if (!empty($filters['discount_type'])) {
            $where[] = 'pc.discount_type = :discount_type';
            $params['discount_type'] = $filters['discount_type'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT pc.*,
                   u.first_name as creator_first_name,
                   u.last_name as creator_last_name,
                   tu.first_name as target_first_name,
                   tu.last_name as target_last_name,
                   tu.email as target_email,
                   (SELECT COUNT(*) FROM promo_code_usages WHERE promo_code_id = pc.id) as usage_count
            FROM promo_codes pc
            LEFT JOIN users u ON pc.created_by = u.id
            LEFT JOIN users tu ON pc.target_user_id = tu.id
            {$whereClause}
            ORDER BY pc.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $promos = $stmt->fetchAll();
        foreach ($promos as &$promo) {
            $promo = self::castBooleans($promo);
        }

        return $promos;
    }

    public static function count(?string $search = null, array $filters = []): int
    {
        $db = Database::getInstance();

        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(code LIKE :s1 OR name LIKE :s2 OR description LIKE :s3)';
            $params['s1'] = '%' . $search . '%';
            $params['s2'] = '%' . $search . '%';
            $params['s3'] = '%' . $search . '%';
        }

        if (isset($filters['is_active'])) {
            $where[] = 'is_active = :is_active';
            $params['is_active'] = $filters['is_active'] ? 1 : 0;
        }

        if (!empty($filters['application_mode'])) {
            $where[] = 'application_mode = :application_mode';
            $params['application_mode'] = $filters['application_mode'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT COUNT(*) FROM promo_codes {$whereClause}";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    // =========================================================================
    // CRÉATION ET MISE À JOUR
    // =========================================================================

    public static function create(array $data): string
    {
        $db = Database::getInstance();
        $id = UUID::generate();

        $stmt = $db->prepare('
            INSERT INTO promo_codes (
                id, code, name, description, discount_type, discount_value,
                application_mode, target_user_id, target_client_type,
                max_uses_total, max_uses_per_user, valid_from, valid_until,
                applies_to_discovery, applies_to_regular, is_active, created_by
            ) VALUES (
                :id, :code, :name, :description, :discount_type, :discount_value,
                :application_mode, :target_user_id, :target_client_type,
                :max_uses_total, :max_uses_per_user, :valid_from, :valid_until,
                :applies_to_discovery, :applies_to_regular, :is_active, :created_by
            )
        ');

        $code = !empty($data['code']) ? strtoupper(trim($data['code'])) : null;

        $stmt->execute([
            'id' => $id,
            'code' => $code,
            'name' => trim($data['name']),
            'description' => !empty($data['description']) ? trim($data['description']) : null,
            'discount_type' => $data['discount_type'],
            'discount_value' => (float)$data['discount_value'],
            'application_mode' => $data['application_mode'] ?? self::MODE_MANUAL,
            'target_user_id' => !empty($data['target_user_id']) ? $data['target_user_id'] : null,
            'target_client_type' => !empty($data['target_client_type']) ? $data['target_client_type'] : null,
            'max_uses_total' => !empty($data['max_uses_total']) ? (int)$data['max_uses_total'] : null,
            'max_uses_per_user' => !empty($data['max_uses_per_user']) ? (int)$data['max_uses_per_user'] : null,
            'valid_from' => !empty($data['valid_from']) ? $data['valid_from'] : null,
            'valid_until' => !empty($data['valid_until']) ? $data['valid_until'] : null,
            'applies_to_discovery' => ($data['applies_to_discovery'] ?? true) ? 1 : 0,
            'applies_to_regular' => ($data['applies_to_regular'] ?? true) ? 1 : 0,
            'is_active' => ($data['is_active'] ?? true) ? 1 : 0,
            'created_by' => $data['created_by'] ?? null
        ]);

        return $id;
    }

    public static function update(string $id, array $data): bool
    {
        $db = Database::getInstance();

        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'code', 'name', 'description', 'discount_type', 'discount_value',
            'application_mode', 'target_user_id', 'target_client_type',
            'max_uses_total', 'max_uses_per_user', 'valid_from', 'valid_until',
            'applies_to_discovery', 'applies_to_regular', 'is_active'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $value = $data[$field];

                if ($field === 'code' && $value !== null) {
                    $value = strtoupper(trim((string)$value));
                } elseif (in_array($field, ['applies_to_discovery', 'applies_to_regular', 'is_active'])) {
                    $value = $value ? 1 : 0;
                } elseif ($field === 'discount_value') {
                    $value = (float)$value;
                } elseif (in_array($field, ['max_uses_total', 'max_uses_per_user']) && $value !== null) {
                    $value = (int)$value;
                } elseif (in_array($field, ['target_user_id', 'target_client_type', 'valid_from', 'valid_until', 'description']) && empty($value)) {
                    $value = null;
                } elseif (is_string($value)) {
                    $value = trim($value);
                }

                $params[$field] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE promo_codes SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);

        return $stmt->execute($params);
    }

    public static function delete(string $id): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM promo_codes WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    // =========================================================================
    // VALIDATION ET VÉRIFICATION
    // =========================================================================

    /**
     * Valide un code promo pour une réservation
     */
    public static function validate(
        string $code,
        string $durationType,
        ?string $userId = null,
        ?string $clientType = null
    ): array {
        $promo = self::findByCode($code);

        if (!$promo) {
            return ['valid' => false, 'error' => 'Code promo invalide'];
        }

        return self::validatePromo($promo, $durationType, $userId, $clientType);
    }

    /**
     * Valide un code promo (objet) pour une réservation
     */
    public static function validatePromo(
        array $promo,
        string $durationType,
        ?string $userId = null,
        ?string $clientType = null
    ): array {
        // Vérifier si actif
        if (!$promo['is_active']) {
            return ['valid' => false, 'error' => 'Ce code promo n\'est plus actif'];
        }

        // Vérifier les dates de validité
        $now = new \DateTime();

        if (!empty($promo['valid_from'])) {
            $validFrom = new \DateTime($promo['valid_from']);
            if ($now < $validFrom) {
                return ['valid' => false, 'error' => 'Ce code promo n\'est pas encore valide'];
            }
        }

        if (!empty($promo['valid_until'])) {
            $validUntil = new \DateTime($promo['valid_until']);
            if ($now > $validUntil) {
                return ['valid' => false, 'error' => 'Ce code promo a expiré'];
            }
        }

        // Vérifier le type de séance
        if ($durationType === 'discovery' && !$promo['applies_to_discovery']) {
            return ['valid' => false, 'error' => 'Ce code promo n\'est pas valide pour les séances découverte'];
        }

        if ($durationType === 'regular' && !$promo['applies_to_regular']) {
            return ['valid' => false, 'error' => 'Ce code promo n\'est pas valide pour les séances classiques'];
        }

        // Vérifier le type de client
        if (!empty($promo['target_client_type']) && $clientType !== null) {
            if ($promo['target_client_type'] !== $clientType) {
                $labels = [
                    'personal' => 'particuliers',
                    'association' => 'associations',
                    'friends_family' => 'Friends & Family'
                ];
                $label = $labels[$promo['target_client_type']] ?? $promo['target_client_type'];
                return ['valid' => false, 'error' => "Ce code promo est réservé aux {$label}"];
            }
        }

        // Vérifier l'utilisateur ciblé
        if (!empty($promo['target_user_id'])) {
            // Si la promo cible un utilisateur spécifique, vérifier que c'est le bon
            if ($userId === null || $promo['target_user_id'] !== $userId) {
                return ['valid' => false, 'error' => 'Ce code promo n\'est pas valide pour votre compte'];
            }
        }

        // Vérifier le nombre d'utilisations totales
        if ($promo['max_uses_total'] !== null) {
            $usageCount = self::getUsageCount($promo['id']);
            if ($usageCount >= $promo['max_uses_total']) {
                return ['valid' => false, 'error' => 'Ce code promo a atteint son nombre maximum d\'utilisations'];
            }
        }

        // Vérifier le nombre d'utilisations par utilisateur
        if ($promo['max_uses_per_user'] !== null && $userId !== null) {
            $userUsageCount = self::getUserUsageCount($promo['id'], $userId);
            if ($userUsageCount >= $promo['max_uses_per_user']) {
                return ['valid' => false, 'error' => 'Vous avez déjà utilisé ce code promo le nombre de fois autorisé'];
            }
        }

        return [
            'valid' => true,
            'promo' => $promo
        ];
    }

    /**
     * Trouve la meilleure promo automatique applicable
     * Compare toutes les promos valides et retourne celle avec la plus grosse remise
     */
    public static function findApplicableAutomatic(
        string $durationType,
        ?string $userId = null,
        ?string $clientType = null
    ): ?array {
        $db = Database::getInstance();

        $now = (new \DateTime())->format('Y-m-d H:i:s');

        // Chercher les promos automatiques actives
        $stmt = $db->prepare("
            SELECT pc.*
            FROM promo_codes pc
            WHERE pc.application_mode = 'automatic'
            AND pc.is_active = 1
            AND (pc.valid_from IS NULL OR pc.valid_from <= :now1)
            AND (pc.valid_until IS NULL OR pc.valid_until >= :now2)
        ");
        $stmt->execute(['now1' => $now, 'now2' => $now]);

        $promos = $stmt->fetchAll();

        // Récupérer le prix original pour comparer les remises
        $originalPrice = Session::getPriceForType($durationType);

        $validPromos = [];

        foreach ($promos as $promo) {
            $promo = self::castBooleans($promo);
            $promo['usage_count'] = self::getUsageCount($promo['id']);

            // Valider la promo
            $validation = self::validatePromo($promo, $durationType, $userId, $clientType);

            if ($validation['valid']) {
                // Calculer le montant de la remise pour comparaison
                $discount = self::calculateDiscount($promo, $originalPrice);
                $promo['_calculated_discount'] = $discount['discount_amount'];
                $validPromos[] = $promo;
            }
        }

        if (empty($validPromos)) {
            return null;
        }

        // Trier par montant de remise décroissant et retourner la meilleure
        usort($validPromos, function ($a, $b) {
            return $b['_calculated_discount'] <=> $a['_calculated_discount'];
        });

        $bestPromo = $validPromos[0];
        unset($bestPromo['_calculated_discount']); // Nettoyer le champ temporaire

        return $bestPromo;
    }

    /**
     * Vérifie si des codes manuels actifs existent
     */
    public static function hasActiveManualCodes(): bool
    {
        $db = Database::getInstance();
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $stmt = $db->prepare("
            SELECT COUNT(*) FROM promo_codes
            WHERE application_mode = 'manual'
            AND is_active = 1
            AND code IS NOT NULL
            AND (valid_from IS NULL OR valid_from <= :now1)
            AND (valid_until IS NULL OR valid_until >= :now2)
        ");
        $stmt->execute(['now1' => $now, 'now2' => $now]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Récupère tous les codes promo disponibles pour un utilisateur
     * Inclut les codes globaux et les codes ciblés pour cet utilisateur
     * @param string|null $excludeSessionId Session à exclure du comptage des utilisations (pour l'édition)
     */
    public static function getAvailableForUser(
        ?string $userId = null,
        ?string $clientType = null,
        ?string $durationType = null,
        ?string $excludeSessionId = null
    ): array {
        $db = Database::getInstance();
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        // Récupérer tous les codes actifs et dans leur période de validité
        $stmt = $db->prepare("
            SELECT pc.*,
                   tu.first_name as target_first_name,
                   tu.last_name as target_last_name,
                   tu.email as target_email
            FROM promo_codes pc
            LEFT JOIN users tu ON pc.target_user_id = tu.id
            WHERE pc.is_active = 1
            AND (pc.valid_from IS NULL OR pc.valid_from <= :now1)
            AND (pc.valid_until IS NULL OR pc.valid_until >= :now2)
            ORDER BY pc.name ASC
        ");
        $stmt->execute(['now1' => $now, 'now2' => $now]);

        $allPromos = $stmt->fetchAll();
        $availablePromos = [];

        foreach ($allPromos as $promo) {
            $promo = self::castBooleans($promo);
            // Compter les utilisations en excluant la session en cours d'édition
            $promo['usage_count'] = self::getUsageCount($promo['id'], $excludeSessionId);

            // Vérifier les limites d'utilisation totales
            if ($promo['max_uses_total'] !== null && $promo['usage_count'] >= $promo['max_uses_total']) {
                continue; // Code épuisé
            }

            // Vérifier si le code est ciblé pour un autre utilisateur
            if (!empty($promo['target_user_id'])) {
                if ($userId === null || $promo['target_user_id'] !== $userId) {
                    continue; // Code réservé à un autre utilisateur
                }
            }

            // Vérifier le type de client si spécifié
            if (!empty($promo['target_client_type']) && $clientType !== null) {
                if ($promo['target_client_type'] !== $clientType) {
                    continue; // Code réservé à un autre type de client
                }
            }

            // Vérifier le type de séance si spécifié
            if ($durationType !== null) {
                if ($durationType === 'discovery' && !$promo['applies_to_discovery']) {
                    continue;
                }
                if ($durationType === 'regular' && !$promo['applies_to_regular']) {
                    continue;
                }
            }

            // Vérifier les limites par utilisateur (en excluant la session en cours)
            if ($promo['max_uses_per_user'] !== null && $userId !== null) {
                $userUsageCount = self::getUserUsageCount($promo['id'], $userId, $excludeSessionId);
                if ($userUsageCount >= $promo['max_uses_per_user']) {
                    continue; // Utilisateur a atteint sa limite
                }
            }

            // Ajouter le label de remise
            $promo['discount_label'] = self::getDiscountLabel($promo);

            $availablePromos[] = $promo;
        }

        return $availablePromos;
    }

    // =========================================================================
    // CALCUL DE REMISE
    // =========================================================================

    /**
     * Calcule la remise pour un code promo
     */
    public static function calculateDiscount(array $promo, float $originalPrice): array
    {
        $discountAmount = 0.0;

        switch ($promo['discount_type']) {
            case self::DISCOUNT_TYPE_PERCENTAGE:
                $discountAmount = $originalPrice * ((float)$promo['discount_value'] / 100);
                break;
            case self::DISCOUNT_TYPE_FIXED_AMOUNT:
                $discountAmount = (float)$promo['discount_value'];
                break;
            case self::DISCOUNT_TYPE_FREE_SESSION:
                $discountAmount = $originalPrice; // 100% off
                break;
        }

        // Prix minimum 0€ (pas de prix négatif)
        $finalPrice = max(0, $originalPrice - $discountAmount);
        // Ajuster le montant de remise si cappé
        $discountAmount = $originalPrice - $finalPrice;

        return [
            'original_price' => round($originalPrice, 2),
            'discount_amount' => round($discountAmount, 2),
            'final_price' => round($finalPrice, 2)
        ];
    }

    /**
     * Formate le label de la remise pour l'affichage
     */
    public static function getDiscountLabel(array $promo): string
    {
        $value = (float)$promo['discount_value'];

        switch ($promo['discount_type']) {
            case self::DISCOUNT_TYPE_PERCENTAGE:
                return '-' . number_format($value, 0) . '%';
            case self::DISCOUNT_TYPE_FIXED_AMOUNT:
                return '-' . number_format($value, 2, ',', ' ') . ' €';
            case self::DISCOUNT_TYPE_FREE_SESSION:
                return 'Gratuit';
            default:
                return '';
        }
    }

    // =========================================================================
    // UTILISATION
    // =========================================================================

    /**
     * Enregistre une utilisation de code promo
     */
    public static function recordUsage(
        string $promoCodeId,
        string $sessionId,
        float $originalPrice,
        float $discountAmount,
        float $finalPrice,
        ?string $userId = null,
        ?string $ipAddress = null
    ): string {
        $db = Database::getInstance();
        $id = UUID::generate();

        $stmt = $db->prepare('
            INSERT INTO promo_code_usages (
                id, promo_code_id, session_id, user_id,
                original_price, discount_amount, final_price, ip_address
            ) VALUES (
                :id, :promo_code_id, :session_id, :user_id,
                :original_price, :discount_amount, :final_price, :ip_address
            )
        ');

        $stmt->execute([
            'id' => $id,
            'promo_code_id' => $promoCodeId,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'original_price' => $originalPrice,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'ip_address' => $ipAddress
        ]);

        return $id;
    }

    /**
     * Vérifie si un code promo est une séance gratuite (fidélité ou autre)
     */
    public static function isFreeSession(string $promoCodeId): bool
    {
        $promo = self::findById($promoCodeId);
        return $promo && $promo['discount_type'] === self::DISCOUNT_TYPE_FREE_SESSION;
    }

    /**
     * Supprime l'utilisation d'un code promo pour une session donnée
     */
    public static function deleteUsageBySession(string $sessionId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM promo_code_usages WHERE session_id = :session_id');
        return $stmt->execute(['session_id' => $sessionId]);
    }

    /**
     * Compte le nombre total d'utilisations d'un code promo
     * @param string|null $excludeSessionId Session à exclure du comptage
     */
    public static function getUsageCount(string $promoCodeId, ?string $excludeSessionId = null): int
    {
        $db = Database::getInstance();

        if ($excludeSessionId) {
            $stmt = $db->prepare('SELECT COUNT(*) FROM promo_code_usages WHERE promo_code_id = :id AND session_id != :exclude_session');
            $stmt->execute(['id' => $promoCodeId, 'exclude_session' => $excludeSessionId]);
        } else {
            $stmt = $db->prepare('SELECT COUNT(*) FROM promo_code_usages WHERE promo_code_id = :id');
            $stmt->execute(['id' => $promoCodeId]);
        }

        return (int)$stmt->fetchColumn();
    }

    /**
     * Compte le nombre d'utilisations d'un code par un utilisateur
     * @param string|null $excludeSessionId Session à exclure du comptage
     */
    public static function getUserUsageCount(string $promoCodeId, string $userId, ?string $excludeSessionId = null): int
    {
        $db = Database::getInstance();

        if ($excludeSessionId) {
            $stmt = $db->prepare('
                SELECT COUNT(*) FROM promo_code_usages
                WHERE promo_code_id = :promo_id AND user_id = :user_id AND session_id != :exclude_session
            ');
            $stmt->execute([
                'promo_id' => $promoCodeId,
                'user_id' => $userId,
                'exclude_session' => $excludeSessionId
            ]);
        } else {
            $stmt = $db->prepare('
                SELECT COUNT(*) FROM promo_code_usages
                WHERE promo_code_id = :promo_id AND user_id = :user_id
            ');
            $stmt->execute([
                'promo_id' => $promoCodeId,
                'user_id' => $userId
            ]);
        }

        return (int)$stmt->fetchColumn();
    }

    /**
     * Récupère l'historique des utilisations d'un code promo
     */
    public static function getUsages(string $promoCodeId, int $limit = 50, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT pcu.*,
                   u.first_name as user_first_name,
                   u.last_name as user_last_name,
                   u.email as user_email,
                   s.session_date,
                   p.first_name as person_first_name,
                   p.last_name as person_last_name
            FROM promo_code_usages pcu
            LEFT JOIN users u ON pcu.user_id = u.id
            LEFT JOIN sessions s ON pcu.session_id = s.id
            LEFT JOIN persons p ON s.person_id = p.id
            WHERE pcu.promo_code_id = :promo_id
            ORDER BY pcu.used_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':promo_id', $promoCodeId);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Récupère les stats d'un code promo
     */
    public static function getStats(string $promoCodeId): array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT
                COUNT(*) as usage_count,
                SUM(discount_amount) as total_discount,
                SUM(original_price) as total_original,
                SUM(final_price) as total_final,
                MIN(used_at) as first_use,
                MAX(used_at) as last_use
            FROM promo_code_usages
            WHERE promo_code_id = :id
        ");
        $stmt->execute(['id' => $promoCodeId]);

        return $stmt->fetch() ?: [
            'usage_count' => 0,
            'total_discount' => 0,
            'total_original' => 0,
            'total_final' => 0,
            'first_use' => null,
            'last_use' => null
        ];
    }

    // =========================================================================
    // UTILITAIRES
    // =========================================================================

    /**
     * Génère un code promo aléatoire
     */
    public static function generateRandomCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Sans I, O, 0, 1 pour éviter confusion
        $code = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, $max)];
        }

        // Vérifier l'unicité
        if (self::codeExists($code)) {
            return self::generateRandomCode($length);
        }

        return $code;
    }

    /**
     * Vérifie si un code existe déjà
     */
    public static function codeExists(string $code, ?string $excludeId = null): bool
    {
        $db = Database::getInstance();

        $sql = 'SELECT COUNT(*) FROM promo_codes WHERE code = :code';
        $params = ['code' => strtoupper(trim($code))];

        if ($excludeId) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Génère un code promo de fidélité pour un utilisateur
     * Réinitialise également la carte de fidélité
     * @return array Le code promo créé avec son code
     */
    public static function generateLoyaltyCode(string $userId, string $userName): array
    {
        // Générer un code unique avec préfixe FIDEL-
        $code = 'FIDEL-' . self::generateRandomCode(6);

        // Créer le code promo
        $promoId = self::create([
            'code' => $code,
            'name' => "Séance gratuite fidélité - {$userName}",
            'description' => "Code de fidélité généré automatiquement après 9 séances payées",
            'discount_type' => self::DISCOUNT_TYPE_FREE_SESSION,
            'discount_value' => 100,
            'application_mode' => self::MODE_MANUAL,
            'target_user_id' => $userId,
            'max_uses_total' => 1,
            'max_uses_per_user' => 1,
            'applies_to_discovery' => true,
            'applies_to_regular' => true,
            'is_active' => true,
            'created_by' => null // Généré automatiquement par le système
        ]);

        // Réinitialiser la carte de fidélité immédiatement
        // Le client peut recommencer à accumuler des séances
        LoyaltyCard::resetCard($userId);

        return [
            'id' => $promoId,
            'code' => $code
        ];
    }

    /**
     * Convertit les champs boolean MySQL (TINYINT) en boolean PHP
     */
    private static function castBooleans(array $promo): array
    {
        $boolFields = ['is_active', 'applies_to_discovery', 'applies_to_regular'];
        foreach ($boolFields as $field) {
            if (isset($promo[$field])) {
                $promo[$field] = (bool)$promo[$field];
            }
        }
        return $promo;
    }
}
