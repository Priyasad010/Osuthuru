<?php

// After installing, check the public website:  php bin/shared/check_site.php
// (--insecure accepts a test certificate)
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
Env::load(dirname(__DIR__, 2));
$base = rtrim((string)Env::get('APP_URL', ''), '/');
$insecure = in_array('--insecure', $argv, true);
$failed = 0;

$request = function (string $path, array $headers = [], ?array $post = null, string $cookies = '') use ($base, $insecure): array {
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => !$insecure,
        CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
    ]);
    if ($cookies !== '') {
        curl_setopt($curl, CURLOPT_COOKIE, $cookies);
    }
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $response = (string)curl_exec($curl);
    $result = [(int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE), $response, curl_error($curl)];
    curl_close($curl);
    return $result;
};
$check = function (string $what, bool $ok, string $detail = '') use (&$failed): void {
    echo ($ok ? '[ok]   ' : '[FAIL] ') . $what . (!$ok && $detail !== '' ? " - {$detail}" : '') . PHP_EOL;
    $failed += $ok ? 0 : 1;
};

echo "Checking {$base}\n";
$check('APP_URL starts with https://', str_starts_with($base, 'https://'));
[$code, $page, $error] = $request('/login');
$check('Sign-in page opens', $code === 200 && str_contains($page, '_csrf_token'), "status {$code} {$error}; is the site's root directory the public folder?");
preg_match('/^Set-Cookie: (OSUTHURU_SECURE=[^;]+)(.*)$/mi', $page, $cookie);
$check('Session cookie is marked Secure', isset($cookie[2]) && stripos($cookie[2], 'secure') !== false, 'check PROXY_IP_HEADER in .env');
[$code] = $request('/products');
$check('Products page and database', $code === 200, "status {$code}");
foreach (['/.env', '/../.env', '/composer.json', '/vendor/autoload.php', '/uploads/', '/uploads/slips/x.jpg'] as $path) {
    [$code, $body] = $request($path);
    $check("Not public: {$path}", in_array($code, [301, 403, 404], true) && !str_contains($body, 'DB_PASS'), "status {$code}");
}

// A visitor must not be able to choose the address the shop sees, or sign-in limits could be dodged
if (isset($cookie[1]) && preg_match('/name="_csrf_token" value="([^"]+)"/', $page, $token)) {
    $probe = 'site-check-' . bin2hex(random_bytes(4)) . '@check.invalid';
    foreach (['203.0.113.10', '198.51.100.20'] as $fake) {
        $request('/login', ["X-Real-IP: {$fake}", "X-Forwarded-For: {$fake}"], ['_csrf_token' => $token[1], 'email' => $probe, 'password' => 'wrong-password'], $cookie[1]);
    }
    $pdo = Database::getConnection();
    $find = $pdo->prepare('SELECT COUNT(*) AS attempts, COUNT(DISTINCT ip_hash) AS addresses FROM login_attempts WHERE email = ?');
    $find->execute([$probe]);
    $seen = $find->fetch();
    $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$probe]);
    $check('Visitors cannot fake their address', (int)$seen['attempts'] === 2 && (int)$seen['addresses'] === 1,
        (int)$seen['attempts'] === 2 ? 'remove the PROXY_IP_HEADER line from .env' : 'the test sign-ins were not recorded');
}

echo $failed === 0 ? "All checks passed.\n" : "{$failed} check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
