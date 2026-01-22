<?php

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set default timezone
date_default_timezone_set('Europe/Paris');

// Load test environment variables if .env.testing exists
$envFile = __DIR__ . '/../.env.testing';
if (file_exists($envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname($envFile), '.env.testing');
    $dotenv->load();
}
