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
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['ok' => false, 'error' => 'Invalid file upload parameter.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => self::getErrorMessage($file['error'])];
        }

        // 1. Size check
        if ($file['size'] > self::MAX_SIZE) {
            return ['ok' => false, 'error' => 'File size exceeds maximum limit of 2 MB.'];
        }

        // 2. MIME type check with finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mime, self::ALLOWED_MIME)) {
            return ['ok' => false, 'error' => 'Invalid file format. Only JPG, PNG, and WebP images are allowed.'];
        }

        $extension = self::ALLOWED_MIME[$mime];

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
                return ['ok' => false, 'error' => 'Failed to save uploaded file.'];
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
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the allowed file size.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Unknown upload error.'
        };
    }
}
