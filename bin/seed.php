<?php

/**
 * Load the demo catalogue into a migrated database.
 * Safe to run again: existing rows are left as they are.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;
use App\Core\Migrator;

Env::load(dirname(__DIR__));

try {
    $sql = file_get_contents(dirname(__DIR__) . '/database/seed/v1_catalog_seed.sql');
    Migrator::runScript(Database::createScriptConnection(), (string)$sql);
    echo "Catalogue seed loaded.\n";
    exit(0);
} catch (Throwable $e) {
    echo "Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
