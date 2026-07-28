<?php

declare(strict_types=1);

namespace Karhu\Http;

/**
 * Pluggable HTTP-error response handler.
 *
 * v0.1.4 hook — apps bind their own implementation via the container to
 * render branded error pages (custom 404 template, JSON-negotiated bodies,
 * etc.) instead of framework-default plaintext.
 *
 * Distinct from Karhu\Error\ExceptionHandler:
 * - ExceptionHandler catches uncaught throwables at the SAPI boundary via
 *   set_exception_handler(). It's the last-resort net for 500s + anything
 *   that escapes the request pipeline (CLI commands, queue workers).
 * - ErrorHandler is invoked by Karhu\App during dispatch when the router
 *   found no matching route (404) OR a matched controller threw
 *   NotFoundException. Future 405/500-context extensions plug into the
 *   same interface via the $context array.
 *
 * MUST be an interface, not an abstract class. Karhu\Container\Container's
 * has() method falls back to class_exists() — an abstract class would
 * make has() return true even when unbound, and get() would then fail
 * in resolve() (abstract classes are not instantiable). Interfaces make
 * class_exists() return false, so has() correctly returns false when no
 * binding is configured, and Karhu\App falls back to DefaultErrorHandler.
 *
 * @see DefaultErrorHandler   The bland fallback used when no binding exists.
 * @see NotFoundException     The throw-shape that App::callHandler catches
 *                            and routes through the handler.
 */
interface ErrorHandler
{
    /**
     * Build a Response for an HTTP error condition discovered inside dispatch.
     *
     * @param Request              $request The incoming request. Handlers use
     *                                      this for content-negotiation
     *                                      (Accept header via
     *                                      $request->prefersJson()) and to
     *                                      look up path/session state.
     * @param \Throwable|null      $error   Populated on the "controller threw
     *                                      NotFoundException" path (message
     *                                      may carry a hint like "widget 42
     *                                      not found"). Null on the "router
     *                                      matched no route" path.
     * @param array<string, mixed> $context At minimum carries
     *                                      ['status' => int]. Future keys
     *                                      may include 'allowed_methods'
     *                                      for a MethodNotAllowedHandler
     *                                      shape without a v0.2.0 refactor.
     */
    public function handle(Request $request, ?\Throwable $error, array $context): Response;
}
