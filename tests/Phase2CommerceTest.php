<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Env;
use App\Core\Database;
use App\Repository\ProductRepo;
use App\Service\DeliveryZoneService;
use App\Service\CouponService;
use App\Service\CheckoutService;
use App\Service\OrderService;
use App\Service\InvoiceService;
use App\Service\WishlistAndAlertService;
use PDO;

class Phase2CommerceTest extends TestCase
{
    private PDO $pdo;
    private int $userId1;
    private int $userId2;
    private int $productId;
    private int $outOfStockProductId;
    private int $categoryId;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        $this->pdo = Database::getConnection();

        $this->cleanup();

        // Users
        $this->pdo->exec("INSERT INTO `user` (name, email, password, status) VALUES ('P2 User 1', 'p2u1@test.lk', 'hash', 'active')");
        $this->userId1 = (int)$this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO `user` (name, email, password, status) VALUES ('P2 User 2', 'p2u2@test.lk', 'hash', 'active')");
        $this->userId2 = (int)$this->pdo->lastInsertId();

        $cat = $this->pdo->query("SELECT category_id FROM category LIMIT 1")->fetchColumn();
        if (!$cat) {
            $this->pdo->exec("INSERT INTO category (name) VALUES ('P2 Test Cat')");
            $cat = (int)$this->pdo->lastInsertId();
        }
        $this->categoryId = (int)$cat;

        // Product 1: Base price 2000.00, stock 10
        $p1 = $this->pdo->prepare("INSERT INTO product (product_name, description, price, quantity, category_id, image) VALUES ('P2 Ashwagandha', 'Pure root', 2000.00, 10, :cid, 'p1.jpg')");
        $p1->execute([':cid' => $this->categoryId]);
        $this->productId = (int)$this->pdo->lastInsertId();

