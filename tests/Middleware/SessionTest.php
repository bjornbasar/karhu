<?php

declare(strict_types=1);

namespace Karhu\Tests\Middleware;

use Karhu\Middleware\Session;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    #[Test]
    public function get_and_set(): void
    {
        Session::set('user', 'bjorn');
        $this->assertSame('bjorn', Session::get('user'));
    }

    #[Test]
    public function get_returns_default_when_missing(): void
    {
        $this->assertNull(Session::get('nope'));
        $this->assertSame('fallback', Session::get('nope', 'fallback'));
    }

    #[Test]
    public function has_and_forget(): void
    {
        Session::set('token', 'abc');
        $this->assertTrue(Session::has('token'));
        Session::forget('token');
        $this->assertFalse(Session::has('token'));
    }

    #[Test]
    public function destroy_clears_session(): void
    {
        Session::set('key', 'val');
        Session::destroy();
        $this->assertSame([], $_SESSION);
    }
}
