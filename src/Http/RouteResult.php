<?php

declare(strict_types=1);

namespace Karhu\Http;

/**
 * Result of Router::match() — one of: found, not found, method not allowed.
 */
final class RouteResult
{
    private function __construct(
        public readonly bool $found,
        public readonly string $handler = '',
        public readonly string $method = '',
        /** @var array<string, string> */
        public readonly array $params = [],
        /** @var list<string> Allowed methods when status is 405 */
        public readonly array $allowedMethods = [],
    ) {}

    /** Route matched.
     * @param array<string, string> $params
     */
    public static function found(string $handler, string $method, array $params = []): self
    {
        return new self(true, $handler, $method, $params);
    }

    /** No route matched the path. */
    public static function notFound(): self
    {
        return new self(false);
    }

    /** Path matched but method is not allowed.
     * @param list<string> $allowedMethods
     */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        return new self(false, allowedMethods: $allowedMethods);
    }

    public function isMethodNotAllowed(): bool
    {
        return !$this->found && $this->allowedMethods !== [];
    }
}
