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

    public static function sendPasswordReset(string $toEmail, string $toName, string $resetToken, string $lang = 'en'): bool
    {
        $resetUrl = url('reset-password?token=' . urlencode($resetToken));

        return Lang::runIn($lang, fn() => self::send(
            $toEmail,
            $toName,
            t('mail_reset_subject'),
            self::layout(
                $toName,
                [t('mail_reset_intro')],
                t('reset_title'),
                $resetUrl,
                t('mail_reset_note')
            )
        ));
    }

    public static function sendVerification(string $toEmail, string $toName, string $verificationToken, string $lang = 'en'): bool
    {
        $verifyUrl = url('verify?token=' . urlencode($verificationToken));

        return Lang::runIn($lang, fn() => self::send(
            $toEmail,
            $toName,
            t('mail_verify_subject'),
            self::layout($toName, [t('mail_verify_intro')], t('mail_verify_button'), $verifyUrl)
        ));
    }

    /**
     * Shared HTML email body in the current language. All arguments are plain text.
     */
    public static function layout(string $name, array $paragraphs, string $button, string $link, string $note = ''): string
    {
        $text = '';
        foreach ($paragraphs as $paragraph) {
            $text .= '<p>' . e($paragraph) . '</p>';
        }
        $noteHtml = $note !== '' ? "<p style='color: #666; font-size: 0.9em;'>" . e($note) . '</p>' : '';

        return "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='background-color: #198754; color: white; padding: 15px 20px; text-align: center;'>
                    <h2 style='margin: 0;'>Suwoda HerbHub</h2>
                </div>
                <div style='padding: 20px; border: 1px solid #e0e0e0;'>
                    <p>" . e(t('mail_hello', ['name' => $name])) . "</p>
                    {$text}
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='" . e($link) . "' style='background-color: #198754; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>" . e($button) . "</a>
                    </p>
                    <p style='word-break: break-all;'><a href='" . e($link) . "'>" . e($link) . "</a></p>
                    {$noteHtml}
                </div>
            </div>
        ";
    }
}
