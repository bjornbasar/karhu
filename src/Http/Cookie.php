<?php

declare(strict_types=1);

namespace Karhu\Http;

/**
 * Cookie read/write helper with secure defaults.
 *
 * All cookies are HttpOnly, SameSite=Lax, and Secure (when on HTTPS)
 * by default. Override per-cookie via the $options parameter.
 */
final class Cookie
{
    /** Default cookie options — secure by default. */
    private const DEFAULTS = [
        'expires' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    /**
     * Read a cookie value from the request.
     */
    public static function get(string $name, string $default = ''): string
    {
        return $_COOKIE[$name] ?? $default;
    }

    /**
     * Check if a cookie exists.
     */
    public static function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Set a cookie with secure defaults.
     *
     * @param string               $name
     * @param string               $value
     * @param array<string, mixed> $options Override defaults (expires, path, domain, secure, httponly, samesite)
     */
    public static function set(string $name, string $value, array $options = []): void
    {
        $opts = array_merge(self::DEFAULTS, $options);

        if (!isset($options['secure'])) {
            $opts['secure'] = self::isHttps();
        }

        setcookie($name, $value, self::buildCookieOptions($opts));
    }

    /**
     * Delete a cookie by setting its expiry in the past.
     *
     * @param array<string, mixed> $options Must match path/domain of original set()
     */
    public static function delete(string $name, array $options = []): void
    {
        $opts = array_merge(self::DEFAULTS, $options, ['expires' => time() - 3600]);
        setcookie($name, '', self::buildCookieOptions($opts));
        unset($_COOKIE[$name]);
    }

    /**
     * Build a setcookie()-compatible options array with correct types.
     *
     * @param array<string, mixed> $opts
     * @return array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'None'|'Strict'}
     */
    private static function buildCookieOptions(array $opts): array
    {
        $samesite = (string) ($opts['samesite'] ?? 'Lax');
        /** @var 'Lax'|'None'|'Strict' $normalised */
        $normalised = match (strtolower($samesite)) {
            'strict' => 'Strict',
            'none' => 'None',
            default => 'Lax',
        };

        return [
            'expires' => (int) ($opts['expires'] ?? 0),
            'path' => (string) ($opts['path'] ?? '/'),
            'domain' => (string) ($opts['domain'] ?? ''),
            'secure' => (bool) ($opts['secure'] ?? false),
            'httponly' => (bool) ($opts['httponly'] ?? true),
            'samesite' => $normalised,
        ];
    }

    /** Detect whether the current request is over HTTPS. */
    private static function isHttps(): bool
    {
        return ($_SERVER['HTTPS'] ?? '') === 'on'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
