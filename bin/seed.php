<?php

/**
 * Load seed data into a migrated database. Safe to run again.
 *
 *   php bin/seed.php          the catalogue
 *   php bin/seed.php --demo   the catalogue plus demonstration data
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;
use App\Core\Migrator;

Env::load(dirname(__DIR__));

$demo = in_array('--demo', $argv, true);
if ($demo && Env::get('APP_ENV') === 'production') {
    echo "Demo data is not loaded when APP_ENV is production.\n";
    exit(1);
}

try {
    $files = $demo ? ['v1_catalog_seed.sql', 'demo.sql'] : ['v1_catalog_seed.sql'];
    $connection = Database::createScriptConnection();
    foreach ($files as $file) {
        $sql = file_get_contents(dirname(__DIR__) . '/database/seed/' . $file);
        Migrator::runScript($connection, (string)$sql);
        echo "Loaded {$file}\n";
    }
    if ($demo) {
        echo "Demo customers: nimali@demo.invalid, kasun@demo.invalid, fathima@demo.invalid (password DemoPass123!).\n";
    }
    exit(0);
} catch (Throwable $e) {
    echo "Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
