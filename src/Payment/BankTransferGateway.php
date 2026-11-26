<?php

namespace App\Payment;

use App\Core\Database;
use Throwable;

class BankTransferGateway implements PaymentGateway
{
    public function getCode(): string
    {
        return 'bank_transfer';
    }

    public function getName(): string
    {
        return 'Bank Transfer (Slip Upload)';
    }

    public function initiatePayment(int $orderId, float $totalAmount, array $context = []): array
    {
        $pdo = Database::getConnection();

        try {
            $stmt = $pdo->prepare("SELECT user_id FROM `orders` WHERE order_id = :order_id");
            $stmt->execute([':order_id' => $orderId]);
            $userId = $stmt->fetchColumn();

            if (!$userId) {
                return ['ok' => false, 'error' => t('err_order_404')];
            }

            // Clear purchased items from cart
            $clearCart = $pdo->prepare("
                DELETE FROM `cart`
                WHERE user_id = :user_id
                  AND product_id IN (SELECT product_id FROM `order_item` WHERE order_id = :order_id)
            ");
            $clearCart->execute([
                ':user_id' => $userId,
                ':order_id' => $orderId
            ]);

            // If slip path was provided in context, record it
            if (!empty($context['slip_path'])) {
                $ins = $pdo->prepare("
                    INSERT INTO `bank_slip` (order_id, file_path, status, created_at)
                    VALUES (:order_id, :file_path, 'submitted', NOW())
                ");
                $ins->execute([
                    ':order_id' => $orderId,
                    ':file_path' => $context['slip_path']
                ]);
            }

            return [
                'ok' => true,
                'redirect_url' => "/orders/{$orderId}?placed=bank_transfer",
                'message' => t('msg_bank_placed')
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
