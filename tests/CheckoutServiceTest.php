<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Env;
use App\Core\Database;
use App\Service\CheckoutService;
use PDO;

class CheckoutServiceTest extends TestCase
{
    private PDO $pdo;
    private int $testUserId;
    private int $testProductId;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        $this->pdo = Database::getConnection();

        // Create a fresh test user
        $this->pdo->exec("DELETE FROM `user` WHERE email = 'checkout@test.com'");
        $ins = $this->pdo->prepare("INSERT INTO `user` (name, email, password, status) VALUES ('Checkout User', 'checkout@test.com', 'hash', 'active')");
        $ins->execute();
        $this->testUserId = (int)$this->pdo->lastInsertId();

        // Create test product with stock = 10, using only columns that exist
        $existingCat = $this->pdo->query("SELECT category_id FROM category LIMIT 1")->fetchColumn();
        if (!$existingCat) {
            $this->pdo->exec("INSERT INTO category (name) VALUES ('Test Category')");
            $existingCat = (int)$this->pdo->lastInsertId();
        }

        $ins2 = $this->pdo->prepare("
            INSERT INTO product (product_name, description, price, quantity, category_id, image)
            VALUES ('Test Herb Oil', 'Test product', 1500.00, 10, :cat_id, 'test.jpg')
        ");
        $ins2->execute([':cat_id' => $existingCat]);
        $this->testProductId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        // Clean up cart, order items, orders, users, products from test
        $this->pdo->prepare("DELETE FROM cart WHERE user_id = :uid")->execute([':uid' => $this->testUserId]);
        $this->pdo->prepare("DELETE FROM order_item WHERE order_id IN (SELECT order_id FROM orders WHERE user_id = :uid)")->execute([':uid' => $this->testUserId]);
        $this->pdo->prepare("DELETE FROM orders WHERE user_id = :uid")->execute([':uid' => $this->testUserId]);
        $this->pdo->prepare("DELETE FROM user WHERE user_id = :uid")->execute([':uid' => $this->testUserId]);
        $this->pdo->prepare("DELETE FROM product WHERE product_id = :pid")->execute([':pid' => $this->testProductId]);
    }

    public function testOrderCreatedAsUnpaidByDefault(): void
    {
        $result = CheckoutService::createOrder($this->testUserId, [
            ['product_id' => $this->testProductId, 'quantity' => 2]
        ], 'card');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $orderId = $result['order_id'];

        $order = $this->pdo->query("SELECT * FROM orders WHERE order_id = {$orderId}")->fetch();
        $this->assertEquals('unpaid', $order['payment_status']);

        // Stock should NOT be reduced on unpaid order
        $product = $this->pdo->query("SELECT quantity FROM product WHERE product_id = {$this->testProductId}")->fetch();
        $this->assertEquals(10, (int)$product['quantity']);
    }

    public function testConfirmPaymentReducesStockOnce(): void
    {
        $result = CheckoutService::createOrder($this->testUserId, [
            ['product_id' => $this->testProductId, 'quantity' => 3]
        ], 'card');

        $this->assertTrue($result['ok']);
        $orderId = $result['order_id'];

        // Confirm payment - stock must drop from 10 to 7
        $confirm = CheckoutService::confirmPayment($orderId);
        $this->assertTrue($confirm['ok']);

        $product = $this->pdo->query("SELECT quantity FROM product WHERE product_id = {$this->testProductId}")->fetch();
        $this->assertEquals(7, (int)$product['quantity']);

        // Calling confirmPayment again must be idempotent (stock stays at 7)
        $confirm2 = CheckoutService::confirmPayment($orderId);
        $this->assertTrue($confirm2['ok']);
        $this->assertTrue($confirm2['already_paid'] ?? false);

        $product2 = $this->pdo->query("SELECT quantity FROM product WHERE product_id = {$this->testProductId}")->fetch();
        $this->assertEquals(7, (int)$product2['quantity']);
    }

    public function testOrderWithEmptyItemsFails(): void
    {
        $result = CheckoutService::createOrder($this->testUserId, [], 'card');
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('empty', strtolower($result['error']));
    }
}
