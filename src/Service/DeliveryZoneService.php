<?php

namespace App\Service;

use App\Core\Database;
use PDO;

class DeliveryZoneService
{
    public static function getAllZones(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM `delivery_zone` ORDER BY `district` ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findByDistrict(string $district): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `delivery_zone` WHERE LOWER(district) = :district LIMIT 1");
        $stmt->execute([':district' => strtolower(trim($district))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Calculate delivery fee for a given district and order subtotal (after discounts).
     * Returns ['ok' => bool, 'fee' => float, 'free_over' => float, 'is_free' => bool, 'error' => ?string]
     */
    public static function calculateFee(string $district, float $subtotal): array
    {
        $zone = self::findByDistrict($district);
        if (!$zone) {
            return [
                'ok' => false,
                'fee' => 0.0,
                'free_over' => 0.0,
                'is_free' => false,
                'error' => t('err_district', ['district' => $district])
            ];
        }

        $fee = (float)$zone['fee'];
        $freeOver = (float)$zone['free_over'];

        if ($freeOver > 0 && $subtotal >= $freeOver) {
            return [
                'ok' => true,
                'district' => $zone['district'],
                'fee' => 0.0,
                'base_fee' => $fee,
                'free_over' => $freeOver,
                'is_free' => true
            ];
        }

        return [
            'ok' => true,
            'district' => $zone['district'],
            'fee' => $fee,
            'base_fee' => $fee,
            'free_over' => $freeOver,
            'is_free' => false
        ];
    }

    public static function upsertZone(string $district, float $fee, float $freeOver): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO `delivery_zone` (`district`, `fee`, `free_over`)
            VALUES (:district, :fee, :free_over)
            ON DUPLICATE KEY UPDATE `fee` = :fee_upd, `free_over` = :free_over_upd
        ");
        return $stmt->execute([
            ':district' => trim($district),
            ':fee' => $fee,
            ':free_over' => $freeOver,
            ':fee_upd' => $fee,
            ':free_over_upd' => $freeOver,
        ]);
    }

    public static function deleteZone(int $zoneId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM `delivery_zone` WHERE zone_id = :id");
        return $stmt->execute([':id' => $zoneId]);
    }
}