        // Product 2: Out of stock (0)
        $p2 = $this->pdo->prepare("INSERT INTO product (product_name, description, price, quantity, category_id, image) VALUES ('P2 OutOfStock Oil', 'Rare oil', 1000.00, 0, :cid, 'p2.jpg')");
        $p2->execute([':cid' => $this->categoryId]);
        $this->outOfStockProductId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->pdo->exec("DELETE FROM coupon WHERE code LIKE 'P2TEST%'");
        $this->pdo->exec("DELETE FROM orders WHERE user_id IN (SELECT user_id FROM user WHERE email IN ('p2u1@test.lk', 'p2u2@test.lk'))");
        $this->pdo->exec("DELETE FROM user WHERE email IN ('p2u1@test.lk', 'p2u2@test.lk')");
        $this->pdo->exec("DELETE FROM product WHERE product_name LIKE 'P2 %'");
    }

    public function testCodOrderDropsStockAtPlacementAndMarksPaidOnDelivered(): void
    {
        $orderRes = CheckoutService::createOrder(
            $this->userId1,
            [['product_id' => $this->productId, 'quantity' => 2]],
            'cod',
            null,
            [
                'shipping_name' => 'Kamal Perera',
                'shipping_phone' => '0771234567',
                'shipping_address' => '12 Galle Rd',
                'district' => 'Colombo',
            ]
        );
        $this->assertTrue($orderRes['ok'], $orderRes['error'] ?? '');
        $orderId = (int)$orderRes['order_id'];

        $gw = CheckoutService::getGateway('cod');
        $payRes = $gw->initiatePayment($orderId, (float)$orderRes['total_price']);
        $this->assertTrue($payRes['ok']);

        // Unpaid at placement, stock dropped from 10 to 8
        $order = $this->pdo->query("SELECT payment_status, status FROM orders WHERE order_id = {$orderId}")->fetch();
        $this->assertEquals('unpaid', $order['payment_status']);
        $stock = (int)$this->pdo->query("SELECT quantity FROM product WHERE product_id = {$this->productId}")->fetchColumn();
        $this->assertEquals(8, $stock);

        // Status transitions add history rows; Delivered sets paid
        OrderService::updateStatus($orderId, 'Confirmed', 'Verified', null, false);
        OrderService::updateStatus($orderId, 'Delivered', 'Handed over', null, false);

        $orderAfter = $this->pdo->query("SELECT payment_status, status FROM orders WHERE order_id = {$orderId}")->fetch();
        $this->assertEquals('Delivered', $orderAfter['status']);
        $this->assertEquals('paid', $orderAfter['payment_status']);

        $history = OrderService::getTimeline($orderId);
        $this->assertCount(3, $history); // Pending -> Confirmed -> Delivered
    }

    public function testNoOrderEmailForAnUnknownOrder(): void
    {
        $this->assertFalse(\App\Service\OrderService::sendPlacedEmail(999999999));
    }

    public function testDeliveryFeeChangesWithDistrictAndFreeOverThreshold(): void
    {
        $colomboSmall = DeliveryZoneService::calculateFee('Colombo', 1500.00);
        $kandySmall = DeliveryZoneService::calculateFee('Kandy', 1500.00);
        $this->assertTrue($colomboSmall['ok']);
        $this->assertTrue($kandySmall['ok']);
        $this->assertNotEquals($colomboSmall['fee'], $kandySmall['fee']);

        // Above free_over (Colombo free_over is 4000)
        $colomboFree = DeliveryZoneService::calculateFee('Colombo', 4500.00);
        $this->assertTrue($colomboFree['is_free']);
        $this->assertEquals(0.0, $colomboFree['fee']);
    }

    public function testCouponRejectsExpiredAndMaxUsesAndShowsOnInvoice(): void
    {
        // Expired coupon
        CouponService::create([
            'code' => 'P2TEST_EXP',
            'type' => 'fixed',
            'value' => 200,
            'min_order' => 0,
            'max_uses' => 10,
            'starts_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $expRes = CouponService::validateAndCalculate('P2TEST_EXP', 2000.00, $this->userId1);
        $this->assertFalse($expRes['ok']);
        $this->assertStringContainsString('expired', strtolower($expRes['error']));

        // Valid coupon with max_uses = 1
        CouponService::create([
            'code' => 'P2TEST_ONE',
            'type' => 'percent',
            'value' => 10,
            'min_order' => 500,
            'max_uses' => 1,
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        // Use on an order
        $orderRes = CheckoutService::createOrder(
            $this->userId1,
            [['product_id' => $this->productId, 'quantity' => 1]],
            'cod',
            null,
            [
                'shipping_name' => 'Nimal',
                'shipping_phone' => '0770000000',
                'shipping_address' => 'Kandy Rd',
                'district' => 'Kandy',
                'coupon_code' => 'P2TEST_ONE'
            ]
        );
        $this->assertTrue($orderRes['ok'], $orderRes['error'] ?? '');
        $this->assertEquals(200.00, $orderRes['discount_total']);

        // Second user tries same max_uses=1 coupon -> rejected
        $maxRes = CouponService::validateAndCalculate('P2TEST_ONE', 2000.00, $this->userId2);
        $this->assertFalse($maxRes['ok']);
        $this->assertStringContainsString('maximum usage', strtolower($maxRes['error']));

        // Verify discount shows on invoice HTML and PDF generates
        $order = OrderService::getOrderDetails((int)$orderRes['order_id']);
        $html = InvoiceService::generateHtml($order);
        $this->assertStringContainsString('P2TEST_ONE', $html);
        $this->assertStringContainsString('200.00', $html);

        $pdfBytes = InvoiceService::renderPdf($order);
        $this->assertStringStartsWith('%PDF-', $pdfBytes);
    }

    public function testRestockingNotifiesEachWaitingUserExactlyOnce(): void
    {
        // User 1 subscribes twice (should dedupe), User 2 subscribes once
        WishlistAndAlertService::subscribeStockAlert($this->userId1, $this->outOfStockProductId);
        WishlistAndAlertService::subscribeStockAlert($this->userId1, $this->outOfStockProductId);
        WishlistAndAlertService::subscribeStockAlert($this->userId2, $this->outOfStockProductId);

        // Raise stock from 0 to 5
        $res = WishlistAndAlertService::updateProductStock($this->outOfStockProductId, 5, false);
        $this->assertTrue($res['ok']);
        $this->assertEquals(2, $res['notified_count']); // Exactly 1 per user (2 users)

        // Updating stock again does not re-notify
        $res2 = WishlistAndAlertService::updateProductStock($this->outOfStockProductId, 10, false);
        $this->assertEquals(0, $res2['notified_count']);
    }

    public function testCatalogSalePriceComputedAtReadTimeWithoutChangingDbBasePrice(): void
    {
        // Add active 25% flash discount on productId (base price 2000.00)
        $ins = $this->pdo->prepare("
            INSERT INTO discount (product_id, discount_percentage, start_date, end_date)
            VALUES (:pid, 25.00, (NOW() - INTERVAL 1 HOUR), (NOW() + INTERVAL 1 HOUR))
        ");
        $ins->execute([':pid' => $this->productId]);

        $catalogProduct = ProductRepo::findById($this->productId);
        $this->assertTrue($catalogProduct['has_discount']);
        $this->assertEquals(2000.00, $catalogProduct['base_price']);
        $this->assertEquals(1500.00, $catalogProduct['sale_price']); // 2000 - 25%

        // Verify product.price in database is still 2000.00
        $dbPrice = (float)$this->pdo->query("SELECT price FROM product WHERE product_id = {$this->productId}")->fetchColumn();
        $this->assertEquals(2000.00, $dbPrice);
    }
}
