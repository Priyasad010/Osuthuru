<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

class InvoiceService
{
    public static function generateHtml(array $order): string
    {
        $itemsSubtotal = 0.0;
        $rowsHtml = '';
        foreach ($order['items'] as $item) {
            $sub = (float)$item['subtotal'];
            $itemsSubtotal += $sub;
            $rowsHtml .= "
                <tr>
                    <td style='padding:8px; border-bottom:1px solid #ddd;'>" . htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') . "</td>
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
                <td colspan='3' style='padding:6px 8px; text-align:right; color:#198754;'><strong>Coupon Discount{$couponCode}:</strong></td>
                <td style='padding:6px 8px; text-align:right; color:#198754;'>- Rs. " . number_format($discountTotal, 2) . "</td>
            </tr>
        " : "";

        $shippingName = htmlspecialchars($order['shipping_name'] ?? $order['user_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $shippingPhone = htmlspecialchars($order['shipping_phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $shippingAddr = nl2br(htmlspecialchars($order['shipping_address'] ?? '', ENT_QUOTES, 'UTF-8'));
        $district = htmlspecialchars($order['district'] ?? '', ENT_QUOTES, 'UTF-8');

        $orderId = (int)$order['order_id'];
        $orderDate = htmlspecialchars($order['order_date'] ?? '', ENT_QUOTES, 'UTF-8');
        $payMethod = strtoupper(str_replace('_', ' ', (string)($order['payment_method'] ?? 'card')));
        $payStatus = strtoupper((string)($order['payment_status'] ?? 'unpaid'));
        $status = htmlspecialchars((string)($order['status'] ?? 'Pending'), ENT_QUOTES, 'UTF-8');

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Invoice #{$orderId}</title>
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; color: #222; font-size: 13px; margin: 25px; }
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
            <div class='header'>
                <h1>Suwoda HerbHub - Official Invoice</h1>
                <div>Invoice #INV-{$orderId} | Date: {$orderDate}</div>
            </div>

            <table class='meta'>
                <tr>
                    <td>
                        <strong>Billed / Shipped To:</strong><br>
                        {$shippingName}<br>
                        {$shippingPhone}<br>
                        {$shippingAddr}<br>
                        {$district}
                    </td>
                    <td style='text-align:right;'>
                        <strong>Order Status:</strong> {$status}<br>
                        <strong>Payment Method:</strong> {$payMethod}<br>
                        <strong>Payment Status:</strong> {$payStatus}
                    </td>
                </tr>
            </table>

            <table class='items'>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th style='text-align:right;'>Qty</th>
                        <th style='text-align:right;'>Unit Price</th>
                        <th style='text-align:right;'>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                    <tr>
                        <td colspan='3' style='padding:8px; text-align:right;'><strong>Items Subtotal:</strong></td>
                        <td style='padding:8px; text-align:right;'>Rs. " . number_format($itemsSubtotal, 2) . "</td>
                    </tr>
                    {$discountRow}
                    <tr>
                        <td colspan='3' style='padding:6px 8px; text-align:right;'><strong>Delivery Fee ({$district}):</strong></td>
                        <td style='padding:6px 8px; text-align:right;'>" . ($deliveryFee > 0 ? "Rs. " . number_format($deliveryFee, 2) : "FREE") . "</td>
                    </tr>
                    <tr style='background-color:#f1f5f3; font-size:15px;'>
                        <td colspan='3' style='padding:10px 8px; text-align:right;'><strong>Grand Total:</strong></td>
                        <td style='padding:10px 8px; text-align:right;'><strong>Rs. " . number_format($grandTotal, 2) . "</strong></td>
                    </tr>
                </tbody>
            </table>

            <div class='footer'>
                Thank you for choosing Suwoda HerbHub. For inquiries: support@suwoda.lk
            </div>
        </body>
        </html>
        ";
    }

    public static function renderPdf(array $order): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::generateHtml($order));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
