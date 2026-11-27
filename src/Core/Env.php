<?php

namespace App\Core;

use Dotenv\Dotenv;

class Env
{
    private static bool $loaded = false;

    public static function load(string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        $rootPath = $path ?? dirname(__DIR__, 2);

        if (file_exists($rootPath . '/.env')) {
            if (class_exists(Dotenv::class)) {
                $dotenv = Dotenv::createImmutable($rootPath);
                $dotenv->safeLoad();
            } else {
                // Fallback parser if vendor is not yet installed
                $lines = file($rootPath . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (str_contains($line, '=')) {
                        [$name, $value] = explode('=', $line, 2);
                        $name = trim($name);
                        $value = trim($value, " \t\n\r\0\x0B\"'");
                        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                            putenv("{$name}={$value}");
                            $_ENV[$name] = $value;
                            $_SERVER[$name] = $value;
                        }
                    }
                }
            }
        }

        // Dates and times throughout the application are Sri Lanka time
        date_default_timezone_set('Asia/Colombo');

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();

        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($val === false || $val === null) {
            return $default;
        }

        // Convert common string booleans/nulls
        $lower = strtolower((string)$val);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if ($lower === 'null') return null;

        return $val;
    }
}
