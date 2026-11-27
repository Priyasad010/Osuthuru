<?php

namespace App\Service;

use App\Core\Database;
use App\Core\Lang;
use App\Repository\BatchRepo;
use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use PDO;

/**
 * Sales figures for the dashboard and reports. Screens and exports use the same
 * functions, so their numbers always agree.
 */
class ReportService
{
    /** An order counts as a sale unless cancelled or still waiting for payment. */
    public const SALE = "o.status <> 'Cancelled' AND (o.payment_status = 'paid' OR o.status <> 'Pending' OR o.payment_method = 'cod')";
    public const LOW_STOCK_UNITS = 10;
    public const LOW_STOCK_DAYS = 14;
    private const MAX_DAYS = 366;

    private static function rows(string $sql, array $params = []): array
    {
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * A valid inclusive date range, by default the last 30 days.
     *
     * @return array{0:string,1:string}
     */
    public static function range(mixed $from, mixed $to): array
    {
        $parse = function (mixed $value): ?DateTimeImmutable {
            if (!is_string($value)) {
                return null;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, BatchRepo::today()->getTimezone());
            return $date && $date->format('Y-m-d') === $value ? $date : null;
        };

        $end = $parse($to) ?? BatchRepo::today();
        $start = $parse($from) ?? $end->modify('-29 days');
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }
        if ($start < $end->modify('-' . (self::MAX_DAYS - 1) . ' days')) {
            $start = $end->modify('-' . (self::MAX_DAYS - 1) . ' days');
        }
        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    /** SQL condition and parameters for orders placed between two days. */
    private static function period(string $from, string $to): array
    {
        return [
            'o.order_date >= :from AND o.order_date < :to + INTERVAL 1 DAY',
            [':from' => $from, ':to' => $to],
        ];
    }

    public static function summary(string $from, string $to): array
    {
        [$where, $params] = self::period($from, $to);
        $row = self::rows("
            SELECT COUNT(*) AS orders, COALESCE(SUM(o.total_price), 0) AS revenue
            FROM orders o WHERE {$where} AND " . self::SALE, $params)[0];

        $orders = (int)$row['orders'];
        $revenue = (float)$row['revenue'];
        return [
            'orders' => $orders,
            'revenue' => $revenue,
            'average_order_value' => $orders > 0 ? round($revenue / $orders, 2) : 0.0,
        ];
    }

    /** One row per day, including days without sales. 'products' excludes delivery and discounts. */
    public static function daily(string $from, string $to): array
    {
        [$where, $params] = self::period($from, $to);
        $orders = self::rows("
            SELECT DATE(o.order_date) AS day, COUNT(*) AS orders, SUM(o.total_price) AS revenue
            FROM orders o WHERE {$where} AND " . self::SALE . "
            GROUP BY DATE(o.order_date)", $params);
        $items = self::rows("
            SELECT DATE(o.order_date) AS day, SUM(i.subtotal) AS products
            FROM orders o JOIN order_item i ON i.order_id = o.order_id
            WHERE {$where} AND " . self::SALE . "
            GROUP BY DATE(o.order_date)", $params);

        $orders = array_column($orders, null, 'day');
        $items = array_column($items, 'products', 'day');

        $days = [];
        for ($d = new DateTimeImmutable($from); $d->format('Y-m-d') <= $to; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $days[] = [
                'day' => $key,
                'orders' => (int)($orders[$key]['orders'] ?? 0),
                'revenue' => (float)($orders[$key]['revenue'] ?? 0),
                'products' => (float)($items[$key] ?? 0),
            ];
        }
        return $days;
    }

    public static function topProducts(string $from, string $to, int $limit = 5): array
    {
        [$where, $params] = self::period($from, $to);
        return self::rows("
            SELECT p.product_id, p.product_name, SUM(i.quantity) AS units, SUM(i.subtotal) AS sales
            FROM orders o
            JOIN order_item i ON i.order_id = o.order_id
            JOIN product p ON p.product_id = i.product_id
            WHERE {$where} AND " . self::SALE . "
            GROUP BY p.product_id, p.product_name
            ORDER BY sales DESC, p.product_id
            LIMIT " . max(1, $limit), $params);
    }

    public static function categorySales(string $from, string $to): array
    {
        [$where, $params] = self::period($from, $to);
        return self::rows("
            SELECT COALESCE(c.name, '-') AS category, SUM(i.quantity) AS units, SUM(i.subtotal) AS sales
            FROM orders o
            JOIN order_item i ON i.order_id = o.order_id
            JOIN product p ON p.product_id = i.product_id
            LEFT JOIN category c ON c.category_id = p.category_id
            WHERE {$where} AND " . self::SALE . "
            GROUP BY category
            ORDER BY sales DESC, category", $params);
    }

    /** All orders of the period (any status), counted by status or payment method. */
    public static function breakdown(string $column, string $from, string $to): array
    {
        if (!in_array($column, ['status', 'payment_method'], true)) {
            return [];
        }
        [$where, $params] = self::period($from, $to);
        return self::rows("
            SELECT o.{$column} AS label, COUNT(*) AS orders, SUM(o.total_price) AS total
            FROM orders o WHERE {$where}
            GROUP BY o.{$column} ORDER BY orders DESC, label", $params);
    }

    public static function report(string $from, string $to): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'summary' => self::summary($from, $to),
            'daily' => self::daily($from, $to),
            'top' => self::topProducts($from, $to, 10),
            'categories' => self::categorySales($from, $to),
            'statuses' => self::breakdown('status', $from, $to),
            'methods' => self::breakdown('payment_method', $from, $to),
        ];
    }

    /** Products that are nearly out, by units left or by expected days of stock. */
    public static function lowStock(?MlClient $client = null): array
    {
        $list = array_filter(
            ForecastService::stockOutlook($client),
            fn($p) => $p['stock'] <= self::LOW_STOCK_UNITS
                || ($p['days_until_stockout'] !== null && $p['days_until_stockout'] <= self::LOW_STOCK_DAYS)
        );
        usort($list, fn($a, $b) => [$a['days_until_stockout'] ?? PHP_INT_MAX, $a['stock']]
            <=> [$b['days_until_stockout'] ?? PHP_INT_MAX, $b['stock']]);
        return $list;
    }

    public static function kpis(): array
    {
        $today = BatchRepo::today()->format('Y-m-d');
        $scalar = fn(string $sql) => (int)Database::getConnection()->query($sql)->fetchColumn();

        return [
            'today_revenue' => self::summary($today, $today)['revenue'],
            'pending_orders' => $scalar("SELECT COUNT(*) FROM orders WHERE status = 'Pending'"),
            'expiring_batches' => count(BatchRepo::expiringWithin(30)),
            'new_customers' => $scalar("SELECT COUNT(*) FROM `user` WHERE created_at >= NOW() - INTERVAL 30 DAY"),
        ];
    }

    // ------------------------------------------------------------ exports

    /** The report as rows of cells, shared by both export formats. */
    private static function sections(array $report): array
    {
        $money = fn($v) => number_format((float)$v, 2, '.', '');
        $s = $report['summary'];

        return [
            t('report_summary') => [
                [t('report_from'), t('report_to'), t('report_orders'), t('report_revenue'), t('report_average')],
                [$report['from'], $report['to'], $s['orders'], $money($s['revenue']), $money($s['average_order_value'])],
            ],
            t('report_daily') => array_merge(
                [[t('col_date'), t('report_orders'), t('report_revenue'), t('report_product_sales')]],
                array_map(fn($d) => [$d['day'], $d['orders'], $money($d['revenue']), $money($d['products'])], $report['daily'])
            ),
            t('report_top') => array_merge(
                [[t('col_product'), t('report_units'), t('report_product_sales')]],
                array_map(fn($p) => [$p['product_name'], $p['units'], $money($p['sales'])], $report['top'])
            ),
            t('report_categories') => array_merge(
                [[t('admin_category'), t('report_units'), t('report_product_sales')]],
                array_map(fn($c) => [$c['category'], $c['units'], $money($c['sales'])], $report['categories'])
            ),
            t('report_statuses') => array_merge(
                [[t('col_status'), t('report_orders'), t('col_total')]],
                array_map(fn($r) => [t_or('status_', $r['label']), $r['orders'], $money($r['total'])], $report['statuses'])
            ),
            t('report_methods') => array_merge(
                [[t('col_method'), t('report_orders'), t('col_total')]],
                array_map(fn($r) => [t_or('method_', $r['label']), $r['orders'], $money($r['total'])], $report['methods'])
            ),
        ];
    }

    /** Spreadsheets run cells that start like a formula, so those are prefixed. */
    public static function csvCell(mixed $value): string
    {
        $value = (string)$value;
        return preg_match('/^[=+\-@\t\r]/', $value) && !is_numeric($value) ? "'" . $value : $value;
    }

    public static function csv(array $report): string
    {
        $out = fopen('php://temp', 'w+b');
        // Byte order mark so that Sinhala opens correctly in spreadsheets
        fwrite($out, "\xEF\xBB\xBF");
        foreach (self::sections($report) as $title => $rows) {
            fputcsv($out, [$title], ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'csvCell'], $row), ',', '"', '');
            }
            fputcsv($out, [], ',', '"', '');
        }
        rewind($out);
        return (string)stream_get_contents($out);
    }

    /** English only: Dompdf cannot shape Sinhala. */
    public static function pdfHtml(array $report): string
    {
        return Lang::runIn('en', function () use ($report) {
            $body = '';
            foreach (self::sections($report) as $title => $rows) {
                $body .= '<h2>' . e($title) . '</h2><table>';
                foreach ($rows as $i => $row) {
                    $tag = $i === 0 ? 'th' : 'td';
                    $body .= '<tr>' . implode('', array_map(fn($c) => "<{$tag}>" . e((string)$c) . "</{$tag}>", $row)) . '</tr>';
                }
                $body .= '</table>';
            }
            return "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
                body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; }
                h1 { font-size: 15pt; color: #198754; } h2 { font-size: 11pt; margin: 14px 0 4px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #bbb; padding: 3px 5px; text-align: left; }
                th { background: #f1f5f3; }
            </style></head><body><h1>Suwoda HerbHub - " . e(t('admin_reports')) . '</h1>' . $body . '</body></html>';
        });
    }

    public static function pdf(array $report): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::pdfHtml($report));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
}
