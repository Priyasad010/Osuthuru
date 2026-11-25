<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);

// 1. Autoloader
if (file_exists($rootDir . '/vendor/autoload.php')) {
    require_once $rootDir . '/vendor/autoload.php';
} else {
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
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Validator;
use App\Core\Mailer;
use App\Service\PasswordResetService;
use App\Service\CheckoutService;

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
Auth::initSession();
Lang::init();

$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ----------------------------------------------------
// PUBLIC API & HEALTH
// ----------------------------------------------------
if ($requestUri === '/api/health' && $requestMethod === 'GET') {
    json_response(['ok' => true]);
}

// ----------------------------------------------------
// HOME PAGE
// ----------------------------------------------------
if ($requestUri === '/' || $requestUri === '') {
    echo View::render('home', [
        'pageTitle' => t('welcome_title')
    ]);
    exit;
}

// ----------------------------------------------------
// USER AUTHENTICATION
// ----------------------------------------------------
if ($requestUri === '/login') {
    if (Auth::check()) {
        header('Location: /');
        exit;
    }

    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            echo View::render('auth/login', ['error' => 'Invalid or expired CSRF token. Please try again.']);
            exit;
        }

        $email = (string)($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $result = Auth::attemptUser($email, $password, $clientIp);
        if ($result['ok']) {
            header('Location: /');
            exit;
        }

        echo View::render('auth/login', [
            'error' => $result['error'],
            'email' => $email
        ]);
        exit;
    }

    echo View::render('auth/login', [
        'pageTitle' => t('nav_login'),
        'success' => $_GET['registered'] ? 'Registration successful! You may now login.' : null
    ]);
    exit;
}

if ($requestUri === '/register') {
    if (Auth::check()) {
        header('Location: /');
        exit;
    }

    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            echo View::render('auth/register', ['error' => 'Invalid or expired CSRF token. Please try again.']);
            exit;
        }

        $validator = Validator::make($_POST, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|max:100',
            'password' => 'required|min:8|matches:password_confirmation',
        ]);

        if ($validator->fails()) {
            echo View::render('auth/register', [
                'error' => $validator->first('name') ?? $validator->first('email') ?? $validator->first('password'),
                'old' => $_POST
            ]);
            exit;
        }

        $pdo = Database::getConnection();
        $email = strtolower(trim((string)$_POST['email']));

        // Check if email already registered
        $stmt = $pdo->prepare("SELECT user_id FROM `user` WHERE LOWER(email) = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            echo View::render('auth/register', [
                'error' => 'This email address is already registered.',
                'old' => $_POST
            ]);
            exit;
        }

        // Create new user (hashed password only)
        $passwordHash = password_hash((string)$_POST['password'], PASSWORD_DEFAULT);
        $verifyToken = bin2hex(random_bytes(32));

        $ins = $pdo->prepare("
            INSERT INTO `user` (name, email, password, phone, status, verification_token)
            VALUES (:name, :email, :password, :phone, 'active', :token)
        ");
        $ins->execute([
            ':name' => trim((string)$_POST['name']),
            ':email' => $email,
            ':password' => $passwordHash,
            ':phone' => trim((string)($_POST['phone'] ?? '')),
            ':token' => $verifyToken
        ]);

        // Optional verification email dispatch
        Mailer::sendVerification($email, trim((string)$_POST['name']), $verifyToken);

        header('Location: /login?registered=1');
        exit;
    }

    echo View::render('auth/register', ['pageTitle' => t('nav_register')]);
    exit;
}

if ($requestUri === '/verify') {
    $token = (string)($_GET['token'] ?? '');
    if (!empty($token)) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE `user` SET status = 'active', verification_token = NULL WHERE verification_token = :token");
        $stmt->execute([':token' => $token]);
    }
    header('Location: /login?verified=1');
    exit;
}

if ($requestUri === '/forgot-password') {
    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            echo View::render('auth/forgot_password', ['error' => 'Invalid CSRF token.']);
            exit;
        }

        $email = (string)($_POST['email'] ?? '');
        $res = PasswordResetService::requestReset($email);

        echo View::render('auth/forgot_password', [
            'message' => $res['message'] ?? null,
            'error' => $res['error'] ?? null
        ]);
        exit;
    }

    echo View::render('auth/forgot_password', ['pageTitle' => 'Forgot Password']);
    exit;
}

