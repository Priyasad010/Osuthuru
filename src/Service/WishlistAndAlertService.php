<?php

namespace App\Service;

use App\Core\Database;
use App\Core\Lang;
use App\Repository\ProductRepo;
use App\Core\Mailer;
use PDO;

class WishlistAndAlertService
{
    public static function toggleWishlist(int $userId, int $productId): bool
    {
        $pdo = Database::getConnection();
        $check = $pdo->prepare("SELECT 1 FROM `wishlist` WHERE user_id = :uid AND product_id = :pid");
        $check->execute([':uid' => $userId, ':pid' => $productId]);

        if ($check->fetchColumn()) {
            $del = $pdo->prepare("DELETE FROM `wishlist` WHERE user_id = :uid AND product_id = :pid");
            $del->execute([':uid' => $userId, ':pid' => $productId]);
            return false; // removed
        }

        $ins = $pdo->prepare("INSERT IGNORE INTO `wishlist` (user_id, product_id, created_at) VALUES (:uid, :pid, NOW())");
        $ins->execute([':uid' => $userId, ':pid' => $productId]);
        return true; // added
    }

    public static function getUserWishlistIds(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT product_id FROM `wishlist` WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function getUserWishlistProducts(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT p.*, c.name AS category_name, c.name_si AS category_name_si,
                   (SELECT MAX(d.discount_percentage)
                    FROM discount d
                    WHERE d.product_id = p.product_id
                      AND (d.start_date IS NULL OR d.start_date <= NOW())
                      AND (d.end_date IS NULL OR d.end_date >= NOW())
                   ) AS active_discount_percentage
            FROM wishlist w
            JOIN product p ON p.product_id = w.product_id
            LEFT JOIN category c ON c.category_id = p.category_id
            WHERE w.user_id = :uid
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            fn($p) => \App\Repository\ProductRepo::enrichWithSalePrice(\App\Repository\ProductRepo::enrichLocale($p)),
            $rows
        );
    }

    public static function subscribeStockAlert(int $userId, int $productId): bool
    {
        $pdo = Database::getConnection();
        // Avoid duplicate pending alert for same user + product
        $check = $pdo->prepare("
            SELECT alert_id FROM `stock_alert`
            WHERE user_id = :uid AND product_id = :pid AND notified_at IS NULL
            LIMIT 1
        ");
        $check->execute([':uid' => $userId, ':pid' => $productId]);
        if ($check->fetchColumn()) {
            return true;
        }

        $ins = $pdo->prepare("
            INSERT INTO `stock_alert` (user_id, product_id, created_at, notified_at)
            VALUES (:uid, :pid, NOW(), NULL)
        ");
        return $ins->execute([':uid' => $userId, ':pid' => $productId]);
    }

    /**
     * Update product stock; if stock was 0 and is now > 0, notify waiting users (exactly once per user).
     */
    public static function updateProductStock(int $productId, int $newQuantity, bool $sendEmail = true): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT quantity, product_name FROM `product` WHERE product_id = :pid LIMIT 1");
        $stmt->execute([':pid' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return ['ok' => false, 'error' => 'Product not found.', 'notified_count' => 0];
        }

        $oldQty = (int)$product['quantity'];

        $upd = $pdo->prepare("UPDATE `product` SET quantity = :qty WHERE product_id = :pid");
        $upd->execute([':qty' => $newQuantity, ':pid' => $productId]);

        $notifiedCount = 0;
        if ($oldQty <= 0 && $newQuantity > 0) {
            $notifiedCount = self::notifyWaitingUsers($productId, $sendEmail);
        }

        return ['ok' => true, 'notified_count' => $notifiedCount];
    }

    public static function notifyWaitingUsers(int $productId, bool $sendEmail = true): int
    {
        $pdo = Database::getConnection();

        // Fetch distinct waiting users for this product
        $stmt = $pdo->prepare("
            SELECT sa.user_id, u.email, u.name, u.lang, p.product_name, p.product_name_si
            FROM `stock_alert` sa
            JOIN `user` u ON u.user_id = sa.user_id
            JOIN `product` p ON p.product_id = sa.product_id
            WHERE sa.product_id = :pid AND sa.notified_at IS NULL
            GROUP BY sa.user_id, u.email, u.name, u.lang, p.product_name, p.product_name_si
        ");
        $stmt->execute([':pid' => $productId]);
        $waiters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($waiters)) {
            return 0;
        }

        // Mark all pending alerts for this product as notified
        $mark = $pdo->prepare("
            UPDATE `stock_alert`
            SET notified_at = NOW()
            WHERE product_id = :pid AND notified_at IS NULL
        ");
        $mark->execute([':pid' => $productId]);

        $count = 0;
        foreach ($waiters as $w) {
            if ($sendEmail && !empty($w['email'])) {
                Lang::runIn((string)$w['lang'], function () use ($w, $productId) {
                    $productName = ProductRepo::enrichLocale($w)['display_name'];
                    $name = (string)($w['name'] ?? '');

                    return Mailer::send(
                        $w['email'],
                        $name,
                        t('mail_stock_subject', ['product' => $productName]),
                        Mailer::layout(
                            $name,
                            [t('mail_stock_intro', ['product' => $productName])],
                            t('view_product'),
                            url("products/{$productId}")
                        )
                    );
                });
            }
            $count++;
        }

        return $count;
    }
}
