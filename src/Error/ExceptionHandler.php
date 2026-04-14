<?php

declare(strict_types=1);

namespace Karhu\Error;

use Karhu\Http\Request;
use Karhu\Http\Response;

/**
 * Content-negotiated exception handler.
 *
 * JSON responses use the RFC 7807 (application/problem+json) shape.
 * HTML responses show a stack trace only when APP_ENV=local (dev mode);
 * in production a generic error page is shown. All errors are logged
 * to stderr.
 */
final class ExceptionHandler
{
    private bool $devMode;

    public function __construct(?bool $devMode = null)
    {
        $this->devMode = $devMode ?? (getenv('APP_ENV') === 'local');
    }

    /**
     * Convert an exception to an HTTP Response, content-negotiated
     * based on the incoming Request's Accept header.
     */
    public function handle(\Throwable $e, ?Request $request = null): Response
    {
        $this->log($e);

        $status = $this->statusCode($e);
        $wantsJson = $request !== null && $request->accepts('application/json')
            && !$request->accepts('text/html');

        return $wantsJson
            ? $this->jsonResponse($e, $status)
            : $this->htmlResponse($e, $status);
    }

    /**
     * Register as PHP's global exception/error handler.
     * Emits a Response directly — use from public/index.php.
     */
    public function register(): void
    {
        set_exception_handler(function (\Throwable $e): void {
            $request = null;
            try {
                $request = Request::fromGlobals();
            } catch (\Throwable) {
                // Suppress — we're already in error handling
            }
            $this->handle($e, $request)->emit();
        });

        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    /** RFC 7807 problem+json response. */
    private function jsonResponse(\Throwable $e, int $status): Response
    {
        $body = [
            'type' => 'about:blank',
            'title' => $this->title($status),
            'status' => $status,
        ];

        if ($this->devMode) {
            $body['detail'] = $e->getMessage();
            $body['exception'] = $e::class;
            $body['file'] = $e->getFile() . ':' . $e->getLine();
            $body['trace'] = $this->traceAsStrings($e);
        }

        return (new Response($status))
            ->withHeader('Content-Type', 'application/problem+json')
            ->withBody(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    /** HTML error page — full trace in dev, generic in prod. */
    private function htmlResponse(\Throwable $e, int $status): Response
    {
        $title = $this->title($status);

        if ($this->devMode) {
            $class = htmlspecialchars($e::class, ENT_QUOTES);
            $message = htmlspecialchars($e->getMessage(), ENT_QUOTES);
            $file = htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES);
            $trace = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES);

            $html = <<<HTML
            <!DOCTYPE html>
            <html><head><title>{$title}</title>
            <style>body{font-family:monospace;margin:2em;background:#1a1a2e;color:#e0e0e0}
            h1{color:#f44}pre{background:#111;padding:1em;overflow-x:auto;border-radius:4px}</style></head>
            <body><h1>{$status} {$title}</h1>
            <p><strong>{$class}:</strong> {$message}</p>
            <p><code>{$file}</code></p>
            <pre>{$trace}</pre></body></html>
            HTML;
        } else {
            $html = <<<HTML
            <!DOCTYPE html>
            <html><head><title>{$title}</title>
            <style>body{font-family:sans-serif;margin:2em;text-align:center;color:#333}
            h1{font-size:3em;margin-top:20vh}</style></head>
            <body><h1>{$status}</h1><p>{$title}</p></body></html>
            HTML;
        }

        return (new Response($status))
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($html);
    }

    /** Log the error to stderr. */
    private function log(\Throwable $e): void
    {
        $message = sprintf(
            "[%s] %s: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        );
        file_put_contents('php://stderr', $message, FILE_APPEND);
    }

    /** Map exception types to HTTP status codes. */
    private function statusCode(\Throwable $e): int
    {
        if ($e instanceof \InvalidArgumentException) {
            return 400;
        }
        return 500;
    }

    /** HTTP status title text. */
    private function title(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }

    /**
     * @return list<string>
     */
    private function traceAsStrings(\Throwable $e): array
    {
        return array_map(
            static fn (array $frame): string => sprintf(
                '%s:%s %s%s%s()',
                $frame['file'] ?? '<internal>',
                $frame['line'] ?? '?',
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'],
            ),
            $e->getTrace(),
        );
    }
}
