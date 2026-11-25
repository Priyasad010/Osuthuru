<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Env;
use App\Core\Database;
use App\Service\PasswordResetService;
use PDO;

class PasswordResetTest extends TestCase
{
    private PDO $pdo;
    private int $testUserId;
    private string $testEmail = 'resettest@suwoda.lk';

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        $this->pdo = Database::getConnection();

        $this->pdo->exec("DELETE FROM `user` WHERE email = '{$this->testEmail}'");
        $hash = password_hash('OldPassword123', PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO `user` (name, email, password, status) VALUES ('Reset Tester', :email, :pass, 'active')");
        $stmt->execute([':email' => $this->testEmail, ':pass' => $hash]);
        $this->testUserId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare("DELETE FROM `password_resets` WHERE user_id = :uid")->execute([':uid' => $this->testUserId]);
        $this->pdo->prepare("DELETE FROM `user` WHERE user_id = :uid")->execute([':uid' => $this->testUserId]);
    }

    public function testValidTokenResetsPasswordAndCannotBeReused(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $ins = $this->pdo->prepare("
            INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
            VALUES (:uid, :hash, (NOW() + INTERVAL 30 MINUTE), NOW())
        ");
        $ins->execute([':uid' => $this->testUserId, ':hash' => $tokenHash]);

        // 1. Token validates
        $val = PasswordResetService::validateToken($rawToken);
        $this->assertTrue($val['ok']);

        // 2. Reset password succeeds
        $res = PasswordResetService::resetPassword($rawToken, 'NewSecurePass456!');
        $this->assertTrue($res['ok']);

        // 3. Password in DB is updated and hashed
        $newDbHash = $this->pdo->query("SELECT password FROM `user` WHERE user_id = {$this->testUserId}")->fetchColumn();
        $this->assertTrue(password_verify('NewSecurePass456!', $newDbHash));

        // 4. Reusing the same token is rejected
        $reuse = PasswordResetService::resetPassword($rawToken, 'AnotherPass789!');
        $this->assertFalse($reuse['ok']);
        $this->assertStringContainsString('already been used', $reuse['error']);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        // Insert an already expired token (1 hour ago)
        $ins = $this->pdo->prepare("
            INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
            VALUES (:uid, :hash, (NOW() - INTERVAL 60 MINUTE), NOW())
        ");
        $ins->execute([':uid' => $this->testUserId, ':hash' => $tokenHash]);

        $val = PasswordResetService::validateToken($rawToken);
        $this->assertFalse($val['ok']);
        $this->assertStringContainsString('expired', strtolower($val['error']));
    }
}
