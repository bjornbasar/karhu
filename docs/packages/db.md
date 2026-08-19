# karhu-db

A thin PDO wrapper, an active-record base class, and a ready-made
[`UserRepositoryInterface`](../api/auth.md#userrepositoryinterface) implementation.

Zero runtime dependencies beyond PDO. **Every query uses prepared statements.**

[github.com/bjornbasar/karhu-db](https://github.com/bjornbasar/karhu-db) · v0.1.0 · MIT

```bash
composer require bjornbasar/karhu-db
```

---

## `Connection`

A wrapper over `PDO` that returns arrays instead of statements.

```php
use Karhu\Db\Connection;

$db = new Connection('pgsql:host=localhost;dbname=myapp', 'user', 'pass');
```

| Method | Returns | Description |
|---|---|---|
| `pdo()` | `PDO` | The underlying handle, for anything not covered here. |
| `fetchAll(string $sql, array $params = [])` | `array` | All rows. |
| `fetchOne(string $sql, array $params = [])` | `?array` | First row, or `null`. |
| `fetchScalar(string $sql, array $params = [])` | `mixed` | First column of the first row. |
| `run(string $sql, array $params = [])` | `int` | Rows affected — for statements with no result set. |
| `insert(string $table, array $data)` | `string` | Inserts and returns the new id. |
| `update(string $table, array $data, array $where)` | `int` | Rows affected. |
| `delete(string $table, array $where)` | `int` | Rows affected. |

```php
$rows   = $db->fetchAll('SELECT * FROM users WHERE active = :active', ['active' => 1]);
$user   = $db->fetchOne('SELECT * FROM users WHERE id = :id', ['id' => 42]);
$total  = $db->fetchScalar('SELECT COUNT(*) FROM users');

$id = $db->insert('users', ['name' => 'Bjorn', 'email' => 'bjorn@example.com']);
$db->update('users', ['name' => 'Updated'], ['id' => $id]);
$db->delete('users', ['id' => $id]);
```

!!! warning "Table and column names are not parameters"
    Values are bound; identifiers are interpolated. `insert()`, `update()` and `delete()` build
    their SQL from the **keys** of the arrays you pass, so never build those keys from user input.
    Values are always safe.

`insert()` returns a `string` because that is what `PDO::lastInsertId()` gives — cast it if your
column is an integer.

---

## `TableBase`

An active-record-flavoured base for one table. Subclass it and set two properties:

```php
use Karhu\Db\TableBase;

final class UserTable extends TableBase
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
}

$users = new UserTable($db);
```

| Method | Returns | Description |
|---|---|---|
| `getAll()` | `array` | Every row. |
| `get(string\|int $id)` | `?array` | One row by primary key. |
| `getBy(array $conditions)` | `array` | Rows matching an equality map. |
| `create(array $data)` | `string` | Insert, returning the new id. |
| `update(string\|int $id, array $data)` | `int` | Rows affected. |
| `delete(string\|int $id)` | `int` | Rows affected. |
| `count(array $conditions = [])` | `int` | Row count, optionally filtered. |

```php
$users->getAll();
$users->get(42);
$users->getBy(['role' => 'admin']);
$users->create(['name' => 'New User']);
$users->update(42, ['name' => 'Updated']);
$users->delete(42);
$users->count(['role' => 'admin']);
```

`$conditions` is an equality map only — `['role' => 'admin']` becomes `WHERE role = :role`. For
anything with `LIKE`, `IN`, a join or an `OR`, drop to `Connection::fetchAll()` and write the SQL.
There is no query builder, deliberately.

---

## `PdoUserRepository`

Implements karhu's [`UserRepositoryInterface`](../api/auth.md#userrepositoryinterface), so RBAC
works with no glue code:

```php
use Karhu\Auth\UserRepositoryInterface;
use Karhu\Db\PdoUserRepository;

$app->container()->set(UserRepositoryInterface::class, new PdoUserRepository($db));
```

That is the one line from [Tutorial part 4](../tutorial/04-auth.md) that replaces the in-memory
demo repository. Nothing else changes.

### Expected schema

```sql
CREATE TABLE users (
    username      VARCHAR(100) PRIMARY KEY,
    password_hash VARCHAR(255) NOT NULL
);

CREATE TABLE user_roles (
    username VARCHAR(100) NOT NULL,
    role     VARCHAR(50)  NOT NULL
);
```

`VARCHAR(255)` on the hash is not arbitrary — argon2id output runs to about 96 characters, and
truncating it silently breaks every login.

## Related

- [Auth](../auth.md) — the login flow
- [karhu-queue](queue.md) — uses a `Connection` for its database driver
