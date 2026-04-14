<?php

declare(strict_types=1);

namespace Karhu\Middleware;

use Karhu\Http\Request;
use Karhu\Http\Response;

/**
 * CORS middleware — configurable with sensible defaults.
 *
 * Preflight OPTIONS requests are auto-handled before Router dispatch.
 * Default: same-origin only; GET, POST, PUT, DELETE allowed; common
 * request headers permitted.
 */
final class Cors
{
    /** @var list<string> Allowed origins ('*' for any) */
    private array $allowedOrigins;

    /** @var list<string> Allowed HTTP methods */
    private array $allowedMethods;

    /** @var list<string> Allowed request headers */
    private array $allowedHeaders;

    private bool $allowCredentials;

    private int $maxAge;

    /**
     * @param array<string, mixed> $config Keys: origins, methods, headers, credentials, max_age
     */
    public function __construct(array $config = [])
    {
        /** @var list<string> $origins */
        $origins = (array) ($config['origins'] ?? []);
        $this->allowedOrigins = $origins;
        /** @var list<string> $methods */
        $methods = (array) ($config['methods'] ?? ['GET', 'POST', 'PUT', 'DELETE']);
        $this->allowedMethods = $methods;
        /** @var list<string> $headers */
        $headers = (array) ($config['headers'] ?? ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'X-CSRF-Token']);
        $this->allowedHeaders = $headers;
        $this->allowCredentials = (bool) ($config['credentials'] ?? false);
        $this->maxAge = (int) ($config['max_age'] ?? 86400);
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $origin = $request->header('origin');

        // No Origin header → not a CORS request, pass through
        if ($origin === '') {
            return $next($request);
        }

        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            return $next($request);
        }

        // Preflight (OPTIONS) — respond immediately without dispatching
        if ($request->method() === 'OPTIONS') {
            return $this->preflight($origin);
        }

        // Actual request — add CORS headers to the response
        /** @var Response $response */
        $response = $next($request);
        return $this->addCorsHeaders($response, $origin);
    }

    /** Check if the given origin is in the allow list. */
    private function isOriginAllowed(string $origin): bool
    {
        if ($this->allowedOrigins === [] || in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }

    /** Build a preflight response with all CORS headers. */
    private function preflight(string $origin): Response
    {
        $response = (new Response(204))
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /** Add CORS headers to an actual (non-preflight) response. */
    private function addCorsHeaders(Response $response, string $origin): Response
    {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin');

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
