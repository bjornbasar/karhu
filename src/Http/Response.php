<?php

declare(strict_types=1);

namespace Karhu\Http;

/**
 * Minimal emittable HTTP response — fluent, immutable-ish.
 *
 * PSR-7 *shape*, not full compliance. Provides the methods controllers
 * actually call: withStatus, withHeader, withBody, json, redirect, emit.
 */
final class Response
{
    private int $status = 200;

    /** @var array<string, string> */
    private array $headers = [];

    private string $body = '';

    /** Create with optional initial status code. */
    public function __construct(int $status = 200)
    {
        $this->status = $status;
    }

    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = $value;
        return $clone;
    }

    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    /**
     * JSON convenience — sets Content-Type and encodes the payload.
     *
     * @param mixed $data  Any JSON-encodable value.
     */
    public function json(mixed $data, int $status = 200): self
    {
        $clone = clone $this;
        $clone->status = $status;
        $clone->headers['content-type'] = 'application/json';
        $clone->body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        return $clone;
    }

    /** Redirect convenience — 302 by default. */
    public function redirect(string $url, int $status = 302): self
    {
        $clone = clone $this;
        $clone->status = $status;
        $clone->headers['location'] = $url;
        return $clone;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function body(): string
    {
        return $this->body;
    }

    /** Send the response to the client (headers + body). */
    public function emit(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        echo $this->body;
    }
}
