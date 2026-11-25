<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Csrf;

class CsrfTest extends TestCase
{
    public function testCsrfTokenGenerationAndValidation(): void
    {
        $token = Csrf::getToken();
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));

        $this->assertTrue(Csrf::validate($token));
        $this->assertFalse(Csrf::validate('invalid_token_123'));
        $this->assertFalse(Csrf::validate(null));
        $this->assertFalse(Csrf::validate(''));
    }

    public function testCsrfFieldContainsToken(): void
    {
        $token = Csrf::getToken();
        $field = Csrf::field();

        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_csrf_token"', $field);
        $this->assertStringContainsString($token, $field);
    }
}
