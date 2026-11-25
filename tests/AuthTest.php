<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Env;
use App\Core\Auth;
use App\Core\Database;

class AuthTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        $this->pdo = Database::getConnection();

        // Clear test users and login attempts
        $this->pdo->exec("DELETE FROM `login_attempts` WHERE email LIKE '%@authtest.com'");
        $this->pdo->exec("DELETE FROM `user` WHERE email LIKE '%@authtest.com'");
    }

    public function testPasswordOnlyStoresHash(): void
    {
        $plainPass = 'SecurePassword123!';
        $hash = password_hash($plainPass, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("
            INSERT INTO `user` (name, email, password, status)
            VALUES ('Auth User', 'test@authtest.com', :pass, 'active')
        ");
        $stmt->execute([':pass' => $hash]);

        $check = $this->pdo->query("SELECT password FROM `user` WHERE email = 'test@authtest.com'")->fetchColumn();
        $this->assertNotEquals($plainPass, $check);
        $this->assertTrue(password_verify($plainPass, $check));
        $this->assertFalse(password_verify('WrongPassword', $check));
    }

    public function testLoginThrottlingLocksAfterFiveFailedAttempts(): void
    {
        $testEmail = 'throttle@authtest.com';
        $testIp = '192.168.1.100';

        // Pre-create active user
        $hash = password_hash('CorrectPassword123', PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO `user` (name, email, password, status) VALUES ('Throttle Test', :email, :pass, 'active')");
        $stmt->execute([':email' => $testEmail, ':pass' => $hash]);

        $this->assertFalse(Auth::isLockedOut($testEmail, $testIp));

        // 4 failed attempts -> still not locked out
        for ($i = 0; $i < 4; $i++) {
            $res = Auth::attemptUser($testEmail, 'WrongPassword', $testIp);
            $this->assertFalse($res['ok']);
            $this->assertFalse(Auth::isLockedOut($testEmail, $testIp));
        }

        // 5th failed attempt -> account locked
        $res5 = Auth::attemptUser($testEmail, 'WrongPassword', $testIp);
        $this->assertFalse($res5['ok']);
        $this->assertTrue(Auth::isLockedOut($testEmail, $testIp));

        // Even with correct password, now rejected because locked out
        $lockedAttempt = Auth::attemptUser($testEmail, 'CorrectPassword123', $testIp);
        $this->assertFalse($lockedAttempt['ok']);
        $this->assertStringContainsString('locked', strtolower($lockedAttempt['error']));
    }
}
