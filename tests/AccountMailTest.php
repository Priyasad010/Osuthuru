<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\Lang;
use App\Core\Mailer;
use App\Core\View;
use App\Service\PasswordResetService;
use App\Service\RegistrationService;
use PDO;

/**
 * Confirming an account by e-mail, including when the message cannot be sent.
 */
class AccountMailTest extends TestCase
{
    private PDO $pdo;
    private array $env;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        Lang::setLocale('en');
        $this->env = $_ENV;
        $this->pdo = Database::getConnection();
        $this->cleanup();
        RegistrationService::register('Mail Person', 'person@mailtest.lk', 'Password123!');
    }

    protected function tearDown(): void
    {
        $_ENV = $this->env;
        $_SESSION = [];
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->pdo->exec("DELETE FROM login_attempts WHERE email LIKE '%@mailtest.lk'");
        $this->pdo->exec("DELETE FROM `user` WHERE email LIKE '%@mailtest.lk'");
    }

    private function row(): array
    {
        return $this->pdo->query("SELECT * FROM `user` WHERE email = 'person@mailtest.lk'")->fetch(PDO::FETCH_ASSOC);
    }

    private function resetToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, NOW() + INTERVAL 30 MINUTE, NOW())");
        $stmt->execute([$this->row()['user_id'], hash('sha256', $token)]);
        return $token;
    }

    public function testSignInDoesNotClaimAnEmailWasSentWhenItWasNot(): void
    {
        $sent = Auth::attemptUser('person@mailtest.lk', 'Password123!', '10.9.0.1');
        $this->assertSame(t('err_unverified'), $sent['error']);

        $_ENV['MAIL_HOST'] = '127.0.0.1';
        $_ENV['MAIL_PORT'] = '1';
        $log = (string)ini_set('error_log', '/dev/null');
        $failed = Auth::attemptUser('person@mailtest.lk', 'Password123!', '10.9.0.1');
        ini_set('error_log', $log);

        $this->assertSame(t('err_unverified_mail_failed'), $failed['error']);
        $this->assertTrue($failed['unverified']);
        $this->assertNotSame('', Mailer::$lastError);
    }

    public function testTestInboxIsPointedOutOnlyWhenMailStaysOnThisComputer(): void
    {
        $this->assertSame('http://localhost:8025', Mailer::testInboxUrl());
        $this->assertStringContainsString(
            'http://localhost:8025',
            View::render('auth/login', ['mailInbox' => Mailer::testInboxUrl()])
        );

        $_ENV['MAIL_HOST'] = 'smtp.example.org';
        $this->assertNull(Mailer::testInboxUrl());

        $_ENV['MAIL_HOST'] = 'mailpit';
        $_ENV['APP_ENV'] = 'production';
        $this->assertNull(Mailer::testInboxUrl());
        $this->assertStringNotContainsString('8025', View::render('auth/login', []));
    }

    public function testVerifyingLiftsTheLockFromEarlierAttempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Auth::recordAttempt('person@mailtest.lk', '10.9.0.2', false);
        }
        $this->assertTrue(RegistrationService::verify($this->row()['verification_token']));
        $this->assertTrue(Auth::attemptUser('person@mailtest.lk', 'Password123!', '10.9.0.2')['ok']);
    }

    public function testPasswordResetAlsoConfirmsTheAddress(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Auth::recordAttempt('person@mailtest.lk', '10.9.0.3', false);
        }
        $this->assertTrue(PasswordResetService::resetPassword($this->resetToken(), 'Changed123!')['ok']);

        $this->assertSame('active', $this->row()['status']);
        $this->assertNull($this->row()['verification_token']);
        $this->assertTrue(Auth::attemptUser('person@mailtest.lk', 'Changed123!', '10.9.0.3')['ok']);
    }

    public function testPasswordResetNeverUnblocksAnAccount(): void
    {
        $this->pdo->exec("UPDATE `user` SET status = 'blocked' WHERE email = 'person@mailtest.lk'");
        $this->assertTrue(PasswordResetService::resetPassword($this->resetToken(), 'Changed123!')['ok']);

        $this->assertSame('blocked', $this->row()['status']);
        $this->assertFalse(Auth::attemptUser('person@mailtest.lk', 'Changed123!', '10.9.0.4')['ok']);
    }
}
