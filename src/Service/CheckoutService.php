<?php

namespace App\Service;

use App\Core\Database;
use App\Core\Env;
use App\Repository\SettingRepo;
use App\Repository\ProductRepo;
use App\Payment\PaymentGateway;
use App\Payment\StripeGateway;
use App\Payment\CodGateway;
use App\Payment\BankTransferGateway;
use App\Payment\PayHereGateway;
use PDO;
use Throwable;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Checkout\Session as StripeSession;

class CheckoutService
{
    /** Payment methods a customer can choose now; online ones need their keys in .env. */
    public static function availableMethods(): array
    {
        $methods = ['cod'];
        // Offered only when customers can be told which account to pay
        if (SettingRepo::bankConfigured()) {
            $methods[] = 'bank_transfer';
        }
        if (PayHereService::isConfigured()) {
            $methods[] = 'payhere';
        }
        if (self::stripeConfigured()) {
            $methods[] = 'card';
        }
        return $methods;
    }

    private static function stripeConfigured(): bool
    {
        $secret = (string)Env::get('STRIPE_SECRET', '');
        return $secret !== '' && !str_starts_with($secret, 'sk_test_placeholder');
    }

    public static function getGateway(string $paymentMethod): PaymentGateway
    {
        return match ($paymentMethod) {
            'cod' => new CodGateway(),
            'bank_transfer' => new BankTransferGateway(),
            'payhere' => new PayHereGateway(),
            default => new StripeGateway(),
        };
    }

