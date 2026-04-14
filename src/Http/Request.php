<?php

declare(strict_types=1);

namespace Karhu\Http;

/**
 * Minimal readonly HTTP request built from PHP superglobals.
 *
 * PSR-7 *shape* — not full compliance. Covers the 80% case;
 * power users can wrap this for strict PSR-7 interop.
 */
final class Request
{
    /** @var array<string, string> */
    private readonly array $headers;

    /** @var array<string, string> */
    private readonly array $query;

    /** @var array<string, string> */
    private readonly array $post;

    private readonly string $method;
    private readonly string $path;
    private readonly string $rawBody;

    /** @var array<string, mixed>|null Lazy-decoded JSON body */
    private ?array $jsonBody = null;
    private bool $jsonDecoded = false;

    /** @var array<string, string> Route parameters injected by Router after dispatch */
    private array $routeParams = [];

    /**
     * Build from superglobals. Prefer Request::fromGlobals() in application
     * code; the constructor is public to support testing with custom values.
     *
     * @param array<string, string> $server  $_SERVER equivalent
     * @param array<string, string> $get     $_GET equivalent
     * @param array<string, string> $post    $_POST equivalent
     * @param string                $body    php://input equivalent
     * @param array<string, string> $headers Pre-parsed headers (if empty, extracted from $server)
     */
    public function __construct(
        array $server = [],
        array $get = [],
        array $post = [],
        string $body = '',
        array $headers = [],
    ) {
        $this->method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        $this->path = strtok($server['REQUEST_URI'] ?? '/', '?') ?: '/';
        $this->query = $get;
        $this->post = $post;
        $this->rawBody = $body;
        $this->headers = $headers !== [] ? $headers : self::extractHeaders($server);
    }

    /** Create a request from PHP superglobals. */
    public static function fromGlobals(): self
    {
        return new self(
            $_SERVER,
            $_GET,
            $_POST,
            file_get_contents('php://input') ?: '',
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /** Get a single header value (case-insensitive), or default. */
    public function header(string $name, string $default = ''): string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** @return array<string, string> All headers, keys lowercased. */
    public function headers(): array
    {
        return $this->headers;
    }

    /** Get a single query parameter, or default. */
    public function query(string $key, string $default = ''): string
    {
        return $this->query[$key] ?? $default;
    }

    /** Get a single POST parameter, or default. */
    public function post(string $key, string $default = ''): string
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Decoded request body. For JSON content-type, auto-decodes and
     * returns the associative array. For other content-types, returns
     * the raw string.
     *
     * @return array<string, mixed>|string
     */
    public function body(): array|string
    {
        $ct = $this->header('content-type');

        if (str_contains($ct, 'application/json') || str_contains($ct, '+json')) {
            if (!$this->jsonDecoded) {
                $decoded = json_decode($this->rawBody, true);
                $this->jsonBody = is_array($decoded) ? $decoded : null;
                $this->jsonDecoded = true;
            }
            return $this->jsonBody ?? [];
        }

        return $this->rawBody;
    }

    /** Raw body string regardless of content-type. */
    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /** Check whether the client accepts a given media type. */
    public function accepts(string $type): bool
    {
        $accept = $this->header('accept', '*/*');
        return str_contains($accept, $type) || str_contains($accept, '*/*');
    }

    /** @return array<string, string> Route parameters set by Router. */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    /** @param array<string, string> $params */
    public function withRouteParams(array $params): self
    {
        $clone = clone $this;
        $clone->routeParams = $params;
        return $clone;
    }

    /**
     * Extract HTTP headers from $_SERVER keys.
     *
     * @param array<string, string> $server
     * @return array<string, string>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        // Content-Type and Content-Length aren't prefixed with HTTP_
        if (isset($server['CONTENT_TYPE'])) {
            $headers['content-type'] = $server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $headers['content-length'] = $server['CONTENT_LENGTH'];
        }

        return $headers;
    }
}
