<?php

namespace App\Payment;

interface PaymentGateway
{
    public function getCode(): string;

    public function getName(): string;

    /**
     * Initiate payment for a newly created order.
     * Returns ['ok' => bool, 'redirect_url' => ?string, 'message' => ?string, 'error' => ?string]
     */
    public function initiatePayment(int $orderId, float $totalAmount, array $context = []): array;
}
