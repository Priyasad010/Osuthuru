<?php

namespace App\Service;

use App\Core\Lang;
use App\Repository\SettingRepo;
use App\Repository\ProductRepo;
use Dompdf\Dompdf;
use Dompdf\Options;

class InvoiceService
{
    /**
     * Invoice document in the current language.
     */
    public static function generateHtml(array $order, bool $forBrowser = false): string
    {
        $itemsSubtotal = 0.0;
        $rowsHtml = '';
        foreach ($order['items'] as $item) {
            $sub = (float)$item['subtotal'];
            $itemsSubtotal += $sub;
            $rowsHtml .= "
                <tr>
                    <td style='padding:8px; border-bottom:1px solid #ddd;'>" . e(ProductRepo::enrichLocale($item)['display_name']) . "</td>
                    <td style='padding:8px; border-bottom:1px solid #ddd; text-align:right;'>" . (int)$item['quantity'] . "</td>
                    <td style='padding:8px; border-bottom:1px solid #ddd; text-align:right;'>Rs. " . number_format((float)$item['unit_price'], 2) . "</td>
                    <td style='padding:8px; border-bottom:1px solid #ddd; text-align:right;'>Rs. " . number_format($sub, 2) . "</td>
                </tr>
            ";
        }

        $discountTotal = (float)($order['discount_total'] ?? 0);
        $deliveryFee = (float)($order['delivery_fee'] ?? 0);
        $grandTotal = (float)$order['total_price'];

        $couponCode = !empty($order['coupon_code']) ? " (" . htmlspecialchars($order['coupon_code'], ENT_QUOTES, 'UTF-8') . ")" : "";
        $discountRow = $discountTotal > 0 ? "
            <tr>
                <td colspan='3' style='padding:6px 8px; text-align:right; color:#198754;'><strong>" . e(t('inv_coupon')) . "{$couponCode}:</strong></td>
                <td style='padding:6px 8px; text-align:right; color:#198754;'>- Rs. " . number_format($discountTotal, 2) . "</td>
            </tr>
        " : "";

        $shippingName = htmlspecialchars($order['shipping_name'] ?? $order['user_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $shippingPhone = htmlspecialchars($order['shipping_phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $shippingAddr = nl2br(htmlspecialchars($order['shipping_address'] ?? '', ENT_QUOTES, 'UTF-8'));
        $district = htmlspecialchars($order['district'] ?? '', ENT_QUOTES, 'UTF-8');

        $orderId = (int)$order['order_id'];
        $orderDate = htmlspecialchars($order['order_date'] ?? '', ENT_QUOTES, 'UTF-8');
        $payMethod = e(t_or('method_', (string)($order['payment_method'] ?? 'card')));
        $payStatus = e(t_or('payment_', (string)($order['payment_status'] ?? 'unpaid')));
        $status = e(t_or('status_', (string)($order['status'] ?? 'Pending')));

        $l = [];
        foreach ([
            'title' => 'inv_title', 'number' => 'inv_number', 'date' => 'col_date', 'to' => 'inv_billed_to',
            'status' => 'inv_order_status', 'method' => 'method_label', 'payment' => 'payment_status_label',
            'product' => 'col_product', 'qty' => 'col_qty', 'price' => 'col_unit_price', 'subtotal' => 'col_subtotal',
            'items' => 'items_subtotal', 'delivery' => 'delivery_fee_label', 'total' => 'grand_total',
            'thanks' => 'inv_thanks', 'print' => 'inv_print',
        ] as $name => $key) {
            $l[$name] = e(t($key));
        }
        $lang = e(Lang::getLocale());
        $free = e(t('free'));
        // The shop's own contact details, or the contact page until they are entered
        $contact = e(implode(' · ', array_filter([SettingRepo::get('support_email'), SettingRepo::get('support_phone')])) ?: url('contact'));
        // Browser view: load a Sinhala font and offer printing or saving as PDF
        $fontLink = $forBrowser
            ? "<link href='https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;700&display=swap' rel='stylesheet'>"
            : '';
        $fontFamily = $forBrowser ? "'Noto Sans Sinhala', Arial, sans-serif" : 'DejaVu Sans, Arial, sans-serif';
        $printButton = $forBrowser
            ? "<p style='text-align:right;'><button type='button' onclick='window.print()'>{$l['print']}</button></p>"
            : '';

        return "
        <!DOCTYPE html>
        <html lang='{$lang}'>
        <head>
            <meta charset='UTF-8'>
            <title>{$l['number']} #{$orderId}</title>
            {$fontLink}
            <style>
                @media print { button { display: none; } }
                body { font-family: {$fontFamily}; color: #222; font-size: 13px; margin: 25px; }
                .header { background-color: #198754; color: #fff; padding: 15px 20px; margin-bottom: 20px; }
                .header h1 { margin: 0; font-size: 20px; }
                .meta { width: 100%; margin-bottom: 20px; }
                .meta td { vertical-align: top; width: 50%; }
                table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                table.items th { background-color: #f1f5f3; padding: 8px; border-bottom: 2px solid #198754; text-align: left; }
                .footer { margin-top: 30px; font-size: 11px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
            </style>
        </head>
        <body>
            {$printButton}
            <div class='header'>
                <h1>Osuthuru - {$l['title']}</h1>
                <div>{$l['number']} #INV-{$orderId} | {$l['date']}: {$orderDate}</div>
            </div>

            <table class='meta'>
                <tr>
                    <td>
                        <strong>{$l['to']}</strong><br>
                        {$shippingName}<br>
                        {$shippingPhone}<br>
                        {$shippingAddr}<br>
                        {$district}
                    </td>
                    <td style='text-align:right;'>
                        <strong>{$l['status']}</strong> {$status}<br>
                        <strong>{$l['method']}</strong> {$payMethod}<br>
                        <strong>{$l['payment']}</strong> {$payStatus}
                    </td>
                </tr>
            </table>

            <table class='items'>
                <thead>
                    <tr>
                        <th>{$l['product']}</th>
                        <th style='text-align:right;'>{$l['qty']}</th>
                        <th style='text-align:right;'>{$l['price']}</th>
                        <th style='text-align:right;'>{$l['subtotal']}</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                    <tr>
                        <td colspan='3' style='padding:8px; text-align:right;'><strong>{$l['items']}</strong></td>
                        <td style='padding:8px; text-align:right;'>Rs. " . number_format($itemsSubtotal, 2) . "</td>
                    </tr>
                    {$discountRow}
                    <tr>
                        <td colspan='3' style='padding:6px 8px; text-align:right;'><strong>{$l['delivery']} ({$district}):</strong></td>
                        <td style='padding:6px 8px; text-align:right;'>" . ($deliveryFee > 0 ? "Rs. " . number_format($deliveryFee, 2) : $free) . "</td>
                    </tr>
                    <tr style='background-color:#f1f5f3; font-size:15px;'>
                        <td colspan='3' style='padding:10px 8px; text-align:right;'><strong>{$l['total']}</strong></td>
                        <td style='padding:10px 8px; text-align:right;'><strong>Rs. " . number_format($grandTotal, 2) . "</strong></td>
                    </tr>
                </tbody>
            </table>

            <div class='footer'>
                {$l['thanks']} {$contact}
            </div>
        </body>
        </html>
        ";
    }

    /**
     * PDF invoice, always in English: Dompdf cannot shape Sinhala script.
     * Sinhala readers use the printable HTML invoice instead.
     */
    public static function renderPdf(array $order): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(Lang::runIn('en', fn() => self::generateHtml($order)));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
