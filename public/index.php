<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);

// 1. Autoloader
if (file_exists($rootDir . '/vendor/autoload.php')) {
    require_once $rootDir . '/vendor/autoload.php';
} else {
    // PSR-4 fallback autoloader
    spl_autoload_register(function ($class) use ($rootDir) {
        $prefix = 'App\\';
        $baseDir = $rootDir . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

// 2. Helpers
require_once $rootDir . '/src/Core/helpers.php';

use App\Core\Env;
use App\Core\Lang;
use App\Core\View;

// 3. Environment & Error Reporting
Env::load($rootDir);

$debug = (bool)Env::get('APP_DEBUG', true);
if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 4. Session & Language
Lang::init();

// 5. Basic Router
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// API Health Check
if ($requestUri === '/api/health' && $requestMethod === 'GET') {
    json_response(['ok' => true]);
}

// Home Page
if ($requestUri === '/' || $requestUri === '') {
    echo View::render('home', [
        'pageTitle' => t('welcome_title')
    ]);
    exit;
}

// 404 Not Found
http_response_code(404);
if (str_starts_with($requestUri, '/api/')) {
    json_response(['ok' => false, 'error' => 'Endpoint not found'], 404);
} else {
    echo "<h1>404 Not Found</h1><p>The page requested was not found.</p>";
}
