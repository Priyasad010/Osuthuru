<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Core\Env;
use App\Core\Lang;
use App\Core\Mailer;
use App\Core\Migrator;
use App\Core\View;
use App\Repository\ProductRepo;
use App\Service\InvoiceService;
use App\Service\RegistrationService;
use PDOException;
use RuntimeException;

class CatalogueAndLocaleTest extends TestCase
{
    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        Lang::setLocale('en');
    }

    protected function tearDown(): void
    {
        Lang::setLocale('en');
    }

    public function testTestsRunAgainstTheTestDatabase(): void
    {
        $this->assertSame('osuthuru_test', Database::getConnection()->query('SELECT DATABASE()')->fetchColumn());
    }

    public function testScriptErrorsAfterTheFirstStatementAreReported(): void
    {
        $this->expectException(PDOException::class);
        Migrator::runScript(Database::createScriptConnection(), 'SELECT 1; SELECT * FROM table_that_does_not_exist;');
    }

    public function testFailedMigrationIsNotRecorded(): void
    {
        $dir = sys_get_temp_dir() . '/migration_' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("{$dir}/999_broken.sql", 'SELECT 1; THIS IS NOT SQL;');

        try {
            Migrator::migrate(Database::createScriptConnection(), $dir);
            $this->fail('A broken migration must throw');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('999_broken.sql', $e->getMessage());
        } finally {
            unlink("{$dir}/999_broken.sql");
            rmdir($dir);
        }

        $count = Database::getConnection()->query("SELECT COUNT(*) FROM schema_migrations WHERE version = '999_broken.sql'")->fetchColumn();
        $this->assertSame(0, (int)$count);
    }

    public function testSeededCatalogueHasNoClaimsOrBrokenCharacters(): void
    {
        $pdo = Database::getConnection();
        $texts = array_merge(
            $pdo->query('SELECT description FROM product WHERE product_id <= 20')->fetchAll(\PDO::FETCH_COLUMN),
            $pdo->query('SELECT description_si FROM product WHERE product_id <= 20')->fetchAll(\PDO::FETCH_COLUMN),
            $pdo->query('SELECT name FROM category')->fetchAll(\PDO::FETCH_COLUMN),
            $pdo->query('SELECT content FROM blog')->fetchAll(\PDO::FETCH_COLUMN)
        );
        $all = implode("\n", array_map('strval', $texts));

        $this->assertNotEmpty($all);
        $this->assertDoesNotMatchRegularExpression(
            '/eczema|psoriasis|arthritis|paralysis|acne|anti-?inflammatory|antifungal|pain reli|blood sugar|cholesterol|jaundice/i',
            $all
        );
        $this->assertDoesNotMatchRegularExpression('/Ã|â€/u', $all);
    }

    public function testMigrationReplacesOldClaimsAndKeepsAdminText(): void
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $pdo->exec("UPDATE product SET description = 'Neem Oil (Neem Thaila) is a powerful Ayurvedic oil known for X' WHERE product_id = 1");
            $pdo->exec("UPDATE product SET description = 'Text written by the admin' WHERE product_id = 2");

            $sql = file_get_contents(dirname(__DIR__) . '/database/migrations/006_neutral_catalogue_text.sql');
            preg_match_all('/^UPDATE .*?;$/ms', $sql, $statements);
            foreach ($statements[0] as $statement) {
                $pdo->exec($statement);
            }

            $this->assertSame('Neem Oil (Neem Thaila) is an Ayurvedic oil.', ProductRepo::findById(1)['description']);
            $this->assertSame('Text written by the admin', ProductRepo::findById(2)['description']);
        } finally {
            $pdo->rollBack();
        }
    }

    public function testAdminCanEditEnglishDescription(): void
    {
        $before = ProductRepo::findById(12);
        try {
            ProductRepo::updateDetails(12, ['description' => ' Reviewed text ', 'product_name_si' => $before['product_name_si'], 'description_si' => $before['description_si']]);
            $this->assertSame('Reviewed text', ProductRepo::findById(12)['description']);
        } finally {
            ProductRepo::updateDetails(12, ['description' => $before['description'], 'product_name_si' => $before['product_name_si'], 'description_si' => $before['description_si']]);
        }
    }

    public function testRunInRestoresTheCurrentLanguage(): void
    {
        $text = Lang::runIn('si', fn() => t('mail_verify_button'));
        $this->assertSame('ගිණුම තහවුරු කරන්න', $text);
        $this->assertSame('en', Lang::getLocale());
    }

    public function testEmailBodyUsesRecipientLanguageAndEscapesText(): void
    {
        $html = Lang::runIn('si', fn() => Mailer::layout('<b>Nimal</b>', [t('mail_verify_intro')], t('mail_verify_button'), 'http://localhost/x'));
        $this->assertStringContainsString('ආයුබෝවන්', $html);
        $this->assertStringNotContainsString('<b>Nimal</b>', $html);
    }

    public function testRegistrationRemembersLanguage(): void
    {
        $pdo = Database::getConnection();
        Lang::setLocale('si');
        $res = RegistrationService::register('Lang User', 'lang@localetest.lk', 'Password123!');

        try {
            $lang = $pdo->query("SELECT lang FROM `user` WHERE user_id = {$res['user_id']}")->fetchColumn();
            $this->assertSame('si', $lang);
            RegistrationService::saveLanguage($res['user_id'], 'xx');
            RegistrationService::saveLanguage($res['user_id'], 'en');
            $this->assertSame('en', $pdo->query("SELECT lang FROM `user` WHERE user_id = {$res['user_id']}")->fetchColumn());
        } finally {
            $pdo->exec("DELETE FROM `user` WHERE user_id = {$res['user_id']}");
        }
    }

    public function testInvoiceIsSinhalaInBrowserAndEnglishAsPdf(): void
    {
        $order = [
            'order_id' => 1, 'order_date' => '2026-01-01', 'total_price' => 100, 'status' => 'Pending',
            'payment_method' => 'cod', 'payment_status' => 'unpaid',
            'items' => [['product_name' => 'Soap', 'product_name_si' => 'සබන්', 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100]],
        ];
        Lang::setLocale('si');

        $html = InvoiceService::generateHtml($order, true);
        $this->assertStringContainsString('නිල ඉන්වොයිසිය', $html);
        $this->assertStringContainsString('සබන්', $html);
        $this->assertStringContainsString('window.print()', $html);

        $this->assertStringStartsWith('%PDF-', InvoiceService::renderPdf($order));
        $this->assertSame('si', Lang::getLocale());
    }

    public function testAdminPagesRenderInSinhala(): void
    {
        Lang::setLocale('si');
        $html = View::render('admin/dashboard', [], false)
            . View::render('admin/orders', ['orders' => []], false)
            . View::render('admin/coupons', ['coupons' => []], false)
            . View::render('admin/zones', ['zones' => []], false)
            . View::render('admin/bank_slips', ['slips' => []], false)
            . View::render('admin/products', ['products' => [], 'allHerbs' => []], false)
            . View::render('admin/herbs', ['herbs' => []], false)
            . View::render('auth/admin_login', [], false);

        $visible = strip_tags(preg_replace('/<script.*?<\/script>|\sdata-chart="[^"]*"/s', '', $html));
        foreach (['Dashboard', 'Manage', 'Coupon', 'Delivery', 'Bank', 'Status', 'Login', 'Save', 'Product', 'Stock', 'Herb'] as $word) {
            $this->assertStringNotContainsString($word, $visible);
        }
    }
}
