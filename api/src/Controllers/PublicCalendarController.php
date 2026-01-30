<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\OffDay;
use App\Models\Session;
use App\Models\Setting;
use App\Services\ICSGeneratorService;
use App\Utils\Response;

class PublicCalendarController
{
    /**
     * GET /public/calendar/feed.ics - Calendrier complet (séances + jours off)
     * Protégé par token optionnel (paramètre ?token=xxx)
     */
    public function feed(): void
    {
        // Check token if configured
        $configuredToken = Setting::getString('calendar_feed_token', '');

        if (!empty($configuredToken)) {
            $providedToken = $_GET['token'] ?? '';

            if ($providedToken !== $configuredToken) {
                http_response_code(401);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Token invalide ou manquant';
                exit;
            }
        }

        // Get confirmed sessions (past 1 month + future 6 months)
        $dateFrom = (new \DateTime('-1 month'))->format('Y-m-d');
        $dateTo = (new \DateTime('+6 months'))->format('Y-m-d');

        $sessions = Session::findAll(1000, 0, null, [
            'status' => [Session::STATUS_CONFIRMED, Session::STATUS_COMPLETED],
            'date_from' => $dateFrom,
            'date_to' => $dateTo . ' 23:59:59'
        ]);

        // Get all off days
        $offDays = OffDay::getAll();

        // Generate combined ICS
        $icsContent = ICSGeneratorService::generateFullCalendar($sessions, $offDays);

        // Send ICS headers (inline for subscription, not download)
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="sensea-calendar.ics"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo $icsContent;
        exit;
    }

    /**
     * GET /public/calendar/off-days.ics - Jours off uniquement
     */
    public function offDays(): void
    {
        $offDays = OffDay::getAll();
        $icsContent = ICSGeneratorService::generateOffDaysCalendar($offDays);

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="sensea-off-days.ics"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo $icsContent;
        exit;
    }
}
