<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Validator;

class ValidatorTest extends TestCase
{
    public function testRequiredRuleFails(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertNotNull($v->first('name'));
    }

    public function testEmailRuleFails(): void
    {
        $v = Validator::make(['email' => 'notanemail'], ['email' => 'required|email']);
        $this->assertTrue($v->fails());
        $this->assertNotNull($v->first('email'));
    }

    public function testEmailRulePasses(): void
    {
        $v = Validator::make(['email' => 'test@suwoda.lk'], ['email' => 'required|email']);
        $this->assertTrue($v->passes());
    }

    public function testMinRuleFails(): void
    {
        $v = Validator::make(['password' => 'short'], ['password' => 'required|min:8']);
        $this->assertTrue($v->fails());
    }

    public function testMinRulePasses(): void
    {
        $v = Validator::make(['password' => 'longpassword'], ['password' => 'required|min:8']);
        $this->assertTrue($v->passes());
    }

    public function testMatchesRuleFails(): void
    {
        $v = Validator::make(
            ['password' => 'pass1', 'password_confirmation' => 'pass2'],
            ['password' => 'required|matches:password_confirmation']
        );
        $this->assertTrue($v->fails());
    }

    public function testMatchesRulePasses(): void
    {
        $v = Validator::make(
            ['password' => 'same_pass', 'password_confirmation' => 'same_pass'],
            ['password' => 'required|matches:password_confirmation']
        );
        $this->assertTrue($v->passes());
    }

    public function testMultipleRulesAccumulateErrors(): void
    {
        $v = Validator::make(['email' => '', 'name' => ''], [
            'email' => 'required|email',
            'name'  => 'required|min:2'
        ]);
        $this->assertTrue($v->fails());
        $this->assertNotNull($v->first('email'));
        $this->assertNotNull($v->first('name'));
    }
}
