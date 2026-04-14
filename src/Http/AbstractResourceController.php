<?php

declare(strict_types=1);

namespace Karhu\Http;

/**
 * Base resource controller — auto-dispatches on HTTP verb with
 * content negotiation from a single method body.
 *
 * Subclasses implement index/show/create/update/delete. The dispatch
 * method routes based on HTTP method + presence of an {id} route param.
 * Responses branch JSON vs HTML based on the Accept header.
 *
 * Spiritual port of chukwu Core_RestServer.
 */
abstract class AbstractResourceController
{
    /**
     * Dispatch a request to the appropriate action based on HTTP verb.
     *
     * Convention:
     *   GET    /resource       → index()
     *   GET    /resource/{id}  → show(id)
     *   POST   /resource       → create()
     *   PUT    /resource/{id}  → update(id)
     *   DELETE /resource/{id}  → delete(id)
     */
    public function dispatch(Request $request): Response
    {
        $id = $request->routeParams()['id'] ?? null;
        $method = $request->method();

        return match (true) {
            $method === 'GET' && $id === null => $this->index($request),
            $method === 'GET' => $this->show($request, (string) $id),
            $method === 'POST' => $this->create($request),
            $method === 'PUT' && $id !== null => $this->update($request, (string) $id),
            $method === 'DELETE' && $id !== null => $this->delete($request, (string) $id),
            default => (new Response(405))->withBody('Method Not Allowed'),
        };
    }

    /**
     * Helper: respond with JSON or HTML based on Accept header.
     *
     * @param Request              $request
     * @param array<string, mixed> $data    Data for JSON or template context
     * @param string               $html    HTML string for non-JSON clients
     */
    protected function respond(Request $request, array $data, string $html = ''): Response
    {
        if ($request->accepts('application/json') && !$request->accepts('text/html')) {
            return (new Response())->json($data);
        }

        return (new Response())
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($html !== '' ? $html : (string) json_encode($data));
    }

    /** GET /resource — list all. Override in subclass. */
    protected function index(Request $request): Response
    {
        return (new Response(405))->withBody('Not Implemented');
    }

    /** GET /resource/{id} — show one. Override in subclass. */
    protected function show(Request $request, string $id): Response
    {
        return (new Response(405))->withBody('Not Implemented');
    }

    /** POST /resource — create new. Override in subclass. */
    protected function create(Request $request): Response
    {
        return (new Response(405))->withBody('Not Implemented');
    }

    /** PUT /resource/{id} — update existing. Override in subclass. */
    protected function update(Request $request, string $id): Response
    {
        return (new Response(405))->withBody('Not Implemented');
    }

    /** DELETE /resource/{id} — delete. Override in subclass. */
    protected function delete(Request $request, string $id): Response
    {
        return (new Response(405))->withBody('Not Implemented');
    }
}
