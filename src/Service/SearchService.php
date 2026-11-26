<?php

namespace App\Service;

use App\Core\Database;
use App\Core\Lang;
use App\Repository\HerbRepo;
use App\Repository\ProductRepo;
use PDO;

class SearchService
{
    private static array $knownTerms = [
        'turmeric', 'neem', 'kohomba', 'gotukola', 'amla', 'nelli', 'aralu', 'ashwagandha',
        'kaha', 'kottamalli', 'coriander', 'bovitiya', 'cinnamon', 'kurundu', 'aloe vera',
        'komarika', 'pinda', 'mahanarayana', 'syrup', 'shampoo', 'soap', 'oil', 'tea'
    ];

    public static function search(string $query, ?string $locale = null): array
    {
        $rawQuery = trim($query);
        if ($rawQuery === '') {
            return [
                'query' => '',
                'herbs' => [],
                'products' => [],
                'concern_shortcut' => null,
                'did_you_mean' => null,
                'total_count' => 0
            ];
        }

        $locale = $locale ?? Lang::getLocale();
        $pdo = Database::getConnection();

        // 1. Check for Health Concern Shortcut
        $concernShortcut = self::findConcernShortcut($rawQuery, $locale);

        // 2. Search Herbs
        $herbs = self::searchHerbs($rawQuery, $locale);

        // 3. Search Products (and include products linked to matched herbs)
        $matchedHerbIds = array_column($herbs, 'herb_id');
        $products = self::searchProducts($rawQuery, $matchedHerbIds, $locale);

        $totalCount = count($herbs) + count($products);
        $didYouMean = null;

        // 4. "Did You Mean...?" spell check when no results found
        if ($totalCount === 0) {
            $didYouMean = self::computeDidYouMean($rawQuery);
        }

        return [
            'query' => $rawQuery,
            'herbs' => $herbs,
            'products' => $products,
            'concern_shortcut' => $concernShortcut,
            'did_you_mean' => $didYouMean,
            'total_count' => $totalCount
        ];
    }

