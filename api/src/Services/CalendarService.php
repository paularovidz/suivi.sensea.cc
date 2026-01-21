<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\Setting;
use App\Utils\UUID;

/**
 * Service pour récupérer et cacher les événements du calendrier Google via iCal
 */
class CalendarService
{
    private const DEFAULT_CACHE_TTL = 300; // 5 minutes par défaut

    /**
     * Récupère le TTL du cache depuis les settings ou utilise la valeur par défaut
     */
    private static function getCacheTTL(): int
    {
        return Setting::getInteger('calendar_cache_ttl', self::DEFAULT_CACHE_TTL);
    }

    private static function env(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Récupère l'URL iCal depuis la configuration
     */
    private static function getICalUrl(): string
    {
        return self::env('GOOGLE_ICAL_URL', '');
    }

    /**
     * Récupère les événements du calendrier pour une plage de dates
     * Utilise le cache si disponible, sinon rafraîchit depuis Google
     */
    public static function getEvents(\DateTime $start, \DateTime $end): array
    {
        self::refreshCacheIfNeeded();

        $db = Database::getInstance();
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        $stmt = $db->prepare('
            SELECT id, event_uid, summary, start_datetime, end_datetime, is_all_day
            FROM booking_calendar_cache
            WHERE (start_datetime < :end_date AND end_datetime > :start_date)
               OR (is_all_day = 1 AND DATE(start_datetime) <= DATE(:end_date2) AND DATE(end_datetime) >= DATE(:start_date2))
            ORDER BY start_datetime
        ');
        $stmt->execute([
            'start_date' => $startStr,
            'end_date' => $endStr,
            'start_date2' => $startStr,
            'end_date2' => $endStr
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Récupère les événements pour une date donnée (format optimisé pour AvailabilityService)
     */
    public static function getEventsForDate(\DateTime $date): array
    {
        self::refreshCacheIfNeeded();

        $db = Database::getInstance();
        $dateStr = $date->format('Y-m-d');
        $timezone = new \DateTimeZone(self::env('APP_TIMEZONE', 'Europe/Paris'));

        $stmt = $db->prepare('
            SELECT id, event_uid, summary, start_datetime, end_datetime, is_all_day
            FROM booking_calendar_cache
            WHERE DATE(start_datetime) = :date
               OR (is_all_day = 1 AND DATE(start_datetime) <= :date2 AND DATE(end_datetime) >= :date3)
            ORDER BY start_datetime
        ');
        $stmt->execute([
            'date' => $dateStr,
            'date2' => $dateStr,
            'date3' => $dateStr
        ]);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $events[] = [
                'start' => new \DateTime($row['start_datetime'], $timezone),
                'end' => new \DateTime($row['end_datetime'], $timezone),
                'is_all_day' => (bool) $row['is_all_day'],
                'summary' => $row['summary']
            ];
        }

        return $events;
    }

    /**
     * Vérifie si un créneau est bloqué par un événement du calendrier
     */
    public static function isSlotBlocked(\DateTime $start, \DateTime $end): bool
    {
        self::refreshCacheIfNeeded();

        $db = Database::getInstance();
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');
        $startDateStr = $start->format('Y-m-d');

        // Vérifier les événements avec horaires précis
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM booking_calendar_cache
            WHERE (
                (start_datetime < :end_time AND end_datetime > :start_time)
                OR (is_all_day = 1 AND DATE(start_datetime) <= DATE(:start_date) AND DATE(end_datetime) >= DATE(:start_date2))
            )
        ');
        $stmt->execute([
            'start_time' => $startStr,
            'end_time' => $endStr,
            'start_date' => $startDateStr,
            'start_date2' => $startDateStr
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Rafraîchit le cache si nécessaire (TTL expiré)
     */
    public static function refreshCacheIfNeeded(): void
    {
        $db = Database::getInstance();

        // Vérifier la date du dernier fetch
        $stmt = $db->query('SELECT MAX(last_fetched_at) FROM booking_calendar_cache');
        $lastFetch = $stmt->fetchColumn();

        if ($lastFetch) {
            $lastFetchTime = new \DateTime($lastFetch);
            $now = new \DateTime();
            $diff = $now->getTimestamp() - $lastFetchTime->getTimestamp();

            if ($diff < self::getCacheTTL()) {
                return; // Cache encore valide
            }
        }

        // Rafraîchir le cache
        self::refreshCache();
    }

    /**
     * Force le rafraîchissement du cache depuis Google Calendar
     */
    public static function refreshCache(): bool
    {
        $icalUrl = self::getICalUrl();
        if (empty($icalUrl)) {
            error_log('CalendarService: GOOGLE_ICAL_URL non configurée');
            return false;
        }

        try {
            // Récupérer le contenu iCal
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Sensea-Snoezelen/1.0'
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ]);

            $icalContent = @file_get_contents($icalUrl, false, $context);
            if ($icalContent === false) {
                error_log('CalendarService: Impossible de récupérer le calendrier iCal');
                return false;
            }

            // Parser le contenu
            $events = self::parseICalContent($icalContent);

            // Mettre à jour le cache
            self::updateCache($events);

            return true;
        } catch (\Exception $e) {
            error_log('CalendarService: Erreur lors du rafraîchissement: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Parse le contenu iCal et extrait les événements
     */
    private static function parseICalContent(string $content): array
    {
        $events = [];
        $lines = preg_split('/\r\n|\r|\n/', $content);

        // Déplier les lignes (les lignes qui commencent par un espace sont des continuations)
        $unfoldedLines = [];
        foreach ($lines as $line) {
            if (preg_match('/^[ \t]/', $line) && count($unfoldedLines) > 0) {
                $unfoldedLines[count($unfoldedLines) - 1] .= substr($line, 1);
            } else {
                $unfoldedLines[] = $line;
            }
        }

        $inEvent = false;
        $currentEvent = [];

        foreach ($unfoldedLines as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $currentEvent = [
                    'uid' => '',
                    'summary' => '',
                    'dtstart' => null,
                    'dtend' => null,
                    'is_all_day' => false
                ];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($currentEvent['uid'] && $currentEvent['dtstart']) {
                    // Si pas de date de fin, utiliser la date de début
                    if (!$currentEvent['dtend']) {
                        $currentEvent['dtend'] = clone $currentEvent['dtstart'];
                        if ($currentEvent['is_all_day']) {
                            $currentEvent['dtend']->modify('+1 day');
                        } else {
                            $currentEvent['dtend']->modify('+1 hour');
                        }
                    }
                    $events[] = $currentEvent;
                }
                $inEvent = false;
                continue;
            }

            if (!$inEvent) {
                continue;
            }

            // Parser les propriétés
            if (preg_match('/^([A-Z-]+)(?:;[^:]*)?:(.*)$/', $line, $matches)) {
                $property = $matches[1];
                $value = $matches[2];

                switch ($property) {
                    case 'UID':
                        $currentEvent['uid'] = $value;
                        break;

                    case 'SUMMARY':
                        $currentEvent['summary'] = self::unescapeICalValue($value);
                        break;

                    case 'DTSTART':
                        $parsed = self::parseICalDateTime($line);
                        if ($parsed) {
                            $currentEvent['dtstart'] = $parsed['datetime'];
                            $currentEvent['is_all_day'] = $parsed['is_all_day'];
                        }
                        break;

                    case 'DTEND':
                        $parsed = self::parseICalDateTime($line);
                        if ($parsed) {
                            $currentEvent['dtend'] = $parsed['datetime'];
                        }
                        break;
                }
            }
        }

        return $events;
    }

    /**
     * Parse une date/heure iCal avec gestion des timezones
     */
    private static function parseICalDateTime(string $line): ?array
    {
        // Format: DTSTART;TZID=Europe/Paris:20240115T090000
        // ou: DTSTART:20240115T090000Z (UTC)
        // ou: DTSTART;VALUE=DATE:20240115 (journée entière)

        $isAllDay = false;
        $timezone = new \DateTimeZone(self::env('APP_TIMEZONE', 'Europe/Paris'));

        // Extraire la valeur après le dernier ':'
        $parts = explode(':', $line, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $params = $parts[0];
        $value = $parts[1];

        // Détecter si c'est une journée entière
        if (strpos($params, 'VALUE=DATE') !== false) {
            $isAllDay = true;
        }

        // Extraire le timezone si spécifié
        if (preg_match('/TZID=([^;:]+)/', $params, $tzMatches)) {
            try {
                $timezone = new \DateTimeZone($tzMatches[1]);
            } catch (\Exception $e) {
                // Garder le timezone par défaut
            }
        }

        try {
            if ($isAllDay) {
                // Format: YYYYMMDD
                $datetime = \DateTime::createFromFormat('Ymd', $value, $timezone);
                if ($datetime) {
                    $datetime->setTime(0, 0, 0);
                }
            } elseif (substr($value, -1) === 'Z') {
                // Format UTC: YYYYMMDDTHHmmssZ
                $datetime = \DateTime::createFromFormat('Ymd\THis\Z', $value, new \DateTimeZone('UTC'));
                if ($datetime) {
                    $datetime->setTimezone($timezone);
                }
            } else {
                // Format local: YYYYMMDDTHHmmss
                $datetime = \DateTime::createFromFormat('Ymd\THis', $value, $timezone);
            }

            if (!$datetime) {
                return null;
            }

            return [
                'datetime' => $datetime,
                'is_all_day' => $isAllDay
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Déséchape les valeurs iCal
     */
    private static function unescapeICalValue(string $value): string
    {
        $value = str_replace('\\n', "\n", $value);
        $value = str_replace('\\,', ',', $value);
        $value = str_replace('\\;', ';', $value);
        $value = str_replace('\\\\', '\\', $value);
        return $value;
    }

    /**
     * Met à jour le cache avec les nouveaux événements
     */
    private static function updateCache(array $events): void
    {
        $db = Database::getInstance();
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        // Supprimer les anciens événements qui ne sont plus dans le calendrier
        $uids = array_map(fn($e) => $e['uid'], $events);

        if (!empty($uids)) {
            $placeholders = implode(',', array_fill(0, count($uids), '?'));
            $stmt = $db->prepare("DELETE FROM booking_calendar_cache WHERE event_uid NOT IN ({$placeholders})");
            $stmt->execute($uids);
        } else {
            // Si pas d'événements, tout supprimer
            $db->exec('DELETE FROM booking_calendar_cache');
        }

        // Insérer ou mettre à jour les événements
        $stmt = $db->prepare('
            INSERT INTO booking_calendar_cache (id, event_uid, summary, start_datetime, end_datetime, is_all_day, last_fetched_at)
            VALUES (:id, :event_uid, :summary, :start_datetime, :end_datetime, :is_all_day, :last_fetched_at)
            ON DUPLICATE KEY UPDATE
                summary = VALUES(summary),
                start_datetime = VALUES(start_datetime),
                end_datetime = VALUES(end_datetime),
                is_all_day = VALUES(is_all_day),
                last_fetched_at = VALUES(last_fetched_at)
        ');

        foreach ($events as $event) {
            $stmt->execute([
                'id' => UUID::generate(),
                'event_uid' => $event['uid'],
                'summary' => $event['summary'] ?: null,
                'start_datetime' => $event['dtstart']->format('Y-m-d H:i:s'),
                'end_datetime' => $event['dtend']->format('Y-m-d H:i:s'),
                'is_all_day' => $event['is_all_day'] ? 1 : 0,
                'last_fetched_at' => $now
            ]);
        }
    }

    /**
     * Vide le cache (utile pour les tests ou forcer un refresh complet)
     */
    public static function clearCache(): void
    {
        $db = Database::getInstance();
        $db->exec('DELETE FROM booking_calendar_cache');
    }
}
