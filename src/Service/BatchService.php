<?php

namespace App\Service;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Lang;
use App\Repository\BatchRepo;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use PDO;

class BatchService
{
    /** A token seen from more than this many different addresses is suspicious. */
    public const DUPLICATE_THRESHOLD = 50;
    public const MAX_LABELS = 60;

    /** 'recalled' wins over 'expired', which wins over 'genuine'. */
    public static function status(array $batch): string
    {
        if ($batch['status'] === 'recalled') {
            return 'recalled';
        }
        // A pack is usable up to and including its expiry date
        return $batch['expires_on'] < BatchRepo::today()->format('Y-m-d') ? 'expired' : 'genuine';
    }

    public static function recordScan(int $batchId, string $ip, string $userAgent): void
    {
        $stmt = Database::getConnection()->prepare("
            INSERT INTO batch_scan (batch_id, ip_hash, ua_hash) VALUES (:batch, :ip, :ua)
        ");
        $stmt->execute([':batch' => $batchId, ':ip' => Auth::hashIp($ip), ':ua' => Auth::hashIp($userAgent)]);
    }

    public static function looksDuplicated(array $batch): bool
    {
        return (int)$batch['scan_sources'] > self::DUPLICATE_THRESHOLD;
    }

    public static function verifyUrl(array $batch): string
    {
        return url('verify?t=' . $batch['public_token']);
    }

    /**
     * Assign batches to an order's items, first expiry first out. Items without a
     * batch that can cover the whole quantity are left unassigned.
     * Must run inside a transaction.
     */
    public static function allocate(int $orderId): void
    {
        $pdo = Database::getConnection();
        $items = $pdo->prepare("SELECT order_item_id, product_id, quantity FROM order_item WHERE order_id = :order AND batch_id IS NULL");
        $items->execute([':order' => $orderId]);

        $pick = $pdo->prepare("
            SELECT batch_id FROM product_batch
            WHERE product_id = :product AND status = 'active' AND expires_on >= :today
              AND units >= units_allocated + :qty
            ORDER BY expires_on ASC, batch_id ASC
            LIMIT 1
            FOR UPDATE
        ");
        $take = $pdo->prepare("UPDATE product_batch SET units_allocated = units_allocated + :qty WHERE batch_id = :batch");
        $link = $pdo->prepare("UPDATE order_item SET batch_id = :batch WHERE order_item_id = :item");

        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $pick->execute([
                ':product' => $item['product_id'],
                ':today' => BatchRepo::today()->format('Y-m-d'),
                ':qty' => (int)$item['quantity'],
            ]);
            $batchId = $pick->fetchColumn();
            $pick->closeCursor();

            if ($batchId !== false) {
                $take->execute([':qty' => (int)$item['quantity'], ':batch' => $batchId]);
                $link->execute([':batch' => $batchId, ':item' => $item['order_item_id']]);
            }
        }
    }

    /** Give a cancelled order's packs back to their batches. Must run inside a transaction. */
    public static function release(int $orderId): void
    {
        $pdo = Database::getConnection();
        $items = $pdo->prepare("SELECT order_item_id, batch_id, quantity FROM order_item WHERE order_id = :order AND batch_id IS NOT NULL");
        $items->execute([':order' => $orderId]);

        $give = $pdo->prepare("
            UPDATE product_batch
            SET units_allocated = IF(units_allocated >= :qty1, units_allocated - :qty2, 0)
            WHERE batch_id = :batch
        ");
        $unlink = $pdo->prepare("UPDATE order_item SET batch_id = NULL WHERE order_item_id = :item");

        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $qty = (int)$item['quantity'];
            $give->execute([':qty1' => $qty, ':qty2' => $qty, ':batch' => $item['batch_id']]);
            $unlink->execute([':item' => $item['order_item_id']]);
        }
    }

    /** Label sheet markup. English only: Dompdf cannot shape Sinhala. */
    public static function labelHtml(array $batch, int $count): string
    {
        $count = max(1, min(self::MAX_LABELS, $count));
        $image = (new PngWriter())->write(new QrCode(self::verifyUrl($batch)))->getDataUri();

        return Lang::runIn('en', function () use ($batch, $count, $image) {
            $label = "<img src='{$image}' alt='QR' style='width:112px;height:112px;'><br>"
                . '<strong>' . e($batch['product_name']) . '</strong><br>'
                . e(t('batch_code')) . ': ' . e($batch['batch_code']) . '<br>'
                . e(t('batch_expires')) . ': ' . e($batch['expires_on']) . '<br>'
                . e(t('batch_scan_to_verify'));

            $rows = '';
            for ($i = 0; $i < $count; $i += 3) {
                $rows .= '<tr>';
                for ($j = 0; $j < 3; $j++) {
                    $rows .= ($i + $j < $count) ? "<td class='label'>{$label}</td>" : '<td></td>';
                }
                $rows .= '</tr>';
            }

            return "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
                @page { margin: 12mm; }
                body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; margin: 0; }
                table { width: 100%; border-collapse: collapse; table-layout: fixed; }
                tr { page-break-inside: avoid; }
                td { text-align: center; vertical-align: top; padding: 5px; }
                td.label { border: 1px dashed #999; }
            </style></head><body><table>{$rows}</table></body></html>";
        });
    }

    public static function labelPdf(array $batch, int $count): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::labelHtml($batch, $count));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
