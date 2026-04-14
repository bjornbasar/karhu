# Getting Started

## Installation

```bash
composer create-project bjornbasar/karhu-skeleton my-app
cd my-app
```

## Project structure

```
my-app/
├── app/
│   ├── Controllers/    # Your controllers with #[Route] attributes
│   └── Commands/       # Your CLI commands with #[Command] attributes
├── config/
│   ├── controllers.php # List of controller classes to scan
│   └── commands.php    # List of command classes to scan
├── public/
│   └── index.php       # Front controller (<5 lines)
└── docs/deployment/    # nginx + Apache config snippets
```

## The front controller

`public/index.php` is the entry point:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = new Karhu\App();
$app->router()->scanControllers(require __DIR__ . '/../config/controllers.php');
$app->run();
```

## Your first controller

```php
<?php
namespace App\Controllers;

use Karhu\Attributes\Route;
use Karhu\Http\Request;
use Karhu\Http\Response;

final class HomeController
{
    #[Route('/', name: 'home')]
    public function index(Request $request): Response
    {
        return (new Response())->json(['message' => 'Hello from karhu!']);
    }
}
```

Register it in `config/controllers.php`:

```php
return [
    App\Controllers\HomeController::class,
];
```

## Run the dev server

```bash
php -S localhost:8080 -t public
# or use the composer script:
composer serve
```

## Adding middleware

```php
$app = new Karhu\App();
$app->pipe(new \Karhu\Middleware\Cors(['origins' => ['*']]));
$app->pipe(new \Karhu\Middleware\Session());
$app->pipe(new \Karhu\Middleware\Csrf());
$app->router()->scanControllers(require __DIR__ . '/../config/controllers.php');
$app->run();
```

## Production: route cache

```bash
bin/karhu route:cache
```

This compiles routes to `cache/routes.php`, skipping reflection on every request.
