# Migrating from chukwu / Peopsquik

These recipes show side-by-side before/after code from the actual legacy repos ([chukwu](https://github.com/bjornbasar/chukwu), [Peopsquik](https://github.com/bjornbasar/Peopsquik)) and their karhu equivalents.

## 1. modules.ini routing → #[Route] attributes

**Before (Peopsquik `index.php`):**

```php
// Parse the modules.ini file
$modules = parse_ini_file(APP_LIB . 'modules.ini', true);
if (isset($modules[$module])) {
    $parameters = $modules[$module];
} else {
    // Filesystem convention: users-list → apps/controllers/main/users/list.php
    $parameters['module'] = 'main/' . str_replace('-', '/', $module) . '.php';
}
```

**After (karhu):**

```php
final class UserController
{
    #[Route('/users', methods: ['GET'], name: 'users.index')]
    public function index(Request $request): Response { /* ... */ }

    #[Route('/users/{id}', methods: ['GET'], name: 'users.show')]
    public function show(Request $request): Response { /* ... */ }
}
```

No INI files. No filesystem convention. Routes are declared where the handler lives.

---

## 2. Core_DB manual SQL → prepared statements

**Before (Peopsquik `lib/Core/DB.php` — SQL injection vulnerable):**

```php
// getBy() builds SQL via string interpolation — INJECTION RISK
public function getBy($fields)
{
    $sql = "SELECT * FROM `{$this->table}` WHERE 1 ";
    foreach ($fields as $key => $value) {
        $sql .= "and `$key` = '$value' ";  // <-- no escaping!
    }
    return $this->getArray($sql);
}
```

**After (karhu-db, or raw PDO):**

```php
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username');
$stmt->execute(['username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
```

Every query uses named parameters. No string interpolation of user input, ever.

---

## 3. __autoload → PSR-4 autoload

**Before (Peopsquik `settings.php`):**

```php
function __autoload($className)
{
    $class = str_replace('_', '/', $className) . '.php';
    if (fileexists($class)) {
        require_once $class;
    } elseif (fileexists(str_replace('.php', '.class.php', $class))) {
        require_once str_replace('.php', '.class.php', $class);
    }
}
```

`__autoload` was deprecated in PHP 7.2 and removed in PHP 8.0. It also relied on `set_include_path` with multiple search directories.

**After (karhu `composer.json`):**

```json
{
    "autoload": {
        "psr-4": {
            "Karhu\\": "src/"
        }
    }
}
```

One line. Composer handles the rest. Class `Karhu\Http\Router` lives at `src/Http/Router.php`.

---

## 4. Core_ACL session auth → UserRepositoryInterface + RequireRole

**Before (Peopsquik `lib/Core/ACL.php`):**

```php
class Core_ACL
{
    public function __construct()
    {
        $this->_db = new Core_DB();
        // Direct SQL: SELECT * FROM auth_roles ...
        // Stores results in a serialized file at APP_DATA_AUTH . 'ACL'
    }
}
```

The ACL class owned the database connection, serialized role data to a flat file, and read `$_SESSION` directly.

**After (karhu):**

```php
// 1. Implement the interface with YOUR storage
final class PdoUserRepository implements UserRepositoryInterface
{
    public function findByUsername(string $username): ?array { /* PDO query */ }
    public function rolesFor(string $username): array { /* PDO query */ }
}

// 2. Bind in container
$app->container()->bind(UserRepositoryInterface::class, PdoUserRepository::class);

// 3. Protect routes with middleware
$app->pipe(RequireRole::for($rbac, ['admin']));
```

Auth is decoupled from storage. RBAC queries go through an interface; the middleware handles the HTTP response.

---

## 5. Smarty templates → userland views

**Before (Peopsquik `lib/Core/Peopsquik.php`):**

```php
$this->_template = new Smarty();
$this->_template->template_dir = APP_VIEWS;
$this->_template->compile_dir = APP_VIEWS_COMPILED;
$this->_template->cache_dir = APP_VIEWS_CACHE;
$this->_template->assign('TEMPLATE_TITLE', APP_NAME);
$this->_template->display($parameters['template']);
```

The framework was coupled to Smarty 2. Templates required `chmod 0777` compiled/cache directories.

**After (karhu):**

karhu does not ship a template engine. Views are userland. Use any engine:

```php
// With Twig (install via karhu-view or directly)
$twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader('templates'));

#[Route('/dashboard')]
public function dashboard(Request $request): Response
{
    $html = $twig->render('dashboard.html.twig', ['user' => $user]);
    return (new Response())->withHeader('Content-Type', 'text/html')->withBody($html);
}
```

Or return JSON directly — the framework handles both via `Response::json()` and content negotiation in `AbstractResourceController`.
