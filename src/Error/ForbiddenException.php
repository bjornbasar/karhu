<?php

declare(strict_types=1);

namespace Karhu\Error;

/**
 * Signals that the current user is not allowed to perform the requested action.
 *
 * When `$redirectTo` is null, ExceptionHandler renders a 403 response
 * (content-negotiated HTML or RFC 7807 problem+json).
 *
 * When `$redirectTo` is set, ExceptionHandler renders a 302 redirect to that URL —
 * useful for stale-session recovery (e.g., a user who was kicked from a household
 * gets redirected to /household/setup rather than seeing a 403).
 */
final class ForbiddenException extends \RuntimeException
{
    public function __construct(
        string $message = 'Forbidden',
        public readonly ?string $redirectTo = null,
    ) {
        parent::__construct($message);
    }
}
