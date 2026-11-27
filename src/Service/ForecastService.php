<?php

namespace App\Service;

use App\Core\Database;
use App\Repository\BatchRepo;

/**
 * Sales and stock forecasts from the ML service, with a moving-average fallback.
 * The admin screens that show these arrive in Phase 7.
 */
class ForecastService
{
    private const WINDOW_DAYS = 7;
    private const SALE = "o.status <> 'Cancelled' AND (o.payment_status = 'paid' OR o.status <> 'Pending' OR o.payment_method = 'cod')";

    /** @return array ['method' => string, 'days' => [['date','value','lower','upper'], ...]] */
    public static function sales(int $horizon = 30, ?MlClient $client = null): array
    {
        $horizon = max(1, min(90, $horizon));
        $data = ($client ?? MlClient::fromEnv())->get('/forecast/sales?horizon=' . $horizon);
        if (is_array($data['days'] ?? null) && count($data['days']) === $horizon) {
            return $data;
        }

        $average = self::dailyAverage('SUM(i.subtotal)');
        $today = BatchRepo::today();
        $days = [];
        for ($i = 0; $i < $horizon; $i++) {
            $days[] = [
                'date' => $today->modify("+{$i} days")->format('Y-m-d'),
                'value' => round($average, 2),
                'lower' => 0.0,
                'upper' => round($average * 2, 2),
            ];
        }
        return ['method' => 'php_moving_average', 'days' => $days];
    }

    /** Expected daily demand and days until stock-out, or null for an unknown product. */
    public static function product(int $productId, ?MlClient $client = null): ?array
    {
        $stmt = Database::getConnection()->prepare("SELECT quantity FROM product WHERE product_id = :id");
        $stmt->execute([':id' => $productId]);
        $stock = $stmt->fetchColumn();
        if ($stock === false) {
            return null;
        }

        $data = ($client ?? MlClient::fromEnv())->get('/forecast/product/' . $productId);
        if (isset($data['daily_demand'])) {
            return $data;
        }

        $demand = self::dailyAverage('SUM(i.quantity)', $productId);
        return [
            'method' => 'php_moving_average',
            'product_id' => $productId,
            'daily_demand' => round($demand, 3),
            'stock' => (int)$stock,
            'days_until_stockout' => (int)$stock <= 0 ? 0 : ($demand < 0.01 ? null : (int)floor((int)$stock / $demand)),
        ];
    }

    /** Average per day over the last full week. $total is one of the fixed expressions above. */
    private static function dailyAverage(string $total, ?int $productId = null): float
    {
        $sql = "
            SELECT COALESCE({$total}, 0)
            FROM order_item i
            JOIN orders o ON o.order_id = i.order_id
            WHERE " . self::SALE . "
              AND o.order_date >= CURDATE() - INTERVAL " . self::WINDOW_DAYS . " DAY AND o.order_date < CURDATE()
        ";
        $params = [];
        if ($productId !== null) {
            $sql .= ' AND i.product_id = :id';
            $params[':id'] = $productId;
        }
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn() / self::WINDOW_DAYS;
    }
}
