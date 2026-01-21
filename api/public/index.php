<?php

declare(strict_types=1);

// CORS must be handled FIRST, before anything else
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

// Handle preflight immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Now load everything else
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Middleware\SecurityMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\PersonController;
use App\Controllers\SessionController;
use App\Controllers\SensoryProposalController;
use App\Controllers\StatsController;
use App\Controllers\PublicBookingController;
use App\Controllers\BookingController;
use App\Controllers\SettingsController;
use App\Controllers\DocumentController;
use App\Utils\Response;

// Error handling
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e) {
    error_log("Uncaught exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());

    // Ensure CORS headers are sent even on error
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    $debug = ($_ENV['DEBUG'] ?? getenv('DEBUG') ?: 'false') === 'true';

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $debug ? $e->getMessage() : 'Une erreur est survenue',
        'trace' => $debug ? $e->getTraceAsString() : null
    ]);
    exit;
});

// Load environment variables
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (Exception $e) {
    // In Docker, env vars come from docker-compose, .env file may not exist
    // Continue without .env file
}

// Apply security middleware
SecurityMiddleware::handle();

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove base path if needed (e.g., /api)
$basePath = '/api';
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// Remove trailing slash
$uri = rtrim($uri, '/') ?: '/';

// Router
$routes = [
    // Auth routes (public)
    'POST /auth/request-magic-link' => ['controller' => AuthController::class, 'method' => 'requestMagicLink', 'rateLimit' => 'strict'],
    'GET /auth/verify/(.+)' => ['controller' => AuthController::class, 'method' => 'verifyMagicLink'],
    'POST /auth/refresh' => ['controller' => AuthController::class, 'method' => 'refresh'],
    'POST /auth/logout' => ['controller' => AuthController::class, 'method' => 'logout'],

    // Impersonation routes (admin only)
    'POST /auth/impersonate/([a-f0-9-]+)' => ['controller' => AuthController::class, 'method' => 'impersonate'],
    'POST /auth/stop-impersonate' => ['controller' => AuthController::class, 'method' => 'stopImpersonate'],

    // Users routes
    'GET /users' => ['controller' => UserController::class, 'method' => 'index'],
    'GET /users/me' => ['controller' => UserController::class, 'method' => 'me'],
    'GET /users/([a-f0-9-]+)' => ['controller' => UserController::class, 'method' => 'show'],
    'POST /users' => ['controller' => UserController::class, 'method' => 'store'],
    'PUT /users/([a-f0-9-]+)' => ['controller' => UserController::class, 'method' => 'update'],
    'PATCH /users/([a-f0-9-]+)' => ['controller' => UserController::class, 'method' => 'update'],
    'DELETE /users/([a-f0-9-]+)' => ['controller' => UserController::class, 'method' => 'destroy'],
    'GET /users/([a-f0-9-]+)/loyalty' => ['controller' => UserController::class, 'method' => 'getLoyaltyCard'],
    'POST /users/([a-f0-9-]+)/persons/([a-f0-9-]+)' => ['controller' => UserController::class, 'method' => 'assignPerson'],
    'DELETE /users/([a-f0-9-]+)/persons/([a-f0-9-]+)' => ['controller' => UserController::class, 'method' => 'unassignPerson'],

    // Persons routes
    'GET /persons' => ['controller' => PersonController::class, 'method' => 'index'],
    'GET /persons/([a-f0-9-]+)' => ['controller' => PersonController::class, 'method' => 'show'],
    'GET /persons/([a-f0-9-]+)/sessions' => ['controller' => PersonController::class, 'method' => 'sessions'],
    'POST /persons' => ['controller' => PersonController::class, 'method' => 'store'],
    'PUT /persons/([a-f0-9-]+)' => ['controller' => PersonController::class, 'method' => 'update'],
    'PATCH /persons/([a-f0-9-]+)' => ['controller' => PersonController::class, 'method' => 'update'],
    'DELETE /persons/([a-f0-9-]+)' => ['controller' => PersonController::class, 'method' => 'destroy'],

    // Sessions routes
    'GET /sessions' => ['controller' => SessionController::class, 'method' => 'index'],
    'GET /sessions/labels' => ['controller' => SessionController::class, 'method' => 'getLabels'],
    'GET /sessions/stats' => ['controller' => SessionController::class, 'method' => 'stats'],
    'GET /sessions/person/([a-f0-9-]+)/stats' => ['controller' => SessionController::class, 'method' => 'personStats'],
    'GET /sessions/([a-f0-9-]+)' => ['controller' => SessionController::class, 'method' => 'show'],
    'POST /sessions' => ['controller' => SessionController::class, 'method' => 'store'],
    'PUT /sessions/([a-f0-9-]+)' => ['controller' => SessionController::class, 'method' => 'update'],
    'PATCH /sessions/([a-f0-9-]+)' => ['controller' => SessionController::class, 'method' => 'update'],
    'DELETE /sessions/([a-f0-9-]+)' => ['controller' => SessionController::class, 'method' => 'destroy'],

    // Sensory Proposals routes
    'GET /sensory-proposals' => ['controller' => SensoryProposalController::class, 'method' => 'index'],
    'GET /sensory-proposals/types' => ['controller' => SensoryProposalController::class, 'method' => 'getTypes'],
    'GET /sensory-proposals/search' => ['controller' => SensoryProposalController::class, 'method' => 'search'],
    'GET /sensory-proposals/([a-f0-9-]+)' => ['controller' => SensoryProposalController::class, 'method' => 'show'],
    'POST /sensory-proposals' => ['controller' => SensoryProposalController::class, 'method' => 'store'],
    'PUT /sensory-proposals/([a-f0-9-]+)' => ['controller' => SensoryProposalController::class, 'method' => 'update'],
    'PATCH /sensory-proposals/([a-f0-9-]+)' => ['controller' => SensoryProposalController::class, 'method' => 'update'],
    'DELETE /sensory-proposals/([a-f0-9-]+)' => ['controller' => SensoryProposalController::class, 'method' => 'destroy'],

    // Stats routes (admin only)
    'GET /stats/dashboard' => ['controller' => StatsController::class, 'method' => 'dashboard'],
    'GET /stats/audit-logs' => ['controller' => StatsController::class, 'method' => 'auditLogs'],

    // Settings routes (admin only)
    'GET /settings' => ['controller' => SettingsController::class, 'method' => 'index'],
    'GET /settings/category/([a-z]+)' => ['controller' => SettingsController::class, 'method' => 'getByCategory'],
    'PUT /settings' => ['controller' => SettingsController::class, 'method' => 'update'],
    'GET /settings/sms-credits' => ['controller' => SettingsController::class, 'method' => 'getSmsCredits'],

    // ============================================
    // PUBLIC BOOKING ROUTES (No authentication)
    // ============================================
    'GET /public/availability/schedule' => ['controller' => PublicBookingController::class, 'method' => 'getSchedule'],
    'GET /public/availability/dates' => ['controller' => PublicBookingController::class, 'method' => 'getAvailableDates'],
    'GET /public/availability/slots' => ['controller' => PublicBookingController::class, 'method' => 'getAvailableSlots'],
    'POST /public/bookings/check-email' => ['controller' => PublicBookingController::class, 'method' => 'checkEmail', 'rateLimit' => 'strict'],
    'GET /public/bookings/persons' => ['controller' => PublicBookingController::class, 'method' => 'getPersonsByEmail'],
    'POST /public/bookings' => ['controller' => PublicBookingController::class, 'method' => 'createBooking', 'rateLimit' => 'strict'],
    'GET /public/bookings/confirm/([a-f0-9]+)' => ['controller' => PublicBookingController::class, 'method' => 'confirmBooking'],
    'POST /public/bookings/cancel/([a-f0-9]+)' => ['controller' => PublicBookingController::class, 'method' => 'cancelBooking'],
    'GET /public/bookings/([a-f0-9]+)' => ['controller' => PublicBookingController::class, 'method' => 'getBookingByToken'],
    'GET /public/bookings/([a-f0-9]+)/ics' => ['controller' => PublicBookingController::class, 'method' => 'downloadICS'],

    // ============================================
    // ADMIN BOOKING ROUTES (Authentication required)
    // ============================================
    'GET /bookings' => ['controller' => BookingController::class, 'method' => 'index'],
    'GET /bookings/stats' => ['controller' => BookingController::class, 'method' => 'stats'],
    'GET /bookings/calendar' => ['controller' => BookingController::class, 'method' => 'calendar'],
    'GET /bookings/pending-sessions' => ['controller' => BookingController::class, 'method' => 'pendingSessions'],
    'GET /bookings/export/calendar' => ['controller' => BookingController::class, 'method' => 'exportCalendar'],
    'GET /bookings/([a-f0-9-]+)' => ['controller' => BookingController::class, 'method' => 'show'],
    'PUT /bookings/([a-f0-9-]+)' => ['controller' => BookingController::class, 'method' => 'update'],
    'PATCH /bookings/([a-f0-9-]+)/status' => ['controller' => BookingController::class, 'method' => 'updateStatus'],
    'DELETE /bookings/([a-f0-9-]+)' => ['controller' => BookingController::class, 'method' => 'destroy'],
    'POST /bookings/([a-f0-9-]+)/reminder' => ['controller' => BookingController::class, 'method' => 'sendReminder'],
    'POST /bookings/([a-f0-9-]+)/create-session' => ['controller' => BookingController::class, 'method' => 'createSession'],

    // ============================================
    // DOCUMENTS ROUTES (Admin only)
    // ============================================
    'GET /documents/(user|person)/([a-f0-9-]+)' => ['controller' => DocumentController::class, 'method' => 'listByEntity'],
    'POST /documents/(user|person)/([a-f0-9-]+)' => ['controller' => DocumentController::class, 'method' => 'upload'],
    'GET /documents/([a-f0-9-]+)/download' => ['controller' => DocumentController::class, 'method' => 'download'],
    'GET /documents/([a-f0-9-]+)/view' => ['controller' => DocumentController::class, 'method' => 'view'],
    'DELETE /documents/([a-f0-9-]+)' => ['controller' => DocumentController::class, 'method' => 'destroy'],

    // Health check
    'GET /' => ['handler' => fn() => Response::success(['status' => 'ok', 'version' => '1.0.0'], 'API Snoezelen')],
    'GET /health' => ['handler' => fn() => Response::success(['status' => 'ok'], 'Service opérationnel')],
];

// Find matching route
$matched = false;

foreach ($routes as $route => $config) {
    list($routeMethod, $routePath) = explode(' ', $route, 2);

    if ($method !== $routeMethod) {
        continue;
    }

    // Convert route to regex
    $pattern = '#^' . $routePath . '$#';

    if (preg_match($pattern, $uri, $matches)) {
        $matched = true;

        // Apply rate limiting
        if (isset($config['rateLimit'])) {
            if ($config['rateLimit'] === 'strict') {
                RateLimitMiddleware::handleStrict();
            } else {
                RateLimitMiddleware::handle();
            }
        } else {
            RateLimitMiddleware::handle();
        }

        // Execute handler or controller
        if (isset($config['handler'])) {
            $config['handler']();
        } else {
            $controller = new $config['controller']();
            $methodName = $config['method'];

            // Extract route parameters
            array_shift($matches); // Remove full match

            call_user_func_array([$controller, $methodName], $matches);
        }

        break;
    }
}

if (!$matched) {
    Response::notFound('Route non trouvée');
}
