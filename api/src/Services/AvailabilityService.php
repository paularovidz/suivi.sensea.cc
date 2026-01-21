<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\Booking;
use App\Models\Setting;

/**
 * Service de gestion des disponibilités pour les réservations
 * Les créneaux sont générés dynamiquement basés sur les Settings
 */
class AvailabilityService
{
    // Types de durée
    public const TYPE_DISCOVERY = 'discovery';
    public const TYPE_REGULAR = 'regular';

    // Valeurs par défaut (utilisées si Settings non disponibles)
    private const DEFAULT_BUSINESS_HOURS = [
        0 => null, // Dimanche - FERMÉ
        1 => ['open' => '09:00', 'close' => '18:00'],
        2 => ['open' => '09:00', 'close' => '18:00'],
        3 => ['open' => '09:00', 'close' => '18:00'],
        4 => null, // Jeudi - FERMÉ
        5 => ['open' => '09:00', 'close' => '18:00'],
        6 => ['open' => '10:00', 'close' => '17:00'],
    ];

    private const DEFAULT_DISCOVERY_DISPLAY = 75;
    private const DEFAULT_DISCOVERY_PAUSE = 15;
    private const DEFAULT_REGULAR_DISPLAY = 45;
    private const DEFAULT_REGULAR_PAUSE = 20;
    private const DEFAULT_LUNCH_START = '12:30';
    private const DEFAULT_LUNCH_END = '13:30';
    private const DEFAULT_FIRST_SLOT = '09:00';

