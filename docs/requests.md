# Requests & Responses

`Request` and `Response` are the two value objects every handler deals with. Both are
**immutable** — nothing mutates in place, and every modifier returns a clone.

Method-by-method detail: [`Karhu\Http`](api/http.md).

## The handler contract

```php
#[Route('/issues/{id}', methods: ['GET'])]
public function show(Request $request): Response
{
    return (new Response())->json(['id' => $request->routeParams()['id']]);
}
```

One argument in, one `Response` out. karhu will also accept a `string` (200 with that body) or an
`array` (200 as JSON), which is handy for a quick endpoint, but returning a `Response` is what
lets you set a status or a header.

---

## Reading input

| Source | Method |
|---|---|
| Route placeholders | `$request->routeParams()` |
| Query string | `$request->query('page', '1')` |
| Form fields | `$request->post('title')` |
| JSON body | `$request->body()` |
| Raw body | `$request->rawBody()` |
| Headers | `$request->header('authorization')` |
| Method / path | `$request->method()`, `$request->path()` |

### `body()` adapts to the content type

```php
$data = $request->body();
```

A JSON body is decoded to an array **once** and cached; anything else comes back as the raw
string. So `body()` returns `array|string`, and code that assumes an array will break on a form
post. If you need certainty, be explicit:

```php
$data = is_array($body = $request->body()) ? $body : [];
```

### Every accessor returns a string

`query()` and `post()` are typed `string`, with a string default. There is no `getInt()`. Cast at
the point of use:

```php
$page = max(1, (int) $request->query('page', '1'));
```

### Headers are case-insensitive

`$request->header('Content-Type')` and `header('content-type')` are the same lookup — keys are
lower-cased on the way in. `headers()` returns them all, lower-cased.

---

## Building responses

```php
new Response();                                  // 200, empty
(new Response(201))->withBody('created');
(new Response())->json(['ok' => true]);
(new Response())->json(['error' => 'nope'], 422);
(new Response())->redirect('/login');            // 302
(new Response())->redirect('/moved', 301);
```

Chain freely — each call returns a new instance:

```php
return (new Response())
    ->withStatus(201)
    ->withHeader('Location', $router->urlFor('issues.show', ['id' => $id]))
    ->withHeader('Cache-Control', 'no-store')
    ->json($issue);
```

!!! warning "`json()` sets the status too"
    `json(mixed $data, int $status = 200)` **overwrites** the status with its second argument,
    which defaults to 200. So this returns 200, not 201:

    ```php
    (new Response(201))->json($issue);        // → 200
    (new Response())->json($issue, 201);      // → 201  ✓
    ```

    Put `json()` last in a chain, and pass the status to it.

!!! warning "`json()` is not static"
    `Response::json([...])` raises an `Error`. Write `(new Response())->json([...])`.

### Immutability in practice

```php
$response = new Response();
$response->withHeader('X-Thing', 'value');   // discarded — the clone was thrown away

$response = $response->withHeader('X-Thing', 'value');   // ✓
```

This is what makes middleware safe: decorating a response cannot affect what an earlier layer
holds.

### JSON encoding

`json()` encodes with `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES`. Unencodable data — a
resource, a recursive structure, invalid UTF-8 — raises `JsonException` rather than silently
producing `false`. `ExceptionHandler` will turn that into a 500.

---

## Content negotiation

Use **`prefersJson()`**, not `accepts()`:

```php
if ($request->prefersJson()) {
    return (new Response())->json($data);
}

return (new Response())
    ->withHeader('Content-Type', 'text/html; charset=UTF-8')
    ->withBody($this->twig->render('issues/show.html.twig', $data));
```

`accepts('application/json')` is `true` for a wildcard `Accept`, so it is `true` for a browser
too — it answers "would this client tolerate JSON", not "does it want JSON".

| Client | `Accept` | `accepts('application/json')` | `prefersJson()` |
|---|---|---|---|
| `curl -H 'Accept: application/json'` | `application/json` | `true` | `true` |
| Browser | `text/html,…,*/*` | `true` | **`false`** |
| `curl` default | `*/*` | `true` | **`false`** |

karhu applies this same rule internally in `Csrf`, `RequireRole` and `ExceptionHandler`, so an
API client gets problem-JSON and a browser gets a page from all of them.

---

## Emitting

`emit()` sends the status line, the headers and the body to the SAPI. `App::run()` calls it for
you; `App::handle()` does not, which is what makes testing straightforward.

```php
$app->run();                          // handle + emit
$response = $app->handle($request);   // just the Response — for tests
```

!!! warning "Nothing may be printed before `emit()`"
    Output before headers are sent triggers PHP's "headers already sent" warning and the status
    and headers are lost. Watch for stray whitespace after a closing `?>` — which is why karhu's
    own files omit it.
