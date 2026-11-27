<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;
use App\Core\Migrator;

// Bootstrap environment
Env::load(dirname(__DIR__));

echo "==========================================\n";
echo "Osuthuru - Database Migration\n";
echo "==========================================\n";

try {
    $applied = Migrator::migrate(
        Database::createScriptConnection(),
        dirname(__DIR__) . '/database/migrations',
        function (string $file): void {
            echo "Applied {$file} [OK]\n";
        }
    );

    if ($applied === []) {
        echo "No new migrations to apply. Database is up to date.\n";
    } else {
        echo 'Successfully applied ' . count($applied) . " migration(s).\n";
    }

    exit(0);
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