if ($requestUri === '/reset-password') {
    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            echo View::render('auth/reset_password', ['error' => 'Invalid CSRF token.', 'token' => '']);
            exit;
        }

        $token = (string)($_POST['token'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirmation'] ?? '');

        if (strlen($password) < 8) {
            echo View::render('auth/reset_password', ['error' => 'Password must be at least 8 characters.', 'token' => $token]);
            exit;
        }

        if ($password !== $confirm) {
            echo View::render('auth/reset_password', ['error' => 'Passwords do not match.', 'token' => $token]);
            exit;
        }

        $res = PasswordResetService::resetPassword($token, $password);
        if ($res['ok']) {
            echo View::render('auth/reset_password', ['success' => $res['message'], 'token' => $token]);
            exit;
        }

        echo View::render('auth/reset_password', ['error' => $res['error'], 'token' => $token]);
        exit;
    }

    $token = (string)($_GET['token'] ?? '');
    $check = PasswordResetService::validateToken($token);

    if (!$check['ok']) {
        echo View::render('auth/reset_password', ['error' => $check['error'], 'token' => '']);
        exit;
    }

    echo View::render('auth/reset_password', ['token' => $token]);
    exit;
}

if ($requestUri === '/logout' && $requestMethod === 'POST') {
    if (Csrf::validateRequest()) {
        Auth::logout();
    }
    header('Location: /');
    exit;
}

// ----------------------------------------------------
// ADMIN ROUTES & GUARDS
// ----------------------------------------------------
if ($requestUri === '/admin/login') {
    if (Auth::isAdmin()) {
        header('Location: /admin/dashboard');
        exit;
    }

    if ($requestMethod === 'POST') {
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            echo View::render('auth/admin_login', ['error' => 'Invalid CSRF token.']);
            exit;
        }

        $email = (string)($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $res = Auth::attemptAdmin($email, $password, $clientIp);
        if ($res['ok']) {
            header('Location: /admin/dashboard');
            exit;
        }

        echo View::render('auth/admin_login', [
            'error' => $res['error'],
            'email' => $email
        ]);
        exit;
    }

    echo View::render('auth/admin_login', ['pageTitle' => 'Admin Login']);
    exit;
}

if ($requestUri === '/admin/logout' && $requestMethod === 'POST') {
    if (Csrf::validateRequest()) {
        Auth::logout();
    }
    header('Location: /admin/login');
    exit;
}

// GUARD: Protect all other /admin/* routes
if (str_starts_with($requestUri, '/admin/')) {
    Auth::requireAdmin();

    if ($requestUri === '/admin/dashboard' || $requestUri === '/admin/') {
        $pdo = Database::getConnection();
        $stats = [
            'products' => (int)$pdo->query("SELECT COUNT(*) FROM `product`")->fetchColumn(),
            'herbs'    => (int)$pdo->query("SELECT COUNT(*) FROM `herb`")->fetchColumn(),
            'orders'   => (int)$pdo->query("SELECT COUNT(*) FROM `orders`")->fetchColumn(),
            'users'    => (int)$pdo->query("SELECT COUNT(*) FROM `user`")->fetchColumn(),
        ];

        echo View::render('admin/dashboard', [
            'pageTitle' => 'Admin Dashboard',
            'stats' => $stats
        ]);
        exit;
    }
}

// ----------------------------------------------------
// STRIPE WEBHOOK
// ----------------------------------------------------
if ($requestUri === '/webhooks/stripe' && $requestMethod === 'POST') {
    $payload = @file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    $result = CheckoutService::handleWebhook($payload, $sigHeader);
    if ($result['ok']) {
        json_response(['ok' => true]);
    } else {
        json_response(['ok' => false, 'error' => $result['error'] ?? 'Webhook error'], 400);
    }
}

// ----------------------------------------------------
// 404 NOT FOUND
// ----------------------------------------------------
http_response_code(404);
if (str_starts_with($requestUri, '/api/')) {
    json_response(['ok' => false, 'error' => 'Endpoint not found'], 404);
} else {
    echo "<h1>404 Not Found</h1><p>The page requested was not found.</p>";
}
