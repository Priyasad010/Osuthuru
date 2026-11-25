<?php

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use Throwable;

class Mailer
{
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $mail = new PHPMailer(true);

        try {
            $host = Env::get('MAIL_HOST', 'mailpit');
            $port = (int)Env::get('MAIL_PORT', 1025);
            $user = Env::get('MAIL_USER', '');
            $pass = Env::get('MAIL_PASS', '');
            $from = Env::get('MAIL_FROM', 'no-reply@suwoda.lk');
            $fromName = Env::get('MAIL_FROM_NAME', 'Suwoda HerbHub');

            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->CharSet = 'UTF-8';

            if (!empty($user) && !empty($pass)) {
                $mail->SMTPAuth = true;
                $mail->Username = $user;
                $mail->Password = $pass;
                if ($port === 587) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($port === 465) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                }
            } else {
                $mail->SMTPAuth = false;
            }

            $mail->setFrom($from, $fromName);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = !empty($textBody) ? $textBody : strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (Throwable $e) {
            if (Env::get('APP_DEBUG', false)) {
                error_log("Mailer Error: " . $e->getMessage());
            }
            return false;
        }
    }

    public static function sendPasswordReset(string $toEmail, string $toName, string $resetToken): bool
    {
        $resetUrl = url('reset-password?token=' . urlencode($resetToken));
        $subject = 'Reset Your Suwoda HerbHub Password';

        $body = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='background-color: #198754; color: white; padding: 15px 20px; text-align: center;'>
                    <h2 style='margin: 0;'>Suwoda HerbHub</h2>
                </div>
                <div style='padding: 20px; border: 1px solid #e0e0e0;'>
                    <p>Hello " . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . ",</p>
                    <p>We received a request to reset your password. Click the button below to choose a new password:</p>
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='{$resetUrl}' style='background-color: #198754; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Reset Password</a>
                    </p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all;'><a href='{$resetUrl}'>{$resetUrl}</a></p>
                    <p style='color: #666; font-size: 0.9em;'>This link will expire in 30 minutes and can only be used once. If you did not request a password reset, please ignore this email.</p>
                </div>
            </div>
        ";

        return self::send($toEmail, $toName, $subject, $body);
    }

    public static function sendVerification(string $toEmail, string $toName, string $verificationToken): bool
    {
        $verifyUrl = url('verify?token=' . urlencode($verificationToken));
        $subject = 'Verify Your Suwoda HerbHub Account';

        $body = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='background-color: #198754; color: white; padding: 15px 20px; text-align: center;'>
                    <h2 style='margin: 0;'>Suwoda HerbHub</h2>
                </div>
                <div style='padding: 20px; border: 1px solid #e0e0e0;'>
                    <p>Hello " . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . ",</p>
                    <p>Thank you for registering at Suwoda HerbHub. Please confirm your email address by clicking the link below:</p>
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='{$verifyUrl}' style='background-color: #198754; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Verify Account</a>
                    </p>
                    <p>Or visit: <a href='{$verifyUrl}'>{$verifyUrl}</a></p>
                </div>
            </div>
        ";

        return self::send($toEmail, $toName, $subject, $body);
    }
}
