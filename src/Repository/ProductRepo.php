<?php

namespace App\Repository;

use App\Core\Database;
use App\Core\Lang;
use PDO;

class ProductRepo
{
    public static function enrichLocale(array $p, ?string $locale = null): array
    {
        $locale = $locale ?? Lang::getLocale();
        $isSi = ($locale === 'si');

        $p['display_name'] = ($isSi && !empty($p['product_name_si'])) ? $p['product_name_si'] : $p['product_name'];
        $p['display_description'] = ($isSi && !empty($p['description_si'])) ? $p['description_si'] : ($p['description'] ?? '');

        if (isset($p['category_name'])) {
            $p['display_category_name'] = ($isSi && !empty($p['category_name_si'])) ? $p['category_name_si'] : $p['category_name'];
        }

        return $p;
    }

    public static function findAll(?int $categoryId = null, ?string $locale = null): array
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT p.*, c.name AS category_name, c.name_si AS category_name_si,
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

        return array_map(function($p) use ($locale) {
            return self::enrichWithSalePrice(self::enrichLocale($p, $locale));
        }, $rows);
    }

    public static function findById(int $productId, ?string $locale = null): ?array
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
            FROM product p
            LEFT JOIN category c ON c.category_id = p.category_id
            WHERE p.product_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row = self::enrichWithSalePrice(self::enrichLocale($row, $locale));
        $row['herbs'] = HerbRepo::getHerbsByProduct($productId, $locale);
        return $row;
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

    /** @param bool $onlyStocked leave out categories that have no products (for shoppers) */
    public static function getAllCategories(?string $locale = null, bool $onlyStocked = false): array
    {
        $pdo = Database::getConnection();
        $where = $onlyStocked ? 'WHERE EXISTS (SELECT 1 FROM product p WHERE p.category_id = c.category_id)' : '';
        $rows = $pdo->query("SELECT c.* FROM category c {$where} ORDER BY c.name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $isSi = ($locale ?? Lang::getLocale()) === 'si';

        return array_map(function($c) use ($isSi) {
            $c['display_name'] = ($isSi && !empty($c['name_si'])) ? $c['name_si'] : $c['name'];
            return $c;
        }, $rows);
    }

    public static function updateDetails(int $productId, array $data): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE product
            SET product_name_si = :name_si,
                description_si = :desc_si
            WHERE product_id = :id
        ");
        $stmt->execute([
            ':name_si' => !empty($data['product_name_si']) ? trim((string)$data['product_name_si']) : null,
            ':desc_si' => !empty($data['description_si']) ? trim((string)$data['description_si']) : null,
            ':id' => $productId,
        ]);

        if (array_key_exists('description', $data) && $data['description'] !== null) {
            $desc = $pdo->prepare("UPDATE product SET description = :description WHERE product_id = :id");
            $desc->execute([':description' => trim((string)$data['description']), ':id' => $productId]);
        }

        if (isset($data['herb_ids']) && is_array($data['herb_ids'])) {
            HerbRepo::syncProductHerbs($productId, $data['herb_ids']);
        }

        return true;
    }
}
