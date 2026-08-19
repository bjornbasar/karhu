# `Karhu\Auth`

Password hashing and role-based access control. There is **no SQL in this namespace** — every
user and role read goes through [`UserRepositoryInterface`](#userrepositoryinterface), which you
implement against whatever storage you use
([ADR 0006](../adr/0006-rbac-via-repository-interface.md)).

---

## `PasswordHasher`

[`src/Auth/PasswordHasher.php`](https://github.com/bjornbasar/karhu/blob/main/src/Auth/PasswordHasher.php)

A thin wrapper over PHP's password API, pinned to **Argon2id**. No constructor.

| Method | Returns | Description |
|---|---|---|
| `hash(string $plaintext)` | `string` | `password_hash($plaintext, PASSWORD_ARGON2ID)`. |
| `verify(string $plaintext, string $hash)` | `bool` | `password_verify()` — constant-time. |
| `needsRehash(string $hash)` | `bool` | Whether the hash predates the current algorithm parameters. |

The returned hash is self-describing (algorithm, cost parameters and salt are all encoded in the
string), so store it in a single column and never store a salt separately. Argon2id output is
about 96 characters — size the column at `VARCHAR(255)`.

### Rehash on login

`needsRehash()` is only useful where you hold the plaintext, which is the moment of a successful
login:

```php
if ($hasher->verify($password, $user['password_hash'])) {
    if ($hasher->needsRehash($user['password_hash'])) {
        $users->updateHash($user['username'], $hasher->hash($password));
    }
    // ... log them in
}
```

!!! note "Requires Argon2 support in the PHP build"
    `PASSWORD_ARGON2ID` needs libargon2, which is standard in the official Docker images and most
    distribution packages. Without it, `hash()` raises an error rather than silently downgrading
    to bcrypt.

---

## `Rbac`

[`src/Auth/Rbac.php`](https://github.com/bjornbasar/karhu/blob/main/src/Auth/Rbac.php)

Role checks and authentication, delegating all storage to the repository.

```php
public function __construct(private readonly UserRepositoryInterface $users)
```

| Method | Returns | Description |
|---|---|---|
| `hasRole(string $username, string $role)` | `bool` | Whether the user holds that exact role. |
| `hasAnyRole(string $username, array $roles)` | `bool` | Whether the user holds **at least one** of them. |
| `authenticate(string $username, string $password, PasswordHasher $hasher)` | `?array` | `['username' => string, 'roles' => list<string>]`, or `null` on failure. |

`authenticate()` returns `null` both for an unknown user and for a wrong password — deliberately
indistinguishable, so the response cannot be used to enumerate accounts.

Role comparison is **exact and case-sensitive**: there is no hierarchy, so an `admin` does not
automatically satisfy a check for `editor`. List every acceptable role instead.

```php
$rbac = new Rbac($users);

if ($account = $rbac->authenticate($username, $password, new PasswordHasher())) {
    Session::regenerate();
    Session::set('username', $account['username']);
}

$rbac->hasAnyRole($username, ['admin', 'editor']);   // true if either
```

!!! warning "Every call hits the repository"
    `hasRole()` and `hasAnyRole()` call `rolesFor()` each time — there is no caching layer. If a
    request checks roles repeatedly, cache inside your repository implementation.

---

## `UserRepositoryInterface`

[`src/Auth/UserRepositoryInterface.php`](https://github.com/bjornbasar/karhu/blob/main/src/Auth/UserRepositoryInterface.php)

The decoupling point between auth and storage. Implement it over PDO, karhu-db, Doctrine, or an
array in a test.

| Method | Returns | Description |
|---|---|---|
| `findByUsername(string $username)` | `?array` | `['username' => string, 'password_hash' => string, 'roles' => list<string>]`, or `null`. |
| `rolesFor(string $username)` | `list<string>` | Role names. Return `[]` for an unknown user. |

!!! warning "`findByUsername()` must return all three keys"
    `Rbac::authenticate()` reads `password_hash`, `username` and `roles` without checking they
    exist. Returning a partial row raises an undefined-key error rather than failing the login
    cleanly.

[karhu-db](../packages/db.md) ships a ready-made `PdoUserRepository`. A minimal in-memory
implementation for tests:

```php
final class ArrayUsers implements UserRepositoryInterface
{
    public function __construct(private array $rows) {}

    public function findByUsername(string $username): ?array
    {
        return $this->rows[$username] ?? null;
    }

    public function rolesFor(string $username): array
    {
        return $this->rows[$username]['roles'] ?? [];
    }
}
```

## See also

- [`RequireRole`](middleware.md#requirerole) — the middleware that consumes `Rbac`
- [Auth guide](../auth.md) — the full login flow
