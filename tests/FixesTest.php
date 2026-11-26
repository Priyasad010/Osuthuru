<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\Lang;
use App\Core\Request;
use App\Core\View;
use App\Repository\HerbRepo;
use App\Service\CheckoutService;
use App\Service\OrderService;
use App\Service\RegistrationService;
use App\Service\SearchService;
use PDO;

/**
 * Regression tests for the Phase 1-3 gaps.
 */
class FixesTest extends TestCase
{
    private PDO $pdo;
    private int $userId;
    private int $productId;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        Lang::setLocale('en');
        $this->pdo = Database::getConnection();
        $this->cleanup();

        $this->pdo->exec("INSERT INTO `user` (name, email, password, status) VALUES ('Fix User', 'owner@fixtest.lk', 'hash', 'active')");
        $this->userId = (int)$this->pdo->lastInsertId();

        $category = (int)$this->pdo->query("SELECT category_id FROM category LIMIT 1")->fetchColumn();
        $this->pdo->exec("INSERT INTO product (product_name, description, price, quantity, category_id) VALUES ('FixTest Zanthora Balm', 'x', 1000.00, 10, {$category})");
        $this->productId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        Lang::setLocale('en');
        $_SESSION = [];
    }

    private function cleanup(): void
    {
        $this->pdo->exec("DELETE FROM login_attempts WHERE email LIKE '%@fixtest.lk'");
        $this->pdo->exec("DELETE FROM order_item WHERE order_id IN (SELECT order_id FROM orders WHERE user_id IN (SELECT user_id FROM `user` WHERE email LIKE '%@fixtest.lk'))");
        $this->pdo->exec("DELETE FROM orders WHERE user_id IN (SELECT user_id FROM `user` WHERE email LIKE '%@fixtest.lk')");
        $this->pdo->exec("DELETE FROM `user` WHERE email LIKE '%@fixtest.lk'");
        $this->pdo->exec("DELETE FROM product WHERE product_name LIKE 'FixTest %'");
    }

    private function userRow(string $email): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `user` WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function stock(): int
    {
        return (int)$this->pdo->query("SELECT quantity FROM product WHERE product_id = {$this->productId}")->fetchColumn();
    }

    private function order(string $method, bool $place = true): int
    {
        $res = CheckoutService::createOrder($this->userId, [['product_id' => $this->productId, 'quantity' => 3]], $method);
        $this->assertTrue($res['ok'], $res['error'] ?? '');
        if ($place) {
            $pay = CheckoutService::getGateway($method)->initiatePayment((int)$res['order_id'], (float)$res['total_price']);
            $this->assertTrue($pay['ok'], $pay['error'] ?? '');
        }
        return (int)$res['order_id'];
    }

    // 1. Email verification

    public function testNewAccountMustVerifyEmailBeforeLogin(): void
    {
        $res = RegistrationService::register('New Person', 'new@fixtest.lk', 'Password123!');
        $this->assertTrue($res['ok']);

        $row = $this->userRow('new@fixtest.lk');
        $this->assertSame('inactive', $row['status']);
        $this->assertSame(64, strlen($row['verification_token']));

        $before = Auth::attemptUser('new@fixtest.lk', 'Password123!', '10.0.0.1');
        $this->assertFalse($before['ok']);
        $this->assertStringContainsString('verify', strtolower($before['error']));

        $this->assertFalse(RegistrationService::verify('wrong-token'));
        $this->assertFalse(RegistrationService::verify(''));
        $this->assertTrue(RegistrationService::verify($row['verification_token']));
        $this->assertFalse(RegistrationService::verify($row['verification_token']), 'Token is single use');

        $this->assertTrue(Auth::attemptUser('new@fixtest.lk', 'Password123!', '10.0.0.1')['ok']);
    }

    public function testVerificationLinkCannotUnblockAnAccount(): void
    {
        RegistrationService::register('Blocked', 'blocked@fixtest.lk', 'Password123!');
        $this->pdo->exec("UPDATE `user` SET status = 'blocked' WHERE email = 'blocked@fixtest.lk'");

        $this->assertFalse(RegistrationService::verify($this->userRow('blocked@fixtest.lk')['verification_token']));
        $this->assertSame('blocked', $this->userRow('blocked@fixtest.lk')['status']);
    }

    public function testWrongPasswordDoesNotRevealAccountStatus(): void
    {
        RegistrationService::register('Hidden', 'hidden@fixtest.lk', 'Password123!');

        $res = Auth::attemptUser('hidden@fixtest.lk', 'WrongPassword', '10.0.0.2');
        $this->assertSame('Invalid email or password.', $res['error']);
    }

    // 2. Login lockout

    public function testForwardedHeaderCannotChangeTheClientIp(): void
    {
        $ip = Request::clientIp(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $this->assertSame('203.0.113.9', $ip);

        for ($i = 1; $i <= 5; $i++) {
            $spoofed = Request::clientIp(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => "10.1.1.{$i}"]);
            Auth::attemptUser('owner@fixtest.lk', 'wrong', $spoofed);
        }
        $this->assertTrue(Auth::isLockedOut('owner@fixtest.lk', '203.0.113.9'));
    }

    // 3. Stock on cancellation

    public function testAdminCancellingCodOrderReturnsStockOnce(): void
    {
        $id = $this->order('cod');
        $this->assertSame(7, $this->stock());

        $this->assertTrue(OrderService::updateStatus($id, 'Cancelled', null, null, false)['ok']);
        $this->assertSame(10, $this->stock());

        OrderService::updateStatus($id, 'Cancelled', null, null, false);
        $this->assertSame(10, $this->stock());

        $this->assertFalse(OrderService::updateStatus($id, 'Confirmed', null, null, false)['ok'], 'Cancelled is final');
        $this->assertSame(10, $this->stock());
    }

    public function testCustomerCancellationReturnsStock(): void
    {
        $id = $this->order('cod');
        $this->assertTrue(OrderService::cancelByCustomer($id, $this->userId)['ok']);
        $this->assertSame(10, $this->stock());
    }

    public function testCancellingAnOrderThatNeverTookStockAddsNone(): void
    {
        $unpaidBank = $this->order('bank_transfer');
        $failedCod = $this->order('cod', false);

        OrderService::updateStatus($unpaidBank, 'Cancelled', null, null, false);
        OrderService::cancelByCustomer($failedCod, $this->userId);

        $this->assertSame(10, $this->stock());
    }

    public function testCancellingPaidOrderReturnsStockAndFlagsRefund(): void
    {
        $id = $this->order('bank_transfer');
        CheckoutService::confirmPayment($id);
        $this->assertSame(7, $this->stock());

        $res = OrderService::updateStatus($id, 'Cancelled', null, null, false);
        $this->assertTrue($res['refund_needed']);
        $this->assertSame(10, $this->stock());
    }

    public function testDeliveredOrderCannotBeCancelled(): void
    {
        $id = $this->order('cod');
        OrderService::updateStatus($id, 'Delivered', null, null, false);

        $this->assertFalse(OrderService::updateStatus($id, 'Cancelled', null, null, false)['ok']);
        $this->assertSame(7, $this->stock());
    }

    public function testPaymentAfterCancellationTakesNoStock(): void
    {
        $id = $this->order('card', false);
        OrderService::updateStatus($id, 'Cancelled', null, null, false);

        $res = CheckoutService::confirmPayment($id);
        $this->assertTrue($res['ok']);
        $this->assertTrue($res['refund_needed']);
        $this->assertSame(10, $this->stock());
    }

    public function testConfirmingPaymentOnCodOrderDoesNotTakeStockTwice(): void
    {
        $id = $this->order('cod');
        $this->assertTrue(CheckoutService::confirmPayment($id)['ok']);
        $this->assertSame(7, $this->stock());
    }

    // 4. Translations

    public function testBothLanguageFilesHaveTheSameKeys(): void
    {
        $en = array_keys(require dirname(__DIR__) . '/lang/en.php');
        $si = array_keys(require dirname(__DIR__) . '/lang/si.php');
        $this->assertSame([], array_values(array_diff($en, $si)), 'Missing in si.php');
        $this->assertSame([], array_values(array_diff($si, $en)), 'Missing in en.php');
    }

    public function testEveryTranslationKeyUsedInCodeExists(): void
    {
        $root = dirname(__DIR__);
        $keys = require $root . '/lang/en.php';
        $files = array_merge(
            glob($root . '/templates/*.php'),
            glob($root . '/templates/*/*.php'),
            glob($root . '/src/*/*.php'),
            [$root . '/public/index.php']
        );

        $missing = [];
        foreach ($files as $file) {
            preg_match_all("/\\bt\\('([a-z0-9_]+)'\\s*[,)]/", file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                if (!isset($keys[$key])) {
                    $missing[] = basename($file) . ': ' . $key;
                }
            }
        }
        $this->assertSame([], $missing);
    }

    public function testShopPagesRenderInSinhala(): void
    {
        $orderId = $this->order('cod');
        $order = OrderService::getOrderDetails($orderId);
        $item = [
            'product_id' => $this->productId, 'product_name' => 'Balm', 'display_name' => 'බාම්', 'quantity' => 1,
            'stock' => 5, 'has_discount' => false, 'discount_percentage' => 0, 'effective_price' => 100, 'line_total' => 100,
        ];
        $zones = [['district' => 'Colombo', 'fee' => 250, 'free_over' => 4000]];
        Lang::setLocale('si');

        $html = View::render('shop/cart', ['cartItems' => [$item], 'subtotal' => 100, 'zones' => $zones], false)
            . View::render('shop/checkout', ['cartItems' => [$item], 'subtotal' => 100, 'zones' => $zones], false)
            . View::render('shop/orders', ['orders' => [$order]], false)
            . View::render('shop/order_detail', ['order' => $order], false)
            . View::render('shop/wishlist', ['products' => []], false);

        $english = [
            'Order Summary', 'Place Order', 'Shipping Details', 'Payment Method', 'My Orders', 'View Timeline',
            'Order Items', 'Order Timeline', 'Cancel Order', 'Grand Total', 'Cash on Delivery', 'My Wishlist',
            'Proceed to Checkout', 'Order placed', '>Pending<',
        ];
        foreach ($english as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
        $this->assertStringContainsString('ඇණවුම් සාරාංශය', $html);
        $this->assertStringContainsString('බලාපොරොත්තුවෙන්', $html);
    }

    // 5. Search

    public function testDidYouMeanUsesNamesFromTheDatabase(): void
    {
        $this->assertSame('zanthora', SearchService::computeDidYouMean('zanthra'));
        $this->assertNull(SearchService::computeDidYouMean('qqqqqqqqqqqq'));
    }

    public function testDidYouMeanWorksForSinhala(): void
    {
        $this->pdo->exec("UPDATE product SET product_name_si = 'පරීක්ෂාබාම්' WHERE product_id = {$this->productId}");
        $this->assertSame('පරීක්ෂාබාම්', SearchService::computeDidYouMean('පරීක්ෂාබාම'));
    }

    public function testSearchAcceptsPunctuation(): void
    {
        foreach (['neem (', '"neem', '+-neem', '@@@', 'neem*)(~<>'] as $query) {
            $this->assertArrayHasKey('total_count', SearchService::search($query), $query);
        }
    }

    // 6. Autocomplete

    public function testAutocompleteScriptNeverInjectsHtml(): void
    {
        $js = file_get_contents(dirname(__DIR__) . '/public/assets/js/search.js');
        $this->assertStringNotContainsString('innerHTML', $js);
        $this->assertStringNotContainsString('insertAdjacentHTML', $js);
    }

    // 7. Herb text

    public function testHerbWithoutReviewedTextSaysSoAndAdminCanAddIt(): void
    {
        $this->pdo->exec("INSERT INTO herb (name, scientific_name) VALUES ('FixTest Herb', 'Testus plantus')");
        $id = (int)$this->pdo->lastInsertId();

        try {
            $page = fn() => View::render('shop/herb_detail', [
                'herb' => HerbRepo::findById($id), 'products' => [], 'concerns' => [],
            ], false);

            $this->assertStringContainsString('awaiting review', $page());

            HerbRepo::updateUses($id, 'Reviewed text from admin', '');
            $this->assertStringContainsString('Reviewed text from admin', $page());
            $this->assertNull(HerbRepo::findById($id)['uses_si']);
        } finally {
            $this->pdo->exec("DELETE FROM herb WHERE herb_id = {$id}");
        }
    }
}
