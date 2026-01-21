<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service de génération de fichiers ICS (iCalendar)
 */
class ICSGeneratorService
{
    private static function env(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Génère un fichier ICS pour une réservation
     */
    public static function generateBookingEvent(array $booking): string
    {
        $timezone = self::env('APP_TIMEZONE', 'Europe/Paris');
        $startDate = new \DateTime($booking['session_date'], new \DateTimeZone($timezone));
        $endDate = (clone $startDate)->modify("+{$booking['duration_blocked_minutes']} minutes");

        $summary = "Séance Snoezelen - {$booking['person_first_name']} {$booking['person_last_name']}";
        $description = self::buildDescription($booking);
        $uid = $booking['id'] . '@' . parse_url(self::env('APP_URL', 'sensea.cc'), PHP_URL_HOST);

        $ics = self::buildICS([
            'uid' => $uid,
            'summary' => $summary,
            'description' => $description,
            'start' => $startDate,
            'end' => $endDate,
            'timezone' => $timezone,
            'location' => 'Sensea Snoezelen',
            'organizer_email' => self::env('MAIL_FROM', 'noreply@sensea.cc'),
            'organizer_name' => 'Sensea Snoezelen'
        ]);

        return $ics;
    }

    /**
     * Génère un fichier ICS avec plusieurs événements
     */
    public static function generateCalendarFile(array $bookings): string
    {
        $timezone = self::env('APP_TIMEZONE', 'Europe/Paris');
        $events = [];

        foreach ($bookings as $booking) {
            $startDate = new \DateTime($booking['session_date'], new \DateTimeZone($timezone));
            $endDate = (clone $startDate)->modify("+{$booking['duration_blocked_minutes']} minutes");

            $events[] = [
                'uid' => $booking['id'] . '@' . parse_url(self::env('APP_URL', 'sensea.cc'), PHP_URL_HOST),
                'summary' => "Séance Snoezelen - {$booking['person_first_name']} {$booking['person_last_name']}",
                'description' => self::buildDescription($booking),
                'start' => $startDate,
                'end' => $endDate,
                'timezone' => $timezone,
                'location' => 'Sensea Snoezelen'
            ];
        }

        return self::buildICSMultiple($events, $timezone);
    }

    /**
     * Construit la description de l'événement
     */
    private static function buildDescription(array $booking): string
    {
        $type = $booking['duration_type'] === 'discovery' ? 'Séance découverte' : 'Séance classique';
        $duration = $booking['duration_display_minutes'] . ' minutes';

        $lines = [
            "Type: {$type}",
            "Durée: {$duration}",
            "",
            "Bénéficiaire: {$booking['person_first_name']} {$booking['person_last_name']}",
            "Contact: {$booking['client_first_name']} {$booking['client_last_name']}",
            "Email: {$booking['client_email']}"
        ];

        if (!empty($booking['client_phone'])) {
            $lines[] = "Téléphone: {$booking['client_phone']}";
        }

        return implode("\\n", $lines);
    }

    /**
     * Construit le contenu ICS pour un seul événement
     */
    private static function buildICS(array $event): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Sensea Snoezelen//Booking System//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Sensea Snoezelen',
            'X-WR-TIMEZONE:' . $event['timezone'],
            '',
            self::generateVTimezone($event['timezone']),
            '',
            'BEGIN:VEVENT',
            'UID:' . $event['uid'],
            'DTSTAMP:' . self::formatDateTime(new \DateTime('now', new \DateTimeZone('UTC')), true),
            'DTSTART;TZID=' . $event['timezone'] . ':' . self::formatDateTime($event['start']),
            'DTEND;TZID=' . $event['timezone'] . ':' . self::formatDateTime($event['end']),
            'SUMMARY:' . self::escapeValue($event['summary']),
            'DESCRIPTION:' . self::escapeValue($event['description']),
        ];

        if (!empty($event['location'])) {
            $lines[] = 'LOCATION:' . self::escapeValue($event['location']);
        }

        if (!empty($event['organizer_email'])) {
            $name = $event['organizer_name'] ?? 'Sensea';
            $lines[] = 'ORGANIZER;CN=' . self::escapeValue($name) . ':mailto:' . $event['organizer_email'];
        }

        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'SEQUENCE:0';
        $lines[] = 'BEGIN:VALARM';
        $lines[] = 'TRIGGER:-PT1H';
        $lines[] = 'ACTION:DISPLAY';
        $lines[] = 'DESCRIPTION:Rappel: Séance Snoezelen dans 1 heure';
        $lines[] = 'END:VALARM';
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    /**
     * Construit le contenu ICS pour plusieurs événements
     */
    private static function buildICSMultiple(array $events, string $timezone): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Sensea Snoezelen//Booking System//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Sensea Snoezelen - Réservations',
            'X-WR-TIMEZONE:' . $timezone,
            '',
            self::generateVTimezone($timezone),
        ];

        foreach ($events as $event) {
            $lines[] = '';
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $event['uid'];
            $lines[] = 'DTSTAMP:' . self::formatDateTime(new \DateTime('now', new \DateTimeZone('UTC')), true);
            $lines[] = 'DTSTART;TZID=' . $event['timezone'] . ':' . self::formatDateTime($event['start']);
            $lines[] = 'DTEND;TZID=' . $event['timezone'] . ':' . self::formatDateTime($event['end']);
            $lines[] = 'SUMMARY:' . self::escapeValue($event['summary']);
            $lines[] = 'DESCRIPTION:' . self::escapeValue($event['description']);

            if (!empty($event['location'])) {
                $lines[] = 'LOCATION:' . self::escapeValue($event['location']);
            }

            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    /**
     * Génère le bloc VTIMEZONE pour Europe/Paris
     */
    private static function generateVTimezone(string $timezone): string
    {
        if ($timezone === 'Europe/Paris') {
            return <<<VTIMEZONE
BEGIN:VTIMEZONE
TZID:Europe/Paris
X-LIC-LOCATION:Europe/Paris
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
VTIMEZONE;
        }

        // Fallback pour UTC
        return <<<VTIMEZONE
BEGIN:VTIMEZONE
TZID:{$timezone}
BEGIN:STANDARD
TZOFFSETFROM:+0000
TZOFFSETTO:+0000
TZNAME:UTC
DTSTART:19700101T000000
END:STANDARD
END:VTIMEZONE
VTIMEZONE;
    }

    /**
     * Formate une date pour ICS
     */
    private static function formatDateTime(\DateTime $datetime, bool $utc = false): string
    {
        if ($utc) {
            $datetime = clone $datetime;
            $datetime->setTimezone(new \DateTimeZone('UTC'));
            return $datetime->format('Ymd\THis\Z');
        }
        return $datetime->format('Ymd\THis');
    }

    /**
     * Échappe les caractères spéciaux pour ICS
     */
    private static function escapeValue(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace("\n", '\\n', $value);
        $value = str_replace("\r", '', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace(';', '\\;', $value);
        return $value;
    }

    /**
     * Génère les headers HTTP pour le téléchargement d'un fichier ICS
     */
    public static function getDownloadHeaders(string $filename = 'booking.ics'): array
    {
        return [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ];
    }
}
