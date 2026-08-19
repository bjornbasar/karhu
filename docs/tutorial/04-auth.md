# Part 4 — Authentication and roles

Every issue is authored by `'anonymous'` and anyone can delete anything. Time to add login, and
then permissions.

## A user repository

karhu's auth never touches storage directly — it goes through `UserRepositoryInterface`, which is
two methods. `app/DemoUserRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App;

use Karhu\Auth\PasswordHasher;
use Karhu\Auth\UserRepositoryInterface;

final class DemoUserRepository implements UserRepositoryInterface
{
    /** @var array<string, array{username:string, password_hash:string, roles:list<string>}> */
    private array $users = [];

    public function seed(PasswordHasher $hasher): void
    {
        $this->users['admin'] = [
            'username' => 'admin',
            'password_hash' => $hasher->hash('admin123'),
            'roles' => ['admin', 'editor'],
        ];

        $this->users['editor'] = [
            'username' => 'editor',
            'password_hash' => $hasher->hash('editor123'),
            'roles' => ['editor'],
        ];
    }

    public function findByUsername(string $username): ?array
    {
        return $this->users[$username] ?? null;
    }

    public function rolesFor(string $username): array
    {
        return $this->users[$username]['roles'] ?? [];
    }
}
```

That is the entire coupling between auth and storage. Swapping in
[karhu-db](../packages/db.md)'s `PdoUserRepository` later changes nothing else.

!!! warning "`findByUsername()` must return all three keys"
    `Rbac::authenticate()` reads `username`, `password_hash` and `roles` without checking they are
    present. A partial row raises an undefined-key error instead of failing the login cleanly.

## Passwords

```php
$hasher = new PasswordHasher();

$hash  = $hasher->hash('admin123');            // argon2id
$ok    = $hasher->verify('admin123', $hash);   // true
$stale = $hasher->needsRehash($hash);          // false
```

The hash encodes its own algorithm, cost parameters and salt, so it goes in one `VARCHAR(255)`
column and there is no separate salt to store.

## A login controller

`app/Controllers/AuthController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Karhu\Attributes\Route;
use Karhu\Auth\PasswordHasher;
use Karhu\Auth\Rbac;
use Karhu\Http\Request;
use Karhu\Http\Response;
use Karhu\Middleware\Session;

final class AuthController
{
    public function __construct(
        private readonly Rbac $rbac,
        private readonly PasswordHasher $hasher,
    ) {}

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $body = is_array($b = $request->body()) ? $b : [];

        $user = $this->rbac->authenticate(
            (string) ($body['username'] ?? ''),
            (string) ($body['password'] ?? ''),
            $this->hasher,
        );

        if ($user === null) {
            return (new Response())->json(['error' => 'Invalid credentials'], 401);
        }

        Session::regenerate();                        // FIRST — see below
        Session::set('username', $user['username']);
        Session::set('roles', $user['roles']);

        return (new Response())->json(['message' => 'Logged in', 'user' => $user]);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        Session::destroy();

        return (new Response())->json(['message' => 'Logged out']);
    }
}
```

!!! warning "Regenerate the session id before storing the identity"
    Without `Session::regenerate()`, an attacker who planted a session id before login still holds
    a valid session afterwards — session fixation. Doing it first also rotates the CSRF token,
    which is the correct behaviour on a privilege change.

`authenticate()` returns `null` for both an unknown user and a wrong password, on purpose: the
response cannot be used to enumerate accounts.

## Wire it up

`public/index.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\DemoUserRepository;
use App\IssueStore;
use Karhu\Auth\PasswordHasher;
use Karhu\Auth\Rbac;
use Karhu\Auth\UserRepositoryInterface;
use Karhu\Middleware\Cors;
use Karhu\Middleware\Csrf;
use Karhu\Middleware\Session;

$hasher = new PasswordHasher();

$users = new DemoUserRepository();
$users->seed($hasher);

$store = new IssueStore();
$store->seed();

$rbac = new Rbac($users);

$app = new Karhu\App();

$app->container()->set(PasswordHasher::class, $hasher);
$app->container()->set(UserRepositoryInterface::class, $users);
$app->container()->set(Rbac::class, $rbac);
$app->container()->set(IssueStore::class, $store);

$app->pipe(new Cors(['origins' => ['*']]))
    ->pipe(new Session())
    ->pipe(new Csrf());

$app->router()->scanControllers(require __DIR__ . '/../config/controllers.php');
$app->run();
```

Add `AuthController` to `config/controllers.php`.

**Middleware order is load-bearing here.** `Cors` first so preflights are answered before anything
else; `Session` before `Csrf`, because the CSRF token lives in the session and silently falls back
to a weaker cookie mode without one.

## Record the author

In `IssueController::create()`, replace the hard-coded `'anonymous'`:

```php
$username = Session::get('username', 'anonymous');

$issue = $this->store->add(
    (string) $data['title'],
    (string) $data['body'],
    is_string($username) ? $username : 'anonymous',
);
```

## Permissions

Writes should need a role: creating requires `editor` or `admin`, deleting requires `admin`.

`RequireRole` gates the **whole pipeline**, so applying it per route means a small dispatching
middleware:

```php
use Karhu\Http\Request;
use Karhu\Http\Response;
use Karhu\Middleware\RequireRole;

$app->pipe(function (Request $request, callable $next) use ($rbac): Response {
    $path = $request->path();
    $method = $request->method();

    if ($method === 'POST' && $path === '/issues') {
        return (RequireRole::for($rbac, ['editor', 'admin']))($request, $next);
    }

    if ($method === 'DELETE' && str_starts_with($path, '/issues/')) {
        return (RequireRole::for($rbac, ['admin']))($request, $next);
    }

    return $next($request);
});
```

Pipe it **after** `Session`, since it reads the username from there.

`RequireRole` returns `401` when nobody is logged in and `403` when the user lacks the role, both
content-negotiated. The check passes on **any** of the listed roles — roles are flat, with no
hierarchy, so `admin` does not imply `editor` unless you say so.

## Try the whole flow

CSRF is on, so use a cookie jar and send the token on writes.

```bash
php -S localhost:8080 -t public
```

```bash
# Anonymous write → 401
curl -s -o /dev/null -w 'anon POST   → %{http_code}\n' -X POST localhost:8080/issues \
  -H 'Content-Type: application/json' -d '{"title":"Valid title","body":"Long enough body here."}'

# Log in as editor
curl -s -c jar.txt -X POST localhost:8080/login \
  -H 'Content-Type: application/json' -d '{"username":"editor","password":"editor123"}'

# Editor can create
curl -s -b jar.txt -o /dev/null -w 'editor POST → %{http_code}\n' -X POST localhost:8080/issues \
  -H 'Content-Type: application/json' -d '{"title":"Valid title","body":"Long enough body here."}'

# But not delete
curl -s -b jar.txt -o /dev/null -w 'editor DEL  → %{http_code}\n' -X DELETE localhost:8080/issues/1
```

```
anon POST   → 401
editor POST → 201
editor DEL  → 403
```

!!! note "Getting a 403 on every write?"
    That is `Csrf` doing its job. Read the token with `Csrf::token()` and send it as
    `X-CSRF-Token`, or drop `->pipe(new Csrf())` while experimenting with curl. For a browser
    form, `<?= Csrf::field() ?>` emits the hidden input.

## Where we are

Login, sessions, hashed passwords, and role-gated writes — with the only storage coupling being
two methods on an interface.

**[Part 5 — Errors, CLI and production →](05-production.md)**
