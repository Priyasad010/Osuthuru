<?php

/** Delete assistant chat older than 30 days (can be run from a daily scheduled task). */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Repository\AssistantRepo;

Env::load(dirname(__DIR__));
AssistantRepo::purge();
echo "Old assistant chat removed.\n";
