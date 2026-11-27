<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\Lang;
use App\Core\View;
use App\Repository\ProductRepo;
use App\Service\CatalogService;
use App\Service\CustomerService;
use App\Service\ForecastService;
use App\Service\MlClient;
use App\Service\OrderService;
use App\Service\ReportService;
use PDO;

class Phase7AdminTest extends TestCase
{
    private PDO $pdo;
    private int $userId;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        Lang::setLocale('en');
        $_SESSION = [];
        $this->pdo = Database::getConnection();
        $this->cleanup();
        $this->userId = $this->user('p7@test.lk');
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        $_SESSION = [];
        Lang::setLocale('en');
    }

    private function cleanup(): void
    {
        $users = "SELECT user_id FROM `user` WHERE email LIKE 'p7%@test.lk'";
        $this->pdo->exec("DELETE FROM order_item WHERE order_id IN (SELECT order_id FROM orders WHERE user_id IN ({$users}))");
        $this->pdo->exec("DELETE FROM orders WHERE user_id IN ({$users})");
        $this->pdo->exec("DELETE FROM `user` WHERE email LIKE 'p7%@test.lk'");
        $this->pdo->exec("DELETE FROM discount WHERE product_id IN (SELECT product_id FROM product WHERE product_name LIKE 'P7 %')");
        $this->pdo->exec("DELETE FROM product WHERE product_name LIKE 'P7 %'");
        $this->pdo->exec("DELETE FROM category WHERE name LIKE 'P7 %'");
        $this->pdo->exec("DELETE FROM herb WHERE name LIKE 'P7 %'");
    }

    private function user(string $email): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO `user` (name, email, password, status) VALUES ('P7', ?, ?, 'active')");
        $stmt->execute([$email, password_hash('Password123!', PASSWORD_DEFAULT)]);
        return (int)$this->pdo->lastInsertId();
    }

    private function order(string $date, int $total, array $items = [1 => 1], string $status = 'Delivered', string $method = 'cod'): int
    {
        $this->pdo->exec("INSERT INTO orders (user_id, order_date, total_price, payment_status, payment_method, status)
            VALUES ({$this->userId}, '{$date}', {$total}, 'paid', '{$method}', '{$status}')");
        $order = (int)$this->pdo->lastInsertId();
        foreach ($items as $product => $quantity) {
            $this->pdo->exec("INSERT INTO order_item (order_id, product_id, quantity, unit_price, subtotal)
                VALUES ({$order}, {$product}, {$quantity}, 100, " . (100 * $quantity) . ')');
        }
        return $order;
    }

    public function testApplicationAndDatabaseUseSriLankaTime(): void
    {
        $this->assertSame('Asia/Colombo', date_default_timezone_get());
        $database = strtotime($this->pdo->query('SELECT NOW()')->fetchColumn());
        $this->assertLessThan(5, abs($database - strtotime(date('Y-m-d H:i:s'))));
    }

    public function testReportTotalsIncludeOnlySales(): void
    {
        $this->order('2025-03-01 00:10:00', 1000, [1 => 2]);
        $this->order('2025-03-01 23:50:00', 500, [17 => 1]);
        $this->order('2025-03-03 10:00:00', 900, [1 => 1], 'Cancelled');
        $this->order('2025-03-04 10:00:00', 700); // outside the range

        $report = ReportService::report('2025-03-01', '2025-03-03');

        $this->assertSame(['orders' => 2, 'revenue' => 1500.0, 'average_order_value' => 750.0], $report['summary']);
        $this->assertSame(['2025-03-01', '2025-03-02', '2025-03-03'], array_column($report['daily'], 'day'));
        $this->assertSame([1500.0, 0.0, 0.0], array_column($report['daily'], 'revenue'));
        $this->assertSame([300.0, 0.0, 0.0], array_column($report['daily'], 'products'));
        $this->assertSame(1, (int)$report['top'][0]['product_id']);
        $this->assertSame(3, array_sum(array_column($report['statuses'], 'orders')));
    }

    public function testExportsMatchTheScreen(): void
    {
        $this->order('2025-03-01 10:00:00', 1234);
        $report = ReportService::report('2025-03-01', '2025-03-01');

        $csv = ReportService::csv($report);
        $this->assertStringContainsString('2025-03-01,2025-03-01,1,1234.00,1234.00', $csv);
        $this->assertStringContainsString('1234.00', ReportService::pdfHtml($report));
        $screen = View::render('admin/reports', ['report' => $report], false);
        $this->assertStringContainsString('Rs. 1,234.00', $screen);
        $this->assertStringStartsWith('%PDF-', ReportService::pdf($report));
    }

    public function testCsvCellsCannotBecomeFormulas(): void
    {
        $this->assertSame("'=HYPERLINK(1)", ReportService::csvCell('=HYPERLINK(1)'));
        $this->assertSame("'@cmd", ReportService::csvCell('@cmd'));
        $this->assertSame('-5.00', ReportService::csvCell('-5.00'));
    }

    public function testDateRangeIsSafe(): void
    {
        [$from, $to] = ReportService::range("x' OR 1=1", null);
        $this->assertSame(date('Y-m-d'), $to);
        $this->assertSame(date('Y-m-d', strtotime('-29 days')), $from);
        $this->assertSame(['2025-01-01', '2025-01-05'], ReportService::range('2025-01-05', '2025-01-01'));
        $this->assertSame('2024-01-02', ReportService::range('1990-01-01', '2025-01-01')[0]);
    }

    public function testKpisAndLowStock(): void
    {
        $this->order(date('Y-m-d H:i:s'), 800);
        $kpis = ReportService::kpis();
        $this->assertSame(800.0, $kpis['today_revenue']);
        $this->assertGreaterThanOrEqual(1, $kpis['new_customers']);

        // 70 units a week against 50 in stock: five days left
        $this->pdo->exec("INSERT INTO product (product_name, price, quantity, category_id) VALUES ('P7 Fast', 100, 50, 1), ('P7 Few', 100, 3, 1), ('P7 Plenty', 100, 500, 1)");
        $fast = (int)$this->pdo->query("SELECT product_id FROM product WHERE product_name = 'P7 Fast'")->fetchColumn();
        $this->order(date('Y-m-d 10:00:00', strtotime('-1 day')), 100, [$fast => 70]);

        $low = array_column(ReportService::lowStock(new MlClient('')), null, 'product_name');
        $this->assertSame(5, $low['P7 Fast']['days_until_stockout']);
        $this->assertArrayHasKey('P7 Few', $low);
        $this->assertArrayNotHasKey('P7 Plenty', $low);
    }

    public function testStockOutlookUsesTheService(): void
    {
        $client = new MlClient('http://fake-p7', fn() => [200, '{"ok":true,"data":{"products":[{"product_id":1,"daily_demand":59}]}}']);
        $outlook = array_column(ForecastService::stockOutlook($client), null, 'product_id');
        $this->assertSame(2, $outlook[1]['days_until_stockout']);
    }

    public function testOrderFilters(): void
    {
        $match = $this->order('2025-04-02 12:00:00', 100, [1 => 1], 'Packed', 'bank_transfer');
        $this->order('2025-04-02 12:00:00', 100, [1 => 1], 'Packed', 'cod');
        $this->order('2025-04-09 12:00:00', 100, [1 => 1], 'Packed', 'bank_transfer');

        $found = OrderService::search(['from' => '2025-04-01', 'to' => '2025-04-02', 'status' => 'Packed', 'method' => 'bank_transfer']);
        $this->assertSame([$match], array_map('intval', array_column($found, 'order_id')));
        $this->assertGreaterThanOrEqual(3, count(OrderService::search(['status' => 'nonsense', 'from' => 'x'])));
    }

    public function testBlockingEndsAccessAndUnblockingRestores(): void
    {
        CustomerService::setBlocked($this->userId, true);
        $this->assertFalse(Auth::attemptUser('p7@test.lk', 'Password123!', '10.7.0.1')['ok']);

        CustomerService::setBlocked($this->userId, false);
        $this->assertSame('active', CustomerService::find($this->userId)['status']);

        $this->pdo->exec("UPDATE `user` SET verification_token = 'abc' WHERE user_id = {$this->userId}");
        CustomerService::setBlocked($this->userId, true);
        CustomerService::setBlocked($this->userId, false);
        $this->assertSame('inactive', CustomerService::find($this->userId)['status']);
        $this->assertFalse(CustomerService::setBlocked(999999, true));
    }

    public function testCustomerListAndProfile(): void
    {
        $this->order('2025-01-01 10:00:00', 250);
        $list = CustomerService::list('p7@test');
        $this->assertCount(1, $list);
        $this->assertSame(250.0, (float)$list[0]['spent']);
        $this->assertArrayNotHasKey('password', $list[0]);

        $this->assertFalse(CustomerService::updateProfile($this->userId, ['name' => 'x'])['ok']);
        $res = CustomerService::updateProfile($this->userId, ['name' => 'New Name', 'phone' => '0770000000', 'address' => 'Kandy', 'email' => 'other@test.lk', 'status' => 'blocked']);
        $this->assertSame(['p7@test.lk', 'active', 'New Name'], [$res['user']['email'], $res['user']['status'], $res['user']['name']]);

        $this->assertFalse(CustomerService::changePassword($this->userId, 'wrong', 'NewPassword1', 'NewPassword1')['ok']);
        $this->assertFalse(CustomerService::changePassword($this->userId, 'Password123!', 'short', 'short')['ok']);
        $this->assertFalse(CustomerService::changePassword($this->userId, 'Password123!', 'NewPassword1', 'Different1')['ok']);
        $this->assertTrue(CustomerService::changePassword($this->userId, 'Password123!', 'NewPassword1', 'NewPassword1')['ok']);
        $this->assertTrue(Auth::attemptUser('p7@test.lk', 'NewPassword1', '10.7.0.2')['ok']);
    }

    public function testProductCategoryAndDiscountManagement(): void
    {
        $category = CatalogService::saveCategory(0, ['name' => 'P7 Category']);
        $this->assertTrue($category['ok']);
        $this->assertFalse(CatalogService::saveCategory(0, ['name' => 'p7 category'])['ok']);

        $data = ['product_name' => 'P7 Item', 'category_id' => $category['id'], 'price' => '250.00', 'quantity' => 4, 'herb_ids' => ['1', '999999']];
        $this->assertFalse(CatalogService::saveProduct(0, ['price' => 0] + $data)['ok']);
        $this->assertFalse(CatalogService::saveProduct(0, ['category_id' => 999999] + $data)['ok']);
        $product = CatalogService::saveProduct(0, $data);
        $this->assertTrue($product['ok'], $product['error'] ?? '');
        $this->assertCount(1, ProductRepo::findById($product['id'])['herbs']);
        $this->assertFalse(CatalogService::deleteCategory($category['id'])['ok'], 'Category in use');

        // Editing never changes stock
        CatalogService::saveProduct($product['id'], ['price' => '300', 'quantity' => 999] + $data);
        $this->assertSame(4, (int)ProductRepo::findById($product['id'])['quantity']);

        $discount = ['product_id' => $product['id'], 'discount_percentage' => '10', 'start_date' => date('Y-m-d\TH:i', strtotime('-1 hour')), 'end_date' => date('Y-m-d\TH:i', strtotime('+1 hour'))];
        $this->assertFalse(CatalogService::saveDiscount(0, ['discount_percentage' => '95'] + $discount)['ok']);
        $this->assertFalse(CatalogService::saveDiscount(0, ['end_date' => $discount['start_date']] + $discount)['ok']);
        $this->assertTrue(CatalogService::saveDiscount(0, $discount)['ok']);
        $this->assertSame(270.0, ProductRepo::findById($product['id'])['sale_price']);

        $this->assertTrue(CatalogService::deleteProduct($product['id'])['ok']);
        $this->assertTrue(CatalogService::deleteCategory($category['id'])['ok']);
    }

    public function testOrderedProductCannotBeDeleted(): void
    {
        $this->order('2025-01-01 10:00:00', 100, [2 => 1]);
        $this->assertFalse(CatalogService::deleteProduct(2)['ok']);
        $this->assertNotNull(ProductRepo::findById(2));
    }

    public function testHerbManagement(): void
    {
        $this->assertFalse(CatalogService::saveHerb(0, ['name' => 'P7 Herb'])['ok']);
        $herb = CatalogService::saveHerb(0, ['name' => 'P7 Herb', 'scientific_name' => 'Septem testus', 'concern_ids' => ['1', '999']]);
        $this->assertTrue($herb['ok']);
        $this->assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM herb_concern WHERE herb_id = {$herb['id']}")->fetchColumn());

        CatalogService::deleteHerb($herb['id']);
        $this->assertSame(0, (int)$this->pdo->query("SELECT COUNT(*) FROM herb_concern WHERE herb_id = {$herb['id']}")->fetchColumn());
    }

    public function testHealthTopicsCanBeRenamed(): void
    {
        $before = $this->pdo->query('SELECT name_en, name_si, slug FROM health_concern WHERE concern_id = 1')->fetch();
        try {
            $this->assertFalse(\App\Repository\HerbRepo::renameConcern(1, '', 'x'));
            $this->assertTrue(\App\Repository\HerbRepo::renameConcern(1, 'New name', 'නව නම'));
            $after = $this->pdo->query('SELECT name_en, name_si, slug FROM health_concern WHERE concern_id = 1')->fetch();
            $this->assertSame(['New name', 'නව නම', $before['slug']], [$after['name_en'], $after['name_si'], $after['slug']]);
        } finally {
            \App\Repository\HerbRepo::renameConcern(1, $before['name_en'], $before['name_si']);
        }
    }

    public function testHttpsUsesItsOwnSessionCookie(): void
    {
        $this->assertSame('PHPSESSID', \App\Core\Auth::sessionName([]));
        $this->assertSame('SUWODA_SECURE', \App\Core\Auth::sessionName(['HTTPS' => 'on']));
    }

    public function testOnlyServedUploadsAreShownAsPictures(): void
    {
        $this->assertNull(image_url('../uploads/products/x.png'));
        $this->assertNull(image_url('/uploads/../../etc/passwd'));
        $this->assertNull(image_url('/uploads/products/missing.png'));
        $this->assertStringContainsString('bi-flower1', picture(null, 'x'));
    }

    public function testAdminScreensRender(): void
    {
        $html = View::render('admin/categories', ['categories' => ProductRepo::getAllCategories()], false)
            . View::render('admin/discounts', ['discounts' => CatalogService::discounts(), 'products' => ProductRepo::findAll()], false)
            . View::render('admin/users', ['users' => CustomerService::list(), 'search' => '"><script>'], false)
            . View::render('admin/user_detail', ['customer' => CustomerService::find($this->userId), 'orders' => []], false)
            . View::render('shop/profile', ['profile' => CustomerService::find($this->userId)], false)
            . View::render('admin/dashboard', ['kpis' => ReportService::kpis(), 'daily' => [], 'forecast' => [], 'top' => [], 'categories' => [], 'lowStock' => []], false);

        $this->assertStringNotContainsString('"><script>', $html);
        $this->assertStringContainsString('name="_csrf_token"', $html);
    }
}
