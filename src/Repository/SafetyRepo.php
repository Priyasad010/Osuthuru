<?php

namespace App\Repository;

use App\Core\Database;
use App\Core\Lang;
use PDO;
use PDOException;

class SafetyRepo
{
    public const SEVERITIES = ['info', 'caution', 'avoid'];

    public static function flags(?string $locale = null): array
    {
        $isSi = ($locale ?? Lang::getLocale()) === 'si';
        $rows = Database::getConnection()->query("SELECT * FROM safety_flag ORDER BY flag_id")->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $f) use ($isSi) {
            $f['display_name'] = ($isSi && $f['name_si'] !== '') ? $f['name_si'] : $f['name_en'];
            return $f;
        }, $rows);
    }

    /** Create (id 0) or rename a flag. The code of an existing flag never changes. */
    public static function saveFlag(int $id, string $code, string $nameEn, string $nameSi): array
    {
        if (trim($nameEn) === '') {
            return ['ok' => false, 'error' => t('safety_err_name')];
        }

        $pdo = Database::getConnection();
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE safety_flag SET name_en = :en, name_si = :si WHERE flag_id = :id");
            $stmt->execute([':en' => trim($nameEn), ':si' => trim($nameSi), ':id' => $id]);
            return ['ok' => true];
        }

        $code = strtolower(trim($code));
        if (!preg_match('/^[a-z0-9_]{2,50}$/', $code)) {
            return ['ok' => false, 'error' => t('safety_err_code')];
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO safety_flag (code, name_en, name_si) VALUES (:code, :en, :si)");
            $stmt->execute([':code' => $code, ':en' => trim($nameEn), ':si' => trim($nameSi)]);
        } catch (PDOException) {
            return ['ok' => false, 'error' => t('safety_err_code')];
        }
        return ['ok' => true];
    }

    public static function deleteFlag(int $id): void
    {
        $stmt = Database::getConnection()->prepare("DELETE FROM safety_flag WHERE flag_id = :id");
        $stmt->execute([':id' => $id]);
    }

    /** Every entry, published or not (admin view). */
    public static function entries(): array
    {
        return Database::getConnection()->query("
            SELECT s.*, h.name AS herb_name, f.name_en AS flag_name_en, f.name_si AS flag_name_si
            FROM herb_safety s
            JOIN herb h ON h.herb_id = s.herb_id
            JOIN safety_flag f ON f.flag_id = s.flag_id
            ORDER BY h.name, f.flag_id
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create (id 0) or update an entry. Publishing requires a note and its source.
     */
    public static function saveEntry(int $id, array $data): array
    {
        $severity = (string)($data['severity'] ?? '');
        $note = trim((string)($data['note_en'] ?? ''));
        $noteSi = trim((string)($data['note_si'] ?? ''));
        $source = trim((string)($data['source_note'] ?? ''));
        $published = !empty($data['published']);

        if (!in_array($severity, self::SEVERITIES, true)) {
            return ['ok' => false, 'error' => t('safety_err_entry')];
        }
        if ($published && ($note === '' || $source === '')) {
            return ['ok' => false, 'error' => t('safety_err_publish')];
        }

        $values = [
            ':herb' => (int)($data['herb_id'] ?? 0),
            ':flag' => (int)($data['flag_id'] ?? 0),
            ':severity' => $severity,
            ':note_en' => $note !== '' ? $note : null,
            ':note_si' => $noteSi !== '' ? $noteSi : null,
            ':source' => $source !== '' ? mb_substr($source, 0, 500) : null,
            ':published' => $published ? 1 : 0,
        ];

        $pdo = Database::getConnection();
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE herb_safety
                    SET herb_id = :herb, flag_id = :flag, severity = :severity, note_en = :note_en,
                        note_si = :note_si, source_note = :source, published = :published
                    WHERE id = :id
                ");
                $stmt->execute($values + [':id' => $id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO herb_safety (herb_id, flag_id, severity, note_en, note_si, source_note, published)
                    VALUES (:herb, :flag, :severity, :note_en, :note_si, :source, :published)
                ");
                $stmt->execute($values);
            }
        } catch (PDOException) {
            // Unknown herb or flag, or an entry for this pair already exists
            return ['ok' => false, 'error' => t('safety_err_duplicate')];
        }

        return ['ok' => true];
    }

    public static function deleteEntry(int $id): void
    {
        $stmt = Database::getConnection()->prepare("DELETE FROM herb_safety WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    /**
     * Herbs of the given products with their PUBLISHED entries.
     * Rows without an entry have null flag and severity.
     */
    public static function publishedRowsForProducts(array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        $stmt = Database::getConnection()->prepare("
            SELECT p.product_id, p.product_name, p.product_name_si,
                   h.herb_id, h.name AS herb_name, h.name_si AS herb_name_si,
                   s.severity, s.note_en, s.note_si,
                   f.code AS flag_code, f.name_en AS flag_name_en, f.name_si AS flag_name_si
            FROM product p
            LEFT JOIN product_herb ph ON ph.product_id = p.product_id
            LEFT JOIN herb h ON h.herb_id = ph.herb_id
            LEFT JOIN herb_safety s ON s.herb_id = h.herb_id AND s.published = 1
            LEFT JOIN safety_flag f ON f.flag_id = s.flag_id
            WHERE p.product_id IN ({$placeholders})
            ORDER BY p.product_id, h.name, f.flag_id
        ");
        $stmt->execute($productIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
