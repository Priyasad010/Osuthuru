<?php

// Send one test message with the MAIL_ settings in .env:  php bin/test_mail.php you@example.com
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Mailer;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
Env::load(dirname(__DIR__));

$to = (string)($argv[1] ?? '');
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/test_mail.php you@example.com\n");
    exit(1);
}

echo 'Mail server: ' . Env::get('MAIL_HOST', 'mailpit') . ':' . Env::get('MAIL_PORT', 1025) . PHP_EOL;
if (!Mailer::send($to, '', 'Osuthuru test e-mail', '<p>E-mail from your shop is working.</p>')) {
    fwrite(STDERR, 'Not sent: ' . Mailer::$lastError . PHP_EOL);
    exit(1);
}
echo 'Sent.' . (Mailer::testInboxUrl() !== null ? ' It is in the test inbox: ' . Mailer::testInboxUrl() : '') . PHP_EOL;
