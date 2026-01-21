<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Helper function for environment variables (works with Docker and .env)
function env(string $key, ?string $default = null): ?string
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Parse command line arguments
$fresh = in_array('--fresh', $argv, true) || in_array('fresh', $argv, true);

// Load environment variables (optional, Docker provides them via docker-compose)
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (Exception $e) {
    // In Docker, env vars come from docker-compose
}

try {
    $dsn = sprintf(
        'mysql:host=%s;charset=%s',
        env('DB_HOST', 'db'),
        env('DB_CHARSET', 'utf8mb4')
    );

    $pdo = new PDO($dsn, env('DB_USER', 'snoezelen'), env('DB_PASS', 'snoezelen_secret'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $dbName = env('DB_NAME', 'snoezelen_db');

    // Fresh mode: drop and recreate database
    if ($fresh) {
        echo "\n🗑️  Mode FRESH: Suppression de la base de données...\n";
        $pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
        echo "✓ Base de données supprimée\n\n";
    }

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");

    echo "Database '{$dbName}' ready.\n";

    // Create migrations tracking table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `_migrations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `filename` VARCHAR(255) NOT NULL,
            `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `idx_migrations_filename` (`filename`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Get executed migrations
    $stmt = $pdo->query("SELECT filename FROM `_migrations`");
    $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Find migration files
    $migrations = glob(__DIR__ . '/*.sql');
    sort($migrations);

    $count = 0;
    foreach ($migrations as $file) {
        $filename = basename($file);

        if (in_array($filename, $executed, true)) {
            echo "Skipping: {$filename} (already executed)\n";
            continue;
        }

        echo "Executing: {$filename}...\n";

        $sql = file_get_contents($file);

        // Execute migration
        $pdo->exec($sql);

        // Record migration
        $stmt = $pdo->prepare("INSERT INTO `_migrations` (`filename`) VALUES (:filename)");
        $stmt->execute(['filename' => $filename]);

        echo "  Done!\n";
        $count++;
    }

    if ($count === 0) {
        echo "\nNo new migrations to execute.\n";
    } else {
        echo "\nExecuted {$count} migration(s) successfully.\n";
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
