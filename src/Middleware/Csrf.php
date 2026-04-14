<?php

declare(strict_types=1);

namespace Karhu\Middleware;

use Karhu\Http\Request;
use Karhu\Http\Response;

/**
 * CSRF protection middleware — session-backed signed token.
 *
 * Bypass safelist: GET, HEAD, OPTIONS (safe methods per RFC 9110).
 * Verification failure returns 403 (JSON or HTML per content negotiation).
 *
 * Token storage: session-backed (primary) with double-submit cookie
 * fallback for stateless setups.
 */
final class Csrf
{
    private const TOKEN_LENGTH = 32;
    private const SESSION_KEY = '_csrf_token';
    private const COOKIE_NAME = '_csrf_cookie';
    private const HEADER_NAME = 'x-csrf-token';
    private const FIELD_NAME = '_csrf_token';

    /** Safe methods that bypass CSRF checks. */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __invoke(Request $request, callable $next): Response
    {
        $method = $request->method();

        // Safe methods bypass CSRF
        if (in_array($method, self::SAFE_METHODS, true)) {
            return $next($request);
        }

        $expected = self::getStoredToken();
        $submitted = self::getSubmittedToken($request);

        if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
            return self::deny($request);
        }

        // Regenerate token after successful verification
        self::regenerate();

        return $next($request);
    }

    /** Get the current CSRF token (generate if missing). */
    public static function token(): string
    {
        $token = self::getStoredToken();
        if ($token === '') {
            $token = self::regenerate();
        }
        return $token;
    }

    /** Generate a new token and store it. */
    public static function regenerate(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));

        // Store in session if available
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    /** HTML hidden input for forms. */
    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . $token . '">';
    }

    /** Retrieve the stored token from session or cookie. */
    private static function getStoredToken(): string
    {
        // Session-backed (primary)
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[self::SESSION_KEY])) {
            return (string) $_SESSION[self::SESSION_KEY];
        }

        // Double-submit cookie fallback
        return $_COOKIE[self::COOKIE_NAME] ?? '';
    }

    /** Retrieve the submitted token from header, POST body, or query. */
    private static function getSubmittedToken(Request $request): string
    {
        // Check header first (for AJAX/API clients)
        $headerToken = $request->header(self::HEADER_NAME);
        if ($headerToken !== '') {
            return $headerToken;
        }

        // Check POST body
        $postToken = $request->post(self::FIELD_NAME);
        if ($postToken !== '') {
            return $postToken;
        }

        return '';
    }

    /** Return a 403 response, content-negotiated. */
    private static function deny(Request $request): Response
    {
        $status = 403;

        if ($request->accepts('application/json') && !$request->accepts('text/html')) {
            return (new Response($status))->json([
                'type' => 'about:blank',
                'title' => 'CSRF token mismatch',
                'status' => $status,
            ], $status);
        }

        return (new Response($status))->withBody('CSRF token mismatch');
    }
}
