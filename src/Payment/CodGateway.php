<?php

namespace App\Payment;

use App\Core\Database;
use App\Service\OrderService;
use PDO;
use Throwable;

class CodGateway implements PaymentGateway
{
    public function getCode(): string
    {
        return 'cod';
    }

    public function getName(): string
    {
        return 'Cash on Delivery (COD)';
    }

    /**
     * For COD: Order remains payment_status = 'unpaid',
     * stock is reduced at placement inside a DB transaction, and cart is cleared.
     */
    public function initiatePayment(int $orderId, float $totalAmount, array $context = []): array
    {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            $orderStmt = $pdo->prepare("SELECT * FROM `orders` WHERE order_id = :order_id FOR UPDATE");
            $orderStmt->execute([':order_id' => $orderId]);
            $order = $orderStmt->fetch();

            if (!$order) {
                throw new \RuntimeException(t('err_order_404'));
            }

            if ($order['status'] === 'Cancelled') {
                throw new \RuntimeException(t('err_order_cancelled'));
            }

            // Already placed: never take stock twice
            if ((int)$order['stock_deducted'] === 1) {
                $pdo->commit();
                return [
                    'ok' => true,
                    'redirect_url' => "/orders/{$orderId}?placed=cod",
                    'message' => t('msg_cod_placed')
                ];
            }

            $itemsStmt = $pdo->prepare("SELECT * FROM `order_item` WHERE order_id = :order_id");
            $itemsStmt->execute([':order_id' => $orderId]);
            $items = $itemsStmt->fetchAll();

            $lockProductStmt = $pdo->prepare("SELECT quantity FROM `product` WHERE product_id = :product_id FOR UPDATE");
            $updateStockStmt = $pdo->prepare("UPDATE `product` SET quantity = quantity - :qty WHERE product_id = :product_id");

            foreach ($items as $item) {
                $qty = (int)$item['quantity'];
                $pid = (int)$item['product_id'];

                $lockProductStmt->execute([':product_id' => $pid]);
                $avail = $lockProductStmt->fetchColumn();

                if ($avail === false || (int)$avail < $qty) {
                    throw new \RuntimeException(t('err_stock_product', ['id' => $pid]));
                }

                $updateStockStmt->execute([':qty' => $qty, ':product_id' => $pid]);
            }

            $mark = $pdo->prepare("UPDATE `orders` SET stock_deducted = 1 WHERE order_id = :order_id");
            $mark->execute([':order_id' => $orderId]);

            // Clear purchased items from cart
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
            OrderService::sendPlacedEmail($orderId);

            return [
                'ok' => true,
                'redirect_url' => "/orders/{$orderId}?placed=cod",
                'message' => t('msg_cod_placed')
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
