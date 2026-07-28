<?php

declare(strict_types=1);

namespace Karhu\Http;

/**
 * Signals "the requested resource does not exist" — idiomatic 404.
 *
 * Controllers should throw this instead of hand-crafting a 404 response
 * for the resource-not-found-by-id shape. Karhu\App::callHandler catches
 * it and routes the response through the container-bound ErrorHandler
 * (or DefaultErrorHandler if none is bound), so the branded 404 page
 * fires for both "unmatched route" AND "matched controller threw NFE"
 * paths through one seam.
 *
 * Distinct from Karhu\Container\NotFoundException — that one signals
 * "DI container has no binding for id X" (a wiring bug), NOT a user-
 * facing HTTP 404. Different namespace + different base class hierarchy
 * (Container's extends ContainerException; this extends RuntimeException,
 * matching ForbiddenException at Karhu\Error\ForbiddenException:17).
 *
 * If NFE escapes the request pipeline (e.g., thrown from a CLI command
 * or a queue worker), Karhu\Error\ExceptionHandler catches it at the
 * SAPI boundary and renders a 404 via its statusCode() map — see
 * ExceptionHandler.php:144-153.
 */
final class NotFoundException extends \RuntimeException
{
    public function __construct(string $message = 'Not Found', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
