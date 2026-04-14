# Routing

karhu uses PHP 8 attributes for route definitions. No YAML, no INI, no closure chains.

## Basic routes

```php
use Karhu\Attributes\Route;
use Karhu\Http\Request;
use Karhu\Http\Response;

final class UserController
{
    #[Route('/users', methods: ['GET'], name: 'users.index')]
    public function index(Request $request): Response { /* ... */ }

    #[Route('/users/{id}', methods: ['GET'], name: 'users.show')]
    public function show(Request $request): Response
    {
        $id = $request->routeParams()['id'];
        // ...
    }

    #[Route('/users', methods: ['POST'])]
    public function create(Request $request): Response { /* ... */ }
}
```

## Route parameters

Use `{name}` placeholders. Values are extracted into `$request->routeParams()`:

```php
#[Route('/posts/{postId}/comments/{commentId}')]
public function showComment(Request $request): Response
{
    $params = $request->routeParams();
    // $params['postId'], $params['commentId']
}
```

## Route groups

```php
$app->router()->group('/api/v1', function ($router) {
    $router->addRoute('/users', ['GET'], UserController::class . '::index');
    $router->addRoute('/posts', ['GET'], PostController::class . '::index');
});
// Matches: /api/v1/users, /api/v1/posts
```

## Named routes + URL generation

```php
$url = $app->router()->urlFor('users.show', ['id' => '42']);
// Returns: /users/42
```

With base path:

```php
$app->setBasePath('/app');
$url = $app->router()->urlFor('users.show', ['id' => '42']);
// Returns: /app/users/42
```

## Base path

For sub-directory deployments:

```php
$app->setBasePath('/my-app');
```

## HEAD and OPTIONS

- **HEAD** is automatically handled for any GET route (RFC 9110).
- **OPTIONS** returns 405 with an `Allow` header listing all methods registered for that path.
- Unmatched methods return **405 Method Not Allowed** with an `Allow` header.

## Production route cache

Attribute scanning uses reflection, which has a per-request cost. For production:

```bash
bin/karhu route:cache        # writes cache/routes.php
bin/karhu route:clear        # removes the cache
```

The router loads the cached file on boot when present, skipping reflection entirely.
