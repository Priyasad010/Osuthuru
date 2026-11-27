<?php

namespace App\Core;

use App\Service\RegistrationService;
use PDO;
use Throwable;

class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    /**
     * HTTPS gets its own cookie name. Browsers do not let a plain-HTTP page
     * replace a Secure cookie, so sharing one name would stop a device that had
     * visited over HTTPS from signing in over HTTP on the same host.
     */
    public static function sessionName(array $server): string
    {
        return ($server['HTTPS'] ?? '') === 'on' ? 'SUWODA_SECURE' : 'PHPSESSID';
    }

    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(self::sessionName($_SERVER));
            // Set secure session cookie parameters
            $params = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => $params['lifetime'],
                'path' => '/',
                'domain' => $params['domain'],
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    public static function hashIp(string $ip): string
    {
        $salt = Env::get('APP_KEY', 'suwoda_herbhub_salt_2026');
        return hash('sha256', $ip . $salt);
    }

    public static function isLockedOut(string $email, string $ip): bool
    {
        try {
            $pdo = Database::getConnection();
            $ipHash = self::hashIp($ip);

            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM login_attempts
                WHERE email = :email AND ip_hash = :ip_hash
                  AND attempted_at >= (NOW() - INTERVAL :minutes MINUTE)
                  AND success = 0
            ");
            $stmt->bindValue(':email', strtolower(trim($email)));
            $stmt->bindValue(':ip_hash', $ipHash);
            $stmt->bindValue(':minutes', self::LOCKOUT_MINUTES, PDO::PARAM_INT);
            $stmt->execute();

            return (int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS;
        } catch (Throwable) {
            return false;
        }
    }

    public static function recordAttempt(string $email, string $ip, bool $success): void
    {
        try {
            $pdo = Database::getConnection();
            $ipHash = self::hashIp($ip);

            $stmt = $pdo->prepare("
                INSERT INTO login_attempts (email, ip_hash, success, attempted_at)
                VALUES (:email, :ip_hash, :success, NOW())
            ");
            $stmt->execute([
                ':email' => strtolower(trim($email)),
                ':ip_hash' => $ipHash,
                ':success' => $success ? 1 : 0
            ]);

            // If success, clear recent failed attempts for this email & IP
            if ($success) {
                $del = $pdo->prepare("
                    DELETE FROM login_attempts 
                    WHERE email = :email AND ip_hash = :ip_hash
                ");
                $del->execute([
                    ':email' => strtolower(trim($email)),
                    ':ip_hash' => $ipHash
                ]);
            }
        } catch (Throwable) {
            // Silently ignore logging errors
        }
    }

    public static function attemptUser(string $email, string $password, string $ip): array
    {
        $cleanEmail = strtolower(trim($email));

        if (self::isLockedOut($cleanEmail, $ip)) {
            return [
                'ok' => false,
                'error' => t('err_locked_user')
            ];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `user` WHERE LOWER(email) = :email LIMIT 1");
        $stmt->execute([':email' => $cleanEmail]);
        $user = $stmt->fetch();

        // Strict password verify - no plain text fallback.
        // The password is checked first so account status is only revealed to its owner.
        if (!$user || !password_verify($password, $user['password'])) {
            self::recordAttempt($cleanEmail, $ip, false);
            return ['ok' => false, 'error' => t('err_invalid_login')];
        }

        if ($user['status'] === 'blocked') {
            return ['ok' => false, 'error' => t('err_account_blocked')];
        }

        if ($user['status'] !== 'active') {
            // Counted as an attempt so verification emails cannot be sent without limit
            self::recordAttempt($cleanEmail, $ip, false);
            RegistrationService::resendVerification($user);
            return ['ok' => false, 'error' => t('err_unverified')];
        }

        self::recordAttempt($cleanEmail, $ip, true);
        self::loginUser($user);

        return ['ok' => true, 'user' => $user];
    }

    public static function attemptAdmin(string $email, string $password, string $ip): array
    {
        $cleanEmail = strtolower(trim($email));

        if (self::isLockedOut($cleanEmail, $ip)) {
            return [
                'ok' => false,
                'error' => t('err_locked_admin')
            ];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `admin` WHERE LOWER(email) = :email LIMIT 1");
        $stmt->execute([':email' => $cleanEmail]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password'])) {
            self::recordAttempt($cleanEmail, $ip, false);
            return ['ok' => false, 'error' => t('err_invalid_admin')];
        }

        self::recordAttempt($cleanEmail, $ip, true);
        self::loginAdmin($admin);

        return ['ok' => true, 'admin' => $admin];
    }

    public static function loginUser(array $user): void
    {
        self::initSession();
        session_regenerate_id(true);

        unset($user['password'], $user['verification_token']);
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['user'] = $user;
    }

    public static function loginAdmin(array $admin): void
    {
        self::initSession();
        session_regenerate_id(true);

        unset($admin['password']);
        $_SESSION['admin_id'] = (int)$admin['admin_id'];
        $_SESSION['admin'] = $admin;
    }

    public static function logout(): void
    {
        self::initSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
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

    /** False once an account has been blocked or removed, even mid-session. */
    private static function stillAllowed(): bool
    {
        $stmt = Database::getConnection()->prepare("SELECT status FROM `user` WHERE user_id = :id");
        $stmt->execute([':id' => self::id()]);
        return $stmt->fetchColumn() === 'active';
    }

    public static function requireUser(): void
    {
        if (self::check() && !self::stillAllowed()) {
            unset($_SESSION['user_id'], $_SESSION['user']);
        }

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
