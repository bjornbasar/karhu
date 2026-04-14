<?php

declare(strict_types=1);

namespace Karhu\Tests\Auth;

use Karhu\Auth\PasswordHasher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    #[Test]
    public function hash_produces_argon2id(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('secret');
        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    #[Test]
    public function verify_correct_password(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('password123');
        $this->assertTrue($hasher->verify('password123', $hash));
    }

    #[Test]
    public function verify_wrong_password(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('password123');
        $this->assertFalse($hasher->verify('wrong', $hash));
    }

    #[Test]
    public function needs_rehash_on_bcrypt(): void
    {
        $hasher = new PasswordHasher();
        $bcrypt = password_hash('test', PASSWORD_BCRYPT);
        $this->assertTrue($hasher->needsRehash($bcrypt));
    }

    #[Test]
    public function does_not_need_rehash_on_fresh_hash(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('test');
        $this->assertFalse($hasher->needsRehash($hash));
    }
}
