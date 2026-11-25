<?php

namespace App\Service;

use App\Core\Database;
use App\Core\Env;
use PDO;
use Throwable;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Checkout\Session as StripeSession;

class CheckoutService
{
    /**
     * Create initial unpaid order.
     * Note: Stock is NOT reduced here for card payments; stock is only reduced upon payment confirmation.
     */
    public static function createOrder(int $userId, array $items, string $paymentMethod = 'card', ?string $stripeSessionId = null): array
    {
        if (empty($items)) {
            return ['ok' => false, 'error' => 'Your cart is empty.'];
        }

        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $totalPrice = 0.0;
            $orderItemsToInsert = [];

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $quantity = max(1, (int)$item['quantity']);

                $stmt = $pdo->prepare("SELECT product_id, product_name, price, quantity FROM product WHERE product_id = :id FOR UPDATE");
                $stmt->execute([':id' => $productId]);
                $product = $stmt->fetch();

                if (!$product) {
                    throw new \RuntimeException("Product ID {$productId} not found.");
                }

                if ($product['quantity'] < $quantity) {
                    throw new \RuntimeException("Insufficient stock for {$product['product_name']} (Available: {$product['quantity']}).");
                }

                $unitPrice = (float)$product['price'];
                $subtotal = $unitPrice * $quantity;
                $totalPrice += $subtotal;

                $orderItemsToInsert[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            // Insert into orders table with payment_status = 'unpaid'
            $orderStmt = $pdo->prepare("
                INSERT INTO `orders` (user_id, order_date, total_price, payment_status, payment_method, stripe_session_id)
                VALUES (:user_id, NOW(), :total_price, 'unpaid', :payment_method, :stripe_session_id)
            ");
            $orderStmt->execute([
                ':user_id' => $userId,
                ':total_price' => $totalPrice,
                ':payment_method' => $paymentMethod,
                ':stripe_session_id' => $stripeSessionId,
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

            $pdo->commit();

            return [
                'ok' => true,
                'order_id' => $orderId,
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

            // 2. Fetch order items and deduct stock atomically
            $itemsStmt = $pdo->prepare("SELECT * FROM `order_item` WHERE order_id = :order_id");
            $itemsStmt->execute([':order_id' => $orderId]);
            $items = $itemsStmt->fetchAll();

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
        if (empty($stripeSecret) || str_starts_with($stripeSecret, 'sk_test_placeholder')) {
            return ['ok' => false, 'error' => 'Stripe is not configured in this environment.'];
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
                            'name' => "Suwoda HerbHub Order #{$orderId}",
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
