<?php

// Create .env on shared hosting by answering a few questions:  php bin/shared/setup_env.php
// Passwords are not shown while typing. An existing .env is never changed.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__, 2);
$target = $root . '/.env';
if (file_exists($target)) {
    fwrite(STDERR, ".env already exists; it was not changed. Edit it with: nano .env\n");
    exit(1);
}

$ask = function (string $question, callable $valid, string $default = '', bool $hidden = false): string {
    $terminal = $hidden && function_exists('stream_isatty') && stream_isatty(STDIN);
    while (true) {
        echo $question . ($default !== '' ? " [{$default}]" : '') . ': ';
        if ($terminal) {
            shell_exec('stty -echo');
        }
        $line = fgets(STDIN);
        if ($terminal) {
            shell_exec('stty echo');
            echo PHP_EOL;
        }
        if ($line === false) {
            fwrite(STDERR, "\nStopped; nothing was written.\n");
            exit(1);
        }
        $value = trim($line) !== '' ? trim($line) : $default;
        // A single quote would end the quoted value in .env
        if (!str_contains($value, "'") && $valid($value)) {
            return $value;
        }
        echo "That is not valid, please try again.\n";
    }
};
$matches = fn(string $pattern) => fn(string $v): bool => preg_match($pattern, $v) === 1;
$isEmail = fn(string $v): bool => filter_var($v, FILTER_VALIDATE_EMAIL) !== false;

$account = $ask('alwaysdata account name', $matches('/^[a-z0-9][a-z0-9-]*$/'));
$url = $ask('Website address', $matches('#^https://[a-z0-9.-]+$#'), "https://{$account}.alwaysdata.net");
$dbName = $ask('Database name', $matches('/^[A-Za-z0-9_-]+$/'), "{$account}_osuthuru");
$dbUser = $ask('Database user', $matches('/^[A-Za-z0-9_-]+$/'), $account);
$dbPass = $ask('Database password', fn(string $v): bool => $v !== '', '', true);
$admin = $ask('Your e-mail address (administrator sign-in)', $isEmail);
$mailUser = $ask('Gmail address that sends the shop e-mail', $isEmail, $admin);
$mailPass = str_replace(' ', '', $ask('Gmail app password (16 letters)', fn(string $v): bool => preg_match('/^[a-zA-Z]{16}$/', str_replace(' ', '', $v)) === 1, '', true));
$llm = $ask('Gemini API key (press Enter to skip)', fn(string $v): bool => true, '', true);

$adminPassword = 'A1-' . bin2hex(random_bytes(10));
$values = [
    'APP_URL' => $url,
    'APP_KEY' => bin2hex(random_bytes(32)),
    'DB_HOST' => "mysql-{$account}.alwaysdata.net",
    'DB_NAME' => $dbName,
    'DB_USER' => $dbUser,
    'DB_PASS' => $dbPass,
    'ADMIN_EMAIL' => $admin,
    'ADMIN_PASSWORD' => $adminPassword,
    'MAIL_USER' => $mailUser,
    'MAIL_PASS' => $mailPass,
    'MAIL_FROM' => $mailUser,
    'LLM_API_KEY' => $llm,
];

$text = str_replace("\r\n", "\n", (string)file_get_contents($root . '/.env.alwaysdata.example'));
foreach ($values as $name => $value) {
    $line = $value === '' ? "{$name}=" : "{$name}='{$value}'";
    $text = preg_replace_callback("/^{$name}=.*$/m", fn() => $line, $text, 1, $count);
    if ($count !== 1) {
        fwrite(STDERR, "The settings template has no {$name} line.\n");
        exit(1);
    }
}
umask(0077);
file_put_contents($target, $text);
chmod($target, 0600);

echo PHP_EOL . ".env created.\n";
echo "Administrator sign-in: {$admin}\n";
echo "Administrator password: {$adminPassword}\n";
echo "Write it down now, and change it after the first sign-in.\n";
