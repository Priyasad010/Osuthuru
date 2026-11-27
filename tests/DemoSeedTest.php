<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Core\Env;
use App\Core\MlGrants;
use App\Core\Migrator;
use PDO;

/**
 * A fresh install with the demo data, in its own database.
 */
class DemoSeedTest extends TestCase
{
    private const DATABASE = 'suwoda_demo_test';

    public function testFreshInstallWithDemoDataWorksAndCanBeRepeated(): void
    {
        Env::load(dirname(__DIR__));
        $root = MlGrants::rootConnection();
        $root->exec('DROP DATABASE IF EXISTS ' . self::DATABASE);
        $root->exec('CREATE DATABASE ' . self::DATABASE . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $root->exec('GRANT ALL PRIVILEGES ON ' . self::DATABASE . '.* TO ' . $root->quote((string)Env::get('DB_USER', 'app')) . "@'%'");

        $previous = $_ENV['DB_NAME'];
        $_ENV['DB_NAME'] = self::DATABASE;
        try {
            $db = Database::createScriptConnection();
        } finally {
            $_ENV['DB_NAME'] = $previous;
        }

        $dir = dirname(__DIR__) . '/database';
        Migrator::migrate($db, "{$dir}/migrations");
        foreach ([1, 2] as $run) {
            Migrator::runScript($db, (string)file_get_contents("{$dir}/seed/v1_catalog_seed.sql"));
            Migrator::runScript($db, (string)file_get_contents("{$dir}/seed/demo.sql"));
        }
        $this->assertSame([], Migrator::migrate($db, "{$dir}/migrations"), 'Migrating again changes nothing');

        $value = fn(string $sql) => (int)$db->query($sql)->fetchColumn();
        $this->assertSame(3, $value('SELECT COUNT(*) FROM `user`'));
        $this->assertSame(90, $value('SELECT COUNT(*) FROM orders'));
        $this->assertSame(180, $value('SELECT COUNT(*) FROM order_item'));
        $this->assertSame(45, $value('SELECT COUNT(DISTINCT DATE(order_date)) FROM orders'));
        $this->assertSame(0, $value('SELECT COUNT(*) FROM orders o WHERE total_price <> delivery_fee + (SELECT SUM(subtotal) FROM order_item i WHERE i.order_id = o.order_id)'));
        $this->assertSame(0, $value('SELECT COUNT(*) FROM orders WHERE order_date > NOW()'));
        $this->assertSame(3, $value('SELECT COUNT(*) FROM product_batch'));
        $this->assertSame(3, $value('SELECT COUNT(*) FROM review'));
        $this->assertSame(1, $value('SELECT published FROM quiz_setting'));
        $this->assertSame(3, $value('SELECT COUNT(*) FROM herb_safety WHERE published = 1'));
        $this->assertSame(0, $value("SELECT COUNT(*) FROM herb_safety WHERE published = 1 AND note_en NOT LIKE 'SAMPLE%'"), 'Only marked samples');

        $hash = $db->query('SELECT password FROM `user` WHERE user_id = 9001')->fetchColumn();
        $this->assertTrue(password_verify('DemoPass123!', $hash));

        $db = null;
        $root->exec('DROP DATABASE ' . self::DATABASE);
    }

    public function testEveryForeignKeyColumnIsIndexed(): void
    {
        $missing = Database::getConnection()->query("
            SELECT CONCAT(k.TABLE_NAME, '.', k.COLUMN_NAME)
            FROM information_schema.COLUMNS k
            WHERE k.TABLE_SCHEMA = DATABASE() AND k.COLUMN_NAME LIKE '%\\_id'
              AND NOT EXISTS (
                  SELECT 1 FROM information_schema.STATISTICS s
                  WHERE s.TABLE_SCHEMA = k.TABLE_SCHEMA AND s.TABLE_NAME = k.TABLE_NAME
                    AND s.COLUMN_NAME = k.COLUMN_NAME AND s.SEQ_IN_INDEX = 1)
        ")->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame([], $missing);
    }
}
