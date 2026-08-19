# Part 1 — Routes and responses

We start with the smallest thing that answers an HTTP request, then add a route with a parameter.

## The front controller

Every request enters through one file. Create `public/index.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = new Karhu\App();
$app->router()->scanControllers(require __DIR__ . '/../config/controllers.php');
$app->run();
```

Three lines of work: build the app, tell the router which classes to scan for routes, run it.

## A controller

`app/Controllers/IssueController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Karhu\Attributes\Route;
use Karhu\Http\Request;
use Karhu\Http\Response;

final class IssueController
{
    /** @var list<array{id:int, title:string, body:string}> */
    private const ISSUES = [
        ['id' => 1, 'title' => 'Fix login redirect', 'body' => 'Goes to the wrong URL after auth.'],
        ['id' => 2, 'title' => 'Add dark mode', 'body' => 'Requested in settings.'],
    ];

    #[Route('/issues', methods: ['GET'], name: 'issues.index')]
    public function index(Request $request): Response
    {
        return (new Response())->json(['issues' => self::ISSUES]);
    }
}
```

The `#[Route]` attribute is metadata attached to the method. It does nothing by itself — it
becomes a route when `scanControllers()` reflects over the class at boot.

!!! warning "The path is the first argument"
    `#[Route('/issues', methods: ['GET'])]`. Writing `#[Route('GET', '/issues')]` sets the
    *path* to `GET` and raises a `TypeError`.

## The registry

`config/controllers.php`:

```php
<?php

return [
    App\Controllers\IssueController::class,
];
```

Controllers are listed explicitly rather than discovered by scanning directories. That is
deliberate — this same array is what `vendor/bin/karhu route:cache` compiles for production, so what you
read here is exactly what ships.

## Run it

```bash
php -S localhost:8080 -t public
```

```bash
curl -s localhost:8080/issues
```

```json
{"issues":[{"id":1,"title":"Fix login redirect","body":"Goes to the wrong URL after auth."},{"id":2,"title":"Add dark mode","body":"Requested in settings."}]}
```

## A route parameter

Add a second method:

```php
#[Route('/issues/{id}', methods: ['GET'], name: 'issues.show')]
public function show(Request $request): Response
{
    $id = (int) $request->routeParams()['id'];

    foreach (self::ISSUES as $issue) {
        if ($issue['id'] === $id) {
            return (new Response())->json($issue);
        }
    }

    return (new Response())->json(['error' => 'Issue not found'], 404);
}
```

```bash
curl -s localhost:8080/issues/2
# {"id":2,"title":"Add dark mode","body":"Requested in settings."}

curl -s -o /dev/null -w '%{http_code}\n' localhost:8080/issues/99
# 404
```

!!! note "Handlers take the `Request`, not the parameters"
    `show(Request $request)` — not `show(string $id)`. karhu calls the method with the request
    and nothing else; placeholders come from `$request->routeParams()`, always as **strings**, so
    cast when you need a number.

## What the router gives you for free

Try these against the two routes you already have:

```bash
curl -s -o /dev/null -w 'HEAD  /issues      → %{http_code}\n' -I localhost:8080/issues
curl -s -o /dev/null -w 'POST  /issues      → %{http_code}\n' -X POST localhost:8080/issues
curl -s -o /dev/null -w 'GET   /nope        → %{http_code}\n' localhost:8080/nope
curl -s -D- -o /dev/null -X OPTIONS localhost:8080/issues | grep -i '^allow'
```

```
HEAD  /issues      → 200
POST  /issues      → 405
GET   /nope        → 404
Allow: GET, HEAD, OPTIONS
```

- **`HEAD`** works on every `GET` route without registering it (RFC 9110).
- **A known path with the wrong method is a `405`**, with an `Allow` header — not a 404. That
  distinction matters to API clients.
- **`OPTIONS`** reports the methods a path actually supports.

## Named routes

Both routes have a `name`, so URLs can be generated rather than typed:

```php
$app->router()->urlFor('issues.show', ['id' => '2']);   // /issues/2
```

`urlFor()` throws `InvalidArgumentException` for an unknown name or a missing parameter — a
broken link fails loudly at the point it is built, instead of 404-ing for a user later.

## Where we are

```
istrbuddy/
├── app/Controllers/IssueController.php
├── config/controllers.php
├── public/index.php
└── composer.json
```

The data is a hard-coded constant and the controller is doing its own storage. Next we fix both.

**[Part 2 — Controllers and services →](02-controllers.md)**
