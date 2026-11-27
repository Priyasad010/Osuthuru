<?php

namespace App\Core;

use PDO;
use RuntimeException;

/**
 * Database rights of the read-only ML user. Customer names, emails, addresses
 * and passwords are not readable.
 */
class MlGrants
{
    /** table => readable columns (null = every column) */
    public const TABLES = [
        'product' => null,
        'category' => null,
        'herb' => null,
        'product_herb' => null,
        'health_concern' => null,
        'herb_concern' => null,
        'product_dosha' => null,
        'order_item' => null,
        'orders' => ['order_id', 'user_id', 'order_date', 'status', 'payment_status', 'payment_method'],
        'review' => ['review_id', 'user_id', 'product_id', 'rating', 'review_date'],
        'user_category' => null,
        'quiz_result' => ['result_id', 'user_id', 'vata', 'pitta', 'kapha'],
    ];

    /** Root connection, available in the development containers. */
    public static function rootConnection(): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOST', 'db'), Env::get('DB_PORT', 3306));

        return new PDO($dsn, 'root', (string)Env::get('DB_ROOT_PASS', 'root_secret'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    /** Replace the user's rights on one database with exactly the list above. */
    public static function apply(PDO $root, string $database): void
    {
        $user = (string)Env::get('DB_ML_RO_USER', 'ml_ro');
        if (!preg_match('/^[A-Za-z0-9_]+$/', $database) || !preg_match('/^[A-Za-z0-9_]+$/', $user)) {
            throw new RuntimeException('Unexpected database or user name.');
        }
        $account = $root->quote($user) . "@'%'";
        $root->exec("CREATE USER IF NOT EXISTS {$account} IDENTIFIED BY " . $root->quote((string)Env::get('DB_ML_RO_PASS', 'ml_ro_secret')));

        // Remove earlier rights, including the original read-everything grant
        $existing = $root->query("SHOW GRANTS FOR {$account}")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($existing as $grant) {
            if (preg_match('/^GRANT (.+?) ON `' . preg_quote($database, '/') . '`\.(\*|`[A-Za-z0-9_]+`) TO /', $grant, $m)) {
                // Column grants are listed as "SELECT (`a`, `b`)", which REVOKE accepts as is
                $root->exec("REVOKE {$m[1]} ON `{$database}`.{$m[2]} FROM {$account}");
            }
        }

        foreach (self::TABLES as $table => $columns) {
            $list = $columns === null ? '' : ' (`' . implode('`, `', $columns) . '`)';
            $root->exec("GRANT SELECT{$list} ON `{$database}`.`{$table}` TO {$account}");
        }
    }
}
