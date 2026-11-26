<?php

namespace App\Service;

use App\Core\Database;
use PDO;

class CouponService
{
    public static function validateAndCalculate(string $code, float $subtotal, ?int $userId = null): array
    {
        $cleanCode = strtoupper(trim($code));
        if ($cleanCode === '') {
            return ['ok' => false, 'error' => t('err_coupon_enter')];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `coupon` WHERE UPPER(code) = :code LIMIT 1");
        $stmt->execute([':code' => $cleanCode]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            return ['ok' => false, 'error' => t('err_coupon_missing')];
        }

        if ((int)$coupon['active'] !== 1) {
            return ['ok' => false, 'error' => t('err_coupon_inactive')];
        }

        $now = time();
        if (strtotime($coupon['starts_at']) > $now) {
            return ['ok' => false, 'error' => t('err_coupon_early')];
        }

        if (strtotime($coupon['ends_at']) < $now) {
            return ['ok' => false, 'error' => t('err_coupon_expired')];
        }

        if ((int)$coupon['used_count'] >= (int)$coupon['max_uses']) {
            return ['ok' => false, 'error' => t('err_coupon_max')];
        }

        $minOrder = (float)$coupon['min_order'];
        if ($subtotal < $minOrder) {
            return [
                'ok' => false,
                'error' => t('err_coupon_min', ['amount' => format_price($minOrder)])
            ];
        }

        // Check if user already redeemed this coupon (optional one per user or check order)
        if ($userId !== null) {
            $usedStmt = $pdo->prepare("SELECT COUNT(*) FROM `coupon_redemption` WHERE coupon_id = :cid AND user_id = :uid");
            $usedStmt->execute([':cid' => $coupon['coupon_id'], ':uid' => $userId]);
            if ((int)$usedStmt->fetchColumn() > 0) {
                return ['ok' => false, 'error' => t('err_coupon_used')];
            }
        }

        $discountAmount = 0.0;
        if ($coupon['type'] === 'percent') {
            $discountAmount = round($subtotal * ((float)$coupon['value'] / 100), 2);
        } else {
            $discountAmount = min($subtotal, (float)$coupon['value']);
        }

        return [
            'ok' => true,
            'coupon_id' => (int)$coupon['coupon_id'],
            'code' => $coupon['code'],
            'type' => $coupon['type'],
            'value' => (float)$coupon['value'],
            'discount_amount' => $discountAmount
        ];
    }

    public static function redeem(int $couponId, int $userId, int $orderId): bool
    {
        $pdo = Database::getConnection();

        $upd = $pdo->prepare("
            UPDATE `coupon`
            SET used_count = used_count + 1
            WHERE coupon_id = :cid AND used_count < max_uses
        ");
        $upd->execute([':cid' => $couponId]);

        if ($upd->rowCount() === 0) {
            return false;
        }

        $ins = $pdo->prepare("
            INSERT INTO `coupon_redemption` (coupon_id, user_id, order_id, redeemed_at)
            VALUES (:cid, :uid, :oid, NOW())
        ");
        return $ins->execute([
            ':cid' => $couponId,
            ':uid' => $userId,
            ':oid' => $orderId
        ]);
    }

    public static function getAll(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM `coupon` ORDER BY coupon_id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO `coupon` (code, type, value, min_order, max_uses, used_count, starts_at, ends_at, active)
            VALUES (:code, :type, :value, :min_order, :max_uses, 0, :starts_at, :ends_at, :active)
        ");
        $stmt->execute([
            ':code' => strtoupper(trim($data['code'])),
            ':type' => $data['type'],
            ':value' => (float)$data['value'],
            ':min_order' => (float)($data['min_order'] ?? 0),
            ':max_uses' => (int)($data['max_uses'] ?? 100),
            ':starts_at' => $data['starts_at'],
            ':ends_at' => $data['ends_at'],
            ':active' => isset($data['active']) ? (int)$data['active'] : 1
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function toggleActive(int $couponId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE `coupon` SET active = 1 - active WHERE coupon_id = :id");
        return $stmt->execute([':id' => $couponId]);
    }

    public static function delete(int $couponId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM `coupon` WHERE coupon_id = :id");
        return $stmt->execute([':id' => $couponId]);
    }
}
