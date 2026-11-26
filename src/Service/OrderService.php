<?php

namespace App\Service;

use App\Core\Database;
use App\Core\Mailer;
use PDO;
use Throwable;

class OrderService
{
    public const STATUSES = [
        'Pending',
        'Confirmed',
        'Packed',
        'Out for Delivery',
        'Delivered',
        'Cancelled'
    ];

    public static function recordHistory(int $orderId, string $status, ?string $note = null, ?int $adminId = null): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO `order_status_history` (order_id, status, note, changed_by_admin_id, created_at)
            VALUES (:order_id, :status, :note, :admin_id, NOW())
        ");
        $stmt->execute([
            ':order_id' => $orderId,
            ':status' => $status,
            ':note' => $note,
            ':admin_id' => $adminId
        ]);
    }

    /**
     * Update order status, add one history row, handle COD Delivered -> paid, and send one email.
     */
    public static function updateStatus(int $orderId, string $newStatus, ?string $note = null, ?int $adminId = null, bool $sendEmail = true): array
    {
        if (!in_array($newStatus, self::STATUSES, true)) {
            return ['ok' => false, 'error' => 'Invalid order status.'];
        }

        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT o.*, u.email AS user_email, u.name AS user_name
                FROM `orders` o
                JOIN `user` u ON u.user_id = o.user_id
                WHERE o.order_id = :order_id
                FOR UPDATE
            ");
            $stmt->execute([':order_id' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new \RuntimeException("Order #{$orderId} not found.");
            }

            if ($order['status'] === $newStatus) {
                $pdo->commit();
                return ['ok' => true, 'unchanged' => true, 'message' => 'Status unchanged.'];
            }

            // For COD orders: marking as Delivered sets payment_status = 'paid'
            $paymentStatus = $order['payment_status'];
            if ($order['payment_method'] === 'cod' && $newStatus === 'Delivered') {
                $paymentStatus = 'paid';
            }

            $upd = $pdo->prepare("
                UPDATE `orders`
                SET status = :status, payment_status = :payment_status
                WHERE order_id = :order_id
            ");
            $upd->execute([
                ':status' => $newStatus,
                ':payment_status' => $paymentStatus,
                ':order_id' => $orderId
            ]);

            // Record history row
            self::recordHistory($orderId, $newStatus, $note, $adminId);

            $pdo->commit();

            // Send one email per status change
            if ($sendEmail && !empty($order['user_email'])) {
                self::sendStatusEmail($order['user_email'], $order['user_name'] ?? 'Customer', $orderId, $newStatus, $note);
            }

            return ['ok' => true, 'status' => $newStatus, 'payment_status' => $paymentStatus];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Customer can cancel while Pending. Restores stock if COD.
     */
    public static function cancelByCustomer(int $orderId, int $userId, string $reason = 'Cancelled by customer'): array
    {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT o.*, u.email AS user_email, u.name AS user_name
                FROM `orders` o
                JOIN `user` u ON u.user_id = o.user_id
                WHERE o.order_id = :order_id AND o.user_id = :user_id
                FOR UPDATE
            ");
            $stmt->execute([':order_id' => $orderId, ':user_id' => $userId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new \RuntimeException("Order not found.");
            }

            if ($order['status'] !== 'Pending') {
                throw new \RuntimeException("Only Pending orders can be cancelled.");
            }

            // If COD (or already paid), restore product stock
            if ($order['payment_method'] === 'cod' || $order['payment_status'] === 'paid') {
                $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM `order_item` WHERE order_id = :oid");
                $itemsStmt->execute([':oid' => $orderId]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                $restock = $pdo->prepare("UPDATE `product` SET quantity = quantity + :qty WHERE product_id = :pid");
                foreach ($items as $item) {
                    $restock->execute([':qty' => (int)$item['quantity'], ':pid' => (int)$item['product_id']]);
                }
            }

            $upd = $pdo->prepare("UPDATE `orders` SET status = 'Cancelled' WHERE order_id = :oid");
            $upd->execute([':oid' => $orderId]);

            self::recordHistory($orderId, 'Cancelled', $reason, null);

            $pdo->commit();

            if (!empty($order['user_email'])) {
                self::sendStatusEmail($order['user_email'], $order['user_name'] ?? 'Customer', $orderId, 'Cancelled', $reason);
            }

            return ['ok' => true, 'message' => "Order #{$orderId} has been cancelled."];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function getTimeline(int $orderId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT h.*, a.name AS admin_name
            FROM `order_status_history` h
            LEFT JOIN `admin` a ON a.admin_id = h.changed_by_admin_id
            WHERE h.order_id = :oid
            ORDER BY h.created_at ASC, h.id ASC
        ");
        $stmt->execute([':oid' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getOrderDetails(int $orderId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT o.*, u.name AS user_name, u.email AS user_email, c.code AS coupon_code
            FROM `orders` o
            JOIN `user` u ON u.user_id = o.user_id
            LEFT JOIN `coupon` c ON c.coupon_id = o.coupon_id
            WHERE o.order_id = :oid
            LIMIT 1
        ");
        $stmt->execute([':oid' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        $itemsStmt = $pdo->prepare("
            SELECT oi.*, p.product_name, p.image
            FROM `order_item` oi
            JOIN `product` p ON p.product_id = oi.product_id
            WHERE oi.order_id = :oid
        ");
        $itemsStmt->execute([':oid' => $orderId]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        $order['timeline'] = self::getTimeline($orderId);

        $slipStmt = $pdo->prepare("SELECT * FROM `bank_slip` WHERE order_id = :oid ORDER BY slip_id DESC LIMIT 1");
        $slipStmt->execute([':oid' => $orderId]);
        $order['bank_slip'] = $slipStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $order;
    }

    public static function getUserOrders(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE user_id = :uid ORDER BY order_date DESC, order_id DESC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function sendStatusEmail(string $email, string $name, int $orderId, string $status, ?string $note = null): bool
    {
        $orderUrl = url("orders/{$orderId}");
        $subject = "Order #{$orderId} Status Update: {$status}";
        $noteHtml = $note ? "<p><strong>Note:</strong> " . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . "</p>" : "";

        $body = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='background-color: #198754; color: white; padding: 15px 20px;'>
                    <h2 style='margin: 0;'>Suwoda HerbHub - Order Update</h2>
                </div>
                <div style='padding: 20px; border: 1px solid #e0e0e0;'>
                    <p>Hello " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ",</p>
                    <p>Your order <strong>#{$orderId}</strong> status has been updated to: <strong style='color: #198754;'>{$status}</strong></p>
                    {$noteHtml}
                    <p style='margin-top: 20px;'>
                        <a href='{$orderUrl}' style='background-color: #198754; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Order Timeline</a>
                    </p>
                </div>
            </div>
        ";

        return Mailer::send($email, $name, $subject, $body);
    }
}
