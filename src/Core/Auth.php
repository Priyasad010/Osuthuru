<?php

namespace App\Core;

class Auth
{
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function check(): bool
    {
        self::initSession();
        return !empty($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        self::initSession();
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        self::initSession();
        return $_SESSION['user_id'] ?? null;
    }

    public static function isAdmin(): bool
    {
        self::initSession();
        return !empty($_SESSION['admin_id']);
    }

    public static function admin(): ?array
    {
        self::initSession();
        return $_SESSION['admin'] ?? null;
    }

    public static function requireUser(): void
    {
        if (!self::check()) {
            if (self::isJsonRequest()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Authentication required']);
                exit;
            }

            header('Location: /login');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            if (self::isJsonRequest()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Admin privileges required']);
                exit;
            }

            header('Location: /admin/login');
            exit;
        }
    }

    private static function isJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_contains($accept, 'application/json') || str_starts_with($uri, '/api/');
    }
}
