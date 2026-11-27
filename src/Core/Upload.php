<?php

namespace App\Core;

use finfo;

class Upload
{
    private const MAX_SIZE = 2097152; // 2 MB in bytes
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public static function process(array $file, string $subfolder = 'images'): array
    {
        return self::store($file, $subfolder, self::ALLOWED_MIME, 'err_upload_format');
    }

    /** PDF documents (lab reports), same size limit. */
    public static function processPdf(array $file, string $subfolder = 'reports'): array
    {
        return self::store($file, $subfolder, ['application/pdf' => 'pdf'], 'err_upload_pdf');
    }

    /**
     * Locate a stored upload that is only ever sent through the application.
     * @return array{path: string, mime: string}|null
     */
    public static function privateFile(string $stored, string $subfolder): ?array
    {
        $folder = realpath(dirname(__DIR__, 2) . '/public/uploads/' . $subfolder);
        $file = realpath(dirname(__DIR__, 2) . '/public' . $stored);
        if (!$folder || !$file || !str_starts_with($file, $folder . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return null;
        }
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($file);
        return isset(self::ALLOWED_MIME[$mime]) ? ['path' => $file, 'mime' => $mime] : null;
    }

    private static function store(array $file, string $subfolder, array $allowed, string $formatError): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['ok' => false, 'error' => t('err_upload_param')];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => self::getErrorMessage($file['error'])];
        }

        // 1. Size check
        if ($file['size'] > self::MAX_SIZE) {
            return ['ok' => false, 'error' => t('err_upload_size')];
        }

        // 2. MIME type check with finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mime, $allowed)) {
            return ['ok' => false, 'error' => t($formatError)];
        }

        $extension = $allowed[$mime];

        // 3. Generate random file name
        $randomName = bin2hex(random_bytes(16)) . '.' . $extension;

        // 4. Ensure destination directory exists
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/' . trim($subfolder, '/\\');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . '/' . $randomName;

        // 5. Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            // If in CLI test where move_uploaded_file fails on non-HTTP uploads, copy if tmp file exists
            if (php_sapi_name() === 'cli' && copy($file['tmp_name'], $destination)) {
                // Cli test copy fallback
            } else {
                return ['ok' => false, 'error' => t('err_upload_save')];
            }
        }

        return [
            'ok' => true,
            'filename' => $randomName,
            'path' => '/uploads/' . trim($subfolder, '/\\') . '/' . $randomName
        ];
    }

    private static function getErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => t('err_upload_limit'),
            UPLOAD_ERR_PARTIAL => t('err_upload_partial'),
            UPLOAD_ERR_NO_FILE => t('err_upload_none'),
            UPLOAD_ERR_NO_TMP_DIR => t('err_upload_save'),
            UPLOAD_ERR_CANT_WRITE => t('err_upload_save'),
            UPLOAD_ERR_EXTENSION => t('err_upload_save'),
            default => t('err_upload_save')
        };
    }
}
