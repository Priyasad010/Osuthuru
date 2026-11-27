<?php

namespace App\Repository;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;

class BatchRepo
{
    private const SELECT = "
        SELECT b.*, p.product_name, p.product_name_si,
               (SELECT COUNT(*) FROM batch_scan s WHERE s.batch_id = b.batch_id) AS scan_count,
               (SELECT COUNT(DISTINCT s.ip_hash) FROM batch_scan s WHERE s.batch_id = b.batch_id) AS scan_sources
        FROM product_batch b
        JOIN product p ON p.product_id = b.product_id
    ";

    /** Today's date in Sri Lanka. */
    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', new DateTimeZone('Asia/Colombo'));
    }

    public static function all(): array
    {
        return Database::getConnection()
            ->query(self::SELECT . " ORDER BY b.batch_id DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $batchId): ?array
    {
        $stmt = Database::getConnection()->prepare(self::SELECT . " WHERE b.batch_id = :id");
        $stmt->execute([':id' => $batchId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $stmt = Database::getConnection()->prepare(self::SELECT . " WHERE b.public_token = :token");
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Active batches expiring from today up to the given number of days ahead. */
    public static function expiringWithin(int $days = 30): array
    {
        $stmt = Database::getConnection()->prepare(self::SELECT . "
            WHERE b.status = 'active' AND b.expires_on >= :today AND b.expires_on <= :until
            ORDER BY b.expires_on ASC, b.batch_id ASC
        ");
        $today = self::today();
        $stmt->execute([
            ':today' => $today->format('Y-m-d'),
            ':until' => $today->modify("+{$days} days")->format('Y-m-d'),
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create (id 0) or update a batch. The public token never changes.
     *
     * @return array ['ok' => true, 'batch_id' => int] or ['ok' => false, 'error' => string]
     */
    public static function save(int $batchId, array $data): array
    {
        $code = trim((string)($data['batch_code'] ?? ''));
        $made = self::date($data['manufactured_on'] ?? '');
        $expires = self::date($data['expires_on'] ?? '');
        $productId = (int)($data['product_id'] ?? 0);
        $units = (int)($data['units'] ?? 0);

        if ($code === '' || mb_strlen($code) > 60 || !$made || !$expires || !ProductRepo::findById($productId)) {
            return ['ok' => false, 'error' => t('batch_err_required')];
        }
        if ($expires < $made) {
            return ['ok' => false, 'error' => t('batch_err_dates')];
        }

        $existing = $batchId > 0 ? self::find($batchId) : null;
        if ($batchId > 0 && !$existing) {
            return ['ok' => false, 'error' => t('batch_err_required')];
        }
        $allocated = (int)($existing['units_allocated'] ?? 0);
        if ($units < $allocated || $units > 1000000) {
            return ['ok' => false, 'error' => t('batch_err_units', ['count' => $allocated])];
        }
        // Packs already assigned to orders cannot move to another product
        if ($allocated > 0 && (int)$existing['product_id'] !== $productId) {
            return ['ok' => false, 'error' => t('batch_err_product')];
        }

        $optional = function (string $key, int $max) use ($data): ?string {
            $value = trim((string)($data[$key] ?? ''));
            return $value !== '' ? mb_substr($value, 0, $max) : null;
        };
        $values = [
            ':product' => $productId,
            ':code' => $code,
            ':made' => $made,
            ':expires' => $expires,
            ':source' => $optional('source_location', 150),
            ':supplier' => $optional('supplier_name', 150),
            ':notes' => $optional('notes', 2000),
            ':units' => $units,
            // Keeps the current report unless a new one was uploaded
            ':report' => $data['lab_report_path'] ?? ($existing['lab_report_path'] ?? null),
        ];

        $pdo = Database::getConnection();
        try {
            if ($existing) {
                $stmt = $pdo->prepare("
                    UPDATE product_batch
                    SET product_id = :product, batch_code = :code, manufactured_on = :made, expires_on = :expires,
                        source_location = :source, supplier_name = :supplier, notes = :notes, units = :units,
                        lab_report_path = :report
                    WHERE batch_id = :id
                ");
                $stmt->execute($values + [':id' => $batchId]);
                return ['ok' => true, 'batch_id' => $batchId];
            }

            $stmt = $pdo->prepare("
                INSERT INTO product_batch (product_id, batch_code, manufactured_on, expires_on, source_location,
                    supplier_name, notes, units, lab_report_path, public_token)
                VALUES (:product, :code, :made, :expires, :source, :supplier, :notes, :units, :report, :token)
            ");
            // 128 random bits
            $stmt->execute($values + [':token' => bin2hex(random_bytes(16))]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => t('batch_err_code')];
            }
            throw $e;
        }

        return ['ok' => true, 'batch_id' => (int)$pdo->lastInsertId()];
    }

    public static function setRecalled(int $batchId, bool $recalled): bool
    {
        $stmt = Database::getConnection()->prepare("UPDATE product_batch SET status = :status WHERE batch_id = :id");
        $stmt->execute([':status' => $recalled ? 'recalled' : 'active', ':id' => $batchId]);
        return self::find($batchId) !== null;
    }

    /** A valid Y-m-d date, or null. */
    private static function date(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
