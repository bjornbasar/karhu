# Part 3 — Validating input

`POST /issues` returns 405 because `create()` is not implemented. Implementing it means accepting
input from outside, which means validating it.

## Describe the input

Validation is declared on a DTO — a class whose properties are the fields you accept.
`app/CreateIssueDto.php`:

```php
<?php

declare(strict_types=1);

namespace App;

use Karhu\Attributes\Required;
use Karhu\Attributes\StringLength;

final class CreateIssueDto
{
    #[Required]
    #[StringLength(min: 3, max: 100)]
    public string $title = '';

    #[Required]
    #[StringLength(min: 10)]
    public string $body = '';
}
```

The class is never instantiated. `Validation::validate()` reflects over its properties and reads
the attributes — the DTO is a *schema*, not a model.

## Implement `create()`

```php
use Karhu\Http\Validation;
use App\CreateIssueDto;

protected function create(Request $request): Response
{
    $data = is_array($body = $request->body()) ? $body : [];

    $errors = Validation::validate($data, CreateIssueDto::class);

    if ($errors !== []) {
        return (new Response())->json(['errors' => $errors], 422);
    }

    $issue = $this->store->add(
        (string) $data['title'],
        (string) $data['body'],
        'anonymous',           // Part 4 makes this the logged-in user
    );

    return (new Response())->json(['issue' => $issue], 201);
}
```

`validate()` returns an array keyed by field name. **Empty means valid** — there is no boolean and
no exception to catch.

!!! note "`body()` returns `array|string`"
    A JSON body is decoded to an array; anything else stays a raw string. The
    `is_array($body = ...) ? $body : []` guard is the idiom, and it is what keeps PHPStan happy at
    level 8.

## Try it

```bash
php -S localhost:8080 -t public
```

A valid issue:

```bash
curl -s -X POST localhost:8080/issues \
  -H 'Content-Type: application/json' \
  -d '{"title":"Search is slow","body":"Listing issues takes about four seconds."}'
```

```json
{"issue":{"id":3,"title":"Search is slow","body":"Listing issues takes about four seconds.","author":"anonymous"}}
```

An invalid one:

```bash
curl -s -X POST localhost:8080/issues \
  -H 'Content-Type: application/json' \
  -d '{"title":"ab"}'
```

```json
{"errors":{"title":"title must be at least 3 characters.","body":"body is required."}}
```

Two fields, two messages, `422 Unprocessable Content`.

## The six validators

That is all of them. There is no seventh, and no plugin mechanism —
[ADR 0001](../adr/0001-principles.md) calls this a scope fence, to avoid the
validation-library-bloat failure mode.

| Attribute | Parameters | Checks |
|---|---|---|
| `#[Required]` | `message?` | not `null`, not `''` |
| `#[StringLength]` | `min?`, `max?`, `message?` | `mb_strlen()` bounds |
| `#[NumericRange]` | `min?`, `max?`, `message?` | inclusive numeric bounds |
| `#[Email]` | `message?` | `filter_var(..., FILTER_VALIDATE_EMAIL)` |
| `#[Regex]` | `pattern`, `message?` | `preg_match()` |
| `#[In]` | `values`, `message?` | strict membership |

Anything more complex belongs in the controller or a service — it is ordinary PHP, not a rule
engine.

## Rules of evaluation

Two behaviours are worth knowing, because they are what make optional fields work:

1. **`#[Required]` runs first**, and short-circuits the rest of that field. So a missing `title`
   reports "title is required", never "title must be at least 3 characters".
2. **A field that is absent or `''` and not required skips every remaining rule.** That is what
   lets you attach `#[Email]` to an optional field without failing when it is omitted.

Add an optional field to see it:

```php
#[Email]
public string $notify = '';
```

```bash
# omitted → fine
curl -s -X POST localhost:8080/issues -H 'Content-Type: application/json' \
  -d '{"title":"Valid title","body":"A body long enough to pass."}'

# present but wrong → rejected
curl -s -X POST localhost:8080/issues -H 'Content-Type: application/json' \
  -d '{"title":"Valid title","body":"A body long enough to pass.","notify":"nope"}'
# {"errors":{"notify":"notify must be a valid email."}}
```

!!! note "One message per field"
    Only the first failing rule is reported for a given field, so a response never contains two
    complaints about `title`.

## Custom messages

Defaults are built from the property name. Override per rule:

```php
#[Required(message: 'Give the issue a title')]
#[StringLength(min: 3, max: 100, message: 'Titles run from 3 to 100 characters')]
public string $title = '';
```

One caveat: `#[NumericRange]`'s "must be numeric" failure is **not** overridable — `$message`
applies only to the min/max messages.

## Validation is testable on its own

No HTTP needed, which makes edge cases cheap to pin down:

```php
#[Test]
public function it_requires_a_body(): void
{
    $errors = Validation::validate(['title' => 'Valid title'], CreateIssueDto::class);

    self::assertSame(['body' => 'body is required.'], $errors);
}
```

**[Part 4 — Authentication and roles →](04-auth.md)**
