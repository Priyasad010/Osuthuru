<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $port = Env::get('DB_PORT', 3306);
            $dbName = Env::get('DB_NAME', 'suwoda');
            $user = Env::get('DB_USER', 'app');
            $pass = Env::get('DB_PASS', 'app_secret');
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // native prepared statements
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true, // enable multi-statement execution for migrations
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                if (Env::get('APP_DEBUG', false)) {
                    throw $e;
                }
                throw new PDOException("Database connection error. Please try again later.");
            }
        }

        return self::$instance;
    }

    public static function setConnection(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }
}
