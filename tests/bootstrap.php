<?php

/**
 * PHPUnit bootstrap: every run builds a fresh test database from the migrations
 * and the catalogue seed, so tests never touch the development database.
 */

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;

$testDatabase = 'suwoda_test';

// Set before .env is loaded; existing values are not overwritten by it
putenv("DB_NAME={$testDatabase}");
putenv('ML_URL=');
$_ENV['ML_URL'] = $_SERVER['ML_URL'] = '';
$_ENV['DB_NAME'] = $_SERVER['DB_NAME'] = $testDatabase;
Env::load($root);

if (Env::get('DB_NAME') !== $testDatabase) {
    fwrite(STDERR, "Refusing to run: tests must use the {$testDatabase} database.\n");
    exit(1);
}

$host = Env::get('DB_HOST', 'db');
$port = Env::get('DB_PORT', 3306);
$appUser = (string)Env::get('DB_USER', 'app');

// Creating a database needs the root account (development containers only)
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    (string)Env::get('DB_ROOT_PASS', 'root_secret'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$admin->exec("DROP DATABASE IF EXISTS `{$testDatabase}`");
$admin->exec("CREATE DATABASE `{$testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$admin->exec("GRANT ALL PRIVILEGES ON `{$testDatabase}`.* TO " . $admin->quote($appUser) . "@'%'");
$admin = null;

$script = Database::createScriptConnection();
Migrator::migrate($script, $root . '/database/migrations');
Migrator::runScript($script, (string)file_get_contents($root . '/database/seed/v1_catalog_seed.sql'));
$script = null;
