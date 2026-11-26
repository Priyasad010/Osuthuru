<?php

namespace App\Repository;

use App\Core\Database;
use App\Core\Lang;
use PDO;

class HerbRepo
{
    public static function enrich(array $herb, ?string $locale = null): array
    {
        $locale = $locale ?? Lang::getLocale();
        $isSi = ($locale === 'si');

        $herb['display_name'] = ($isSi && !empty($herb['name_si'])) ? $herb['name_si'] : $herb['name'];
        $herb['display_uses'] = ($isSi && !empty($herb['uses_si'])) ? $herb['uses_si'] : $herb['uses'];

        return $herb;
    }

    public static function findAll(?string $letter = null, ?string $locale = null): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT * FROM `herb`";
        $params = [];

        if (!empty($letter)) {
            $sql .= " WHERE `name` LIKE :letter";
            $params[':letter'] = $letter . '%';
        }

        $sql .= " ORDER BY `name` ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($h) => self::enrich($h, $locale), $rows);
    }

    public static function findById(int $herbId, ?string $locale = null): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `herb` WHERE `herb_id` = :id LIMIT 1");
        $stmt->execute([':id' => $herbId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::enrich($row, $locale) : null;
    }

    /**
     * Deterministically selects a 'Herb of the Day' based on current date.
     */
    public static function getHerbOfTheDay(?string $locale = null): ?array
    {
        $pdo = Database::getConnection();
        $herbs = $pdo->query("SELECT * FROM `herb` ORDER BY `herb_id` ASC")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($herbs)) {
            return null;
        }

        $dayIndex = (int)date('z'); // Day of year (0-365)
        $selected = $herbs[$dayIndex % count($herbs)];

        return self::enrich($selected, $locale);
    }

    public static function getProductsByHerb(int $herbId, ?string $locale = null): array
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
            FROM `product_herb` ph
            JOIN `product` p ON p.product_id = ph.product_id
            LEFT JOIN `category` c ON c.category_id = p.category_id
            WHERE ph.herb_id = :hid
            ORDER BY p.product_name ASC
        ");
        $stmt->execute([':hid' => $herbId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($p) => ProductRepo::enrichWithSalePrice(ProductRepo::enrichLocale($p, $locale)), $rows);
    }

    public static function getHerbsByProduct(int $productId, ?string $locale = null): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT h.*
            FROM `product_herb` ph
            JOIN `herb` h ON h.herb_id = ph.herb_id
            WHERE ph.product_id = :pid
            ORDER BY h.name ASC
        ");
        $stmt->execute([':pid' => $productId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($h) => self::enrich($h, $locale), $rows);
    }

    public static function getConcernsByHerb(int $herbId, ?string $locale = null): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT c.*
            FROM `herb_concern` hc
            JOIN `health_concern` c ON c.concern_id = hc.concern_id
            WHERE hc.herb_id = :hid
            ORDER BY c.name_en ASC
        ");
        $stmt->execute([':hid' => $herbId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $isSi = ($locale ?? Lang::getLocale()) === 'si';
        return array_map(function($c) use ($isSi) {
            $c['display_name'] = ($isSi && !empty($c['name_si'])) ? $c['name_si'] : $c['name_en'];
            return $c;
        }, $rows);
    }

    public static function getAllConcerns(?string $locale = null): array
    {
        $pdo = Database::getConnection();
        $rows = $pdo->query("SELECT * FROM `health_concern` ORDER BY `concern_id` ASC")->fetchAll(PDO::FETCH_ASSOC);

        $isSi = ($locale ?? Lang::getLocale()) === 'si';
        return array_map(function($c) use ($isSi) {
            $c['display_name'] = ($isSi && !empty($c['name_si'])) ? $c['name_si'] : $c['name_en'];
            return $c;
        }, $rows);
    }

    public static function getConcernBySlug(string $slug, ?string $locale = null): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `health_concern` WHERE `slug` = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $isSi = ($locale ?? Lang::getLocale()) === 'si';
        $row['display_name'] = ($isSi && !empty($row['name_si'])) ? $row['name_si'] : $row['name_en'];
        return $row;
    }

    public static function getHerbsByConcern(int $concernId, ?string $locale = null): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT h.*
            FROM `herb_concern` hc
            JOIN `herb` h ON h.herb_id = hc.herb_id
            WHERE hc.concern_id = :cid
            ORDER BY h.name ASC
        ");
        $stmt->execute([':cid' => $concernId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($h) => self::enrich($h, $locale), $rows);
    }

    public static function syncProductHerbs(int $productId, array $herbIds): void
    {
        $pdo = Database::getConnection();
        $del = $pdo->prepare("DELETE FROM `product_herb` WHERE `product_id` = :pid");
        $del->execute([':pid' => $productId]);

        if (!empty($herbIds)) {
            $ins = $pdo->prepare("INSERT IGNORE INTO `product_herb` (`product_id`, `herb_id`) VALUES (:pid, :hid)");
            foreach ($herbIds as $hid) {
                if ((int)$hid > 0) {
                    $ins->execute([':pid' => $productId, ':hid' => (int)$hid]);
                }
            }
        }
    }
}
