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
