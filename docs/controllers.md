# Controllers

A karhu controller is a plain class. There is no base class to extend, no interface to implement,
and no naming convention enforced — the only requirement is a public method carrying a
`#[Route]`.

## A plain controller

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Karhu\Attributes\Route;
use Karhu\Http\NotFoundException;
use Karhu\Http\Request;
use Karhu\Http\Response;

final class IssueController
{
    public function __construct(
        private readonly IssueRepository $issues,
    ) {}

    #[Route('/issues', methods: ['GET'], name: 'issues.index')]
    public function index(Request $request): Response
    {
        return (new Response())->json($this->issues->all());
    }

    #[Route('/issues/{id}', methods: ['GET'], name: 'issues.show')]
    public function show(Request $request): Response
    {
        $issue = $this->issues->find($request->routeParams()['id']);

        if ($issue === null) {
            throw new NotFoundException('issue not found');
        }

        return (new Response())->json($issue);
    }
}
```

Register it in `config/controllers.php`:

```php
return [
    App\Controllers\IssueController::class,
];
```

## Constructor injection

Controllers are resolved through the container, so dependencies arrive automatically. Concrete
classes need no registration; interfaces need a `bind()`.

```php
$app->container()->bind(IssueRepository::class, PdoIssueRepository::class);
```

!!! note "Controllers are singletons"
    The container caches every instance, so one controller object serves every request within a
    process. Under PHP-FPM that is a single request, but under a long-running worker it is not —
    do not keep per-request state in properties.

## Reaching the current request

The handler's argument is the request. `App` also re-registers it in the container under
`Request::class` after route params are injected, so a service can be given the current request:

```php
$app->container()->factory(Breadcrumbs::class, fn($c) => new Breadcrumbs($c->get(Request::class)));
```

Prefer the argument. The container entry exists for services that sit deeper than the controller.

## Return values

| Returned | Result |
|---|---|
| `Response` | used as-is |
| `string` | 200, that body |
| `array` | 200, JSON-encoded |
| anything else | empty 200 |

The coercions are a convenience. Anything that needs a status code or a header should return a
`Response`.

## Signalling "not found"

```php
throw new NotFoundException('issue not found');
```

`App` catches it and routes it through the bound [`ErrorHandler`](api/http.md#errorhandler) — the
same path as an unmatched URL, so a branded 404 page is written once rather than in every
controller. See [Error Handling](errors.md).

!!! warning "Import `Karhu\Http\NotFoundException`"
    `Karhu\Container\NotFoundException` is a container wiring error and will surface as a 500.

---

## Resource controllers

When a class maps onto one resource with the usual five actions,
[`AbstractResourceController`](api/http.md#abstractresourcecontroller) dispatches on the HTTP verb
so you register two routes instead of five.

```php
use Karhu\Http\AbstractResourceController;

final class WidgetController extends AbstractResourceController
{
    public function __construct(private readonly WidgetRepository $widgets) {}

    #[Route('/widgets', methods: ['GET', 'POST'])]
    #[Route('/widgets/{id}', methods: ['GET', 'PUT', 'DELETE'])]
    public function __invoke(Request $request): Response
    {
        return $this->dispatch($request);
    }

    protected function index(Request $request): Response
    {
        return $this->respond($request, ['widgets' => $this->widgets->all()], '<h1>Widgets</h1>');
    }

    protected function show(Request $request, string $id): Response
    {
        $widget = $this->widgets->find($id) ?? throw new NotFoundException();

        return $this->respond($request, $widget);
    }

    protected function create(Request $request): Response
    {
        $id = $this->widgets->insert($request->body());

        return (new Response(201))->json(['id' => $id]);
    }
}
```

The dispatch table:

| Verb | `{id}` | Action |
|---|---|---|
| `GET` | absent | `index($request)` |
| `GET` | present | `show($request, $id)` |
| `POST` | — | `create($request)` |
| `PUT` | present | `update($request, $id)` |
| `DELETE` | present | `delete($request, $id)` |
| anything else | — | 405 |

Unoverridden actions return `405 Not Implemented`, so a partially implemented resource is
explicit rather than fatal.

The `protected respond(Request $request, array $data, string $html = '')` helper picks JSON or
HTML by the same rule as `prefersJson()`. With no `$html` it falls back to JSON-encoding the data
into an HTML response — pass real markup for browser clients.

!!! warning "The route parameter must be named `id`"
    `dispatch()` looks for `routeParams()['id']` specifically. `/widgets/{widgetId}` dispatches to
    `index()` — the list action — rather than `show()`, which looks like a caching bug and is not.

### When not to use it

`AbstractResourceController` earns its place for a genuine CRUD resource. For anything with a
different shape — a search endpoint, a webhook receiver, a multi-step form — a plain controller
with explicit routes is clearer. Nothing in karhu prefers one over the other.

## Organising controllers

One class per resource, listed in `config/controllers.php`. The registry is explicit rather than
a directory scan for two reasons: it is the same array `vendor/bin/karhu route:cache` compiles, and a
controller cannot start serving traffic just because a file landed in a folder.

```php
return [
    App\Controllers\HomeController::class,
    App\Controllers\IssueController::class,
    App\Controllers\WidgetController::class,
];
```
