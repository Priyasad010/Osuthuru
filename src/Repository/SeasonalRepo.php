<?php

namespace App\Repository;

use App\Core\Database;
use App\Core\Lang;
use DateTimeImmutable;
use PDO;

class SeasonalRepo
{
    public static function all(?string $locale = null): array
    {
        $si = ($locale ?? Lang::getLocale()) === 'si';
        $rows = Database::getConnection()->query('
            SELECT s.*, c.slug, c.name_en AS concern_en, c.name_si AS concern_si
            FROM seasonal_collection s
            JOIN health_concern c ON c.concern_id = s.concern_id
            ORDER BY s.start_mmdd, s.id')->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $r) use ($si) {
            $r['display_name'] = ($si && $r['name_si'] !== '') ? $r['name_si'] : $r['name_en'];
            return $r;
        }, $rows);
    }

    /** Whether a month-day falls in a range, including ranges that pass the new year. */
    public static function covers(string $start, string $end, string $day): bool
    {
        return $start <= $end ? ($day >= $start && $day <= $end) : ($day >= $start || $day <= $end);
    }

    /** Today's collection with up to four in-stock products of its topic, or null. */
    public static function current(?DateTimeImmutable $today = null, ?string $locale = null): ?array
    {
        $day = ($today ?? BatchRepo::today())->format('md');
        foreach (self::all($locale) as $collection) {
            if (!self::covers($collection['start_mmdd'], $collection['end_mmdd'], $day)) {
                continue;
            }
            $products = [];
            foreach (HerbRepo::getHerbsByConcern((int)$collection['concern_id'], $locale) as $herb) {
                foreach (HerbRepo::getProductsByHerb((int)$herb['herb_id'], $locale) as $product) {
                    if ((int)$product['quantity'] > 0) {
                        $products[(int)$product['product_id']] = $product;
                    }
                }
            }
            $collection['products'] = array_slice(array_values($products), 0, 4);
            return $collection;
        }
        return null;
    }

    /** Dates arrive as "MM-DD". */
    public static function save(int $id, array $data): array
    {
        $text = fn(string $k) => trim(is_string($data[$k] ?? null) ? $data[$k] : '');
        $day = function (string $value): ?string {
            // 2024 is a leap year, so 29 February is accepted
            if (!preg_match('/^(\d{2})-(\d{2})$/', $value, $m) || !checkdate((int)$m[1], (int)$m[2], 2024)) {
                return null;
            }
            return $m[1] . $m[2];
        };
        $start = $day($text('start'));
        $end = $day($text('end'));
        $pdo = Database::getConnection();
        $concern = $pdo->prepare('SELECT 1 FROM health_concern WHERE concern_id = :id');
        $concern->execute([':id' => (int)($data['concern_id'] ?? 0)]);

        if ($text('name_en') === '' || !$start || !$end || !$concern->fetchColumn()) {
            return ['ok' => false, 'error' => t('seasonal_err')];
        }
        $values = [
            ':en' => mb_substr($text('name_en'), 0, 150),
            ':si' => mb_substr($text('name_si'), 0, 150),
            ':start' => $start,
            ':end' => $end,
            ':concern' => (int)$data['concern_id'],
        ];
        if ($id > 0) {
            $pdo->prepare('UPDATE seasonal_collection SET name_en = :en, name_si = :si, start_mmdd = :start, end_mmdd = :end, concern_id = :concern WHERE id = :id')
                ->execute($values + [':id' => $id]);
        } else {
            $pdo->prepare('INSERT INTO seasonal_collection (name_en, name_si, start_mmdd, end_mmdd, concern_id) VALUES (:en, :si, :start, :end, :concern)')
                ->execute($values);
        }
        return ['ok' => true];
    }

    public static function delete(int $id): void
    {
        Database::getConnection()->prepare('DELETE FROM seasonal_collection WHERE id = :id')->execute([':id' => $id]);
    }
}
