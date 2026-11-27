<?php

namespace App\Repository;

use App\Core\Database;
use PDO;

/**
 * Simple SQL recommendations (the fallback) and customers' chosen categories.
 */
class RecommendationRepo
{
    /** Same meaning of "a sale" as the ML service. */
    private const SALE = "o.status <> 'Cancelled' AND (o.payment_status = 'paid' OR o.status <> 'Pending' OR o.payment_method = 'cod')";

    /** Product cards in the given order; missing and sold-out products are skipped. */
    public static function cards(array $productIds, int $limit, ?string $locale = null): array
    {
        $cards = [];
        foreach (array_unique(array_map('intval', $productIds)) as $id) {
            $product = ProductRepo::findById($id, $locale);
            if ($product && (int)$product['quantity'] > 0) {
                $cards[] = $product;
            }
            if (count($cards) >= $limit) {
                break;
            }
        }
        return $cards;
    }

    /**
     * In-stock product ids: the given categories first, then by units sold.
     */
    public static function popular(array $categoryIds, int $limit, array $exclude = []): array
    {
        $marks = fn(array $ids) => implode(',', array_fill(0, count($ids), '?'));

        $without = '';
        $exclude = array_values(array_map('intval', $exclude));
        if ($exclude !== []) {
            $without = ' AND p.product_id NOT IN (' . $marks($exclude) . ')';
        }
        // A constant here would be read as a column position, so the term is left out when unused
        $preferred = '';
        $categoryIds = array_values(array_map('intval', $categoryIds));
        if ($categoryIds !== []) {
            $preferred = '(p.category_id IN (' . $marks($categoryIds) . ')) DESC,';
        }

        $stmt = Database::getConnection()->prepare("
            SELECT p.product_id
            FROM product p
            LEFT JOIN (
                SELECT i.product_id, SUM(i.quantity) AS units
                FROM order_item i
                JOIN orders o ON o.order_id = i.order_id
                WHERE " . self::SALE . "
                GROUP BY i.product_id
            ) s ON s.product_id = p.product_id
            WHERE p.quantity > 0{$without}
            ORDER BY {$preferred} COALESCE(s.units, 0) DESC, p.product_id ASC
            LIMIT " . max(1, $limit));

        // Same order as the placeholders: exclusions (WHERE), then categories (ORDER BY)
        $stmt->execute(array_merge($exclude, $categoryIds));
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Products most often ordered together with this one. */
    public static function boughtWith(int $productId, int $limit): array
    {
        $stmt = Database::getConnection()->prepare("
            SELECT other.product_id
            FROM order_item mine
            JOIN orders o ON o.order_id = mine.order_id
            JOIN order_item other ON other.order_id = mine.order_id AND other.product_id <> mine.product_id
            JOIN product p ON p.product_id = other.product_id AND p.quantity > 0
            WHERE mine.product_id = :id AND " . self::SALE . "
            GROUP BY other.product_id
            ORDER BY COUNT(DISTINCT other.order_id) DESC, other.product_id ASC
            LIMIT " . max(1, $limit));
        $stmt->execute([':id' => $productId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function userCategories(int $userId): array
    {
        $stmt = Database::getConnection()->prepare("SELECT category_id FROM user_category WHERE user_id = :user");
        $stmt->execute([':user' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Replace a customer's chosen categories; unknown ids are ignored. */
    public static function saveUserCategories(int $userId, array $categoryIds): void
    {
        $valid = array_map('intval', array_column(ProductRepo::getAllCategories(), 'category_id'));
        $chosen = [];
        foreach ($categoryIds as $id) {
            if (is_scalar($id) && in_array((int)$id, $valid, true)) {
                $chosen[(int)$id] = (int)$id;
            }
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        $delete = $pdo->prepare("DELETE FROM user_category WHERE user_id = :user");
        $delete->execute([':user' => $userId]);
        $insert = $pdo->prepare("INSERT INTO user_category (user_id, category_id) VALUES (:user, :category)");
        foreach ($chosen as $id) {
            $insert->execute([':user' => $userId, ':category' => $id]);
        }
        $pdo->commit();
    }
}
