<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Core\Env;
use App\Core\Lang;
use App\Core\SessionGuard;
use App\Core\Auth;
use App\Service\AccountService;
use App\Service\RegistrationService;
use PDO;

/**
 * An administrator erasing a customer and everything that belongs to them.
 */
class CustomerRemovalTest extends TestCase
{
    private PDO $pdo;
    private int $adminId;
    private int $userId;
    private int $otherId;
    private int $productId;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        Lang::setLocale('en');
        $this->pdo = Database::getConnection();
        $this->cleanup();

        $hash = password_hash('AdminPass123', PASSWORD_DEFAULT);
        $this->pdo->prepare("INSERT INTO admin (name, email, password) VALUES ('Removal Admin', 'admin@removal.lk', ?)")->execute([$hash]);
        $this->adminId = (int)$this->pdo->lastInsertId();
        $this->userId = $this->user('gone@removal.lk');
        $this->otherId = $this->user('stays@removal.lk');
        $category = (int)$this->pdo->query('SELECT category_id FROM category LIMIT 1')->fetchColumn();
        $this->pdo->exec("INSERT INTO product (product_name, description, price, quantity, category_id) VALUES ('RemovalTest Balm', 'x', 500, 10, {$category})");
        $this->productId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        $_SESSION = [];
    }

    private function cleanup(): void
    {
        $this->pdo->exec("DELETE FROM orders WHERE user_id IN (SELECT user_id FROM `user` WHERE email LIKE '%@removal.lk')");
        $this->pdo->exec("DELETE r FROM review r JOIN `user` u ON u.user_id = r.user_id WHERE u.email LIKE '%@removal.lk'");
        $this->pdo->exec("DELETE c FROM cart c JOIN `user` u ON u.user_id = c.user_id WHERE u.email LIKE '%@removal.lk'");
        $this->pdo->exec("DELETE FROM `user` WHERE email LIKE '%@removal.lk'");
        $this->pdo->exec("DELETE FROM admin WHERE email LIKE '%@removal.lk'");
        $this->pdo->exec("DELETE FROM contact_message WHERE email LIKE '%@removal.lk'");
        $this->pdo->exec("DELETE FROM login_attempts WHERE email LIKE '%@removal.lk'");
        $this->pdo->exec("DELETE FROM coupon WHERE code = 'REMOVALTEST'");
        $this->pdo->exec("DELETE FROM product WHERE product_name LIKE 'RemovalTest %'");
    }

    private function user(string $email): int
    {
        $this->pdo->prepare("INSERT INTO `user` (name, email, password, status) VALUES ('Removal User', ?, 'x', 'active')")->execute([$email]);
        return (int)$this->pdo->lastInsertId();
    }

    private function order(int $userId, string $status, string $payment = 'unpaid'): int
    {
        $this->pdo->prepare("INSERT INTO orders (user_id, order_date, total_price, payment_status, payment_method, status) VALUES (?, NOW(), 500, ?, 'cod', ?)")
            ->execute([$userId, $payment, $status]);
        $id = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO order_item (order_id, product_id, quantity, unit_price, subtotal) VALUES ({$id}, {$this->productId}, 1, 500, 500)");
        $this->pdo->exec("INSERT INTO order_status_history (order_id, status, created_at) VALUES ({$id}, '{$status}', NOW())");
        return $id;
    }

    private function rows(string $sql): int
    {
        return (int)$this->pdo->query($sql)->fetchColumn();
    }

    private function remove(string $email = 'gone@removal.lk', string $password = 'AdminPass123'): array
    {
        return AccountService::purgeCustomer($this->userId, $email, $this->adminId, $password);
    }

    public function testEverythingBelongingToTheCustomerIsErased(): void
    {
        $order = $this->order($this->userId, 'Delivered', 'paid');
        $kept = $this->order($this->otherId, 'Delivered', 'paid');
        $slip = '/uploads/slips/removal_test.txt';
        $file = dirname(__DIR__) . '/public' . $slip;
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, 'x');
        $this->pdo->exec("INSERT INTO bank_slip (order_id, file_path, status) VALUES ({$order}, '{$slip}', 'verified')");
        $this->pdo->exec("INSERT INTO review (user_id, product_id, rating, comment, review_date) VALUES ({$this->userId}, {$this->productId}, 5, 'x', NOW())");
        $this->pdo->exec("INSERT INTO cart (user_id, product_id, quantity) VALUES ({$this->userId}, {$this->productId}, 1)");
        $this->pdo->exec("INSERT INTO wishlist (user_id, product_id) VALUES ({$this->userId}, {$this->productId})");
        $this->pdo->exec("INSERT INTO contact_message (name, email, subject, message) VALUES ('x', 'gone@removal.lk', 's', 'm')");
        $this->pdo->exec("INSERT INTO coupon (code, type, value, min_order, max_uses, used_count, starts_at, ends_at, active) VALUES ('REMOVALTEST', 'fixed', 10, 0, 5, 2, NOW(), NOW() + INTERVAL 1 DAY, 1)");
        $coupon = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO coupon_redemption (coupon_id, user_id, order_id, redeemed_at) VALUES ({$coupon}, {$this->userId}, {$order}, NOW())");

        $result = $this->remove('  GONE@removal.lk ');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertSame(1, $result['orders']);
        foreach (['user', 'orders', 'review', 'cart', 'wishlist', 'coupon_redemption'] as $table) {
            $this->assertSame(0, $this->rows("SELECT COUNT(*) FROM `{$table}` WHERE user_id = {$this->userId}"), $table);
        }
        foreach (['order_item', 'order_status_history', 'bank_slip'] as $table) {
            $this->assertSame(0, $this->rows("SELECT COUNT(*) FROM {$table} WHERE order_id = {$order}"), $table);
        }
        $this->assertSame(0, $this->rows("SELECT COUNT(*) FROM contact_message WHERE email = 'gone@removal.lk'"));
        $this->assertSame(1, $this->rows("SELECT used_count FROM coupon WHERE coupon_id = {$coupon}"));
        $this->assertFileDoesNotExist($file);

        // Nobody else is touched, and delivered goods do not return to stock
        $this->assertSame(1, $this->rows("SELECT COUNT(*) FROM orders WHERE order_id = {$kept}"));
        $this->assertSame(10, $this->rows("SELECT quantity FROM product WHERE product_id = {$this->productId}"));
    }

    public function testTheSameAddressCanRegisterAgain(): void
    {
        $this->assertTrue($this->remove()['ok']);
        $again = RegistrationService::register('Back Again', 'gone@removal.lk', 'Password123!');
        $this->assertTrue($again['ok'], $again['error'] ?? '');
        $this->assertNotSame($this->userId, $again['user_id']);
    }

    public function testASignedInCustomerIsSignedOut(): void
    {
        $this->pdo->exec("UPDATE `user` SET password = 'x' WHERE user_id = {$this->userId}");
        Auth::loginUser(['user_id' => $this->userId, 'email' => 'gone@removal.lk']);
        $this->assertTrue($this->remove()['ok']);
        $this->assertSame('ended', SessionGuard::enforce('/'));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testWrongPasswordOrAddressChangesNothing(): void
    {
        $wrong = $this->remove('gone@removal.lk', 'nope');
        $this->assertFalse($wrong['ok']);
        $this->assertTrue($wrong['wrong_password']);
        $this->assertSame(t('admin_user_delete_email'), $this->remove('stays@removal.lk')['error']);
        $this->assertSame(2, $this->rows("SELECT COUNT(*) FROM `user` WHERE email LIKE '%@removal.lk'"));
    }

    public function testOpenOrdersAndRefundsDueBlockRemoval(): void
    {
        $order = $this->order($this->userId, 'Pending');
        $this->assertSame(t('admin_user_delete_open'), $this->remove()['error']);

        $this->pdo->exec("UPDATE orders SET status = 'Cancelled', payment_status = 'paid' WHERE order_id = {$order}");
        $this->assertSame(t('admin_user_delete_open'), $this->remove()['error']);
        $this->assertSame(1, $this->rows("SELECT COUNT(*) FROM orders WHERE order_id = {$order}"));

        $this->pdo->exec("UPDATE orders SET payment_status = 'refunded' WHERE order_id = {$order}");
        $this->assertTrue($this->remove()['ok']);
    }

    public function testMissingCustomerIsReported(): void
    {
        $this->assertTrue($this->remove()['ok']);
        $this->assertSame(t('admin_user_delete_missing'), $this->remove()['error']);
    }
}
