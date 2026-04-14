<?php

declare(strict_types=1);

namespace Karhu\Tests\Http;

use Karhu\Http\Cookie;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CookieTest extends TestCase
{
    protected function setUp(): void
    {
        $_COOKIE = [];
    }

    #[Test]
    public function get_returns_cookie_value(): void
    {
        $_COOKIE['token'] = 'abc123';
        $this->assertSame('abc123', Cookie::get('token'));
    }

    #[Test]
    public function get_returns_default_when_missing(): void
    {
        $this->assertSame('fallback', Cookie::get('missing', 'fallback'));
        $this->assertSame('', Cookie::get('missing'));
    }

    #[Test]
    public function has_detects_presence(): void
    {
        $_COOKIE['exists'] = 'yes';
        $this->assertTrue(Cookie::has('exists'));
        $this->assertFalse(Cookie::has('nope'));
    }

    #[Test]
    public function delete_removes_from_superglobal(): void
    {
        $_COOKIE['token'] = 'abc';
        // delete() can't actually call setcookie in CLI, but it unsets $_COOKIE
        @Cookie::delete('token');
        $this->assertFalse(Cookie::has('token'));
    }
}
