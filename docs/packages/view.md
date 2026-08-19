# karhu-view

A `ViewInterface` so controllers can render templates without coupling to an engine, plus Twig and
Plates adapters.

[github.com/bjornbasar/karhu-view](https://github.com/bjornbasar/karhu-view) · v0.1.0 · MIT

```bash
composer require bjornbasar/karhu-view

composer require twig/twig       # then pick an engine
composer require league/plates
```

The engine is **not** a dependency of the package — install the one you want.

## Rendering

```php
use Karhu\View\TwigAdapter;

$view = new TwigAdapter(__DIR__ . '/../templates');

$html = $view->render('home.html.twig', ['name' => 'karhu']);

return (new Response())
    ->withHeader('Content-Type', 'text/html; charset=UTF-8')
    ->withBody($html);
```

## `ViewInterface`

| Method | Returns | Description |
|---|---|---|
| `render(string $template, array $data = [])` | `string` | Render a template to a string. |

One method. It returns a string rather than a `Response`, so the caller stays in control of the
status and headers.

## Adapters

### `TwigAdapter`

```php
public function __construct(string $templateDir, bool $cache = false)
```

| Method | Returns | Description |
|---|---|---|
| `render(string $template, array $data = [])` | `string` | Render. |
| `twig()` | `\Twig\Environment` | The underlying environment — for filters, functions, globals. |

```php
$view = new TwigAdapter(__DIR__ . '/../templates', cache: true);

$view->twig()->addGlobal('csrf', Karhu\Middleware\Csrf::token());
$view->twig()->addFilter(new \Twig\TwigFilter('money', fn($n) => '$' . number_format($n, 2)));
```

!!! warning "`cache: false` is the default"
    Twig recompiles every template on every request when caching is off. Turn it on in production.

### `PlatesAdapter`

```php
public function __construct(string $templateDir)
```

| Method | Returns | Description |
|---|---|---|
| `render(string $template, array $data = [])` | `string` | Render. |
| `engine()` | `\League\Plates\Engine` | The underlying engine — for folders and extensions. |

Plates templates are plain PHP, so there is no compilation step and no cache setting.

## Wiring it in

Bind the interface and inject it:

```php
$app->container()->set(
    Karhu\View\ViewInterface::class,
    new Karhu\View\TwigAdapter(__DIR__ . '/../templates', cache: $isProduction),
);
```

```php
final class IssueController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly IssueStore $store,
    ) {}

    #[Route('/issues', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($request->prefersJson()) {
            return (new Response())->json(['issues' => $this->store->all()]);
        }

        return (new Response())
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($this->view->render('issues/index.html.twig', [
                'issues' => $this->store->all(),
            ]));
    }
}
```

Because the controller depends on the interface, swapping Twig for Plates is one container line.

## A custom engine

```php
use Karhu\View\ViewInterface;

final class BladeAdapter implements ViewInterface
{
    public function render(string $template, array $data = []): string
    {
        // ...
    }
}
```

## Rendering error pages

A branded 404 is the natural place for this — bind a view into your
[`ErrorHandler`](../api/http.md#errorhandler):

```php
final class BrandedErrorHandler implements Karhu\Http\ErrorHandler
{
    public function __construct(private readonly ViewInterface $view) {}

    public function handle(Request $request, ?\Throwable $error, array $context): Response
    {
        if ($request->prefersJson()) {
            return (new Response())->json(['status' => $context['status']], $context['status']);
        }

        return (new Response($context['status']))
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($this->view->render('errors/404.html.twig'));
    }
}
```

karhu guards this: if the template is missing and the handler throws, it falls back to a plain 404
rather than turning the error page into a 500. See [Error Handling](../errors.md).
