<?php

namespace App\Core;

use App\Service\SafetyService;

/**
 * Decides on every request whether a signed-in session is still good.
 *
 * A sign-in ends when it has been idle too long, when it is too old, when the
 * account is blocked or removed, or when the account's session version has
 * moved on (password change or reset, block, "sign out other devices").
 */
class SessionGuard
{
    public const USER_IDLE = 7200;      // 2 hours
    public const ADMIN_IDLE = 1800;     // 30 minutes
    public const MAX_AGE = 86400;       // 1 day, however active

    /** role => [session key of the id, table, id column, profile key] */
    private const ROLES = [
        'user' => ['user_id', 'user', 'user_id', 'user'],
        'admin' => ['admin_id', 'admin', 'admin_id', 'admin'],
    ];

    /** Remember when and with which account version this sign-in happened. */
    public static function start(string $role, int $id, ?int $now = null): void
    {
        $now ??= time();
        $_SESSION["auth_{$role}"] = ['started' => $now, 'seen' => $now, 'version' => self::version($role, $id) ?? 0];
    }

    /**
     * End the sign-ins that are no longer valid.
     * @return string|null "expired" (time ran out), "ended" (the account changed) or null
     */
    public static function enforce(string $path, ?int $now = null): ?string
    {
        $now ??= time();
        $reason = null;

        foreach (self::ROLES as $role => [$idKey]) {
            if (empty($_SESSION[$idKey])) {
                continue;
            }
            $state = $_SESSION["auth_{$role}"] ?? null;
            $idle = $role === 'admin' ? self::ADMIN_IDLE : self::USER_IDLE;

            if ($state === null || self::version($role, (int)$_SESSION[$idKey]) !== $state['version']) {
                self::end($role);
                $reason = 'ended';
                continue;
            }
            if ($now - $state['seen'] > $idle || $now - $state['started'] > self::MAX_AGE) {
                self::end($role);
                $reason ??= 'expired';
                continue;
            }
            // Browsing the shop does not keep an administrator's sign-in alive
            if ($role !== 'admin' || str_starts_with($path, '/admin')) {
                $_SESSION["auth_{$role}"]['seen'] = $now;
            }
        }

        return $reason;
    }

    /**
     * Sign out every device of an account.
     * The session making the change stays signed in when $keepCurrent is true.
     */
    public static function revoke(string $role, int $id, bool $keepCurrent = false): void
    {
        [$idKey, $table, $column] = self::ROLES[$role];
        Database::getConnection()
            ->prepare("UPDATE `{$table}` SET session_version = session_version + 1 WHERE {$column} = :id")
            ->execute([':id' => $id]);

        if ($keepCurrent && (int)($_SESSION[$idKey] ?? 0) === $id && isset($_SESSION["auth_{$role}"])) {
            $_SESSION["auth_{$role}"]['version'] = self::version($role, $id) ?? 0;
            self::newSessionId();
        }
    }

    /** The account's current version, or null when it may not be signed in. */
    private static function version(string $role, int $id): ?int
    {
        [, $table, $column] = self::ROLES[$role];
        $active = $role === 'user' ? " AND status = 'active'" : '';
        $stmt = Database::getConnection()->prepare("SELECT session_version FROM `{$table}` WHERE {$column} = :id{$active}");
        $stmt->execute([':id' => $id]);
        $version = $stmt->fetchColumn();

        return $version === false ? null : (int)$version;
    }

    private static function end(string $role): void
    {
        [$idKey, , , $profileKey] = self::ROLES[$role];
        unset($_SESSION[$idKey], $_SESSION[$profileKey], $_SESSION["auth_{$role}"]);
        if ($role === 'user') {
            // Answers that belong to that person leave with them
            SafetyService::clearAnswers();
            unset($_SESSION['quiz_result'], $_SESSION['after_login']);
        }
        self::newSessionId();
    }

    private static function newSessionId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
    }
}
