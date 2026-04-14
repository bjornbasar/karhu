<?php

declare(strict_types=1);

namespace Karhu\Middleware;

use Karhu\Auth\Rbac;
use Karhu\Http\Request;
use Karhu\Http\Response;

/**
 * Middleware that gates access by RBAC role.
 *
 * Reads the current username from the session (key: 'username').
 * Returns 403 if the user doesn't have the required role. Returns
 * 401 if no user is logged in.
 */
final class RequireRole
{
    /**
     * @param Rbac         $rbac  The RBAC service
     * @param list<string> $roles At least one of these roles is required
     * @param string       $sessionKey Session key holding the current username
     */
    public function __construct(
        private readonly Rbac $rbac,
        private readonly array $roles,
        private readonly string $sessionKey = 'username',
    ) {}

    /**
     * Factory: create a middleware callable for a specific role set.
     *
     * @param Rbac         $rbac
     * @param list<string> $roles
     * @return callable(Request, callable): Response
     */
    public static function for(Rbac $rbac, array $roles): callable
    {
        $mw = new self($rbac, $roles);
        return fn (Request $request, callable $next): Response => $mw($request, $next);
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $username = Session::get($this->sessionKey);

        if (!is_string($username) || $username === '') {
            return $this->deny(401, 'Unauthorized', $request);
        }

        if (!$this->rbac->hasAnyRole($username, $this->roles)) {
            return $this->deny(403, 'Forbidden', $request);
        }

        return $next($request);
    }

    private function deny(int $status, string $message, Request $request): Response
    {
        if ($request->accepts('application/json') && !$request->accepts('text/html')) {
            return (new Response($status))->json([
                'type' => 'about:blank',
                'title' => $message,
                'status' => $status,
            ], $status);
        }

        return (new Response($status))->withBody($message);
    }
}
