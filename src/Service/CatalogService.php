<?php

namespace App\Service;

use App\Core\Database;
use App\Repository\HerbRepo;
use DateTimeImmutable;
use PDO;

/**
 * Admin management of categories, products, discounts and herbs.
 * Methods return ['ok' => true, ...] or ['ok' => false, 'error' => string].
 */
class CatalogService
{
    private static function fail(string $key): array
    {
        return ['ok' => false, 'error' => t($key)];
    }

    private static function value(string $sql, array $params): mixed
    {
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private static function run(string $sql, array $params): void
    {
        Database::getConnection()->prepare($sql)->execute($params);
    }

    private static function text(array $data, string $key, int $max = 65000): string
    {
        return mb_substr(trim(is_scalar($data[$key] ?? null) ? (string)$data[$key] : ''), 0, $max);
    }

    // ------------------------------------------------------------ products

    /** Create (id 0) or update. 'image' is only changed when given. */
    public static function saveProduct(int $id, array $data): array
    {
        $name = self::text($data, 'product_name', 255);
        $category = (int)($data['category_id'] ?? 0);
        $price = is_numeric($data['price'] ?? null) ? round((float)$data['price'], 2) : 0.0;

        $quantity = $data['quantity'] ?? '0';
        if ($name === '' || $price <= 0 || $price > 10000000 || (int)$quantity < 0 || (int)$quantity > 100000
            || !self::value('SELECT 1 FROM category WHERE category_id = :id', [':id' => $category])) {
            return self::fail('catalog_err_product');
        }
        if ($id > 0 && !self::value('SELECT 1 FROM product WHERE product_id = :id', [':id' => $id])) {
            return self::fail('err_product_404');
        }

        $values = [
            ':name' => $name,
            ':name_si' => self::text($data, 'product_name_si', 255) ?: null,
            ':category' => $category,
            ':price' => $price,
            ':description' => self::text($data, 'description'),
            ':description_si' => self::text($data, 'description_si') ?: null,
        ];

        if ($id > 0) {
            self::run('UPDATE product SET product_name = :name, product_name_si = :name_si, category_id = :category,
                price = :price, description = :description, description_si = :description_si WHERE product_id = :id', $values + [':id' => $id]);
        } else {
            self::run('INSERT INTO product (product_name, product_name_si, category_id, price, quantity, description, description_si)
                VALUES (:name, :name_si, :category, :price, :quantity, :description, :description_si)',
                $values + [':quantity' => max(0, (int)($data['quantity'] ?? 0))]);
            $id = (int)Database::getConnection()->lastInsertId();
        }

        if (!empty($data['image'])) {
            self::run('UPDATE product SET image = :image WHERE product_id = :id', [':image' => $data['image'], ':id' => $id]);
        }
        $herbs = is_array($data['herb_ids'] ?? null) ? array_map('intval', $data['herb_ids']) : [];
        HerbRepo::syncProductHerbs($id, array_filter($herbs, fn($h) => (bool)self::value('SELECT 1 FROM herb WHERE herb_id = :id', [':id' => $h])));

        return ['ok' => true, 'id' => $id];
    }

    /** A product that was ever ordered is kept, so that order history stays complete. */
    public static function deleteProduct(int $id): array
    {
        if (self::value('SELECT 1 FROM order_item WHERE product_id = :id LIMIT 1', [':id' => $id])) {
            return self::fail('catalog_err_ordered');
        }
        foreach (['cart', 'discount', 'review'] as $table) {
            self::run("DELETE FROM {$table} WHERE product_id = :id", [':id' => $id]);
        }
        self::run('DELETE FROM product WHERE product_id = :id', [':id' => $id]);
        return ['ok' => true];
    }

    // ------------------------------------------------------------ categories

    public static function saveCategory(int $id, array $data): array
    {
        $name = self::text($data, 'name', 255);
        if ($name === '' || self::value('SELECT 1 FROM category WHERE LOWER(name) = LOWER(:name) AND category_id <> :id', [':name' => $name, ':id' => $id])) {
            return self::fail('catalog_err_category');
        }
        $values = [':name' => $name, ':name_si' => self::text($data, 'name_si', 255) ?: null];
        if ($id > 0) {
            self::run('UPDATE category SET name = :name, name_si = :name_si WHERE category_id = :id', $values + [':id' => $id]);
        } else {
            self::run('INSERT INTO category (name, name_si) VALUES (:name, :name_si)', $values);
            $id = (int)Database::getConnection()->lastInsertId();
        }
        return ['ok' => true, 'id' => $id];
    }

    public static function deleteCategory(int $id): array
    {
        if (self::value('SELECT 1 FROM product WHERE category_id = :id LIMIT 1', [':id' => $id])) {
            return self::fail('catalog_err_category_used');
        }
        self::run('DELETE FROM user_category WHERE category_id = :id', [':id' => $id]);
        self::run('DELETE FROM category WHERE category_id = :id', [':id' => $id]);
        return ['ok' => true];
    }

    // ------------------------------------------------------------ discounts

    public static function discounts(): array
    {
        return Database::getConnection()->query('
            SELECT d.*, p.product_name FROM discount d
            JOIN product p ON p.product_id = d.product_id
            ORDER BY d.end_date DESC, d.discount_id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Dates come from "datetime-local" fields in Sri Lanka time. */
    public static function saveDiscount(int $id, array $data): array
    {
        $product = (int)($data['product_id'] ?? 0);
        $percent = is_numeric($data['discount_percentage'] ?? null) ? round((float)$data['discount_percentage'], 2) : 0.0;
        $parse = function (mixed $v): ?string {
            $date = is_string($v) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $v) : false;
            return $date && $date->format('Y-m-d\TH:i') === $v ? $date->format('Y-m-d H:i:s') : null;
        };
        $start = $parse($data['start_date'] ?? null);
        $end = $parse($data['end_date'] ?? null);

        if ($percent <= 0 || $percent > 90 || !$start || !$end || $end <= $start
            || !self::value('SELECT 1 FROM product WHERE product_id = :id', [':id' => $product])) {
            return self::fail('catalog_err_discount');
        }

        $values = [':product' => $product, ':percent' => $percent, ':start' => $start, ':end' => $end];
        if ($id > 0) {
            self::run('UPDATE discount SET product_id = :product, discount_percentage = :percent, start_date = :start, end_date = :end WHERE discount_id = :id', $values + [':id' => $id]);
        } else {
            self::run('INSERT INTO discount (product_id, discount_percentage, start_date, end_date) VALUES (:product, :percent, :start, :end)', $values);
        }
        return ['ok' => true];
    }

    public static function deleteDiscount(int $id): array
    {
        self::run('DELETE FROM discount WHERE discount_id = :id', [':id' => $id]);
        return ['ok' => true];
    }

    // ------------------------------------------------------------ herbs

    public static function saveHerb(int $id, array $data): array
    {
        $name = self::text($data, 'name', 100);
        $scientific = self::text($data, 'scientific_name', 150);
        if ($name === '' || $scientific === '') {
            return self::fail('catalog_err_herb');
        }
        if ($id > 0 && !self::value('SELECT 1 FROM herb WHERE herb_id = :id', [':id' => $id])) {
            return self::fail('err_herb_404');
        }

        $values = [
            ':name' => $name,
            ':name_si' => self::text($data, 'name_si', 150) ?: null,
            ':scientific' => $scientific,
            ':uses' => self::text($data, 'uses') ?: null,
            ':uses_si' => self::text($data, 'uses_si') ?: null,
        ];
        if ($id > 0) {
            self::run('UPDATE herb SET name = :name, name_si = :name_si, scientific_name = :scientific, uses = :uses, uses_si = :uses_si WHERE herb_id = :id', $values + [':id' => $id]);
        } else {
            self::run('INSERT INTO herb (name, name_si, scientific_name, uses, uses_si) VALUES (:name, :name_si, :scientific, :uses, :uses_si)', $values);
            $id = (int)Database::getConnection()->lastInsertId();
        }
        if (!empty($data['image'])) {
            self::run('UPDATE herb SET image = :image WHERE herb_id = :id', [':image' => $data['image'], ':id' => $id]);
        }

        self::run('DELETE FROM herb_concern WHERE herb_id = :id', [':id' => $id]);
        foreach (is_array($data['concern_ids'] ?? null) ? $data['concern_ids'] : [] as $concern) {
            self::run('INSERT IGNORE INTO herb_concern (herb_id, concern_id)
                SELECT :herb, concern_id FROM health_concern WHERE concern_id = :concern', [':herb' => $id, ':concern' => (int)$concern]);
        }
        return ['ok' => true, 'id' => $id];
    }

    public static function deleteHerb(int $id): array
    {
        // Links to products, topics and safety entries are removed with the herb
        self::run('DELETE FROM herb WHERE herb_id = :id', [':id' => $id]);
        return ['ok' => true];
    }
}
