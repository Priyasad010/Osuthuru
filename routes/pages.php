<?php

/**
 * Information pages, the contact form and the files search engines read.
 * Included by public/index.php, which provides $requestUri and $requestMethod.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use App\Repository\ContactRepo;
use App\Repository\SettingRepo;

// Page => number of sections in the language files
$policies = ['privacy' => 7, 'terms' => 6, 'returns' => 5];

if ($requestMethod === 'GET' && isset($policies[ltrim($requestUri, '/')]) && substr_count($requestUri, '/') === 1) {
    $name = ltrim($requestUri, '/');
    echo View::render('pages/policy', [
        'pageTitle' => t("page_{$name}"),
        'name' => $name,
        'sections' => $policies[$name],
        'draft' => SettingRepo::get('policies_reviewed') !== '1',
    ]);
    exit;
}

if ($requestUri === '/about' && $requestMethod === 'GET') {
    echo View::render('pages/about', ['pageTitle' => t('page_about')]);
    exit;
}

if ($requestUri === '/contact') {
    $error = null;
    $old = [];
    if ($requestMethod === 'POST') {
        $ip = Request::clientIp($_SERVER);
        $old = array_filter($_POST, 'is_string');
        if (!Csrf::validateRequest()) {
            http_response_code(403);
            $error = t('err_csrf');
        } elseif (Auth::isLockedOut('contact', $ip)) {
            $error = t('err_too_many');
        } else {
            $sent = ContactRepo::send($_POST);
            if ($sent['ok']) {
                Auth::recordAttempt('contact', $ip, false);
                header('Location: /contact?sent=1');
                exit;
            }
            $error = $sent['error'];
        }
    } elseif (Auth::check()) {
        $user = Auth::user();
        $old = ['name' => (string)($user['name'] ?? ''), 'email' => (string)($user['email'] ?? '')];
    }
    echo View::render('pages/contact', [
        'pageTitle' => t('page_contact'),
        'settings' => SettingRepo::all(),
        'sent' => isset($_GET['sent']),
        'error' => $error,
        'old' => $old,
    ]);
    exit;
}

if ($requestUri === '/robots.txt' && $requestMethod === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\n";
    foreach (['/admin', '/cart', '/checkout', '/orders', '/profile', '/wishlist', '/login', '/register', '/reset-password', '/api/', '/verify'] as $private) {
        echo "Disallow: {$private}\n";
    }
    echo 'Sitemap: ' . url('sitemap.xml') . "\n";
    exit;
}

if ($requestUri === '/sitemap.xml' && $requestMethod === 'GET') {
    $paths = ['', 'products', 'dictionary', 'blogs', 'quiz', 'about', 'contact', 'privacy', 'terms', 'returns', 'credits'];
    $pdo = Database::getConnection();
    foreach (['products/' => 'SELECT product_id FROM product', 'herb/' => 'SELECT herb_id FROM herb', 'blog/' => 'SELECT blog_id FROM blog'] as $prefix => $sql) {
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $paths[] = $prefix . (int)$id;
        }
    }
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($paths as $path) {
        echo '  <url><loc>' . htmlspecialchars(url($path), ENT_XML1) . "</loc></url>\n";
    }
    echo "</urlset>\n";
    exit;
}