    private static function env(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Récupère les horaires d'ouverture depuis les Settings
     */
    private static function getBusinessHoursConfig(): array
    {
        $hours = Setting::getJson('business_hours', []);

        if (empty($hours)) {
            return self::DEFAULT_BUSINESS_HOURS;
        }

        // Convertir les clés string en int
        $result = [];
        foreach ($hours as $day => $config) {
            $result[(int) $day] = $config;
        }

        return $result;
    }

    /**
     * Récupère les durées pour un type de séance
     */
    public static function getDurations(string $type): array
    {
        if ($type === self::TYPE_DISCOVERY) {
            $display = Setting::getInteger('session_discovery_display_minutes', self::DEFAULT_DISCOVERY_DISPLAY);
            $pause = Setting::getInteger('session_discovery_pause_minutes', self::DEFAULT_DISCOVERY_PAUSE);
        } else {
            $display = Setting::getInteger('session_regular_display_minutes', self::DEFAULT_REGULAR_DISPLAY);
            $pause = Setting::getInteger('session_regular_pause_minutes', self::DEFAULT_REGULAR_PAUSE);
        }

        return [
            'display' => $display,
            'pause' => $pause,
            'blocked' => $display + $pause
        ];
    }

    /**
     * Récupère la pause déjeuner
     */
    private static function getLunchBreak(): array
    {
        return [
            'start' => Setting::getString('lunch_break_start', self::DEFAULT_LUNCH_START),
            'end' => Setting::getString('lunch_break_end', self::DEFAULT_LUNCH_END)
        ];
    }

    /**
     * Récupère l'heure du premier créneau
     */
    private static function getFirstSlotTime(): string
    {
        return Setting::getString('first_slot_time', self::DEFAULT_FIRST_SLOT);
    }

    /**
     * Vérifie si un jour donné est ouvert
     */
    public static function isDayOpen(\DateTime $date): bool
    {
        $dayOfWeek = (int) $date->format('w');
        $businessHours = self::getBusinessHoursConfig();
        return isset($businessHours[$dayOfWeek]) && $businessHours[$dayOfWeek] !== null;
    }

    /**
     * Récupère les horaires d'ouverture pour un jour
     */
    public static function getBusinessHours(\DateTime $date): ?array
    {
        $dayOfWeek = (int) $date->format('w');
        $businessHours = self::getBusinessHoursConfig();

        if (!isset($businessHours[$dayOfWeek]) || $businessHours[$dayOfWeek] === null) {
            return null;
        }

        $hours = $businessHours[$dayOfWeek];

        return [
            'open' => $hours['open'] ?? '09:00',
            'close' => $hours['close'] ?? '18:00'
        ];
    }

    /**
     * Génère dynamiquement les créneaux possibles pour une journée
     * Les créneaux sont calculés à partir du premier slot + durée bloquée
     * en prenant en compte la pause déjeuner
     */
    private static function generatePossibleSlots(\DateTime $date, string $durationType): array
    {
        $businessHours = self::getBusinessHours($date);
        if (!$businessHours) {
            return [];
        }

        $timezone = new \DateTimeZone(self::env('APP_TIMEZONE', 'Europe/Paris'));
        $dateStr = $date->format('Y-m-d');
        $durations = self::getDurations($durationType);
        $blockedDuration = $durations['blocked'];
        $lunch = self::getLunchBreak();

        // Récupérer l'heure du premier créneau
        // Le premier slot peut être l'heure d'ouverture ou le paramètre first_slot_time
        $firstSlotTime = self::getFirstSlotTime();
        $openTime = $businessHours['open'];

        // Utiliser le plus tardif des deux
        $startTime = $firstSlotTime > $openTime ? $firstSlotTime : $openTime;

        // Ajuster pour le samedi qui ouvre plus tard
        $dayOfWeek = (int) $date->format('w');
        if ($dayOfWeek === 6 && $openTime > $startTime) {
            $startTime = $openTime;
        }

        $current = new \DateTime("$dateStr $startTime", $timezone);
        $closeTime = new \DateTime("$dateStr {$businessHours['close']}", $timezone);
        $lunchStart = new \DateTime("$dateStr {$lunch['start']}", $timezone);
        $lunchEnd = new \DateTime("$dateStr {$lunch['end']}", $timezone);

        $slots = [];

        while (true) {
            $slotEnd = (clone $current)->modify("+{$blockedDuration} minutes");

            // Le créneau ne doit pas dépasser l'heure de fermeture
            if ($slotEnd > $closeTime) {
                break;
            }

            // Vérifier si le créneau chevauche la pause déjeuner
            $overlapsLunch = ($current < $lunchEnd && $slotEnd > $lunchStart);

            if (!$overlapsLunch) {
                $slots[] = $current->format('H:i');
            }

            // Passer au créneau suivant
            $current->modify("+{$blockedDuration} minutes");

            // Si on est dans la pause déjeuner, sauter à la fin de la pause
            if ($current >= $lunchStart && $current < $lunchEnd) {
                $current = clone $lunchEnd;
            }
        }

        return $slots;
    }

    /**
     * Récupère les dates disponibles pour un mois donné
     * Ne retourne que les dates qui ont au moins un créneau disponible
     */
    public static function getAvailableDates(int $year, int $month, string $durationType = self::TYPE_REGULAR): array
    {
        $timezone = new \DateTimeZone(self::env('APP_TIMEZONE', 'Europe/Paris'));
        $startDate = new \DateTime("$year-$month-01", $timezone);
        $endDate = (clone $startDate)->modify('last day of this month');

        $today = new \DateTime('today', $timezone);
        $now = new \DateTime('now', $timezone);

        // Si on est après 23h, considérer le jour comme terminé
        if ((int) $now->format('H') >= 23) {
            $today->modify('+1 day');
        }

        $availableDates = [];

        $current = clone $startDate;
        while ($current <= $endDate) {
            // Ne pas proposer les dates passées
            if ($current >= $today && self::isDayOpen($current)) {
                // Vérifier s'il reste au moins un créneau disponible
                $slots = self::getAvailableSlots($current, $durationType);
                if (!empty($slots)) {
                    $availableDates[] = $current->format('Y-m-d');
                }
            }
            $current->modify('+1 day');
        }

        return $availableDates;
    }

    /**
     * Récupère les créneaux disponibles pour une date donnée
     * Ne retourne QUE les créneaux disponibles (pas les créneaux barrés)
     */
    public static function getAvailableSlots(\DateTime $date, string $durationType = self::TYPE_REGULAR): array
    {
        $businessHours = self::getBusinessHours($date);
        if (!$businessHours) {
            return [];
        }

        $timezone = new \DateTimeZone(self::env('APP_TIMEZONE', 'Europe/Paris'));
        $durations = self::getDurations($durationType);
        $blockedDuration = $durations['blocked'];
        $dateStr = $date->format('Y-m-d');
        $now = new \DateTime('now', $timezone);

        // Récupérer tous les événements bloquants pour cette journée
        $dayStart = new \DateTime("$dateStr 00:00:00", $timezone);
        $dayEnd = new \DateTime("$dateStr 23:59:59", $timezone);

        // Récupérer les réservations existantes pour cette journée
        $existingBookings = Booking::getBookingsForDate($date);

        // Récupérer les événements du calendrier Google
        $calendarEvents = CalendarService::getEventsForDate($date);

        // Générer les créneaux possibles dynamiquement
        $possibleSlots = self::generatePossibleSlots($date, $durationType);

        $availableSlots = [];

        foreach ($possibleSlots as $timeStr) {
            $slotStart = new \DateTime("$dateStr $timeStr", $timezone);
            $slotEnd = (clone $slotStart)->modify("+{$blockedDuration} minutes");

            // Vérifier que le créneau n'est pas dans le passé
            if ($slotStart <= $now) {
                continue;
            }

            // Vérifier la disponibilité
            if (self::isSlotAvailableWithData($slotStart, $slotEnd, $existingBookings, $calendarEvents)) {
                $availableSlots[] = [
                    'time' => $slotStart->format('H:i'),
                    'datetime' => $slotStart->format('Y-m-d H:i:s'),
                    'available' => true
                ];
            }
        }

        return $availableSlots;
    }

    /**
     * Vérifie si un créneau est disponible avec les données pré-chargées
     */
    private static function isSlotAvailableWithData(
        \DateTime $start,
        \DateTime $end,
        array $bookings,
        array $calendarEvents
    ): bool {
        // Vérifier les réservations
        foreach ($bookings as $booking) {
            $bookingStart = new \DateTime($booking['session_date']);
            $bookingEnd = (clone $bookingStart)->modify("+{$booking['duration_blocked_minutes']} minutes");

            // Vérifier le chevauchement
            if ($start < $bookingEnd && $end > $bookingStart) {
                return false;
            }
        }

        // Vérifier les événements du calendrier Google
        foreach ($calendarEvents as $event) {
            $eventStart = $event['start'];
            $eventEnd = $event['end'];

            // Si c'est un événement toute la journée, bloquer tout
            if ($event['is_all_day'] ?? false) {
                return false;
            }

            // Vérifier le chevauchement
            if ($start < $eventEnd && $end > $eventStart) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifie si un créneau est disponible (ni bloqué par Google Calendar, ni par une réservation)
     */
    public static function isSlotAvailable(\DateTime $start, \DateTime $end): bool
    {
        // 1. Vérifier le calendrier Google
        if (CalendarService::isSlotBlocked($start, $end)) {
            return false;
        }

        // 2. Vérifier les réservations en BDD
        if (Booking::isSlotBooked($start, $end)) {
            return false;
        }

        return true;
    }

    /**
     * Vérifie qu'un créneau demandé est valide et disponible
     * Retourne un tableau d'erreurs ou un tableau vide si OK
     */
    public static function validateSlot(\DateTime $requestedDateTime, string $durationType): array
    {
        $errors = [];
        $timezone = new \DateTimeZone(self::env('APP_TIMEZONE', 'Europe/Paris'));
        $now = new \DateTime('now', $timezone);

        // 1. Vérifier que la date n'est pas dans le passé
        if ($requestedDateTime <= $now) {
            $errors[] = 'Le créneau demandé est dans le passé';
            return $errors;
        }

        // 2. Vérifier que le jour est ouvert
        if (!self::isDayOpen($requestedDateTime)) {
            $errors[] = 'Ce jour est fermé';
            return $errors;
        }

        // 3. Vérifier les horaires d'ouverture
        $businessHours = self::getBusinessHours($requestedDateTime);
        $dateStr = $requestedDateTime->format('Y-m-d');
        $openTime = new \DateTime("$dateStr {$businessHours['open']}", $timezone);
        $closeTime = new \DateTime("$dateStr {$businessHours['close']}", $timezone);

        if ($requestedDateTime < $openTime) {
            $errors[] = "L'établissement ouvre à {$businessHours['open']}";
            return $errors;
        }

        // 4. Calculer l'heure de fin avec la durée bloquée
        $durations = self::getDurations($durationType);
        $slotEnd = (clone $requestedDateTime)->modify("+{$durations['blocked']} minutes");

        if ($slotEnd > $closeTime) {
            $errors[] = "Le créneau dépasse l'heure de fermeture ({$businessHours['close']})";
            return $errors;
        }

        // 5. Vérifier que le créneau est dans la liste des créneaux possibles
        $possibleSlots = self::generatePossibleSlots($requestedDateTime, $durationType);
        $requestedTime = $requestedDateTime->format('H:i');

        if (!in_array($requestedTime, $possibleSlots, true)) {
            $errors[] = 'Créneau horaire non valide';
            return $errors;
        }

        // 6. Vérifier la disponibilité effective
        if (!self::isSlotAvailable($requestedDateTime, $slotEnd)) {
            $errors[] = 'Ce créneau n\'est plus disponible';
            return $errors;
        }

        return [];
    }

    /**
     * Récupère les labels pour les types de durée
     */
    public static function getDurationLabels(): array
    {
        $discoveryDurations = self::getDurations(self::TYPE_DISCOVERY);
        $regularDurations = self::getDurations(self::TYPE_REGULAR);

        return [
            self::TYPE_DISCOVERY => [
                'label' => 'Séance découverte',
                'description' => "Première séance - {$discoveryDurations['display']}min",
                'display_minutes' => $discoveryDurations['display'],
                'blocked_minutes' => $discoveryDurations['blocked']
            ],
            self::TYPE_REGULAR => [
                'label' => 'Séance classique',
                'description' => "Séance habituelle - {$regularDurations['display']}min",
                'display_minutes' => $regularDurations['display'],
                'blocked_minutes' => $regularDurations['blocked']
            ]
        ];
    }

    /**
     * Récupère les jours d'ouverture avec leurs horaires
     */
    public static function getScheduleInfo(): array
    {
        $daysOfWeek = [
            0 => 'Dimanche',
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi'
        ];

        $businessHours = self::getBusinessHoursConfig();
        $schedule = [];

        foreach ($businessHours as $day => $hours) {
            $schedule[] = [
                'day' => $day,
                'name' => $daysOfWeek[$day],
                'open' => $hours !== null,
                'hours' => $hours
            ];
        }

        $lunch = self::getLunchBreak();

        return [
            'schedule' => $schedule,
            'lunch_break' => $lunch,
            'first_slot' => self::getFirstSlotTime(),
            'durations' => [
                self::TYPE_DISCOVERY => self::getDurations(self::TYPE_DISCOVERY),
                self::TYPE_REGULAR => self::getDurations(self::TYPE_REGULAR)
            ]
        ];
    }
}
