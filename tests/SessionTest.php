<?php

namespace Tests;

use App\Core\Database;
use App\Core\Env;
use App\Core\SessionGuard;
use App\Service\AccountService;
use App\Service\CustomerService;
use App\Service\PasswordResetService;
use PDO;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    private PDO $pdo;
    private int $userId;
    private int $adminId;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        $this->pdo = Database::getConnection();
        $this->cleanup();
        $this->pdo->prepare("INSERT INTO `user` (name, email, password, status) VALUES ('Session', 'session@test.lk', ?, 'active')")
            ->execute([password_hash('Password123', PASSWORD_DEFAULT)]);
        $this->userId = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO admin (name, email, password) VALUES ('Session', 'sessionadmin@test.lk', ?)")
            ->execute([password_hash('Password123', PASSWORD_DEFAULT)]);
        $this->adminId = (int)$this->pdo->lastInsertId();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->pdo->exec("DELETE FROM user WHERE email = 'session@test.lk'");
        $this->pdo->exec("DELETE FROM admin WHERE email = 'sessionadmin@test.lk'");
    }

    /** A signed-in browser, as the application stores it. */
    private function signIn(string $role, int $now = 1000000): array
    {
        $id = $role === 'user' ? $this->userId : $this->adminId;
        $_SESSION = ["{$role}_id" => $id, $role => ['name' => 'Session']];
        SessionGuard::start($role, $id, $now);
        return $_SESSION;
    }

    private function signedIn(array $session, string $role, int $now = 1000060, string $path = '/'): bool
    {
        $_SESSION = $session;
        SessionGuard::enforce($path, $now);
        return isset($_SESSION["{$role}_id"]);
    }

    public function testActiveSessionStaysSignedIn(): void
    {
        $this->signIn('user');
        foreach ([3600, 7200, 14000, 21000] as $later) {
            $this->assertNull(SessionGuard::enforce('/', 1000000 + $later), (string)$later);
        }
        $this->assertSame($this->userId, $_SESSION['user_id']);
    }

    public function testIdleAndOldSessionsExpire(): void
    {
        $session = $this->signIn('user');
        $session['safety_answers'] = ['pregnant'];
        $session['quiz_result'] = ['vata' => 1];
        $session['guest_cart'] = [1 => 1];
        $_SESSION = $session;
        $this->assertSame('expired', SessionGuard::enforce('/', 1000000 + SessionGuard::USER_IDLE + 1));
        $this->assertSame(['guest_cart'], array_keys($_SESSION), 'Personal answers leave with the sign-in');

        $this->signIn('user');
        $now = 1000000;
        // Used every hour, but never for longer than a day
        while ($now + 3600 - 1000000 <= SessionGuard::MAX_AGE) {
            $this->assertNull(SessionGuard::enforce('/', $now += 3600));
        }
        $this->assertSame('expired', SessionGuard::enforce('/', $now + 3600));
    }

    public function testAdminIdleLimitIsNotExtendedByTheShop(): void
    {
        $this->signIn('admin');
        SessionGuard::enforce('/products', 1000000 + 1200);
        $this->assertSame('expired', SessionGuard::enforce('/admin/dashboard', 1000000 + SessionGuard::ADMIN_IDLE + 1));

        $this->signIn('admin');
        SessionGuard::enforce('/admin/orders', 1000000 + 1200);
        $this->assertNull(SessionGuard::enforce('/admin/dashboard', 1000000 + 2400));
    }

    public function testSessionsWithoutRecordsAreEnded(): void
    {
        $_SESSION = ['user_id' => $this->userId];
        $this->assertSame('ended', SessionGuard::enforce('/'));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testPasswordChangeEndsOtherDevicesOnly(): void
    {
        $other = $this->signIn('user');
        $this->signIn('user');
        $this->assertTrue(CustomerService::changePassword($this->userId, 'Password123', 'NewPassword9', 'NewPassword9')['ok']);
        $current = $_SESSION;

        $this->assertTrue($this->signedIn($current, 'user'), 'The device that changed it stays');
        $this->assertFalse($this->signedIn($other, 'user'));
    }

    public function testPasswordResetEndsEveryDevice(): void
    {
        $device = $this->signIn('user');
        $_SESSION = [];
        $token = bin2hex(random_bytes(32));
        $this->pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, NOW() + INTERVAL 30 MINUTE, NOW())')
            ->execute([$this->userId, hash('sha256', $token)]);
        $this->assertTrue(PasswordResetService::resetPassword($token, 'NewPassword9')['ok']);
        $this->assertFalse($this->signedIn($device, 'user'));
    }

    public function testBlockedAccountStaysSignedOutAfterUnblocking(): void
    {
        $device = $this->signIn('user');
        CustomerService::setBlocked($this->userId, true);
        $this->assertFalse($this->signedIn($device, 'user'));
        CustomerService::setBlocked($this->userId, false);
        $this->assertFalse($this->signedIn($device, 'user'));
    }

    public function testRemovedAdminAndChangedAdminPasswordEndSessions(): void
    {
        $other = $this->signIn('admin');
        $this->signIn('admin');
        $this->assertTrue(AccountService::changeAdminPassword($this->adminId, 'Password123', 'NewPassword9', 'NewPassword9')['ok']);
        $current = $_SESSION;
        $this->assertTrue($this->signedIn($current, 'admin'));
        $this->assertFalse($this->signedIn($other, 'admin'));

        $this->pdo->exec("DELETE FROM admin WHERE admin_id = {$this->adminId}");
        $this->assertFalse($this->signedIn($current, 'admin'));
    }

    public function testSigningOutOtherDevices(): void
    {
        $other = $this->signIn('user');
        $this->signIn('user');
        SessionGuard::revoke('user', $this->userId, true);
        $current = $_SESSION;
        $this->assertTrue($this->signedIn($current, 'user'));
        $this->assertFalse($this->signedIn($other, 'user'));
    }
}
