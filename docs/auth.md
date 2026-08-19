# Auth

karhu ships password hashing, RBAC, and session-based authentication. No database dependency — auth queries go through `UserRepositoryInterface`.

## Password hashing

```php
$hasher = new \Karhu\Auth\PasswordHasher();

$hash = $hasher->hash('secret');           // argon2id
$valid = $hasher->verify('secret', $hash); // true
$stale = $hasher->needsRehash($hash);      // false (fresh hash)
```

## UserRepositoryInterface

Implement this to connect auth to your storage:

```php
use Karhu\Auth\UserRepositoryInterface;

final class PdoUserRepository implements UserRepositoryInterface
{
    public function findByUsername(string $username): ?array
    {
        // Return: ['username' => ..., 'password_hash' => ..., 'roles' => [...]]
        // Or null if not found
    }

    public function rolesFor(string $username): array
    {
        // Return: ['admin', 'editor']
    }
}
```

Register in the container:

```php
$app->container()->bind(UserRepositoryInterface::class, PdoUserRepository::class);
```

## RBAC

```php
$rbac = new \Karhu\Auth\Rbac($userRepository);

$rbac->hasRole('bjorn', 'admin');              // true/false
$rbac->hasAnyRole('bjorn', ['admin', 'editor']); // true if any match
$rbac->authenticate('bjorn', 'secret', $hasher); // returns user array or null
```

## RequireRole middleware

```php
use Karhu\Middleware\RequireRole;

// Protect specific routes in the middleware stack
$app->pipe(RequireRole::for($rbac, ['admin']));
```

Returns 401 (not logged in) or 403 (missing role). Content-negotiated: JSON `application/problem+json` for API clients, plain text for browsers.

## Login flow

```php
// In your login controller:
$user = $rbac->authenticate($username, $password, $hasher);

if ($user) {
    Session::regenerate();                      // FIRST — prevents session fixation
    Session::set('username', $user['username']);
}
```

`authenticate()` returns `null` for both an unknown user and a wrong password, deliberately — the
response cannot be used to work out which accounts exist.

!!! warning "Regenerate before writing the identity, not after"
    `session_regenerate_id(true)` carries the data across either way, so both orders happen to
    work today. Regenerating **first** is the habit worth keeping: it holds even when the login
    path later grows an early return, and it makes the security intent obvious to the next reader.

## See also

- [API reference — `Karhu\Auth`](api/auth.md) — every method, with the repository contract
- [`RequireRole`](api/middleware.md#requirerole) — the middleware
- [Sessions & Cookies](sessions.md) — cookie flags and why `Secure` needs a proxy header
- [ADR 0006](adr/0006-rbac-via-repository-interface.md) — why RBAC goes through an interface
