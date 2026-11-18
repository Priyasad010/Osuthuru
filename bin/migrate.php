<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/Database.php';

use App\Core\Env;
use App\Core\Database;

// Bootstrap environment
Env::load(dirname(__DIR__));

echo "==========================================\n";
echo "Suwoda HerbHub V2 - Database Migration\n";
echo "==========================================\n";

try {
    $pdo = Database::getConnection();

    // 1. Ensure schema_migrations table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `version` VARCHAR(255) NOT NULL,
            `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Fetch applied migrations
    $stmt = $pdo->query("SELECT `version` FROM `schema_migrations`");
    $applied = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $appliedSet = array_flip($applied);

    // 3. Scan migrations folder
    $migrationsDir = dirname(__DIR__) . '/database/migrations';
    if (!is_dir($migrationsDir)) {
        throw new RuntimeException("Migrations directory not found at {$migrationsDir}");
    }

    $files = scandir($migrationsDir);
    $migrationFiles = [];
    foreach ($files as $file) {
        if (str_ends_with($file, '.sql')) {
            $migrationFiles[] = $file;
        }
    }
    sort($migrationFiles);

    $newMigrationsCount = 0;

    foreach ($migrationFiles as $file) {
        if (isset($appliedSet[$file])) {
            continue; // Already applied
        }

        echo "Applying {$file}... ";
        $filePath = "{$migrationsDir}/{$file}";
        $sql = file_get_contents($filePath);

        // Execute migration
        try {
            $pdo->exec($sql);

            // While multi-statements run, advance cursor to clear any remaining result sets
            while ($pdo->query("SELECT 1")->nextRowset()) {}

            $insertStmt = $pdo->prepare("INSERT INTO `schema_migrations` (`version`) VALUES (:version)");
            $insertStmt->execute([':version' => $file]);

            echo "[OK]\n";
            $newMigrationsCount++;
        } catch (Throwable $e) {
            echo "[FAILED]\n";
            echo "Error executing {$file}: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    if ($newMigrationsCount === 0) {
        echo "No new migrations to apply. Database is up to date.\n";
    } else {
        echo "Successfully applied {$newMigrationsCount} migration(s).\n";
    }

    exit(0);

} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
