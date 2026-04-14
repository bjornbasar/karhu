<?php

declare(strict_types=1);

namespace Karhu\Middleware;

use Karhu\Http\Request;
use Karhu\Http\Response;

/**
 * Session middleware — native PHP sessions with secure defaults.
 *
 * Sets Secure (on HTTPS), HttpOnly, SameSite=Lax cookie params.
 * Call regenerate() after authentication to prevent session fixation.
 */
final class Session
{
    /** @var array<string, mixed> Custom session cookie params */
    private array $cookieParams;

    /**
     * @param array<string, mixed> $cookieParams Override session.cookie_* defaults
     */
    public function __construct(array $cookieParams = [])
    {
        $this->cookieParams = array_merge([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ], $cookieParams);
    }

    /**
     * Middleware invocation — starts session, runs next, returns response.
     */
    public function __invoke(Request $request, callable $next): Response
    {
        $this->start($request);
        return $next($request);
    }

    /** Start the session with secure cookie params. */
    public function start(Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $params = $this->cookieParams;

        // Auto-detect HTTPS
        if (!($params['secure'] ?? false)) {
            $params['secure'] = ($request->header('x-forwarded-proto') === 'https')
                || (($_SERVER['HTTPS'] ?? '') === 'on');
        }

        $samesite = (string) ($params['samesite'] ?? 'Lax');
        /** @var 'Lax'|'None'|'Strict' $normalisedSamesite */
        $normalisedSamesite = match (strtolower($samesite)) {
            'strict' => 'Strict',
            'none' => 'None',
            default => 'Lax',
        };

        session_set_cookie_params([
            'lifetime' => (int) $params['lifetime'],
            'path' => (string) $params['path'],
            'domain' => (string) $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $normalisedSamesite,
        ]);

        session_start();
    }

    /** Regenerate session ID — call after login to prevent fixation. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Get a session value. */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /** Set a session value. */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** Remove a session value. */
    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Check if a session key exists. */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /** Destroy the session entirely. */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }
}
