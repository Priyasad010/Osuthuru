<?php

namespace App\Service;

use App\Core\Database;
use App\Core\Mailer;
use App\Core\SessionGuard;
use PDO;
use Throwable;

class PasswordResetService
{
    public static function requestReset(string $email): array
    {
        $cleanEmail = strtolower(trim($email));

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT user_id, name, email, lang FROM `user` WHERE LOWER(email) = :email LIMIT 1");
        $stmt->execute([':email' => $cleanEmail]);
        $user = $stmt->fetch();

        if (!$user) {
            // For security, do not reveal if the email exists
            return [
                'ok' => true,
                'message' => t('msg_reset_sent')
            ];
        }

        // Generate raw token and hash
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        try {
            // Invalidate any prior unused tokens for this user
            $del = $pdo->prepare("DELETE FROM password_resets WHERE user_id = :user_id AND used_at IS NULL");
            $del->execute([':user_id' => $user['user_id']]);

            // Insert new reset token valid for 30 minutes
            $ins = $pdo->prepare("
                INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
                VALUES (:user_id, :token_hash, (NOW() + INTERVAL 30 MINUTE), NOW())
            ");
            $ins->execute([
                ':user_id' => $user['user_id'],
                ':token_hash' => $tokenHash
            ]);

            // Send reset email
            Mailer::sendPasswordReset($user['email'], $user['name'] ?? '', $rawToken, (string)($user['lang'] ?? 'en'));

            return [
                'ok' => true,
                'message' => t('msg_reset_sent')
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => t('err_reset_generate')];
        }
    }

    public static function validateToken(string $rawToken): array
    {
        if (empty($rawToken)) {
            return ['ok' => false, 'error' => t('err_reset_missing')];
        }

        $tokenHash = hash('sha256', $rawToken);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT pr.*, u.email, u.name 
            FROM password_resets pr
            JOIN `user` u ON u.user_id = pr.user_id
            WHERE pr.token_hash = :token_hash
            LIMIT 1
        ");
        $stmt->execute([':token_hash' => $tokenHash]);
        $reset = $stmt->fetch();

        if (!$reset) {
            return ['ok' => false, 'error' => t('err_reset_invalid')];
        }

        if (!empty($reset['used_at'])) {
            return ['ok' => false, 'error' => t('err_reset_used')];
        }

        if (strtotime($reset['expires_at']) < time()) {
            return ['ok' => false, 'error' => t('err_reset_expired')];
        }

        return ['ok' => true, 'reset' => $reset];
    }

    public static function resetPassword(string $rawToken, string $newPassword): array
    {
        $validation = self::validateToken($rawToken);
        if (!$validation['ok']) {
            return $validation;
        }

        $reset = $validation['reset'];
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            // 1. Update user password hash
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE `user` SET password = :password WHERE user_id = :user_id");
            $stmt->execute([
                ':password' => $newHash,
                ':user_id' => $reset['user_id']
            ]);

            // 2. Mark token as used
            $mark = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = :id");
            $mark->execute([':id' => $reset['id']]);

            $pdo->commit();

            // Whoever was signed in with the old password is signed out
            SessionGuard::revoke('user', (int)$reset['user_id']);

            return ['ok' => true, 'message' => t('msg_reset_done')];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => t('err_reset_failed')];
        }
    }
}