    /**
     * Create initial unpaid order with shipping snapshot, coupon, delivery fee, and sale prices.
     * Note: Stock is NOT reduced here; Stripe reduces at webhook, COD reduces in CodGateway::initiatePayment,
     * Bank Transfer reduces when admin verifies slip.
     */
    public static function createOrder(
        int $userId,
        array $items,
        string $paymentMethod = 'card',
        ?string $stripeSessionId = null,
        array $shippingAndCoupon = []
    ): array {
        if (empty($items)) {
            return ['ok' => false, 'error' => t('cart_empty')];
        }

        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $itemsSubtotal = 0.0;
            $orderItemsToInsert = [];

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $quantity = max(1, (int)$item['quantity']);

                $stmt = $pdo->prepare("
                    SELECT p.product_id, p.product_name, p.product_name_si, p.price, p.quantity,
                           (SELECT MAX(d.discount_percentage)
                            FROM discount d
                            WHERE d.product_id = p.product_id
                              AND (d.start_date IS NULL OR d.start_date <= NOW())
                              AND (d.end_date IS NULL OR d.end_date >= NOW())
                           ) AS active_discount_percentage
                    FROM product p
                    WHERE p.product_id = :id
                    FOR UPDATE
                ");
                $stmt->execute([':id' => $productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new \RuntimeException(t('err_product_404'));
                }

                if ((int)$product['quantity'] < $quantity) {
                    throw new \RuntimeException(t('err_stock', [
                        'name' => ProductRepo::enrichLocale($product)['display_name'],
                        'available' => $product['quantity'],
                    ]));
                }

                $enriched = ProductRepo::enrichWithSalePrice($product);
                $unitPrice = (float)$enriched['effective_price'];
                $subtotal = round($unitPrice * $quantity, 2);
                $itemsSubtotal += $subtotal;

                $orderItemsToInsert[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            // Coupon handling
            $discountTotal = 0.0;
            $couponId = null;
            if (!empty($shippingAndCoupon['coupon_code'])) {
                $couponRes = CouponService::validateAndCalculate((string)$shippingAndCoupon['coupon_code'], $itemsSubtotal, $userId);
                if (!$couponRes['ok']) {
                    throw new \RuntimeException($couponRes['error']);
                }
                $discountTotal = (float)$couponRes['discount_amount'];
                $couponId = (int)$couponRes['coupon_id'];
            }

            $netSubtotal = max(0.0, $itemsSubtotal - $discountTotal);

            // Delivery fee handling
            $deliveryFee = 0.0;
            $district = trim((string)($shippingAndCoupon['district'] ?? ''));
            if ($district !== '') {
                $zoneRes = DeliveryZoneService::calculateFee($district, $netSubtotal);
                if (!$zoneRes['ok']) {
                    throw new \RuntimeException($zoneRes['error']);
                }
                $deliveryFee = (float)$zoneRes['fee'];
            }

            $totalPrice = round($netSubtotal + $deliveryFee, 2);

            // Insert into orders table with payment_status = 'unpaid'
            $orderStmt = $pdo->prepare("
                INSERT INTO `orders` (
                    user_id, order_date, total_price, payment_status, payment_method, stripe_session_id,
                    shipping_name, shipping_phone, shipping_address, district, delivery_fee, discount_total, coupon_id, status
                )
                VALUES (
                    :user_id, NOW(), :total_price, 'unpaid', :payment_method, :stripe_session_id,
                    :shipping_name, :shipping_phone, :shipping_address, :district, :delivery_fee, :discount_total, :coupon_id, 'Pending'
                )
            ");
            $orderStmt->execute([
                ':user_id' => $userId,
                ':total_price' => $totalPrice,
                ':payment_method' => $paymentMethod,
                ':stripe_session_id' => $stripeSessionId,
                ':shipping_name' => $shippingAndCoupon['shipping_name'] ?? null,
                ':shipping_phone' => $shippingAndCoupon['shipping_phone'] ?? null,
                ':shipping_address' => $shippingAndCoupon['shipping_address'] ?? null,
                ':district' => $district !== '' ? $district : null,
                ':delivery_fee' => $deliveryFee,
                ':discount_total' => $discountTotal,
                ':coupon_id' => $couponId,
            ]);

            $orderId = (int)$pdo->lastInsertId();

            // Insert order items
            $itemStmt = $pdo->prepare("
                INSERT INTO `order_item` (order_id, product_id, quantity, unit_price, subtotal)
                VALUES (:order_id, :product_id, :quantity, :unit_price, :subtotal)
            ");

            foreach ($orderItemsToInsert as $oi) {
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $oi['product_id'],
                    ':quantity' => $oi['quantity'],
                    ':unit_price' => $oi['unit_price'],
                    ':subtotal' => $oi['subtotal'],
                ]);
            }

            // Redeem coupon if used
            if ($couponId !== null) {
                if (!CouponService::redeem($couponId, $userId, $orderId)) {
                    throw new \RuntimeException(t('err_coupon_redeem'));
                }
            }

            // Record initial Pending history row
            OrderService::recordHistory($orderId, 'Pending', 'Order placed', null);

            $pdo->commit();

            return [
                'ok' => true,
                'order_id' => $orderId,
                'items_subtotal' => $itemsSubtotal,
                'discount_total' => $discountTotal,
                'delivery_fee' => $deliveryFee,
                'total_price' => $totalPrice,
                'items_count' => count($orderItemsToInsert),
            ];

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mark order as paid, reduce stock atomically, and clean up cart.
     * Idempotent: If already paid, stock is NOT reduced again.
     */
    public static function confirmPayment(int $orderId, ?string $stripeSessionId = null): array
    {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            // 1. Lock order row
            $orderStmt = $pdo->prepare("SELECT * FROM `orders` WHERE order_id = :order_id FOR UPDATE");
            $orderStmt->execute([':order_id' => $orderId]);
            $order = $orderStmt->fetch();

            if (!$order) {
                throw new \RuntimeException("Order #{$orderId} not found.");
            }

            // Idempotence check: if already paid, do not reduce stock again!
            if ($order['payment_status'] === 'paid') {
                $pdo->commit();
                return [
                    'ok' => true,
                    'already_paid' => true,
                    'message' => "Order #{$orderId} is already marked as paid."
                ];
            }

            // Payment arrived after cancellation: record it for a refund, leave stock alone
            if ($order['status'] === 'Cancelled') {
                $paid = $pdo->prepare("
                    UPDATE `orders`
                    SET payment_status = 'paid',
                        stripe_session_id = COALESCE(:stripe_session_id, stripe_session_id)
                    WHERE order_id = :order_id
                ");
                $paid->execute([':stripe_session_id' => $stripeSessionId, ':order_id' => $orderId]);
                OrderService::recordHistory($orderId, 'Cancelled', 'Payment received after cancellation - refund required', null);
                $pdo->commit();
                return [
                    'ok' => true,
                    'already_paid' => false,
                    'refund_needed' => true,
                    'message' => "Order #{$orderId} was cancelled; payment recorded for refund."
                ];
            }

            // 2. Fetch order items and deduct stock atomically
            $itemsStmt = $pdo->prepare("SELECT * FROM `order_item` WHERE order_id = :order_id");
            $itemsStmt->execute([':order_id' => $orderId]);
            // A COD order has already taken its stock at placement
            $items = (int)$order['stock_deducted'] === 1 ? [] : $itemsStmt->fetchAll();

            $lockProductStmt = $pdo->prepare("SELECT quantity FROM `product` WHERE product_id = :product_id FOR UPDATE");
            $updateStockStmt = $pdo->prepare("
                UPDATE `product` 
                SET quantity = quantity - :qty 
                WHERE product_id = :product_id
            ");

            foreach ($items as $item) {
                $qty = (int)$item['quantity'];
                $pid = (int)$item['product_id'];

                $lockProductStmt->execute([':product_id' => $pid]);
                $avail = $lockProductStmt->fetchColumn();

                if ($avail === false || (int)$avail < $qty) {
                    throw new \RuntimeException("Insufficient stock while confirming Order #{$orderId} for product #{$pid}.");
                }

                $updateStockStmt->execute([
                    ':qty' => $qty,
                    ':product_id' => $pid
                ]);
            }

            // 3. Update order status to paid
            $updateOrder = $pdo->prepare("
                UPDATE `orders`
                SET payment_status = 'paid',
                    stock_deducted = 1,
                    stripe_session_id = COALESCE(:stripe_session_id, stripe_session_id)
                WHERE order_id = :order_id
            ");
            $updateOrder->execute([
                ':stripe_session_id' => $stripeSessionId,
                ':order_id' => $orderId
            ]);

            // 4. Remove purchased items from cart for this user
            $clearCart = $pdo->prepare("
                DELETE FROM `cart` 
                WHERE user_id = :user_id 
                  AND product_id IN (SELECT product_id FROM `order_item` WHERE order_id = :order_id)
            ");
            $clearCart->execute([
                ':user_id' => $order['user_id'],
                ':order_id' => $orderId
            ]);

            $pdo->commit();

            // A cash order was announced when it was placed
            if (in_array($order['payment_method'], ['card', 'payhere'], true)) {
                OrderService::sendPlacedEmail($orderId);
            }

            return [
                'ok' => true,
                'already_paid' => false,
                'message' => "Payment confirmed and stock updated for Order #{$orderId}."
            ];

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create Stripe Checkout Session
     */
    public static function createStripeSession(int $orderId, float $totalPrice, string $customerEmail): array
    {
        $stripeSecret = Env::get('STRIPE_SECRET');
        if (!self::stripeConfigured()) {
            return ['ok' => false, 'error' => t('err_stripe_unconfigured')];
        }

        try {
            Stripe::setApiKey($stripeSecret);

            $appUrl = rtrim(Env::get('APP_URL', 'http://localhost:8080'), '/');
            $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'customer_email' => $customerEmail,
                'client_reference_id' => (string)$orderId,
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'lkr',
                        'product_data' => [
                            'name' => "Osuthuru Order #{$orderId}",
                        ],
                        'unit_amount' => (int)round($totalPrice * 100), // In cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => "{$appUrl}/orders/{$orderId}?payment=success",
                'cancel_url' => "{$appUrl}/checkout?payment=cancelled",
            ]);

            // Save stripe_session_id to order
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE `orders` SET stripe_session_id = :sid WHERE order_id = :order_id");
            $stmt->execute([':sid' => $session->id, ':order_id' => $orderId]);

            return ['ok' => true, 'session_url' => $session->url, 'session_id' => $session->id];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Handle incoming Stripe webhook
     */
    public static function handleWebhook(string $payload, string $sigHeader): array
    {
        $webhookSecret = Env::get('STRIPE_WEBHOOK_SECRET');
        $stripeSecret = Env::get('STRIPE_SECRET');

        try {
            Stripe::setApiKey($stripeSecret);

            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $webhookSecret
            );

            if ($event->type === 'checkout.session.completed') {
                $session = $event->data->object;
                $orderId = (int)($session->client_reference_id ?? 0);

                if ($orderId > 0) {
                    return self::confirmPayment($orderId, $session->id);
                }
            }

            return ['ok' => true, 'message' => 'Event ignored or processed.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
