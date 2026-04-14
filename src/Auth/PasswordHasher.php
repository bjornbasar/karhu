<?php

declare(strict_types=1);

namespace Karhu\Auth;

/**
 * Argon2id password hashing wrapper.
 *
 * Uses PHP's built-in password_hash/password_verify with PASSWORD_ARGON2ID.
 * Replaces chukwu's mcrypt-based Core_Hasher (removed in PHP 7.2).
 */
final class PasswordHasher
{
    /** Hash a plaintext password. */
    public function hash(string $plaintext): string
    {
        return password_hash($plaintext, PASSWORD_ARGON2ID);
    }

    /** Verify a plaintext password against a hash. */
    public function verify(string $plaintext, string $hash): bool
    {
        return password_verify($plaintext, $hash);
    }

    /** Check if a hash needs rehashing (e.g. after algorithm parameter changes). */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
}
