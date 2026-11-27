<?php

use App\Core\Env;
use App\Core\Csrf;
use App\Core\Lang;

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('t')) {
    function t(string $key, array $replace = []): string
    {
        return Lang::trans($key, $replace);
    }
}

if (!function_exists('image_url')) {
    /** Web path of an uploaded or catalogue picture, or null if no such file is served. */
    function image_url(?string $stored): ?string
    {
        if (!$stored || !preg_match('#^/(uploads|assets/images/catalog)/#', $stored) || str_contains($stored, '..')) {
            return null;
        }
        return is_file(dirname(__DIR__, 2) . '/public' . $stored) ? $stored : null;
    }
}

if (!function_exists('picture')) {
    /** An uploaded picture filling its box, or the leaf icon. */
    function picture(?string $stored, string $alt, string $iconClass = 'text-success opacity-25 fs-1'): string
    {
        $url = image_url($stored);
        return $url !== null
            ? '<img src="' . e($url) . '" alt="' . e($alt) . '" loading="lazy" style="width:100%;height:100%;object-fit:'
                . (str_contains($url, '/herbs/') ? 'cover' : 'contain') . ';">'
            : '<i class="bi bi-flower1 ' . e($iconClass) . '" aria-hidden="true"></i>';
    }
}

if (!function_exists('t_or')) {
    /** Translate a stored code (status, payment method...), showing the raw value if unknown. */
    function t_or(string $prefix, ?string $value): string
    {
        $value = (string)$value;
        $key = $prefix . trim((string)preg_replace('/[^a-z0-9]+/', '_', strtolower($value)), '_');
        $text = Lang::trans($key);
        return $text !== $key ? $text : $value;
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::getToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('json_response')) {
    function json_response(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('format_price')) {
    function format_price(float|int $amount): string
    {
        return 'Rs. ' . number_format((float)$amount, 2);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(Env::get('APP_URL', 'http://localhost:8080'), '/');
        $path = ltrim($path, '/');
        return $path !== '' ? "{$base}/{$path}" : $base;
    }
}
