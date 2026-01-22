<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Models\Setting;
use App\Services\AuditService;
use App\Utils\Response;

class StatsController
{
    public function dashboard(): void
    {
        AuthMiddleware::requireAdmin();

        $db = Database::getInstance();

        // Total counts
        $stats = [
            'users' => [
                'total' => 0,
                'active' => 0,
                'admins' => 0
            ],
            'persons' => [
                'total' => 0
            ],
            'sessions' => [
                'total' => 0,
                'this_month' => 0,
                'last_30_days' => 0
            ],
            'sensory_proposals' => [
                'total' => 0,
                'by_type' => []
            ],
            'recent_activity' => []
        ];

        // Users stats
        $stmt = $db->query('SELECT COUNT(*) FROM users');
        $stats['users']['total'] = (int)$stmt->fetchColumn();

        $stmt = $db->query('SELECT COUNT(*) FROM users WHERE is_active = 1');
        $stats['users']['active'] = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stats['users']['admins'] = (int)$stmt->fetchColumn();

        // Persons stats
        $stmt = $db->query('SELECT COUNT(*) FROM persons');
        $stats['persons']['total'] = (int)$stmt->fetchColumn();

        // Sessions stats
        $stmt = $db->query('SELECT COUNT(*) FROM sessions');
        $stats['sessions']['total'] = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM sessions WHERE MONTH(session_date) = MONTH(CURRENT_DATE()) AND YEAR(session_date) = YEAR(CURRENT_DATE())");
        $stats['sessions']['this_month'] = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM sessions WHERE session_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['sessions']['last_30_days'] = (int)$stmt->fetchColumn();

        // Average session duration
        $stmt = $db->query("SELECT AVG(duration_minutes) FROM sessions");
        $stats['sessions']['avg_duration'] = round((float)$stmt->fetchColumn(), 1);

        // Sensory proposals stats
        $stmt = $db->query('SELECT COUNT(*) FROM sensory_proposals');
        $stats['sensory_proposals']['total'] = (int)$stmt->fetchColumn();

        $stmt = $db->query('SELECT type, COUNT(*) as count FROM sensory_proposals GROUP BY type ORDER BY count DESC');
        $stats['sensory_proposals']['by_type'] = $stmt->fetchAll();

        // Sessions by month (last 6 months)
        $stmt = $db->query("
            SELECT
                DATE_FORMAT(session_date, '%Y-%m') as month,
                COUNT(*) as count
            FROM sessions
            WHERE session_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(session_date, '%Y-%m')
            ORDER BY month DESC
        ");
        $stats['sessions']['by_month'] = $stmt->fetchAll();

        // Behavior distribution (end of session)
        $stmt = $db->query("
            SELECT behavior_end, COUNT(*) as count
            FROM sessions
            WHERE behavior_end IS NOT NULL
            GROUP BY behavior_end
            ORDER BY count DESC
        ");
        $stats['sessions']['behavior_distribution'] = $stmt->fetchAll();

        // Wants to return stats
        $stmt = $db->query("
            SELECT
                SUM(CASE WHEN wants_to_return = 1 THEN 1 ELSE 0 END) as yes,
                SUM(CASE WHEN wants_to_return = 0 THEN 1 ELSE 0 END) as no,
                SUM(CASE WHEN wants_to_return IS NULL THEN 1 ELSE 0 END) as not_specified
            FROM sessions
        ");
        $stats['sessions']['wants_to_return'] = $stmt->fetch();

        // Revenue calculation using actual session prices
        $regularPrice = Setting::getInteger('session_regular_price', 45);
        $discoveryPrice = Setting::getInteger('session_discovery_price', 55);
        $vatRate = 0.20;

        // Current month revenue (using actual prices from sessions)
        // For sessions without price, use default based on duration_type
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total_count,
                SUM(CASE WHEN duration_type = 'discovery' THEN 1 ELSE 0 END) as discovery_count,
                SUM(CASE WHEN duration_type = 'regular' OR duration_type IS NULL THEN 1 ELSE 0 END) as regular_count,
                SUM(CASE WHEN is_free_session = 1 THEN 1 ELSE 0 END) as free_count,
                SUM(CASE
                    WHEN is_free_session = 1 THEN 0
                    WHEN price IS NOT NULL THEN price
                    WHEN duration_type = 'discovery' THEN :discovery_price
                    ELSE :regular_price
                END) as total_revenue
            FROM sessions
            WHERE MONTH(session_date) = MONTH(CURRENT_DATE())
            AND YEAR(session_date) = YEAR(CURRENT_DATE())
            AND status IN ('completed', 'confirmed', 'pending')
        ");
        $stmt->execute([
            'discovery_price' => $discoveryPrice,
            'regular_price' => $regularPrice
        ]);
        $monthStats = $stmt->fetch();

        $monthRevenueTTC = (float)($monthStats['total_revenue'] ?? 0);
        $monthRevenueHT = round($monthRevenueTTC / (1 + $vatRate), 2);
        $discoveryCount = (int)($monthStats['discovery_count'] ?? 0);
        $regularCount = (int)($monthStats['regular_count'] ?? 0);
        $freeCount = (int)($monthStats['free_count'] ?? 0);

        // Fiscal year revenue (October 1 - September 30)
        $now = new \DateTime();
        $currentMonth = (int) $now->format('n');
        $currentYear = (int) $now->format('Y');

        // Determine fiscal year start
        if ($currentMonth >= 10) {
            // October-December: fiscal year started this year
            $fiscalYearStart = "$currentYear-10-01";
            $fiscalYearEnd = ($currentYear + 1) . "-09-30";
        } else {
            // January-September: fiscal year started last year
            $fiscalYearStart = ($currentYear - 1) . "-10-01";
            $fiscalYearEnd = "$currentYear-09-30";
        }

        // Fiscal year revenue (using actual prices)
        $stmt = $db->prepare("
            SELECT
                SUM(CASE
                    WHEN is_free_session = 1 THEN 0
                    WHEN price IS NOT NULL THEN price
                    WHEN duration_type = 'discovery' THEN :discovery_price
                    ELSE :regular_price
                END) as total_revenue
            FROM sessions
            WHERE session_date >= :fiscal_start AND session_date <= :fiscal_end
            AND status IN ('completed', 'confirmed', 'pending')
        ");
        $stmt->execute([
            'discovery_price' => $discoveryPrice,
            'regular_price' => $regularPrice,
            'fiscal_start' => $fiscalYearStart,
            'fiscal_end' => $fiscalYearEnd . ' 23:59:59'
        ]);
        $fiscalStats = $stmt->fetch();

        $fiscalRevenueTTC = (float)($fiscalStats['total_revenue'] ?? 0);
        $fiscalRevenueHT = round($fiscalRevenueTTC / (1 + $vatRate), 2);

        $stats['revenue'] = [
            'this_month_ht' => $monthRevenueHT,
            'this_month_ttc' => $monthRevenueTTC,
            'fiscal_year_ht' => $fiscalRevenueHT,
            'fiscal_year_ttc' => $fiscalRevenueTTC,
            'fiscal_year_start' => $fiscalYearStart,
            'fiscal_year_end' => $fiscalYearEnd,
            'discovery_count' => $discoveryCount,
            'regular_count' => $regularCount,
            'free_count' => $freeCount,
            'default_discovery_price' => $discoveryPrice,
            'default_regular_price' => $regularPrice
        ];

        // Recent activity (last 10 audit logs)
        $stats['recent_activity'] = AuditService::getRecent(10);

        Response::success($stats);
    }

    public function auditLogs(): void
    {
        AuthMiddleware::requireAdmin();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $logs = AuditService::getRecent($limit, $offset);

        $db = Database::getInstance();
        $stmt = $db->query('SELECT COUNT(*) FROM audit_logs');
        $total = (int)$stmt->fetchColumn();

        Response::success([
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
}
