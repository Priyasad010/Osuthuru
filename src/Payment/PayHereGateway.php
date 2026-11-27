<?php

namespace App\Payment;

use App\Service\PayHereService;

/**
 * PayHere: the order waits as unpaid while the customer pays on PayHere's page.
 * Stock is taken and the cart emptied only when the payment is confirmed.
 */
class PayHereGateway implements PaymentGateway
{
    public function getCode(): string
    {
        return 'payhere';
    }

    public function getName(): string
    {
        return 'PayHere';
    }

    public function initiatePayment(int $orderId, float $totalAmount, array $context = []): array
    {
        if (!PayHereService::isConfigured()) {
            return ['ok' => false, 'error' => t('err_payment_unavailable')];
        }

        return ['ok' => true, 'redirect_url' => "/orders/{$orderId}/pay"];
    }
}
