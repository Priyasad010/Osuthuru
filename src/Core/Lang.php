<?php

namespace App\Core;

class Lang
{
    private static string $currentLang = 'en';
    private static array $translations = [];
    private static string $langDir = '';

    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Language resolution: query param > session > cookie > default 'en'
        $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? $_COOKIE['suwoda_lang'] ?? 'en';
        self::setLocale($lang);
    }

    public static function setLocale(string $lang): void
    {
        $lang = strtolower(trim($lang));
        if (!in_array($lang, ['en', 'si'], true)) {
            $lang = 'en';
        }

        self::$currentLang = $lang;

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['lang'] = $lang;
        }

        if (!headers_sent()) {
            setcookie('suwoda_lang', $lang, [
                'expires' => time() + (86400 * 30),
                'path' => '/',
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
        }

        self::loadTranslations($lang);
    }

    public static function getLocale(): string
    {
        return self::$currentLang;
    }

    private static function getLangDir(): string
    {
        if (empty(self::$langDir)) {
            self::$langDir = dirname(__DIR__, 2) . '/lang';
        }
        return self::$langDir;
    }

    private static function loadTranslations(string $lang): void
    {
        if (isset(self::$translations[$lang])) {
            return;
        }

        $dir = self::getLangDir();
        $file = "{$dir}/{$lang}.php";

        if (file_exists($file)) {
            self::$translations[$lang] = require $file;
        } else {
            self::$translations[$lang] = [];
        }

        // Also ensure fallback 'en' is loaded if current is 'si'
        if ($lang !== 'en' && !isset(self::$translations['en'])) {
            $enFile = "{$dir}/en.php";
            self::$translations['en'] = file_exists($enFile) ? require $enFile : [];
        }
    }

    public static function trans(string $key, array $replace = []): string
    {
        $lang = self::$currentLang;
        self::loadTranslations($lang);

        $text = self::$translations[$lang][$key] 
            ?? self::$translations['en'][$key] 
            ?? $key;

        foreach ($replace as $placeholder => $value) {
            $text = str_replace(':' . $placeholder, (string)$value, $text);
        }

        return $text;
    }
}
