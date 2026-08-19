# Part 2 — Controllers and services

The controller currently owns its data. We move storage into a service, let the container wire it
up, and collapse five actions into one resource controller.

## A store

`app/IssueStore.php`:

```php
<?php

declare(strict_types=1);

namespace App;

final class IssueStore
{
    /** @var array<int, array{id:int, title:string, body:string, author:string}> */
    private array $issues = [];

    private int $nextId = 1;

    public function seed(): void
    {
        $this->add('Fix login redirect', 'Goes to the wrong URL after auth.', 'admin');
        $this->add('Add dark mode', 'Requested in settings.', 'editor');
    }

    /** @return array{id:int, title:string, body:string, author:string} */
    public function add(string $title, string $body, string $author): array
    {
        $issue = ['id' => $this->nextId++, 'title' => $title, 'body' => $body, 'author' => $author];
        $this->issues[$issue['id']] = $issue;

        return $issue;
    }

    /** @return list<array{id:int, title:string, body:string, author:string}> */
    public function all(): array
    {
        return array_values($this->issues);
    }

    /** @return array{id:int, title:string, body:string, author:string}|null */
    public function find(int $id): ?array
    {
        return $this->issues[$id] ?? null;
    }

    public function delete(int $id): bool
    {
        if (!isset($this->issues[$id])) {
            return false;
        }

        unset($this->issues[$id]);

        return true;
    }
}
```

In-memory, so it resets on every request under `php -S`. Part 5 points at the real replacement.

## Ask for it in the constructor

```php
final class IssueController
{
    public function __construct(
        private readonly IssueStore $store,
    ) {}

    #[Route('/issues', methods: ['GET'], name: 'issues.index')]
    public function index(Request $request): Response
    {
        return (new Response())->json(['issues' => $this->store->all()]);
    }
}
```

Nothing else is needed. karhu resolves controllers **through the container**, which auto-wires
constructor dependencies by reflection: it sees the `IssueStore` type hint, finds no registration,
notices `IssueStore` is a concrete class, and builds one.

## When you need to configure it

Auto-wiring builds an *empty* store, so nothing is seeded. Register a prepared instance in
`public/index.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\IssueStore;

$store = new IssueStore();
$store->seed();

$app = new Karhu\App();
$app->container()->set(IssueStore::class, $store);

$app->router()->scanControllers(require __DIR__ . '/../config/controllers.php');
$app->run();
```

`set()` wins over auto-wiring, so the controller now receives the seeded store.

The three registration styles:

```php
$c->set(IssueStore::class, $store);                      // a built instance
$c->factory(PDO::class, fn($c) => new PDO($dsn));        // built on first use, then cached
$c->bind(IssueRepository::class, PdoIssues::class);      // interface → concrete
```

!!! note "Everything is a singleton"
    Whatever the container builds is cached, so one instance serves the whole process. Under
    `php -S` or PHP-FPM that is one request; under a long-running worker it is not — do not keep
    per-request state on a service.

## One controller for the whole resource

`/issues` and `/issues/{id}` across four verbs is a lot of near-identical methods.
`AbstractResourceController` dispatches on the verb instead:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\IssueStore;
use Karhu\Attributes\Route;
use Karhu\Http\AbstractResourceController;
use Karhu\Http\Request;
use Karhu\Http\Response;

final class IssueController extends AbstractResourceController
{
    public function __construct(
        private readonly IssueStore $store,
    ) {}

    #[Route('/issues', methods: ['GET', 'POST'])]
    #[Route('/issues/{id}', methods: ['GET', 'DELETE'])]
    public function __invoke(Request $request): Response
    {
        return $this->dispatch($request);
    }

    protected function index(Request $request): Response
    {
        return $this->respond($request, ['issues' => $this->store->all()]);
    }

    protected function show(Request $request, string $id): Response
    {
        $issue = $this->store->find((int) $id);

        if ($issue === null) {
            return (new Response())->json(['error' => 'Issue not found'], 404);
        }

        return $this->respond($request, $issue);
    }

    protected function delete(Request $request, string $id): Response
    {
        if (!$this->store->delete((int) $id)) {
            return (new Response())->json(['error' => 'Issue not found'], 404);
        }

        return new Response(204);
    }
}
```

`#[Route]` is repeatable, so two attributes on one method cover both paths. `dispatch()` then
routes by verb:

| Verb | `{id}` | Action |
|---|---|---|
| `GET` | absent | `index()` |
| `GET` | present | `show()` |
| `POST` | — | `create()` |
| `PUT` | present | `update()` |
| `DELETE` | present | `delete()` |

Actions you do not override return `405 Not Implemented` — we have not written `create()` yet, so
`POST /issues` says exactly that. Part 3 fills it in.

!!! warning "The parameter must be called `id`"
    `dispatch()` looks for `routeParams()['id']`. Naming it `{issueId}` sends every request to
    `index()`, which looks like a caching bug and is not.

## Try it

```bash
php -S localhost:8080 -t public
```

```bash
curl -s localhost:8080/issues | head -c 80
curl -s localhost:8080/issues/1
curl -s -o /dev/null -w 'DELETE → %{http_code}\n' -X DELETE localhost:8080/issues/1
curl -s -o /dev/null -w 'POST   → %{http_code}\n' -X POST localhost:8080/issues
```

```
{"issues":[{"id":1,"title":"Fix login redirect",...
{"id":1,"title":"Fix login redirect","body":"Goes to the wrong URL after auth.","author":"admin"}
DELETE → 204
POST   → 405
```

## Content negotiation, free

`respond()` returns JSON to API clients and HTML to browsers, using the rule karhu applies
everywhere: JSON when the client accepts `application/json` **and not** `text/html`.

```bash
curl -s -H 'Accept: application/json' localhost:8080/issues | head -c 40
curl -s -H 'Accept: text/html' localhost:8080/issues -D- -o /dev/null | grep -i content-type
```

That is why `accepts('application/json')` is not the right check on its own — a browser sends
`*/*` and would match it.

**[Part 3 — Validating input →](03-validation.md)**
