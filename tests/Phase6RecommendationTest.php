<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Core\Env;
use App\Core\Lang;
use App\Core\MlGrants;
use App\Core\View;
use App\Repository\ProductRepo;
use App\Repository\QuizRepo;
use App\Repository\RecommendationRepo;
use App\Service\ForecastService;
use App\Service\MlClient;
use App\Service\RecommendationService;
use PDO;
use PDOException;

class Phase6RecommendationTest extends TestCase
{
    private PDO $pdo;
    private int $userId;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        Lang::setLocale('en');
        $this->pdo = Database::getConnection();
        $this->cleanup();
        $this->pdo->exec("INSERT INTO `user` (name, email, password, status) VALUES ('P6', 'p6@test.lk', 'x', 'active')");
        $this->userId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $users = "SELECT user_id FROM `user` WHERE email = 'p6@test.lk'";
        $this->pdo->exec("DELETE FROM order_item WHERE order_id IN (SELECT order_id FROM orders WHERE user_id IN ({$users}))");
        $this->pdo->exec("DELETE FROM orders WHERE user_id IN ({$users})");
        $this->pdo->exec("DELETE FROM user_category WHERE user_id IN ({$users})");
        $this->pdo->exec("DELETE FROM `user` WHERE email = 'p6@test.lk'");
        $this->pdo->exec('DELETE FROM product_dosha');
    }

    /** A client whose service always gives this response; records the requested URLs. */
    private function ml(int $status, array|string $body, array &$urls = []): MlClient
    {
        return new MlClient('http://fake-' . bin2hex(random_bytes(4)), function (string $url) use ($status, $body, &$urls) {
            $urls[] = $url;
            return [$status, is_string($body) ? $body : json_encode($body)];
        });
    }

    private function down(): MlClient
    {
        return new MlClient('');
    }

    private function ids(array $cards): array
    {
        return array_map('intval', array_column($cards, 'product_id'));
    }

    private function sell(array $productQuantities, string $date = 'NOW() - INTERVAL 1 DAY', string $status = 'Delivered'): void
    {
        $this->pdo->exec("INSERT INTO orders (user_id, order_date, total_price, payment_status, payment_method, status)
            VALUES ({$this->userId}, {$date}, 100, 'paid', 'cod', '{$status}')");
        $order = (int)$this->pdo->lastInsertId();
        foreach ($productQuantities as $product => $quantity) {
            $this->pdo->exec("INSERT INTO order_item (order_id, product_id, quantity, unit_price, subtotal)
                VALUES ({$order}, {$product}, {$quantity}, 100, " . (100 * $quantity) . ')');
        }
    }

    // ------------------------------------------------------------ client

    public function testClientReturnsDataOnlyForGoodResponses(): void
    {
        $this->assertSame(['a' => 1], $this->ml(200, ['ok' => true, 'data' => ['a' => 1]])->get('/x'));
        $this->assertNull($this->ml(500, ['ok' => true, 'data' => ['a' => 1]])->get('/x'));
        $this->assertNull($this->ml(200, ['ok' => false, 'error' => 'no'])->get('/x'));
        $this->assertNull($this->ml(200, '<html>')->get('/x'));
        $this->assertNull($this->down()->get('/x'));
    }

    public function testUnreachableServiceFailsQuicklyAndIsThenSkipped(): void
    {
        $client = new MlClient('http://127.0.0.1:1');
        $start = microtime(true);
        $this->assertNull($client->get('/x'));
        $this->assertLessThan(4, microtime(true) - $start);

        $calls = 0;
        $client = new MlClient('http://pause-' . bin2hex(random_bytes(4)), function () use (&$calls) {
            $calls++;
            throw new \RuntimeException('refused');
        });
        $client->get('/a');
        $client->get('/b');
        $this->assertSame(1, $calls);
    }

    // ------------------------------------------------------------ recommendations

    public function testServiceOrderIsKeptAndBadIdsAreDropped(): void
    {
        $urls = [];
        $client = $this->ml(200, ['ok' => true, 'data' => ['product_ids' => [19, 999999, 2, 19, 1]]], $urls);
        $result = RecommendationService::forUser($this->userId, null, 3, $client);

        $this->assertSame('ml', $result['source']);
        $this->assertSame([19, 2, 1], $this->ids($result['products']));
        $this->assertStringEndsWith("/recommend/user/{$this->userId}?limit=3", $urls[0]);
    }

    public function testShopStillRecommendsWhenServiceIsDown(): void
    {
        $result = RecommendationService::forUser(null, null, 4, $this->down());

        $this->assertSame('fallback', $result['source']);
        $this->assertCount(4, $result['products']);
    }

    public function testFallbackPrefersChosenCategoriesThenSales(): void
    {
        $this->sell([20 => 9, 1 => 2]);
        $this->assertSame([20, 1], $this->ids(RecommendationService::forUser(null, null, 2, $this->down())['products']));

        RecommendationRepo::saveUserCategories($this->userId, ['11']);
        $category = fn($p) => (int)$p['category_id'];
        $products = RecommendationService::forUser($this->userId, null, 2, $this->down())['products'];
        $this->assertSame([11, 11], array_map($category, $products));
    }

    public function testCancelledOrdersDoNotCountAsSales(): void
    {
        $this->sell([20 => 50], 'NOW()', 'Cancelled');
        $this->assertSame(1, RecommendationRepo::popular([], 1)[0]);
    }

    public function testSoldOutProductsAreReplaced(): void
    {
        $this->pdo->exec('UPDATE product SET quantity = 0 WHERE product_id = 19');
        try {
            $client = $this->ml(200, ['ok' => true, 'data' => ['product_ids' => [19, 2]]]);
            $ids = $this->ids(RecommendationService::forUser(null, null, 3, $client)['products']);
            $this->assertCount(3, $ids);
            $this->assertNotContains(19, $ids);
            $this->assertSame(2, $ids[0]);
        } finally {
            $this->pdo->exec('UPDATE product SET quantity = 296 WHERE product_id = 19');
        }
    }

    public function testQuizResultRanksMatchingProductsHigher(): void
    {
        QuizRepo::saveProductStrengths(18, ['kapha' => 3]);
        $scores = ['vata' => 10, 'pitta' => 10, 'kapha' => 80, 'dominant' => 'kapha', 'token' => str_repeat('6', 32)];

        // Guest: the session result leads even when the service answers
        $client = $this->ml(200, ['ok' => true, 'data' => ['product_ids' => [1, 2]]]);
        $this->assertSame(18, $this->ids(RecommendationService::forUser(null, $scores, 3, $client)['products'])[0]);

        // Member with a saved result, service down
        QuizRepo::saveResult($this->userId, $scores);
        $this->assertSame(18, $this->ids(RecommendationService::forUser($this->userId, null, 3, $this->down())['products'])[0]);
    }

    public function testProductSectionsExcludeTheProductItself(): void
    {
        $this->sell([1 => 1, 17 => 1]);
        $product = ProductRepo::findById(1);

        $fallback = RecommendationService::forProduct($product, 4, $this->down());
        $this->assertSame('fallback', $fallback['source']);
        $this->assertNotContains(1, $this->ids($fallback['similar']));
        $this->assertSame(2, $this->ids($fallback['similar'])[0], 'Same category first');
        $this->assertSame([17], $this->ids($fallback['also_bought']));

        $client = $this->ml(200, ['ok' => true, 'data' => ['similar' => [1, 12], 'also_bought' => []]]);
        $ml = RecommendationService::forProduct($product, 4, $client);
        $this->assertSame([12], $this->ids($ml['similar']));
        $this->assertSame([], $ml['also_bought']);
    }

    public function testCategoriesAreReplacedAndValidated(): void
    {
        RecommendationRepo::saveUserCategories($this->userId, ['1', '2', '2', '999', ['x']]);
        $this->assertEqualsCanonicalizing([1, 2], RecommendationRepo::userCategories($this->userId));

        RecommendationRepo::saveUserCategories($this->userId, []);
        $this->assertSame([], RecommendationRepo::userCategories($this->userId));
    }

    public function testCategoryPageRenders(): void
    {
        $html = View::render('shop/categories', ['categories' => ProductRepo::getAllCategories(), 'selected' => [2], 'flash' => null], false);
        $this->assertStringContainsString('value="2" id="interest2"', $html);
        $this->assertStringContainsString('name="_csrf_token"', $html);
    }

    // ------------------------------------------------------------ forecast

    public function testSalesForecastFallsBackToMovingAverage(): void
    {
        $this->sell([1 => 7]); // 700 within the last week
        $forecast = ForecastService::sales(30, $this->down());

        $this->assertSame('php_moving_average', $forecast['method']);
        $this->assertCount(30, $forecast['days']);
        $this->assertSame(100.0, $forecast['days'][0]['value']);
    }

    public function testProductForecastFallback(): void
    {
        $this->sell([1 => 14]);
        $forecast = ForecastService::product(1, $this->down());

        $this->assertSame(2.0, $forecast['daily_demand']);
        $this->assertSame(59, $forecast['days_until_stockout']);
        $this->assertNull(ForecastService::product(17, $this->down())['days_until_stockout']);
        $this->assertNull(ForecastService::product(999999, $this->down()));
    }

    // ------------------------------------------------------------ database rights

    public function testMlUserCannotReadCustomerData(): void
    {
        MlGrants::apply(MlGrants::rootConnection(), 'suwoda_test');
        $ml = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=suwoda_test;charset=utf8mb4', Env::get('DB_HOST', 'db'), Env::get('DB_PORT', 3306)),
            (string)Env::get('DB_ML_RO_USER', 'ml_ro'),
            (string)Env::get('DB_ML_RO_PASS', 'ml_ro_secret'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        foreach (MlGrants::TABLES as $table => $columns) {
            $ml->query('SELECT ' . ($columns ? implode(',', $columns) : '*') . " FROM `{$table}` LIMIT 1");
        }

        $forbidden = [
            'SELECT * FROM `user`', 'SELECT * FROM admin', 'SELECT shipping_address FROM orders',
            'SELECT * FROM chat_message', 'SELECT * FROM password_resets', 'SELECT comment FROM review',
            'DELETE FROM product',
        ];
        foreach ($forbidden as $sql) {
            try {
                $ml->query($sql);
                $this->fail("Should be refused: {$sql}");
            } catch (PDOException $e) {
                $this->assertStringContainsString('denied', $e->getMessage(), $sql);
            }
        }
    }
}
