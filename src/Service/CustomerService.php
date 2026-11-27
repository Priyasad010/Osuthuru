<?php

namespace App\Service;

use App\Core\Database;
use App\Core\FormRules;
use App\Core\SessionGuard;
use PDO;

class CustomerService
{
    public static function list(string $search = '', int $limit = 200, int $offset = 0): array
    {
        [$where, $params] = self::matching($search);
        $sql = "
            SELECT u.user_id, u.name, u.email, u.phone, u.status, u.created_at,
                   COUNT(o.order_id) AS orders,
                   COALESCE(SUM(CASE WHEN " . ReportService::SALE . " THEN o.total_price END), 0) AS spent
            FROM `user` u
            LEFT JOIN orders o ON o.user_id = u.user_id
            {$where}
            GROUP BY u.user_id, u.name, u.email, u.phone, u.status, u.created_at
            ORDER BY u.user_id DESC LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset);

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function countMatching(string $search = ''): int
    {
        [$where, $params] = self::matching($search);
        $stmt = Database::getConnection()->prepare("SELECT COUNT(*) FROM `user` u {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private static function matching(string $search): array
    {
        if (trim($search) === '') {
            return ['', []];
        }
        return ['WHERE u.name LIKE :name OR u.email LIKE :email', [':name' => '%' . trim($search) . '%', ':email' => '%' . trim($search) . '%']];
    }

    public static function find(int $userId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT user_id, name, email, phone, address, status, created_at FROM `user` WHERE user_id = :id'
        );
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Unblocking returns an account that never verified its email to "inactive". */
    public static function setBlocked(int $userId, bool $blocked): bool
    {
        if (!self::find($userId)) {
            return false;
        }
        $sql = $blocked
            ? "UPDATE `user` SET status = 'blocked' WHERE user_id = :id"
            : "UPDATE `user` SET status = IF(verification_token IS NULL, 'active', 'inactive') WHERE user_id = :id AND status = 'blocked'";
        Database::getConnection()->prepare($sql)->execute([':id' => $userId]);
        // A later unblock must not revive the old sessions
        if ($blocked) {
            SessionGuard::revoke('user', $userId);
        }
        return true;
    }

    public static function updateProfile(int $userId, array $data): array
    {
        $field = fn(string $k) => trim(is_string($data[$k] ?? null) ? $data[$k] : '');
        $name = $field('name');
        $phone = $field('phone');

        if (!FormRules::isPersonName($name) || mb_strlen($field('address')) > 1000) {
            return ['ok' => false, 'error' => t('profile_err_invalid')];
        }
        if ($phone !== '' && ($phone = FormRules::phone($phone)) === null) {
            return ['ok' => false, 'error' => t('err_phone_invalid')];
        }
        $stmt = Database::getConnection()->prepare('UPDATE `user` SET name = :name, phone = :phone, address = :address WHERE user_id = :id');
        $stmt->execute([':name' => $name, ':phone' => $phone, ':address' => $field('address'), ':id' => $userId]);

        return ['ok' => true, 'user' => self::find($userId)];
    }

    public static function changePassword(int $userId, string $current, string $new, string $confirm): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT password FROM `user` WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
        $hash = (string)$stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            return ['ok' => false, 'error' => t('profile_err_current')];
        }
        if ($problem = FormRules::passwordProblem($new)) {
            return ['ok' => false, 'error' => $problem];
        }
        if ($new !== $confirm) {
            return ['ok' => false, 'error' => t('err_password_mismatch')];
        }
        $upd = $pdo->prepare('UPDATE `user` SET password = :password WHERE user_id = :id');
        $upd->execute([':password' => password_hash($new, PASSWORD_DEFAULT), ':id' => $userId]);
        // Other devices must sign in again with the new password
        SessionGuard::revoke('user', $userId, true);
        return ['ok' => true];
    }
}
