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
    Session::set('username', $user['username']);
    Session::regenerate(); // prevent session fixation
}
```
