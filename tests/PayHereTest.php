<?php

namespace Tests;

use App\Core\Database;
use App\Core\Env;
use App\Core\View;
use App\Service\CheckoutService;
use App\Service\OrderService;
use App\Service\PayHereService;
use PDO;
use PHPUnit\Framework\TestCase;

class PayHereTest extends TestCase
{
    private const MERCHANT = '1211149';
    private const SECRET = 'test-secret';

    private PDO $pdo;
    private int $userId;
    private int $productId;
    private array $saved = [];

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        $this->pdo = Database::getConnection();
        $this->cleanup();
        foreach (['PAYHERE_MERCHANT_ID' => self::MERCHANT, 'PAYHERE_SECRET' => self::SECRET, 'PAYHERE_SANDBOX' => 'true', 'STRIPE_SECRET' => ''] as $key => $value) {
            $this->saved[$key] = $_ENV[$key] ?? null;
            $_ENV[$key] = $value;
        }
        $this->pdo->exec("INSERT INTO `user` (name, email, password, status) VALUES ('Pay Tester', 'payhere@test.lk', 'hash', 'active')");
        $this->userId = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO product (product_name, description, price, quantity, category_id) VALUES ('PH Product', 'x', 500, 5, 1)");
        $this->productId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->pdo->exec("DELETE FROM orders WHERE user_id IN (SELECT user_id FROM user WHERE email = 'payhere@test.lk')");
        $this->pdo->exec("DELETE FROM user WHERE email = 'payhere@test.lk'");
        $this->pdo->exec("DELETE FROM product WHERE product_name = 'PH Product'");
    }

    private function order(): array
    {
        $created = CheckoutService::createOrder($this->userId, [['product_id' => $this->productId, 'quantity' => 2]], 'payhere', null, [
            'shipping_name' => 'Nimal Perera', 'shipping_phone' => '0771234567', 'shipping_address' => '1 Test Road', 'district' => 'Colombo',
        ]);
        $this->assertTrue($created['ok'], $created['error'] ?? '');
        return OrderService::getOrderDetails((int)$created['order_id']);
    }

    private function notice(array $order, string $status, array $change = [], bool $resign = true): array
    {
        $post = array_merge([
            'merchant_id' => self::MERCHANT,
            'order_id' => (string)$order['order_id'],
            'payment_id' => '320025000001',
            'payhere_amount' => number_format((float)$order['total_price'], 2, '.', ''),
            'payhere_currency' => 'LKR',
            'status_code' => $status,
        ], $change);
        $secret = $resign ? self::SECRET : 'someone-else';
        $post['md5sig'] = strtoupper(md5($post['merchant_id'] . $post['order_id'] . $post['payhere_amount'] . $post['payhere_currency']
            . $post['status_code'] . strtoupper(md5($secret))));
        return $post;
    }

    private function state(array $order): array
    {
        $row = $this->pdo->query("SELECT payment_status, stock_deducted, payment_reference FROM orders WHERE order_id = " . (int)$order['order_id'])->fetch(PDO::FETCH_ASSOC);
        $row['stock'] = (int)$this->pdo->query("SELECT quantity FROM product WHERE product_id = {$this->productId}")->fetchColumn();
        return $row;
    }

    public function testCheckoutFieldsAreSignedAndNeverCarryTheSecret(): void
    {
        $order = $this->order();
        $fields = PayHereService::checkoutFields($order, 'payhere@test.lk');
        $amount = number_format((float)$order['total_price'], 2, '.', '');

        $this->assertSame($amount, $fields['amount']);
        $this->assertSame(strtoupper(md5(self::MERCHANT . $order['order_id'] . $amount . 'LKR' . strtoupper(md5(self::SECRET)))), $fields['hash']);
        $this->assertSame(['Nimal', 'Perera'], [$fields['first_name'], $fields['last_name']]);
        $this->assertStringContainsString('/payments/payhere/notify', $fields['notify_url']);

        $html = View::render('shop/payhere', ['order' => $order, 'action' => PayHereService::checkoutUrl(), 'sandbox' => true, 'fields' => $fields], false);
        $this->assertStringContainsString('https://sandbox.payhere.lk/pay/checkout', $html);
        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertStringNotContainsString('_csrf_token', $html);
    }

    public function testSuccessfulPaymentCountsOnce(): void
    {
        $order = $this->order();
        $this->assertSame(['unpaid', 5], [$this->state($order)['payment_status'], $this->state($order)['stock']]);

        $this->assertSame('paid', PayHereService::handleNotification($this->notice($order, '2'))['result']);
        $this->assertSame('unchanged', PayHereService::handleNotification($this->notice($order, '2'))['result']);
        $this->assertSame('unchanged', PayHereService::handleNotification($this->notice($order, '-2'))['result']);

        $state = $this->state($order);
        $this->assertSame(['paid', 3, '320025000001'], [$state['payment_status'], $state['stock'], $state['payment_reference']]);
    }

    public function testForgedOrMismatchedNotificationsAreRefused(): void
    {
        $order = $this->order();
        foreach ([
            $this->notice($order, '2', [], false),
            $this->notice($order, '2', ['payhere_amount' => '1.00']),
            $this->notice($order, '2', ['payhere_currency' => 'USD']),
            $this->notice($order, '2', ['merchant_id' => '999']),
            $this->notice($order, '2', ['order_id' => '999999']),
            ['order_id' => (string)$order['order_id'], 'status_code' => '2'],
        ] as $post) {
            $this->assertFalse(PayHereService::handleNotification($post)['ok']);
        }
        $this->assertSame(['unpaid', 5], [$this->state($order)['payment_status'], $this->state($order)['stock']]);
    }

    public function testFailedPaymentCanBeRetriedAndChargebackStays(): void
    {
        $order = $this->order();
        PayHereService::handleNotification($this->notice($order, '-2'));
        $this->assertSame('failed', $this->state($order)['payment_status']);
        $this->assertTrue(PayHereService::canPay(OrderService::getOrderDetails((int)$order['order_id'])));

        PayHereService::handleNotification($this->notice($order, '2'));
        PayHereService::handleNotification($this->notice($order, '-3'));
        PayHereService::handleNotification($this->notice($order, '2'));
        $this->assertSame(['refunded', 3], [$this->state($order)['payment_status'], $this->state($order)['stock']]);
    }

    public function testPaymentAfterStockRanOutIsRecordedForAnAdministrator(): void
    {
        $order = $this->order();
        $this->pdo->exec("UPDATE product SET quantity = 1 WHERE product_id = {$this->productId}");

        $this->assertSame('paid_needs_attention', PayHereService::handleNotification($this->notice($order, '2'))['result']);
        $this->assertSame(['paid', 1], [$this->state($order)['payment_status'], $this->state($order)['stock']]);
        $notes = array_column(OrderService::getTimeline((int)$order['order_id']), 'note');
        $this->assertContains('Payment received but stock is short - restock or refund', $notes);
    }

    public function testOnlineMethodsAppearOnlyWithTheirKeys(): void
    {
        $this->assertSame(['cod', 'payhere'], CheckoutService::availableMethods());
        $_ENV['PAYHERE_SECRET'] = '';
        $this->assertSame(['cod'], CheckoutService::availableMethods());
        $this->assertFalse(CheckoutService::getGateway('payhere')->initiatePayment(1, 1.0)['ok']);
    }
}
