<?php

/**
 * Limit the ML service's database user to the tables and columns it reads.
 * Run after the migrations:  php bin/grant_ml_user.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Core\MlGrants;

Env::load(dirname(__DIR__));

try {
    MlGrants::apply(MlGrants::rootConnection(), (string)Env::get('DB_NAME', 'osuthuru'));
    echo "The ML database user can now read only the tables it needs.\n";
    exit(0);
} catch (Throwable $e) {
    echo 'Could not set the grants: ' . $e->getMessage() . "\n";
    exit(1);
}
