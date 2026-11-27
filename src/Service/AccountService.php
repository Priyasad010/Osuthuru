<?php

namespace App\Service;

use App\Core\Database;
use App\Core\FormRules;
use App\Core\SessionGuard;
use PDO;
use Throwable;

/** Changes to an account that need the current password. */
class AccountService
{
    /**
     * Remove a customer's account. Orders are business records and are kept, but
     * every personal detail is removed from them and from the account.
     *
     * @return array ['ok' => true] or ['ok' => false, 'error' => string]
     */
    public static function deleteCustomer(int $userId, string $password): array
    {
        $pdo = Database::getConnection();
        $find = $pdo->prepare('SELECT email, password FROM `user` WHERE user_id = :id');
        $find->execute([':id' => $userId]);
        $user = $find->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            return ['ok' => false, 'error' => t('profile_err_current')];
        }
        // Orders still on the way, and money still to be returned, need the details
        if (self::hasOpenOrders($userId)) {
            return ['ok' => false, 'error' => t('account_err_open_orders')];
        }

        $reviews = $pdo->prepare('SELECT review_id FROM review WHERE user_id = :id');
        $reviews->execute([':id' => $userId]);
        $reviewIds = $reviews->fetchAll(PDO::FETCH_COLUMN);
        $slips = $pdo->prepare('SELECT b.file_path FROM bank_slip b JOIN orders o ON o.order_id = b.order_id WHERE o.user_id = :id');
        $slips->execute([':id' => $userId]);
        $slipFiles = $slips->fetchAll(PDO::FETCH_COLUMN);

        try {
            $pdo->beginTransaction();

            foreach (['cart', 'wishlist', 'user_category', 'stock_alert', 'quiz_result', 'chat_session', 'password_resets', 'review_helpful'] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE user_id = :id")->execute([':id' => $userId]);
            }
            $pdo->prepare('DELETE b FROM bank_slip b JOIN orders o ON o.order_id = b.order_id WHERE o.user_id = :id')->execute([':id' => $userId]);
            $pdo->prepare('UPDATE orders SET shipping_name = :name, shipping_phone = NULL, shipping_address = NULL WHERE user_id = :id')
                ->execute([':name' => 'Deleted account', ':id' => $userId]);
            foreach (['login_attempts', 'contact_message'] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE email = :email")->execute([':email' => $user['email']]);
            }
            $pdo->prepare("
                UPDATE `user`
                SET name = 'Deleted account', email = :email, password = :password, phone = NULL, address = NULL,
                    status = 'blocked', verification_token = NULL, session_version = session_version + 1
                WHERE user_id = :id
            ")->execute([
                // .invalid addresses can never receive mail
                ':email' => "deleted-{$userId}@deleted.invalid",
                ':password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                ':id' => $userId,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Account deletion failed for user {$userId}: " . $e->getMessage());
            return ['ok' => false, 'error' => t('account_err_delete')];
        }

        // Reviews and files go last: a file cannot be brought back by a rollback
        foreach ($reviewIds as $reviewId) {
            ReviewService::delete((int)$reviewId);
        }
        self::removeFiles($slipFiles, '/uploads/slips/');

        return ['ok' => true];
    }

    /**
     * Administrator removal: the customer and everything belonging to them, orders
     * included, is erased from the database. Sales reports no longer count those
     * orders, and the e-mail address can register again. This cannot be undone.
     *
     * The administrator confirms with the customer's e-mail address and their own password.
     *
     * @return array ['ok' => true, 'orders' => int] or ['ok' => false, 'error' => string, 'wrong_password'?: true]
     */
    public static function purgeCustomer(int $userId, string $typedEmail, int $adminId, string $adminPassword): array
    {
        $pdo = Database::getConnection();
        $admin = $pdo->prepare('SELECT password FROM admin WHERE admin_id = :id');
        $admin->execute([':id' => $adminId]);
        if (!password_verify($adminPassword, (string)$admin->fetchColumn())) {
            return ['ok' => false, 'error' => t('profile_err_current'), 'wrong_password' => true];
        }

        try {
            $pdo->beginTransaction();

            // Locked so that no order can be placed for this account while it is being removed
            $find = $pdo->prepare('SELECT email FROM `user` WHERE user_id = :id FOR UPDATE');
            $find->execute([':id' => $userId]);
            $email = $find->fetchColumn();

            $problem = null;
            if ($email === false) {
                $problem = 'admin_user_delete_missing';
            } elseif (strcasecmp(trim($typedEmail), (string)$email) !== 0) {
                $problem = 'admin_user_delete_email';
            } elseif (self::hasOpenOrders($userId)) {
                $problem = 'admin_user_delete_open';
            }
            if ($problem !== null) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => t($problem)];
            }

