<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Core\Env;
use App\Core\Lang;
use App\Core\View;
use App\Repository\BlogRepo;
use App\Repository\SeasonalRepo;
use App\Service\ReviewService;
use DateTimeImmutable;
use PDO;

class Phase7ContentTest extends TestCase
{
    private PDO $pdo;
    private int $buyer;
    private int $other;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        Lang::setLocale('en');
        $_SESSION = [];
        $this->pdo = Database::getConnection();
        $this->cleanup();
        $this->buyer = $this->user('p7b1@test.lk');
        $this->other = $this->user('p7b2@test.lk');
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        $_SESSION = [];
        Lang::setLocale('en');
    }

    private function cleanup(): void
    {
        $users = "SELECT user_id FROM `user` WHERE email LIKE 'p7b%@test.lk'";
        $this->pdo->exec("DELETE FROM review WHERE user_id IN ({$users})");
        $this->pdo->exec("DELETE FROM order_item WHERE order_id IN (SELECT order_id FROM orders WHERE user_id IN ({$users}))");
        $this->pdo->exec("DELETE FROM orders WHERE user_id IN ({$users})");
        $this->pdo->exec("DELETE FROM `user` WHERE email LIKE 'p7b%@test.lk'");
        $this->pdo->exec("DELETE FROM blog WHERE title LIKE 'P7 %'");
        $this->pdo->exec("DELETE FROM blog_tag WHERE name_en LIKE 'P7 %'");
        $this->pdo->exec('DELETE FROM seasonal_collection');
    }

    private function user(string $email): int
    {
        $this->pdo->exec("INSERT INTO `user` (name, email, password, status) VALUES ('P7B', '{$email}', 'x', 'active')");
        return (int)$this->pdo->lastInsertId();
    }

    private function order(int $user, int $product, string $status): void
    {
        $this->pdo->exec("INSERT INTO orders (user_id, order_date, total_price, payment_status, payment_method, status) VALUES ({$user}, NOW(), 100, 'paid', 'cod', '{$status}')");
        $order = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO order_item (order_id, product_id, quantity, unit_price, subtotal) VALUES ({$order}, {$product}, 1, 100, 100)");
    }

    // ------------------------------------------------------------ reviews

    public function testOnlyDeliveredBuyersCanReviewOnce(): void
    {
        $this->order($this->buyer, 1, 'Packed');
        $this->assertFalse(ReviewService::add($this->buyer, 1, 5, 'Early')['ok']);

        $this->order($this->buyer, 1, 'Delivered');
        $this->assertFalse(ReviewService::add($this->buyer, 1, 6, 'Bad rating')['ok']);
        $this->assertFalse(ReviewService::add($this->buyer, 1, 5, '   ')['ok']);
        $this->assertFalse(ReviewService::add($this->buyer, 17, 5, 'Not bought')['ok']);
        $this->assertTrue(ReviewService::add($this->buyer, 1, 4, 'Good')['ok']);
        $this->assertFalse(ReviewService::add($this->buyer, 1, 5, 'Again')['ok']);
        $this->assertFalse(ReviewService::add($this->other, 1, 5, 'Never ordered')['ok']);
    }

    public function testVerifiedBadgeRequiresDeliveredOrder(): void
    {
        $this->order($this->buyer, 1, 'Delivered');
        ReviewService::add($this->buyer, 1, 5, 'Real');
        // A review without any delivered order (for example imported from V1)
        $this->pdo->exec("INSERT INTO review (review_id, user_id, product_id, rating, comment, review_date) VALUES (990001, {$this->other}, 1, 3, 'Imported', NOW())");
        $this->order($this->other, 1, 'Out for Delivery');

        $byUser = array_column(ReviewService::forProduct(1), null, 'user_id');
        $this->assertSame(1, (int)$byUser[$this->buyer]['verified']);
        $this->assertSame(0, (int)$byUser[$this->other]['verified']);

        $html = View::partial('reviews', ['productId' => 1]);
        $this->assertSame(1, substr_count($html, 'Verified purchase'));
    }

    public function testHelpfulVotesToggleAndExcludeTheAuthor(): void
    {
        $this->order($this->buyer, 1, 'Delivered');
        $id = ReviewService::add($this->buyer, 1, 5, '<script>x</script>')['review_id'];
        $count = fn() => (int)ReviewService::forProduct(1, $this->other)[0]['helpful'];

        ReviewService::toggleHelpful($id, $this->buyer);
        $this->assertSame(0, $count());
        $this->assertSame(1, ReviewService::toggleHelpful($id, $this->other));
        $this->assertSame(1, $count());
        ReviewService::toggleHelpful($id, $this->other);
        $this->assertSame(0, $count());
        $this->assertNull(ReviewService::toggleHelpful(999999, $this->other));
        $this->assertStringNotContainsString('<script>x', View::partial('reviews', ['productId' => 1]));
    }

    public function testUpToThreePhotosAreStoredAndRemoved(): void
    {
        $this->order($this->buyer, 2, 'Delivered');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAIBpaXUtAAAAABJRU5ErkJggg==');
        $file = function () use ($png) {
            $tmp = tempnam(sys_get_temp_dir(), 'rv');
            file_put_contents($tmp, $png);
            return ['name' => 'a.png', 'tmp_name' => $tmp, 'size' => strlen($png), 'error' => UPLOAD_ERR_OK];
        };

        $this->assertFalse(ReviewService::add($this->buyer, 2, 5, 'x', [$file(), $file(), $file(), $file()])['ok']);
        $res = ReviewService::add($this->buyer, 2, 5, 'x', [$file(), $file(), $file()]);
        $this->assertTrue($res['ok'], $res['error'] ?? '');

        $photos = ReviewService::forProduct(2)[0]['photos'];
        $this->assertCount(3, $photos);
        $path = dirname(__DIR__) . '/public' . $photos[0];
        $this->assertFileExists($path);
        ReviewService::delete($res['review_id']);
        $this->assertFileDoesNotExist($path);
    }

    public function testUploadFieldIsSplitIntoFiles(): void
    {
        $field = ['name' => ['a', ''], 'tmp_name' => ['/t', ''], 'size' => [1, 0], 'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE]];
        $this->assertCount(1, ReviewService::files($field));
        $this->assertSame([], ReviewService::files([]));
    }

    // ------------------------------------------------------------ blog

    public function testBlogPostsHaveTagsAndRelatedProducts(): void
    {
        $this->assertTrue(BlogRepo::addTag('P7 Tag', 'ටැග්')['ok']);
        $this->assertFalse(BlogRepo::addTag('P7 Tag', '')['ok']);
        $tag = (int)$this->pdo->query("SELECT tag_id FROM blog_tag WHERE name_en = 'P7 Tag'")->fetchColumn();

        $this->assertFalse(BlogRepo::save(0, ['title' => 'P7 No text'])['ok']);
        $res = BlogRepo::save(0, ['title' => 'P7 Post', 'content' => 'Body <b>x</b>', 'title_si' => 'ලිපිය', 'tag_ids' => [$tag, 999], 'product_ids' => ['1', '999999']]);
        $this->assertTrue($res['ok']);

        $post = BlogRepo::find($res['id']);
        $this->assertSame([1], $post['product_ids']);
        $this->assertCount(1, $post['tags']);
        $this->assertSame('ලිපිය', BlogRepo::find($res['id'], 'si')['display_title']);
        $this->assertSame([$res['id']], array_map('intval', array_column(BlogRepo::all($tag), 'blog_id')));

        $html = View::render('blog/post', ['post' => $post], false);
        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertStringContainsString('/products/1', $html);
        $this->assertStringContainsString('Disclaimer', $html);

        BlogRepo::delete($res['id']);
        $this->assertNull(BlogRepo::find($res['id']));
    }

    // ------------------------------------------------------------ seasonal

    public function testSeasonRangesIncludingNewYear(): void
    {
        $this->assertTrue(SeasonalRepo::covers('0501', '0731', '0501'));
        $this->assertFalse(SeasonalRepo::covers('0501', '0731', '0801'));
        $this->assertTrue(SeasonalRepo::covers('1115', '0131', '1231'));
        $this->assertTrue(SeasonalRepo::covers('1115', '0131', '0101'));
        $this->assertFalse(SeasonalRepo::covers('1115', '0131', '0601'));
    }

    public function testCurrentSeasonShowsItsTopicProducts(): void
    {
        $digestion = (int)$this->pdo->query("SELECT concern_id FROM health_concern WHERE slug = 'immunity'")->fetchColumn();
        $data = ['name_en' => 'P7 Season', 'start' => '05-01', 'end' => '07-31', 'concern_id' => $digestion];

        $this->assertFalse(SeasonalRepo::save(0, ['start' => '13-01'] + $data)['ok']);
        $this->assertFalse(SeasonalRepo::save(0, ['concern_id' => 999] + $data)['ok']);
        $this->assertTrue(SeasonalRepo::save(0, $data)['ok']);

        $this->assertNull(SeasonalRepo::current(new DateTimeImmutable('2026-04-30')));
        $season = SeasonalRepo::current(new DateTimeImmutable('2026-06-15'));
        $this->assertSame('P7 Season', $season['display_name']);
        $shown = array_map('intval', array_column($season['products'], 'product_id'));
        $linked = array_map('intval', $this->pdo->query("SELECT DISTINCT ph.product_id FROM product_herb ph JOIN herb_concern hc ON hc.herb_id = ph.herb_id WHERE hc.concern_id = {$digestion}")->fetchAll(\PDO::FETCH_COLUMN));
        $this->assertNotEmpty($shown);
        $this->assertLessThanOrEqual(4, count($shown));
        $this->assertSame([], array_diff($shown, $linked));
    }
}
