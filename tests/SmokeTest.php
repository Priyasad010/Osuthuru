<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Env;
use App\Core\Csrf;
use App\Core\Lang;
use App\Core\View;

class SmokeTest extends TestCase
{
    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
    }

    public function testEnvironmentLoads(): void
    {
        $appName = Env::get('APP_NAME');
        $this->assertNotEmpty($appName);
        $this->assertEquals('Osuthuru', $appName);
    }

    public function testCsrfTokenGenerationAndValidation(): void
    {
        $token = Csrf::getToken();
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
        $this->assertTrue(Csrf::validate($token));
        $this->assertFalse(Csrf::validate('invalid_token'));
    }

    public function testEscapingHelperProtectsAgainstXss(): void
    {
        $dirty = '<script>alert("hack")</script>';
        $clean = e($dirty);
        $this->assertEquals('&lt;script&gt;alert(&quot;hack&quot;)&lt;/script&gt;', $clean);
    }

    public function testMultiLanguageTranslations(): void
    {
        Lang::setLocale('en');
        $this->assertEquals('Osuthuru', t('app_name'));

        Lang::setLocale('si');
        $this->assertEquals('ඔසුතුරු', t('app_name'));

        // Reset to English
        Lang::setLocale('en');
    }

    public function testHomeViewRendersWithDisclaimer(): void
    {
        $html = View::render('home', ['pageTitle' => 'Test Title']);
        $this->assertStringContainsString('Osuthuru', $html);
        $this->assertStringContainsString('Disclaimer:', $html);
        $this->assertStringContainsString('Herb of the Day', $html);
    }
}
