<?php

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Applies SQL files. Needs a connection from Database::createScriptConnection().
 */
class Migrator
{
    /**
     * Run every statement in a SQL script and fail if any one of them fails.
     */
    public static function runScript(PDO $pdo, string $sql): void
    {
        $result = $pdo->query($sql);
        // An error in the second or a later statement is only raised when its result is reached
        while ($result->nextRowset()) {
        }
        $result->closeCursor();
    }

    /**
     * Apply new migration files in name order.
     *
     * @return string[] Names of the files applied now.
     */
    public static function migrate(PDO $pdo, string $directory, ?callable $log = null): array
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `version` VARCHAR(255) NOT NULL,
                `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $applied = array_flip($pdo->query("SELECT `version` FROM `schema_migrations`")->fetchAll(PDO::FETCH_COLUMN));

        $files = glob(rtrim($directory, '/\\') . '/*.sql');
        if (!$files) {
            throw new RuntimeException("No migration files found in {$directory}");
        }
        sort($files);

        $done = [];
        foreach ($files as $path) {
            $name = basename($path);
            if (isset($applied[$name])) {
                continue;
            }

            try {
                self::runScript($pdo, (string)file_get_contents($path));
            } catch (Throwable $e) {
                throw new RuntimeException("Error executing {$name}: " . $e->getMessage(), 0, $e);
            }

            $insert = $pdo->prepare("INSERT INTO `schema_migrations` (`version`) VALUES (:version)");
            $insert->execute([':version' => $name]);

            $done[] = $name;
            if ($log) {
                $log($name);
            }
        }

        return $done;
    }
}
