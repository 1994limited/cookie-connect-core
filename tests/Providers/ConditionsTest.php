<?php

namespace NineteenNinetyFour\CookieConnect\Core\Tests\Providers;

use NineteenNinetyFour\CookieConnect\Core\Providers\Conditions;
use PHPUnit\Framework\TestCase;

class ConditionsTest extends TestCase
{
    public function test_conditions(): void
    {
        $values = ['on' => true, 'off' => false, 'cdn' => 'eu', 'empty' => ''];

        $this->assertTrue(Conditions::matches(null, $values));
        $this->assertTrue(Conditions::matches('on', $values));
        $this->assertFalse(Conditions::matches('off', $values));
        $this->assertTrue(Conditions::matches('!off', $values));
        $this->assertFalse(Conditions::matches('empty', $values));
        $this->assertFalse(Conditions::matches('missing', $values));
        $this->assertTrue(Conditions::matches('cdn=eu', $values));
        $this->assertFalse(Conditions::matches('cdn!=eu', $values));
        $this->assertTrue(Conditions::matches('on&cdn=eu', $values));
        $this->assertFalse(Conditions::matches('on&off', $values));
    }

    public function test_values_that_depend_on_settings(): void
    {
        $this->assertSame('x', Conditions::resolve('x', []));
        $this->assertSame('auto', Conditions::resolve(['value' => 'auto', 'when' => 'on'], ['on' => true]));
        $this->assertNull(Conditions::resolve(['value' => 'auto', 'when' => 'on'], ['on' => false]));
        $this->assertSame('b', Conditions::resolve([['when' => 'on', 'value' => 'a'], ['value' => 'b']], ['on' => false]));
        $this->assertSame(['type' => 'text/plain'], Conditions::resolve(['type' => 'text/plain'], []));
    }

    public function test_names(): void
    {
        $this->assertSame(['tcf', 'cdn'], Conditions::names('!tcf&cdn=eu'));
        $this->assertSame(['idType'], Conditions::names('idType!=ruleset'));
    }
}
