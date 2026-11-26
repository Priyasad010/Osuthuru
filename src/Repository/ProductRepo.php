<?php

namespace App\Repository;

use App\Core\Database;
use PDO;

class ProductRepo
{
    public static function findAll(?int $categoryId = null): array
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT p.*, c.name AS category_name,
                   (SELECT MAX(d.discount_percentage)
                    FROM discount d
                    WHERE d.product_id = p.product_id
                      AND (d.start_date IS NULL OR d.start_date <= NOW())
                      AND (d.end_date IS NULL OR d.end_date >= NOW())
                   ) AS active_discount_percentage
            FROM product p
            LEFT JOIN category c ON c.category_id = p.category_id
        ";

        $params = [];
        if ($categoryId !== null) {
            $sql .= " WHERE p.category_id = :cat_id";
            $params[':cat_id'] = $categoryId;
        }
        $sql .= " ORDER BY p.product_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([self::class, 'enrichWithSalePrice'], $rows);
    }

    public static function findById(int $productId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT p.*, c.name AS category_name,
                   (SELECT MAX(d.discount_percentage)
                    FROM discount d
                    WHERE d.product_id = p.product_id
                      AND (d.start_date IS NULL OR d.start_date <= NOW())
                      AND (d.end_date IS NULL OR d.end_date >= NOW())
                   ) AS active_discount_percentage
            FROM product p
            LEFT JOIN category c ON c.category_id = p.category_id
            WHERE p.product_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::enrichWithSalePrice($row) : null;
    }

    public static function enrichWithSalePrice(array $product): array
    {
        $basePrice = (float)$product['price'];
        $discountPct = (float)($product['active_discount_percentage'] ?? 0);

        if ($discountPct > 0 && $discountPct <= 100) {
            $salePrice = round($basePrice * (1 - ($discountPct / 100)), 2);
            $product['base_price'] = $basePrice;
            $product['sale_price'] = $salePrice;
            $product['effective_price'] = $salePrice;
            $product['discount_percentage'] = $discountPct;
            $product['has_discount'] = true;
        } else {
            $product['base_price'] = $basePrice;
            $product['sale_price'] = $basePrice;
            $product['effective_price'] = $basePrice;
            $product['discount_percentage'] = 0.0;
            $product['has_discount'] = false;
        }

        return $product;
    }

    public static function getAllCategories(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM category ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
}
