<?php

declare(strict_types=1);

namespace Karhu\Http;

/**
 * Bland default ErrorHandler — the pre-v0.1.4 fallback shape.
 *
 * Karhu\App resolves this when the container has no ErrorHandler binding.
 * Body strings are the byte-for-byte same as the old hard-coded fallbacks
 * in App::dispatch line 99-101 (404) and line 93-97 (405) — a consumer
 * that never binds ErrorHandler (e.g., istrbuddy today) sees zero
 * behavior change on upgrade.
 *
 * Body dispatched by $context['status'] so any future 405/500 wiring
 * (when those seams get added to App) reuses this same handler without
 * a follow-up refactor.
 *
 * Cache-Control: no-store is applied to every response — when a route
 * gets added post-release, users see the new page immediately rather
 * than a cached stale 404 for CDN/browser caching TTLs.
 */
final class DefaultErrorHandler implements ErrorHandler
{
    public function handle(Request $request, ?\Throwable $error, array $context): Response
    {
        $status = is_int($context['status'] ?? null) ? $context['status'] : 500;

        // Status → body map matches Karhu\Error\ExceptionHandler::title()
        // for consistency across framework error responses.
        $body = match ($status) {
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            400 => 'Bad Request',
            403 => 'Forbidden',
            500 => 'Error',
            default => 'Error',
        };

        return (new Response($status))
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($body);
    }
}