    public static function suggest(string $query, ?string $locale = null): array
    {
        $rawQuery = trim($query);
        if (mb_strlen($rawQuery) < 2) {
            return ['ok' => true, 'data' => ['herbs' => [], 'products' => [], 'concerns' => []]];
        }

        $locale = $locale ?? Lang::getLocale();
        $pdo = Database::getConnection();
        $like = '%' . $rawQuery . '%';

        // Fast suggest for Herbs (Limit 5)
        $hStmt = $pdo->prepare("
            SELECT herb_id, name, name_si, scientific_name
            FROM `herb`
            WHERE name LIKE :q1 OR name_si LIKE :q2 OR scientific_name LIKE :q3
            LIMIT 5
        ");
        $hStmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
        $herbs = array_map(fn($h) => [
            'id' => (int)$h['herb_id'],
            'title' => ($locale === 'si' && !empty($h['name_si'])) ? $h['name_si'] : $h['name'],
            'subtitle' => $h['scientific_name'],
            'url' => '/herb/' . $h['herb_id']
        ], $hStmt->fetchAll(PDO::FETCH_ASSOC));

        // Fast suggest for Products (Limit 5)
        $pStmt = $pdo->prepare("
            SELECT product_id, product_name, product_name_si, price
            FROM `product`
            WHERE product_name LIKE :q1 OR product_name_si LIKE :q2
            LIMIT 5
        ");
        $pStmt->execute([':q1' => $like, ':q2' => $like]);
        $products = array_map(fn($p) => [
            'id' => (int)$p['product_id'],
            'title' => ($locale === 'si' && !empty($p['product_name_si'])) ? $p['product_name_si'] : $p['product_name'],
            'subtitle' => 'Rs. ' . number_format((float)$p['price'], 2),
            'url' => '/products/' . $p['product_id']
        ], $pStmt->fetchAll(PDO::FETCH_ASSOC));

        // Fast suggest for Concerns (Limit 3)
        $cStmt = $pdo->prepare("
            SELECT slug, name_en, name_si
            FROM `health_concern`
            WHERE name_en LIKE :q1 OR name_si LIKE :q2 OR slug LIKE :q3
            LIMIT 3
        ");
        $cStmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
        $concerns = array_map(fn($c) => [
            'slug' => $c['slug'],
            'title' => ($locale === 'si' && !empty($c['name_si'])) ? $c['name_si'] : $c['name_en'],
            'url' => '/concern/' . $c['slug']
        ], $cStmt->fetchAll(PDO::FETCH_ASSOC));

        return [
            'ok' => true,
            'data' => [
                'herbs' => $herbs,
                'products' => $products,
                'concerns' => $concerns
            ]
        ];
    }

    private static function searchHerbs(string $query, string $locale): array
    {
        $pdo = Database::getConnection();
        $like = '%' . $query . '%';

        // Combining FULLTEXT match with LIKE for Sinhala and exact matches
        $stmt = $pdo->prepare("
            SELECT h.*,
                   (CASE
                      WHEN LOWER(h.name) = LOWER(:exact1) THEN 100
                      WHEN LOWER(h.name_si) = LOWER(:exact2) THEN 100
                      WHEN LOWER(h.scientific_name) = LOWER(:exact3) THEN 90
                      WHEN h.name LIKE :starts1 THEN 80
                      WHEN h.name_si LIKE :starts2 THEN 80
                      WHEN h.name LIKE :like1 OR h.name_si LIKE :like2 OR h.scientific_name LIKE :like3 THEN 60
                      WHEN MATCH(h.name, h.scientific_name, h.uses) AGAINST(:ft1 IN BOOLEAN MODE) THEN 40
                      WHEN h.uses LIKE :like4 OR h.uses_si LIKE :like5 THEN 30
                      ELSE 10
                    END) AS relevance_score
            FROM `herb` h
            WHERE (
              h.name LIKE :w_like1
              OR h.name_si LIKE :w_like2
              OR h.scientific_name LIKE :w_like3
              OR h.uses LIKE :w_like4
              OR h.uses_si LIKE :w_like5
              OR MATCH(h.name, h.scientific_name, h.uses) AGAINST(:ft2 IN BOOLEAN MODE)
            )
            ORDER BY relevance_score DESC, h.name ASC
        ");

        $stmt->execute([
            ':exact1' => $query,
            ':exact2' => $query,
            ':exact3' => $query,
            ':starts1' => $query . '%',
            ':starts2' => $query . '%',
            ':like1' => $like,
            ':like2' => $like,
            ':like3' => $like,
            ':like4' => $like,
            ':like5' => $like,
            ':ft1' => $query . '*',
            ':w_like1' => $like,
            ':w_like2' => $like,
            ':w_like3' => $like,
            ':w_like4' => $like,
            ':w_like5' => $like,
            ':ft2' => $query . '*'
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($h) => HerbRepo::enrich($h, $locale), $rows);
    }

    private static function searchProducts(string $query, array $matchedHerbIds, string $locale): array
    {
        $pdo = Database::getConnection();
        $like = '%' . $query . '%';

        $herbCondition = '';
        if (!empty($matchedHerbIds)) {
            $inList = implode(',', array_map('intval', $matchedHerbIds));
            $herbCondition = " OR p.product_id IN (SELECT product_id FROM product_herb WHERE herb_id IN ({$inList}))";
        }

        $stmt = $pdo->prepare("
            SELECT p.*, c.name AS category_name, c.name_si AS category_name_si,
                   (SELECT MAX(d.discount_percentage)
                    FROM discount d
                    WHERE d.product_id = p.product_id
                      AND (d.start_date IS NULL OR d.start_date <= NOW())
                      AND (d.end_date IS NULL OR d.end_date >= NOW())
                   ) AS active_discount_percentage,
                   (CASE
                      WHEN LOWER(p.product_name) = LOWER(:exact1) THEN 100
                      WHEN LOWER(p.product_name_si) = LOWER(:exact2) THEN 100
                      WHEN p.product_name LIKE :starts1 THEN 80
                      WHEN p.product_name_si LIKE :starts2 THEN 80
                      WHEN p.product_name LIKE :like1 OR p.product_name_si LIKE :like2 THEN 60
                      WHEN MATCH(p.product_name, p.description) AGAINST(:ft1 IN BOOLEAN MODE) THEN 40
                      WHEN p.description LIKE :like3 OR p.description_si LIKE :like4 THEN 30
                      ELSE 20
                    END) AS relevance_score
            FROM `product` p
            LEFT JOIN `category` c ON c.category_id = p.category_id
            WHERE (
              p.product_name LIKE :w_like1
              OR p.product_name_si LIKE :w_like2
              OR p.description LIKE :w_like3
              OR p.description_si LIKE :w_like4
              OR MATCH(p.product_name, p.description) AGAINST(:ft2 IN BOOLEAN MODE)
              {$herbCondition}
            )
            ORDER BY relevance_score DESC, p.product_name ASC
        ");

        $stmt->execute([
            ':exact1' => $query,
            ':exact2' => $query,
            ':starts1' => $query . '%',
            ':starts2' => $query . '%',
            ':like1' => $like,
            ':like2' => $like,
            ':like3' => $like,
            ':like4' => $like,
            ':ft1' => $query . '*',
            ':w_like1' => $like,
            ':w_like2' => $like,
            ':w_like3' => $like,
            ':w_like4' => $like,
            ':ft2' => $query . '*'
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function($p) use ($locale) {
            return ProductRepo::enrichWithSalePrice(ProductRepo::enrichLocale($p, $locale));
        }, $rows);
    }

    public static function findConcernShortcut(string $query, string $locale = 'en'): ?array
    {
        $pdo = Database::getConnection();
        $like = '%' . $query . '%';

        $stmt = $pdo->prepare("
            SELECT * FROM `health_concern`
            WHERE LOWER(name_en) LIKE LOWER(:c_name_en)
               OR LOWER(name_si) LIKE LOWER(:c_name_si)
               OR LOWER(slug) LIKE LOWER(:c_slug)
            LIMIT 1
        ");
        $stmt->execute([
            ':c_name_en' => $like,
            ':c_name_si' => $like,
            ':c_slug' => $like
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $isSi = ($locale === 'si');
        $row['display_name'] = ($isSi && !empty($row['name_si'])) ? $row['name_si'] : $row['name_en'];
        $row['url'] = '/concern/' . $row['slug'];
        return $row;
    }

    public static function computeDidYouMean(string $query): ?string
    {
        $clean = strtolower(trim($query));
        if (strlen($clean) < 3) {
            return null;
        }

        $bestWord = null;
        $shortestDist = 999;
        $querySoundex = soundex($clean);

        foreach (self::$knownTerms as $term) {
            $dist = levenshtein($clean, $term);
            if ($dist > 0 && $dist <= 3 && $dist < $shortestDist) {
                $shortestDist = $dist;
                $bestWord = $term;
            } elseif (soundex($term) === $querySoundex && $bestWord === null && $clean !== $term) {
                $bestWord = $term;
            }
        }

        return $bestWord;
    }
}