            $column = function (string $sql) use ($pdo, $userId): array {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $userId]);
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            };
            $slipFiles = $column('SELECT b.file_path FROM bank_slip b JOIN orders o ON o.order_id = b.order_id WHERE o.user_id = :id');
            $reviewFiles = $column('SELECT i.file_path FROM review_image i JOIN review r ON r.review_id = i.review_id WHERE r.user_id = :id');
            $orderCount = (int)$column('SELECT COUNT(*) FROM orders WHERE user_id = :id')[0];

            // Coupon uses that disappear with the orders become available again
            $pdo->prepare('
                UPDATE coupon c
                JOIN (SELECT coupon_id, COUNT(*) AS uses FROM coupon_redemption WHERE user_id = :id GROUP BY coupon_id) r
                    ON r.coupon_id = c.coupon_id
                SET c.used_count = GREATEST(CAST(c.used_count AS SIGNED) - r.uses, 0)
            ')->execute([':id' => $userId]);

            // These do not follow the account automatically. Everything else (order lines,
            // slips, history, chats, pictures of reviews) is removed by the foreign keys.
            foreach (['cart', 'wishlist', 'user_category', 'stock_alert', 'quiz_result', 'chat_session', 'password_resets', 'review_helpful', 'review', 'coupon_redemption', 'orders'] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE user_id = :id")->execute([':id' => $userId]);
            }
            foreach (['login_attempts', 'contact_message'] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE email = :email")->execute([':email' => $email]);
            }
            $pdo->prepare('DELETE FROM `user` WHERE user_id = :id')->execute([':id' => $userId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Removal of customer {$userId} failed: " . $e->getMessage());
            return ['ok' => false, 'error' => t('account_err_delete')];
        }

        self::removeFiles($slipFiles, '/uploads/slips/');
        self::removeFiles($reviewFiles, '/uploads/');
        error_log("Administrator {$adminId} removed customer {$userId} and {$orderCount} order(s)");

        return ['ok' => true, 'orders' => $orderCount];
    }

    /** Orders still on the way, and money still to be returned. */
    public static function hasOpenOrders(int $userId): bool
    {
        $open = Database::getConnection()->prepare("
            SELECT COUNT(*) FROM orders
            WHERE user_id = :id
              AND (status NOT IN ('Delivered', 'Cancelled') OR (status = 'Cancelled' AND payment_status = 'paid'))
        ");
        $open->execute([':id' => $userId]);
        return (int)$open->fetchColumn() > 0;
    }

    private static function removeFiles(array $paths, string $folder): void
    {
        foreach ($paths as $path) {
            if (str_starts_with((string)$path, $folder) && !str_contains((string)$path, '..')) {
                @unlink(dirname(__DIR__, 2) . '/public' . $path);
            }
        }
    }

    /** @return array ['ok' => true] or ['ok' => false, 'error' => string] */
    public static function changeAdminPassword(int $adminId, string $current, string $new, string $confirm): array
    {
        $pdo = Database::getConnection();
        $find = $pdo->prepare('SELECT password FROM admin WHERE admin_id = :id');
        $find->execute([':id' => $adminId]);
        $hash = (string)$find->fetchColumn();

        if (!password_verify($current, $hash)) {
            return ['ok' => false, 'error' => t('profile_err_current'), 'wrong_current' => true];
        }
        if ($problem = FormRules::passwordProblem($new)) {
            return ['ok' => false, 'error' => $problem];
        }
        if ($new !== $confirm) {
            return ['ok' => false, 'error' => t('err_password_mismatch')];
        }
        if (password_verify($new, $hash)) {
            return ['ok' => false, 'error' => t('err_password_same')];
        }
        $pdo->prepare('UPDATE admin SET password = :password WHERE admin_id = :id')
            ->execute([':password' => password_hash($new, PASSWORD_DEFAULT), ':id' => $adminId]);
        SessionGuard::revoke('admin', $adminId, true);

        return ['ok' => true];
    }
}
