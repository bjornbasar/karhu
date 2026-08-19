# karhu-skeleton

A starter application: a working front controller, one route, one CLI command, and reference
deployment configs. Meant to be copied and edited.

[github.com/bjornbasar/karhu-skeleton](https://github.com/bjornbasar/karhu-skeleton) · v0.1.1 · MIT

## Install

```bash
composer create-project bjornbasar/karhu-skeleton myapp
cd myapp
composer serve      # http://localhost:8080
```

The skeleton requires **`bjornbasar/karhu: ^0.1.5`**, so you get a framework release rather than
a moving branch. It tracked `dev-main` with `minimum-stability: dev` until skeleton v0.1.1, which
meant two people installing a week apart got different frameworks.

That pin is also what makes `vendor/bin/karhu` work below — Composer only creates the shim
because karhu declares a `bin` key, which landed in karhu v0.1.5.

!!! warning "Use v0.1.1 or newer"
    Skeleton **v0.1.0** predates this template's documentation entirely: no README, no LICENSE,
    and the framework pinned to `dev-main`. It was the only tag when the package was first
    submitted to Packagist. `create-project` resolves the newest stable release, so you get
    v0.1.1 — but do not pin back to v0.1.0.

## What you get

```
myapp/
├── app/
│   ├── Commands/HelloCommand.php        #[Command] sample
│   └── Controllers/HomeController.php   #[Route] sample
├── config/
│   ├── commands.php                     CLI registry
│   └── controllers.php                  route-scan registry
├── public/
│   ├── index.php                        front controller
│   └── .htaccess                        Apache rewrite
├── docs/deployment/{apache.conf,nginx.conf}
└── composer.json
```

The front controller is three lines:

```php
$app = new Karhu\App();
$app->router()->scanControllers(require __DIR__ . '/../config/controllers.php');
$app->run();
```

## The sample route

`HomeController` demonstrates content negotiation — JSON for API clients, HTML for browsers:

```php
final class HomeController
{
    #[Route('/', name: 'home')]
    public function index(Request $request): Response
    {
        if ($request->prefersJson()) {
            return (new Response())->json(['message' => 'Hello from karhu!']);
        }

        return (new Response())
            ->withHeader('Content-Type', 'text/html')
            ->withBody('<h1>Hello from karhu!</h1>');
    }
}
```

```bash
curl localhost:8080                                  # HTML
curl -H 'Accept: application/json' localhost:8080    # JSON
```

## The sample command

```bash
vendor/bin/karhu list
vendor/bin/karhu hello --name=Bjorn
# Hello, Bjorn!
```

!!! warning "`--name=Bjorn`, not a positional argument"
    `HelloCommand` reads `$args['name']`, so `karhu hello Bjorn` lands in `$args['0']` and prints
    `Hello, world!`. The skeleton's own README currently shows the positional form.

## Adding your own

**A route** — create the controller, then add it to `config/controllers.php`:

```php
namespace App\Controllers;

use Karhu\Attributes\Route;
use Karhu\Http\Request;
use Karhu\Http\Response;

final class GreetController
{
    #[Route('/greet/{name}', methods: ['GET'])]
    public function greet(Request $request): Response
    {
        return (new Response())->json(['hello' => $request->routeParams()['name']]);
    }
}
```

!!! warning "The skeleton's README shows a broken example"
    Its `GreetController` snippet is `#[Route('GET', '/greet/{name}')]` with
    `greet(string $name)` and a static `Response::json()`. All three are wrong — the path comes
    first, handlers receive the `Request`, and `json()` is an instance method. Use the form above.

**A command** — create the class, then add it to `config/commands.php`. Commands resolve from the
container, so add `config/container.php` if they have dependencies. See [CLI](../cli.md).

## Related

- [Installation](../installation.md) — including building the same layout by hand
- [Tutorial](../tutorial/index.md) — builds an application from an empty directory
