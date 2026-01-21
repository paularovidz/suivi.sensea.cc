<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Setting
{
    private static ?array $cache = null;

    /**
     * Get all settings, optionally filtered by category
     */
    public static function getAll(?string $category = null): array
    {
        $db = Database::getInstance();

        if ($category) {
            $stmt = $db->prepare('SELECT * FROM settings WHERE category = :category ORDER BY `key`');
            $stmt->execute(['category' => $category]);
        } else {
            $stmt = $db->query('SELECT * FROM settings ORDER BY category, `key`');
        }

        return $stmt->fetchAll();
    }

    /**
     * Get all settings grouped by category
     */
    public static function getAllGrouped(): array
    {
        $settings = self::getAll();
        $grouped = [];

        foreach ($settings as $setting) {
            $category = $setting['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = self::formatSetting($setting);
        }

        return $grouped;
    }

    /**
     * Get a single setting value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Use cache if available
        if (self::$cache === null) {
            self::loadCache();
        }

        if (!isset(self::$cache[$key])) {
            return $default;
        }

        return self::castValue(self::$cache[$key]['value'], self::$cache[$key]['type']);
    }

    /**
     * Get a boolean setting
     */
    public static function getBoolean(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return (bool) $value;
    }

    /**
     * Get an integer setting
     */
    public static function getInteger(string $key, int $default = 0): int
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return (int) $value;
    }

    /**
     * Get a string setting
     */
    public static function getString(string $key, string $default = ''): string
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return (string) $value;
    }

    /**
     * Get a JSON setting as array
     */
    public static function getJson(string $key, array $default = []): array
    {
        $value = self::get($key);

        if ($value === null || !is_array($value)) {
            return $default;
        }

        return $value;
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, mixed $value, ?string $updatedBy = null): bool
    {
        $db = Database::getInstance();

        // Get current setting to know the type
        $stmt = $db->prepare('SELECT type FROM settings WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        $setting = $stmt->fetch();

        if (!$setting) {
            return false;
        }

        // Convert value to storage format
        $storageValue = self::toStorageValue($value, $setting['type']);

        $stmt = $db->prepare('
            UPDATE settings
            SET value = :value, updated_by = :updated_by, updated_at = NOW()
            WHERE `key` = :key
        ');

        $result = $stmt->execute([
            'key' => $key,
            'value' => $storageValue,
            'updated_by' => $updatedBy
        ]);

        // Clear cache
        self::$cache = null;

        return $result;
    }

    /**
     * Update multiple settings at once
     */
    public static function updateMultiple(array $settings, ?string $updatedBy = null): int
    {
        $updated = 0;

        foreach ($settings as $key => $value) {
            if (self::set($key, $value, $updatedBy)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Get setting metadata (for admin UI)
     */
    public static function getMeta(string $key): ?array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT * FROM settings WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        $setting = $stmt->fetch();

        return $setting ? self::formatSetting($setting) : null;
    }

    /**
     * Get all categories
     */
    public static function getCategories(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query('SELECT DISTINCT category FROM settings ORDER BY category');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Check if a setting exists
     */
    public static function exists(string $key): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT 1 FROM settings WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        return $stmt->fetch() !== false;
    }

    /**
     * Clear the settings cache (useful after updates)
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * Load all settings into cache
     */
    private static function loadCache(): void
    {
        $db = Database::getInstance();
        $stmt = $db->query('SELECT `key`, value, type FROM settings');

        self::$cache = [];
        while ($row = $stmt->fetch()) {
            self::$cache[$row['key']] = [
                'value' => $row['value'],
                'type' => $row['type']
            ];
        }
    }

    /**
     * Cast value from database storage to PHP type
     */
    private static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => (bool) (int) $value,
            'integer' => (int) $value,
            'json' => json_decode($value, true) ?? [],
            default => $value
        };
    }

    /**
     * Convert PHP value to storage format
     */
    private static function toStorageValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'json' => json_encode($value),
            default => (string) $value
        };
    }

    /**
     * Format setting for API response
     */
    private static function formatSetting(array $setting): array
    {
        return [
            'key' => $setting['key'],
            'value' => self::castValue($setting['value'], $setting['type']),
            'type' => $setting['type'],
            'label' => $setting['label'],
            'description' => $setting['description'],
            'category' => $setting['category'],
            'updated_at' => $setting['updated_at']
        ];
    }
}
