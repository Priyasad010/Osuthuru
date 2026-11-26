<?php

namespace App\Payment;

use App\Service\CheckoutService;

class StripeGateway implements PaymentGateway
{
    public function getCode(): string
    {
        return 'card';
    }

    public function getName(): string
    {
        return 'Credit / Debit Card (Stripe)';
    }

    public function initiatePayment(int $orderId, float $totalAmount, array $context = []): array
    {
        $email = $context['email'] ?? '';
        $res = CheckoutService::createStripeSession($orderId, $totalAmount, $email);

        if (!$res['ok']) {
            return $res;
        }

        return [
            'ok' => true,
            'redirect_url' => $res['session_url'],
            'session_id' => $res['session_id'],
        ];
    }
}
