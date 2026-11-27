<?php

namespace Tests;

use App\Core\Database;
use App\Core\Env;
use App\Core\Pager;
use App\Core\View;
use App\Repository\ContactRepo;
use App\Repository\SettingRepo;
use App\Service\AccountService;
use App\Service\CartService;
use App\Service\CheckoutService;
use App\Service\OrderService;
use App\Service\ProductListing;
use PDO;
use PHPUnit\Framework\TestCase;

class StoreFeaturesTest extends TestCase
{
    private PDO $pdo;
    private int $userId;
    private int $productId;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        $this->pdo = Database::getConnection();
        $this->cleanup();
        $_SESSION = [];
        $this->pdo->prepare("INSERT INTO `user` (name, email, password, phone, address, status) VALUES ('Store Tester', 'store@test.lk', ?, '0771234567', '1 Test Road, Kandy', 'active')")
            ->execute([password_hash('Password123', PASSWORD_DEFAULT)]);
        $this->userId = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO product (product_name, description, price, quantity, category_id) VALUES ('ST Product', 'x', 500, 10, 1)");
        $this->productId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $ids = "SELECT user_id FROM user WHERE email = 'store@test.lk' OR name = 'Deleted account'";
        $this->pdo->exec("DELETE FROM cart WHERE user_id IN ({$ids}) OR product_id IN (SELECT product_id FROM product WHERE product_name = 'ST Product')");
        $this->pdo->exec("DELETE FROM review WHERE user_id IN ({$ids})");
        $this->pdo->exec("DELETE FROM orders WHERE user_id IN ({$ids})");
        $this->pdo->exec("DELETE FROM user WHERE email = 'store@test.lk' OR name = 'Deleted account'");
        $this->pdo->exec("DELETE FROM product WHERE product_name = 'ST Product'");
        $this->pdo->exec('DELETE FROM site_setting');
        $this->pdo->exec('DELETE FROM contact_message');
        SettingRepo::forget();
    }

    private function order(string $method): int
    {
        $created = CheckoutService::createOrder($this->userId, [['product_id' => $this->productId, 'quantity' => 2]], $method, null, [
            'shipping_name' => 'Store Tester', 'shipping_phone' => '0771234567', 'shipping_address' => '1 Test Road, Kandy', 'district' => 'Kandy',
        ]);
        $this->assertTrue($created['ok'], $created['error'] ?? '');
        return (int)$created['order_id'];
    }

    private function row(int $orderId): array
    {
        return $this->pdo->query("SELECT status, payment_status, stock_deducted, shipping_name, shipping_phone, shipping_address FROM orders WHERE order_id = {$orderId}")->fetch(PDO::FETCH_ASSOC);
    }

    private function stock(): int
    {
        return (int)$this->pdo->query("SELECT quantity FROM product WHERE product_id = {$this->productId}")->fetchColumn();
    }

    public function testUnpaidOrdersCannotBeProcessed(): void
    {
        foreach (['bank_transfer', 'payhere', 'card'] as $method) {
            $id = $this->order($method);
            foreach (['Confirmed', 'Packed', 'Out for Delivery', 'Delivered'] as $status) {
                $this->assertFalse(OrderService::updateStatus($id, $status, null, null, false)['ok'], "{$method} {$status}");
            }
            $this->assertSame('Pending', $this->row($id)['status']);
            $this->assertTrue(OrderService::updateStatus($id, 'Cancelled', null, null, false)['ok'], 'Cancelling stays possible');
        }
        $cod = $this->order('cod');
        $this->assertTrue(OrderService::updateStatus($cod, 'Confirmed', null, null, false)['ok']);
    }

    public function testBankPaymentIsRecordedByHandOnlyOnce(): void
    {
        $id = $this->order('bank_transfer');
        $this->assertTrue(OrderService::markBankPayment($id)['ok']);
        $this->assertSame(['paid', 8], [$this->row($id)['payment_status'], $this->stock()]);
        $this->assertFalse(OrderService::markBankPayment($id)['ok']);
        $this->assertSame(8, $this->stock());
        $this->assertTrue(OrderService::updateStatus($id, 'Confirmed', null, null, false)['ok']);

        $this->assertFalse(OrderService::markBankPayment($this->order('payhere'))['ok'], 'Only PayHere may settle its own orders');
        $this->assertFalse(OrderService::markBankPayment($this->order('cod'))['ok']);
    }

    public function testCancelledPaidOrderWaitsForItsRefund(): void
    {
        $id = $this->order('payhere');
        CheckoutService::confirmPayment($id);
        $this->assertFalse(OrderService::markRefunded($id)['ok'], 'Not cancelled yet');

        $due = OrderService::refundsDue();
        $result = OrderService::cancelByCustomer($id, $this->userId);
        $this->assertTrue($result['ok'] && $result['refund_needed']);
        $this->assertSame($due + 1, OrderService::refundsDue());
        $this->assertContains($id, array_map('intval', array_column(OrderService::search(['refund' => '1']), 'order_id')));
        $this->assertContains('Paid order cancelled - refund required', array_column(OrderService::getTimeline($id), 'note'));
        $this->assertSame(10, $this->stock());

        $this->assertTrue(OrderService::markRefunded($id)['ok']);
        $this->assertSame('refunded', $this->row($id)['payment_status']);
        $this->assertSame($due, OrderService::refundsDue());
        $this->assertFalse(OrderService::markRefunded($id)['ok']);
    }

    public function testCancelledUnpaidOrderNeedsNoRefund(): void
    {
        $id = $this->order('cod');
        $this->assertFalse(OrderService::cancelByCustomer($id, $this->userId)['refund_needed']);
        $this->assertFalse(OrderService::needsRefund($this->row($id)));
    }

    public function testBankTransferNeedsAnAccount(): void
    {
        $this->assertNotContains('bank_transfer', CheckoutService::availableMethods());
        $this->assertFalse(SettingRepo::save(['bank_name' => 'Bank'])['ok'], 'Half filled');
        $this->assertFalse(SettingRepo::save(['bank_name' => 'B', 'bank_account_name' => 'N', 'bank_account_number' => 'abc'])['ok']);
        $this->assertFalse(SettingRepo::save(['support_email' => 'nope'])['ok']);
        $this->assertTrue(SettingRepo::save(['bank_name' => 'Test Bank', 'bank_account_name' => 'Osuthuru', 'bank_account_number' => '12-3456 789', 'support_email' => 'help@test.lk'])['ok']);
        $this->assertContains('bank_transfer', CheckoutService::availableMethods());

        $html = View::partial('bank_details');
        $this->assertStringContainsString('12-3456 789', $html);
        $this->assertTrue(SettingRepo::save([])['ok']);
        $this->assertNotContains('bank_transfer', CheckoutService::availableMethods());
    }

    public function testContactMessagesAreCheckedAndStored(): void
    {
        $good = ['name' => 'Kamal', 'email' => 'k@test.lk', 'subject' => 'Delivery', 'message' => 'When will it arrive?'];
        foreach (['name' => '', 'email' => 'x', 'subject' => 'a', 'message' => 'short'] as $field => $bad) {
            $this->assertFalse(ContactRepo::send([$field => $bad] + $good)['ok'], $field);
        }
        $this->assertSame(0, ContactRepo::count());
        $this->assertTrue(ContactRepo::send(['message' => '<script>alert(1)</script> hello'] + $good)['ok']);
        $stored = ContactRepo::page(1, 5)[0];
        $html = View::render('admin/messages', ['messages' => [$stored], 'pager' => Pager::of(1, 1, 25)], false);
        $this->assertStringNotContainsString('<script>alert', $html);
        ContactRepo::setHandled((int)$stored['message_id'], true);
        $this->assertSame(0, ContactRepo::count(true));
    }

    public function testAdminChangesOwnPassword(): void
    {
        $this->pdo->prepare("INSERT INTO admin (name, email, password) VALUES ('ST', 'stadmin@test.lk', ?)")->execute([password_hash('OldPass123', PASSWORD_DEFAULT)]);
        $id = (int)$this->pdo->lastInsertId();
        try {
            $this->assertFalse(AccountService::changeAdminPassword($id, 'wrong', 'NewPass123', 'NewPass123')['ok']);
            $this->assertFalse(AccountService::changeAdminPassword($id, 'OldPass123', 'weak', 'weak')['ok']);
            $this->assertFalse(AccountService::changeAdminPassword($id, 'OldPass123', 'NewPass123', 'Other123')['ok']);
            $this->assertFalse(AccountService::changeAdminPassword($id, 'OldPass123', 'OldPass123', 'OldPass123')['ok']);
            $this->assertTrue(AccountService::changeAdminPassword($id, 'OldPass123', 'NewPass123', 'NewPass123')['ok']);
            $hash = $this->pdo->query("SELECT password FROM admin WHERE admin_id = {$id}")->fetchColumn();
            $this->assertTrue(password_verify('NewPass123', $hash));
        } finally {
            $this->pdo->exec("DELETE FROM admin WHERE admin_id = {$id}");
        }
    }

    public function testAccountDeletionRemovesPersonalDetails(): void
    {
        $id = $this->order('cod');
        $this->assertFalse(AccountService::deleteCustomer($this->userId, 'Password123')['ok'], 'An order is open');
        OrderService::updateStatus($id, 'Delivered', null, null, false);
        $this->assertFalse(AccountService::deleteCustomer($this->userId, 'wrong')['ok']);

        $this->pdo->exec("INSERT INTO cart (user_id, product_id, quantity) VALUES ({$this->userId}, {$this->productId}, 1)");
        $this->pdo->exec("INSERT INTO review (user_id, product_id, rating, comment, review_date) VALUES ({$this->userId}, {$this->productId}, 5, 'Fine', NOW())");
        $this->pdo->exec("INSERT INTO chat_session (user_id, ip_hash, created_at) VALUES ({$this->userId}, 'x', NOW())");
        $this->pdo->exec("INSERT INTO chat_message (session_id, role, content) VALUES (" . (int)$this->pdo->lastInsertId() . ", 'user', 'hi')");

        $this->assertTrue(AccountService::deleteCustomer($this->userId, 'Password123')['ok']);

        $user = $this->pdo->query("SELECT name, email, phone, address, status FROM user WHERE user_id = {$this->userId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['Deleted account', "deleted-{$this->userId}@deleted.invalid", null, null, 'blocked'], array_values($user));
        foreach (['cart', 'review', 'chat_session'] as $table) {
            $this->assertSame(0, (int)$this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE user_id = {$this->userId}")->fetchColumn(), $table);
        }
        $row = $this->row($id);
        $this->assertSame(['Delivered', 'Deleted account', null, null], [$row['status'], $row['shipping_name'], $row['shipping_phone'], $row['shipping_address']]);
        $this->assertFalse(AccountService::deleteCustomer($this->userId, 'Password123')['ok']);
    }

    public function testVisitorCartMovesIntoTheAccount(): void
    {
        $this->assertNull(CartService::add($this->productId, '2'));
        $this->assertNull(CartService::add($this->productId, '3'));
        $this->assertNotNull(CartService::add($this->productId, '6'), 'More than the stock');
        $this->assertSame([$this->productId => 5], CartService::quantities());
        $this->assertSame(0, (int)$this->pdo->query("SELECT COUNT(*) FROM cart WHERE product_id = {$this->productId}")->fetchColumn());
        $this->assertEquals(2500.0, CartService::contents()['subtotal']);
        $this->assertNotNull(CartService::update($this->productId, '0'));
        $this->assertNull(CartService::update($this->productId, '4'));

        $this->pdo->exec("INSERT INTO cart (user_id, product_id, quantity) VALUES ({$this->userId}, {$this->productId}, 8)");
        $_SESSION['user_id'] = $this->userId;
        CartService::mergeGuestCart();
        // 8 already there and 4 chosen as a visitor, but only 10 in stock
        $this->assertSame([$this->productId => 10], CartService::quantities());
        $this->assertArrayNotHasKey('guest_cart', $_SESSION);

        CartService::remove($this->productId);
        $this->assertSame([], CartService::quantities());
    }

    public function testProductListSortsFiltersAndPages(): void
    {
        $all = ProductListing::browse([], 'en');
        $this->assertSame(ProductListing::PER_PAGE, count($all['products']));
        $this->assertGreaterThan(1, $all['pager']['pages']);

        $cheap = ProductListing::browse(['sort' => 'price_asc'], 'en');
        $prices = array_column($cheap['products'], 'effective_price');
        $sorted = $prices;
        sort($sorted);
        $this->assertSame($sorted, $prices);

        $range = ProductListing::browse(['min' => '600', 'max' => '300'], 'en');
        foreach ($range['products'] as $p) {
            $this->assertTrue($p['effective_price'] >= 300 && $p['effective_price'] <= 600);
        }
        $this->assertSame(['min' => '300', 'max' => '600'], $range['filters']);

        $last = ProductListing::browse(['page' => '999'], 'en');
        $this->assertSame($last['pager']['pages'], $last['pager']['page']);
        $this->assertSame([], ProductListing::browse(['category' => '999999'], 'en')['products']);
        $odd = ProductListing::browse(['sort' => ['x'], 'min' => 'abc', 'page' => '-2'], 'en');
        $this->assertSame([1, 'newest', []], [$odd['pager']['page'], $odd['sort'], $odd['filters']]);
    }

    public function testPageLinksKeepTheFilters(): void
    {
        $this->assertSame('/products?sort=name&page=2', Pager::url('/products', ['sort' => 'name', 'min' => '', 'page' => '9'], 2));
        $this->assertSame('/products', Pager::url('/products', [], 1));
        $this->assertSame(['page' => 1, 'pages' => 1, 'per' => 25, 'total' => 0, 'offset' => 0], Pager::of(0, 'x', 25));
    }

    public function testEveryNewPageHasText(): void
    {
        foreach (['privacy' => 7, 'terms' => 6, 'returns' => 5] as $name => $sections) {
            foreach (['en', 'si'] as $locale) {
                $text = require dirname(__DIR__) . "/lang/{$locale}.php";
                for ($i = 1; $i <= $sections; $i++) {
                    $this->assertArrayHasKey("page_{$name}_h{$i}", $text);
                    $this->assertArrayHasKey("page_{$name}_p{$i}", $text);
                }
                $this->assertArrayNotHasKey("page_{$name}_h" . ($sections + 1), $text);
            }
        }
    }
}
