<?php

namespace App\Core;

class View
{
    private static string $templatesDir = '';

    public static function setTemplatesDir(string $dir): void
    {
        self::$templatesDir = rtrim($dir, '/\\');
    }

    public static function getTemplatesDir(): string
    {
        if (empty(self::$templatesDir)) {
            self::$templatesDir = dirname(__DIR__, 2) . '/templates';
        }
        return self::$templatesDir;
    }

    public static function render(string $template, array $data = [], bool $includeLayout = true): string
    {
        $dir = self::getTemplatesDir();
        $templateFile = "{$dir}/{$template}.php";

        if (!file_exists($templateFile)) {
            throw new \RuntimeException("Template [{$template}] not found at {$templateFile}");
        }

        // Extract variables to local scope
        extract($data, EXTR_SKIP);

        ob_start();
        if ($includeLayout && file_exists("{$dir}/partials/header.php")) {
            require "{$dir}/partials/header.php";
        }

        require $templateFile;

        if ($includeLayout && file_exists("{$dir}/partials/footer.php")) {
            require "{$dir}/partials/footer.php";
        }

        return ob_get_clean();
    }

    public static function partial(string $partial, array $data = []): string
    {
        $dir = self::getTemplatesDir();
        $partialFile = "{$dir}/partials/{$partial}.php";

        if (!file_exists($partialFile)) {
            throw new \RuntimeException("Partial [{$partial}] not found at {$partialFile}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $partialFile;
        return ob_get_clean();
    }
}
