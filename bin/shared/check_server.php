<?php

// Is this server ready for the shop?  php bin/shared/check_server.php
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__, 2);
$failed = 0;
$check = function (string $what, bool $ok, string $help = '') use (&$failed): void {
    echo ($ok ? '[ok]   ' : '[FAIL] ') . $what . (!$ok && $help !== '' ? " - {$help}" : '') . PHP_EOL;
    $failed += $ok ? 0 : 1;
};

$check('PHP 8.2 or newer (found ' . PHP_VERSION . ')', PHP_VERSION_ID >= 80200, 'choose PHP 8.3 in the panel, Environment > PHP');
foreach (['pdo_mysql', 'mbstring', 'intl', 'gd', 'curl', 'openssl', 'fileinfo', 'dom', 'zip'] as $extension) {
    $check("PHP extension {$extension}", extension_loaded($extension), "add extension={$extension}.so in the panel, Environment > PHP > php.ini");
}
$check('.env exists', is_file($root . '/.env'), 'run php bin/shared/setup_env.php');

if (is_file($root . '/vendor/autoload.php') && is_file($root . '/.env')) {
    require $root . '/vendor/autoload.php';
    App\Core\Env::load($root);
    try {
        $version = App\Core\Database::createScriptConnection()->query('SELECT VERSION()')->fetchColumn();
        $check("Database connection ({$version})", true);
    } catch (Throwable $e) {
        $check('Database connection', false, $e->getMessage());
    }
}
exit($failed === 0 ? 0 : 1);
