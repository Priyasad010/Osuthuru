<?php

namespace App\Service;

use App\Core\Database;
use App\Core\Env;
use PDO;

/**
 * PayHere Checkout API (https://support.payhere.lk). The customer pays on
 * PayHere's own page; this shop never sees card details. PayHere then tells the
 * shop the result in a signed server-to-server notification, and only that
 * notification can mark an order as paid.
 */
class PayHereService
{
    private const CURRENCY = 'LKR';
    private const LIVE_URL = 'https://www.payhere.lk/pay/checkout';
    private const SANDBOX_URL = 'https://sandbox.payhere.lk/pay/checkout';

    public static function isConfigured(): bool
    {
        return self::merchantId() !== '' && self::secret() !== '';
    }

    /** Test payments unless PAYHERE_SANDBOX=false is set deliberately. */
    public static function isSandbox(): bool
    {
        return Env::get('PAYHERE_SANDBOX', true) !== false;
    }

    public static function checkoutUrl(): string
    {
        return self::isSandbox() ? self::SANDBOX_URL : self::LIVE_URL;
    }

    /** Whether an order can still be paid through PayHere. */
    public static function canPay(array $order): bool
    {
        return ($order['payment_method'] ?? '') === 'payhere'
            && in_array($order['payment_status'] ?? '', ['unpaid', 'failed'], true)
            && ($order['status'] ?? '') !== 'Cancelled';
    }

    public static function requestHash(int $orderId, float $amount): string
    {
        return self::md5Upper(self::merchantId() . $orderId . self::amount($amount) . self::CURRENCY . self::md5Upper(self::secret()));
    }

    /**
     * The fields posted to PayHere.
     * @param array $order an order with its items, as OrderService::getOrderDetails returns it
     */
    public static function checkoutFields(array $order, string $email): array
    {
        $orderId = (int)$order['order_id'];
        $amount = (float)$order['total_price'];
        [$first, $last] = array_pad(preg_split('/\s+/u', trim((string)($order['shipping_name'] ?: $order['user_name'])), 2), 2, '');
        $district = (string)($order['district'] ?? '');
        $notify = (string)Env::get('PAYHERE_NOTIFY_URL', '');

        return [
            'merchant_id' => self::merchantId(),
            'return_url' => url("orders/{$orderId}?payment=return"),
            'cancel_url' => url("orders/{$orderId}?payment=cancelled"),
            'notify_url' => $notify !== '' ? $notify : url('payments/payhere/notify'),
            'order_id' => (string)$orderId,
            'items' => "Order #{$orderId}",
            'currency' => self::CURRENCY,
            'amount' => self::amount($amount),
            'first_name' => $first,
            // PayHere requires both names
            'last_name' => $last !== '' ? $last : $first,
            'email' => $email,
            'phone' => (string)($order['shipping_phone'] ?? ''),
            'address' => (string)($order['shipping_address'] ?? ''),
            'city' => $district !== '' ? $district : 'Colombo',
            'country' => 'Sri Lanka',
            'hash' => self::requestHash($orderId, $amount),
        ];
    }

