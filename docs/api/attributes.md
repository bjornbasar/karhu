# `Karhu\Attributes`

Eight PHP 8 attributes: two for wiring (`Route`, `Command`) and six for validation. All are
`final`, and all constructor parameters are exposed as `public readonly` properties.

!!! tip "If PHP attributes are new to you"
    `#[Route('/users')]` is metadata attached to a method — it does nothing on its own. It is
    inert until something reflects over the class and reads it, which is what
    `Router::scanControllers()` and `CommandDispatcher::scanCommands()` do at boot. Think Java
    annotations, or a docblock the language actually understands.

---

## `Route`

[`src/Attributes/Route.php`](https://github.com/bjornbasar/karhu/blob/main/src/Attributes/Route.php)

`#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]`

Marks a public controller method as a route handler.

```php
public function __construct(
    public readonly string $path,
    public readonly array $methods = ['GET'],
    public readonly ?string $name = null,
)
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$path` | `string` | — | URI pattern. `{param}` placeholders match one segment. |
| `$methods` | `list<string>` | `['GET']` | HTTP methods. Upper-cased on registration. |
| `$name` | `?string` | `null` | Route name for `Router::urlFor()`. |

!!! warning "The path comes first"
    `#[Route('GET', '/users')]` sets the **path** to `GET` and raises a `TypeError` on the
    `methods` argument. Write `#[Route('/users', methods: ['GET'])]`.

Repeatable, so one method can serve several patterns:

```php
#[Route('/widgets', methods: ['GET', 'POST'])]
#[Route('/widgets/{id}', methods: ['GET', 'PUT', 'DELETE'])]
public function __invoke(Request $request): Response
{
    return $this->dispatch($request);
}
```

---

## `Command`

[`src/Attributes/Command.php`](https://github.com/bjornbasar/karhu/blob/main/src/Attributes/Command.php)

`#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]`

Registers a public method as a CLI command.

```php
public function __construct(
    public readonly string $name,
    public readonly string $description = '',
)
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$name` | `string` | — | Command name, e.g. `route:cache`. |
| `$description` | `string` | `''` | One-line help text, shown by `karhu list`. |

The method receives the parsed arguments array and returns an `int` exit code. See
[`Karhu\Cli`](cli.md).

---

# Validation attributes

Six, and deliberately no more — see [`Validation`](http.md#validation) for how they are applied.
All target properties and all accept a `$message` override.

Two rules govern every validator:

1. `#[Required]` runs first; if it fails, no other rule on that field runs.
2. A field that is `null` or `''` and not required **skips all remaining rules** — so optional
   fields are only validated when actually supplied.

---

## `Required`

[`src/Attributes/Required.php`](https://github.com/bjornbasar/karhu/blob/main/src/Attributes/Required.php)

```php
public function __construct(public readonly ?string $message = null)
```

Fails when the value is `null` or `''`. Default message: `"{field} is required."`

!!! note "`'0'` passes, and so does `0`"
    Only `null` and the empty string fail. A literal zero is a present value.

---

## `StringLength`

[`src/Attributes/StringLength.php`](https://github.com/bjornbasar/karhu/blob/main/src/Attributes/StringLength.php)

```php
public function __construct(
    public readonly ?int $min = null,
    public readonly ?int $max = null,
    public readonly ?string $message = null,
)
```

Length is measured with `mb_strlen()`, so multi-byte characters count as one. Defaults:
`"{field} must be at least {min} characters."` / `"...at most {max} characters."`

```php
#[StringLength(min: 3, max: 120)]
public string $title = '';
```

---

## `NumericRange`

[`src/Attributes/NumericRange.php`](https://github.com/bjornbasar/karhu/blob/main/src/Attributes/NumericRange.php)

```php
public function __construct(
    public readonly ?float $min = null,
    public readonly ?float $max = null,
    public readonly ?string $message = null,
)
```

Bounds are **inclusive**. A non-numeric value fails with `"{field} must be numeric."` — that
message is not overridable by `$message`, which applies only to the min/max failures.

---

## `Email`

[`src/Attributes/Email.php`](https://github.com/bjornbasar/karhu/blob/main/src/Attributes/Email.php)

```php
public function __construct(public readonly ?string $message = null)
```

Validated with `filter_var($value, FILTER_VALIDATE_EMAIL)`. Default message:
`"{field} must be a valid email."`

---

## `Regex`

[`src/Attributes/Regex.php`](https://github.com/bjornbasar/karhu/blob/main/src/Attributes/Regex.php)

```php
public function __construct(
    public readonly string $pattern,
    public readonly ?string $message = null,
)
```

The pattern is passed straight to `preg_match()`, so it **must include delimiters**:
`'/^[a-z-]+$/'`, not `'^[a-z-]+$'`. Default message: `"{field} format is invalid."`

---

## `In`

[`src/Attributes/In.php`](https://github.com/bjornbasar/karhu/blob/main/src/Attributes/In.php)

```php
public function __construct(
    public readonly array $values,
    public readonly ?string $message = null,
)
```

The value is cast to `string` and compared **strictly** against `$values`, so list entries should
be strings. Default message: `"{field} must be one of: a, b, c."`

```php
#[In(['low', 'normal', 'high'])]
public string $priority = 'normal';
```
