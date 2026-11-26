<?php

namespace App\Service;

use App\Core\Database;
use App\Core\Lang;
use App\Core\Mailer;

class RegistrationService
{
    /**
     * Create an inactive account and email its verification link.
     */
    public static function register(string $name, string $email, string $password, string $phone = ''): array
    {
        $pdo = Database::getConnection();
        $name = trim($name);
        $email = strtolower(trim($email));

        $stmt = $pdo->prepare("SELECT user_id FROM `user` WHERE LOWER(email) = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => t('err_email_taken')];
        }

        $token = bin2hex(random_bytes(32));

        $ins = $pdo->prepare("
            INSERT INTO `user` (name, email, password, phone, status, lang, verification_token)
            VALUES (:name, :email, :password, :phone, 'inactive', :lang, :token)
        ");
        $ins->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':phone' => trim($phone),
            ':lang' => Lang::getLocale(),
            ':token' => $token
        ]);
        $userId = (int)$pdo->lastInsertId();

        $mailSent = Mailer::sendVerification($email, $name, $token, Lang::getLocale());

        return ['ok' => true, 'user_id' => $userId, 'mail_sent' => $mailSent];
    }

    /**
     * Activate the inactive account that owns this token. Blocked accounts are never changed.
     */
    public static function verify(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE `user` SET status = 'active', verification_token = NULL
            WHERE verification_token = :token AND status = 'inactive'
        ");
        $stmt->execute([':token' => $token]);

        return $stmt->rowCount() === 1;
    }

    public static function saveLanguage(int $userId, string $lang): void
    {
        if (!in_array($lang, ['en', 'si'], true)) {
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE `user` SET lang = :lang WHERE user_id = :id");
        $stmt->execute([':lang' => $lang, ':id' => $userId]);
    }

    public static function resendVerification(array $user): bool
    {
        $token = (string)($user['verification_token'] ?? '');

        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $pdo = Database::getConnection();
            $upd = $pdo->prepare("UPDATE `user` SET verification_token = :token WHERE user_id = :id");
            $upd->execute([':token' => $token, ':id' => $user['user_id']]);
        }

        return Mailer::sendVerification(
            (string)$user['email'],
            (string)($user['name'] ?? ''),
            $token,
            (string)($user['lang'] ?? 'en')
        );
    }
}