    /**
     * Act on a payment notification. Repeats are harmless.
     * @return array{ok: bool, result?: string, error?: string}
     */
    public static function handleNotification(array $post): array
    {
        $value = fn(string $key): string => isset($post[$key]) && is_string($post[$key]) ? $post[$key] : '';

        if (!self::isConfigured()) {
            return ['ok' => false, 'error' => 'PayHere is not configured'];
        }
        $expected = self::md5Upper(
            $value('merchant_id') . $value('order_id') . $value('payhere_amount') . $value('payhere_currency')
            . $value('status_code') . self::md5Upper(self::secret())
        );
        if (!hash_equals($expected, strtoupper($value('md5sig'))) || !hash_equals(self::merchantId(), $value('merchant_id'))) {
            return ['ok' => false, 'error' => 'Signature does not match'];
        }

        $orderId = ctype_digit($value('order_id')) ? (int)$value('order_id') : 0;
        $pdo = Database::getConnection();
        $find = $pdo->prepare('SELECT * FROM `orders` WHERE order_id = :id');
        $find->execute([':id' => $orderId]);
        $order = $find->fetch(PDO::FETCH_ASSOC);
        if (!$order || $order['payment_method'] !== 'payhere') {
            return ['ok' => false, 'error' => 'Unknown order'];
        }
        if ($value('payhere_currency') !== self::CURRENCY || $value('payhere_amount') === ''
            || self::amount((float)$value('payhere_amount')) !== self::amount((float)$order['total_price'])) {
            return ['ok' => false, 'error' => 'Amount does not match the order'];
        }

        $reference = mb_substr($value('payment_id'), 0, 64);

        return match ($value('status_code')) {
            '2' => self::paid($order, $reference),
            '0' => ['ok' => true, 'result' => 'pending'],
            '-1', '-2' => self::failed($orderId),
            '-3' => self::chargedBack($order, $reference),
            default => ['ok' => false, 'error' => 'Unknown status'],
        };
    }

    private static function paid(array $order, string $reference): array
    {
        $orderId = (int)$order['order_id'];
        // Already settled, or the money was later taken back: nothing to do
        if (in_array($order['payment_status'], ['paid', 'refunded'], true)) {
            return ['ok' => true, 'result' => 'unchanged'];
        }
        self::saveReference($orderId, $reference);

        $confirmed = CheckoutService::confirmPayment($orderId);
        if ($confirmed['ok']) {
            if (empty($confirmed['already_paid']) && empty($confirmed['refund_needed'])) {
                OrderService::recordHistory($orderId, (string)$order['status'], 'Payment received');
            }
            return ['ok' => true, 'result' => 'paid'];
        }

        // The money has been taken but the order could not be completed (stock
        // ran out meanwhile). Record it so that an administrator refunds it.
        $pdo = Database::getConnection();
        $pdo->prepare("UPDATE `orders` SET payment_status = 'paid' WHERE order_id = :id")->execute([':id' => $orderId]);
        OrderService::recordHistory($orderId, (string)$order['status'], 'Payment received but stock is short - restock or refund');
        error_log("PayHere: order {$orderId} was paid but not completed: " . ($confirmed['error'] ?? ''));

        return ['ok' => true, 'result' => 'paid_needs_attention'];
    }

    private static function failed(int $orderId): array
    {
        $update = Database::getConnection()->prepare("UPDATE `orders` SET payment_status = 'failed' WHERE order_id = :id AND payment_status = 'unpaid'");
        $update->execute([':id' => $orderId]);

        return ['ok' => true, 'result' => $update->rowCount() > 0 ? 'failed' : 'unchanged'];
    }

    private static function chargedBack(array $order, string $reference): array
    {
        $orderId = (int)$order['order_id'];
        if ($order['payment_status'] === 'refunded') {
            return ['ok' => true, 'result' => 'unchanged'];
        }
        self::saveReference($orderId, $reference);
        Database::getConnection()->prepare("UPDATE `orders` SET payment_status = 'refunded' WHERE order_id = :id")->execute([':id' => $orderId]);
        OrderService::recordHistory($orderId, (string)$order['status'], 'Payment was charged back');

        return ['ok' => true, 'result' => 'refunded'];
    }

    private static function saveReference(int $orderId, string $reference): void
    {
        if ($reference !== '') {
            Database::getConnection()->prepare('UPDATE `orders` SET payment_reference = :ref WHERE order_id = :id')
                ->execute([':ref' => $reference, ':id' => $orderId]);
        }
    }

    private static function merchantId(): string
    {
        return trim((string)Env::get('PAYHERE_MERCHANT_ID', ''));
    }

    private static function secret(): string
    {
        return trim((string)Env::get('PAYHERE_SECRET', ''));
    }

    private static function amount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private static function md5Upper(string $value): string
    {
        return strtoupper(md5($value));
    }
}
