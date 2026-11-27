<?php

namespace App\Service;

use App\Core\Database;
use App\Core\FormRules;
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
        $open = $pdo->prepare("
            SELECT COUNT(*) FROM orders
            WHERE user_id = :id
              AND (status NOT IN ('Delivered', 'Cancelled') OR (status = 'Cancelled' AND payment_status = 'paid'))
        ");
        $open->execute([':id' => $userId]);
        if ((int)$open->fetchColumn() > 0) {
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
                    status = 'blocked', verification_token = NULL
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
        foreach ($slipFiles as $path) {
            if (str_starts_with((string)$path, '/uploads/slips/') && !str_contains((string)$path, '..')) {
                @unlink(dirname(__DIR__, 2) . '/public' . $path);
            }
        }

        return ['ok' => true];
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

        return ['ok' => true];
    }
}
