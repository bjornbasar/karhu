# Testing

karhu is built test-first, and the framework's own suite is **172 tests / 332 assertions** across
19 classes. Nothing in it needs a web server, a database, or a mocking library.

```bash
composer test           # PHPUnit
composer analyse        # PHPStan level 8
composer cs-check       # php-cs-fixer, dry run
composer docs-check     # API reference vs src/
composer check          # all four
```

## Why testing karhu apps is easy

Three design choices do the work:

1. **`App::handle()` returns the `Response`** instead of emitting it, so a full request/response
   cycle is an ordinary function call.
2. **`Request`'s constructor is public**, so you can build one from arrays rather than mutating
   superglobals.
3. **Controllers resolve from the container**, so a fake dependency is a `set()` call away.

## Testing a route end to end

```php
use Karhu\App;
use Karhu\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IssueRouteTest extends TestCase
{
    #[Test]
    public function it_returns_the_issue_as_json(): void
    {
        $app = new App();
        $app->router()->scanControllers([IssueController::class]);
        $app->container()->set(IssueRepository::class, new InMemoryIssues([
            '42' => ['id' => '42', 'title' => 'Broken link'],
        ]));

        $response = $app->handle(self::get('/issues/42'));

        self::assertSame(200, $response->status());
        self::assertSame('application/json', $response->header('Content-Type'));
        self::assertSame('{"id":"42","title":"Broken link"}', $response->body());
    }

    private static function get(string $path): Request
    {
        return new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path]);
    }
}
```

`set()` before `handle()` wins, because the container returns the registered instance rather than
auto-wiring the real one.

## Building requests

The constructor is `(array $server, array $get, array $post, string $body, array $headers)`.

```php
// GET with a query string
new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/search?q=bear'], ['q' => 'bear']);

// Form POST
new Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/issues'], [], ['title' => 'Hi']);

// JSON POST — body() decodes it
new Request(
    ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/issues'],
    [], [],
    '{"title":"Hi"}',
    ['content-type' => 'application/json'],
);

// Explicit Accept, to exercise content negotiation
new Request(
    ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/issues'],
    [], [], '',
    ['accept' => 'application/json'],
);
```

!!! warning "Header keys must be lower-cased"
    When you pass `$headers` directly it is used **as-is** — `Request::header()` looks up
    `strtolower($name)`, so `['Accept' => ...]` is never found. Either use lower-case keys, or
    pass `HTTP_ACCEPT` in `$server` and let karhu extract them.

## Testing middleware

Middleware is a callable, so call it with a `$next` you control:

```php
#[Test]
public function it_blocks_a_post_without_a_token(): void
{
    $csrf = new Karhu\Middleware\Csrf();
    $called = false;

    $response = $csrf(
        new Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/issues']),
        function () use (&$called) { $called = true; return new Response(); },
    );

    self::assertSame(403, $response->status());
    self::assertFalse($called, 'the pipeline should have been short-circuited');
}
```

Asserting that `$next` was **not** called is the important half — a middleware that returns 403
while still running the rest of the stack has not actually blocked anything.

## Testing validation

No HTTP involved — it is a pure function over an array:

```php
#[Test]
public function it_rejects_a_short_title(): void
{
    $errors = Validation::validate(['title' => 'ab'], CreateIssueDto::class);

    self::assertArrayHasKey('title', $errors);
}

#[Test]
public function it_accepts_valid_input(): void
{
    self::assertSame([], Validation::validate(['title' => 'A real title'], CreateIssueDto::class));
}
```

## Faking the user repository

`UserRepositoryInterface` has two methods, so auth tests need no database and no mocking library:

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

$rbac = new Rbac(new ArrayUsers([
    'ada' => [
        'username' => 'ada',
        'password_hash' => (new PasswordHasher())->hash('secret'),
        'roles' => ['admin'],
    ],
]));
```

## Things that touch global state

Some of the surface wraps PHP globals and needs care:

| Class | Global | In tests |
|---|---|---|
| `Session` | `$_SESSION`, `session_*()` | Set `$_SESSION` directly; the static getters read it without an active session. |
| `Cookie` | `$_COOKIE`, `setcookie()` | Set `$_COOKIE` for reads. `set()`/`delete()` need `@` or output buffering — headers are already sent under PHPUnit. |
| `Csrf` | `$_SESSION`, `$_COOKIE` | Seed `$_SESSION['_csrf_token']`. |
| `Config` | `getenv()` | `putenv('DATABASE_HOST=…')`, and **unset it afterwards**. |
| `ExceptionHandler` | `APP_ENV` | Pass `new ExceptionHandler(true)` rather than setting the env var. |

Reset what you touch in `tearDown()`, or the ordering of tests starts to matter:

```php
protected function tearDown(): void
{
    $_SESSION = [];
    $_COOKIE = [];
    putenv('DATABASE_HOST');   // no '=' unsets
}
```

## Conventions in karhu's own suite

- PHPUnit 11 **attributes**, not annotations — `#[Test]` above each method.
- Test methods are named `snake_case` and read as sentences: `static_route_matches()`,
  `head_is_implicit_for_get()`.
- Stub controllers are declared at the top of the test file, not in fixtures — a route test is
  readable in one screen.
- Tests mirror `src/`: `tests/Http/RouterTest.php` covers `src/Http/Router.php`.
- `failOnRisky` and `failOnWarning` are **on** in `phpunit.xml.dist`, so a test with no assertions
  fails rather than passing quietly.

## Static analysis

PHPStan runs at **level 8** over `src/` and `tools/`, the strictest level short of `max`. It
catches the class of bug that tests do not: an unguarded `string|false` from
`file_get_contents()`, a nullable that is never checked.

```bash
composer analyse
```

## Keeping the docs honest

`composer docs-check` reflects over `src/` and fails when a public method is missing from
`docs/api/`, when the reference names a method that no longer exists, or when a page cites a
`src/` path that has been moved. It runs in CI and gates the docs deploy — the API reference
cannot drift from the code without something going red.
