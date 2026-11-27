<?php

namespace Tests;

use App\Core\Database;
use App\Core\Env;
use App\Core\FormRules;
use App\Repository\BatchRepo;
use App\Repository\QuizRepo;
use PHPUnit\Framework\TestCase;

class FormRulesTest extends TestCase
{
    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        $_SESSION = [];
    }

    public function testPhoneNumbers(): void
    {
        foreach (['0771234567', '077 123 4567', '077-1234567', '+94771234567', '94771234567', '0094771234567', '0112345678'] as $good) {
            $this->assertNotNull(FormRules::phone($good), $good);
        }
        $this->assertSame('0771234567', FormRules::phone('+94 77 123 4567'));
        foreach (['', '077123456', '07712345678', '1771234567', '0071234567', '07712a4567', '+1 555 010 9999'] as $bad) {
            $this->assertNull(FormRules::phone($bad), $bad);
        }
    }

    public function testNamesEmailsAndPasswords(): void
    {
        foreach (['Nimal Perera', "D'Silva", 'M. A. Fernando', 'නිමල් පෙරේරා', 'ශ්‍රීමතී'] as $good) {
            $this->assertTrue(FormRules::isPersonName($good), $good);
        }
        foreach (['', 'A', '12345', '<b>x</b>', 'Name123', str_repeat('a', 101)] as $bad) {
            $this->assertFalse(FormRules::isPersonName($bad), $bad);
        }
        $this->assertTrue(FormRules::isEmail('a@b.lk'));
        $this->assertFalse(FormRules::isEmail('a@b'));
        $this->assertFalse(FormRules::isEmail(str_repeat('a', 100) . '@b.lk'));

        $this->assertNull(FormRules::passwordProblem('Password123!'));
        foreach (['short1', 'onlyletters', '1234567890', str_repeat('a1', 40)] as $bad) {
            $this->assertNotNull(FormRules::passwordProblem($bad), $bad);
        }
    }

    public function testRegistrationReportsEachField(): void
    {
        $errors = FormRules::registration(['name' => '1', 'email' => 'x', 'phone' => '12', 'password' => 'abc', 'password_confirmation' => 'abc']);
        $this->assertSame(['name', 'email', 'phone', 'password'], array_keys($errors));
        $this->assertSame([], FormRules::registration(['name' => 'Kamal', 'email' => 'k@x.lk', 'phone' => '', 'password' => 'Secret123', 'password_confirmation' => 'Secret123']));
        $this->assertArrayHasKey('password_confirmation', FormRules::registration(['name' => 'Kamal', 'email' => 'k@x.lk', 'password' => 'Secret123', 'password_confirmation' => 'Secret124']));
    }

    public function testCheckoutNeedsCompleteDeliveryDetails(): void
    {
        $good = ['shipping_name' => 'Kamal Perera', 'shipping_phone' => '0771234567', 'shipping_address' => '12 Galle Road, Colombo', 'district' => 'Colombo'];
        $this->assertSame([], FormRules::shipping($good));
        foreach (['shipping_name' => '', 'shipping_phone' => 'abc', 'shipping_address' => 'x', 'district' => ''] as $field => $bad) {
            $this->assertSame([$field], array_keys(FormRules::shipping([$field => $bad] + $good)));
        }
        $this->assertArrayHasKey('district', FormRules::shipping(['district' => 'Atlantis'] + $good));
        $this->assertCount(4, FormRules::shipping(['shipping_name' => ['x']]));
    }

    public function testCouponAndZoneLimits(): void
    {
        $good = ['code' => 'NEWYEAR-10', 'type' => 'percent', 'value' => '10', 'min_order' => '0', 'max_uses' => '5', 'ends_at' => date('Y-m-d\TH:i', time() + 86400)];
        $this->assertSame([], FormRules::coupon($good));
        foreach (['code' => 'a b', 'value' => '150', 'min_order' => '-1', 'max_uses' => '0', 'ends_at' => '2020-01-01T00:00'] as $field => $bad) {
            $this->assertSame([$field], array_keys(FormRules::coupon([$field => $bad] + $good)), $field);
        }
        $this->assertSame([], FormRules::coupon(['type' => 'fixed', 'value' => '150'] + $good));
        $this->assertArrayHasKey('value', FormRules::coupon(['value' => '-5'] + $good));

        $this->assertSame([], FormRules::deliveryZone(['district' => 'Nuwara Eliya', 'fee' => '350', 'free_over' => '0']));
        $this->assertSame(['district', 'fee', 'free_over'], array_keys(FormRules::deliveryZone(['district' => '1', 'fee' => '-2', 'free_over' => 'x'])));
    }

    public function testCartQuantityStaysWithinStock(): void
    {
        $pdo = Database::getConnection();
        $stock = (int)$pdo->query('SELECT quantity FROM product WHERE product_id = 1')->fetchColumn();
        $this->assertNull(FormRules::cartProblem(1, '2'));
        foreach (['0', '-1', '1.5', 'abc', '100'] as $bad) {
            $this->assertNotNull(FormRules::cartProblem(1, $bad), $bad);
        }
        $this->assertNotNull(FormRules::cartProblem(999999, '1'));
        $pdo->exec('UPDATE product SET quantity = 3 WHERE product_id = 1');
        try {
            $this->assertNull(FormRules::cartProblem(1, '1', 2));
            $this->assertNotNull(FormRules::cartProblem(1, '2', 2));
        } finally {
            $pdo->exec("UPDATE product SET quantity = {$stock} WHERE product_id = 1");
        }
    }

    public function testRedirectsStayOnThisSite(): void
    {
        $_SERVER['HTTP_HOST'] = 'shop.test';
        foreach (['https://evil.test/x', '//evil.test/x', 'https://shop.test//evil.test', 'javascript:alert(1)', ''] as $bad) {
            $_SERVER['HTTP_REFERER'] = $bad;
            $this->assertSame('/cart', back_path('/cart'), $bad);
        }
        $_SERVER['HTTP_REFERER'] = 'http://shop.test/products?category=2';
        $this->assertSame('/products?category=2', back_path('/cart'));
        unset($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']);
    }

    public function testAdminRecordsRejectImpossibleValues(): void
    {
        $tomorrow = date('Y-m-d', time() + 86400);
        $this->assertFalse(BatchRepo::save(0, ['batch_code' => 'FUTURE-1', 'product_id' => 1, 'manufactured_on' => $tomorrow, 'expires_on' => $tomorrow, 'units' => 1])['ok']);
        $this->assertFalse(QuizRepo::saveQuestion(0, str_repeat('a', 256), '', 1));
    }
}
