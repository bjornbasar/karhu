# karhu — Code Tour

> A **reading-guide map**, not a tutorial. It assumes 20 years of dev instinct and points you *into the real source* in the order the code actually executes. Open the linked files alongside this doc and follow the trace. Every `file:line` link is clickable in your IDE and on GitHub.
>
> **How to use it:** read §1 once to get the spine, then walk §2–§10 with the files open. §11 is the pattern index (the "why"), §12 is active-recall exercises, §13 is the bridge to mishka/istrbuddy.

---

## 0. Orientation — the shape in one breath

karhu is **~3,000 LOC, zero runtime dependencies, attribute-routed**. Coming from your background, calibrate like this:

- **Not** Slim/Lumen/Flight: there is **no closure routing, no `routes.php`, no YAML**. Routes are PHP 8 attributes on controller methods, full stop ([ADR-0007](docs/adr/0007-opinionated-attribute-only-routing.md)).
- **Not** Symfony/Laravel: the container auto-wires by reflection but there is no service-provider ceremony, no config compilation, no facades-everywhere.
- **It is** the direct descendant of your own `chukwu`/`Peopsquik` front controllers — see the class docblock on [src/App.php:14-20](src/App.php#L14-L20), which literally names them as ancestors. The "new approach" is *attributes + reflection + a tiny auto-wiring container*, replacing hand-wired dispatch.

The whole framework is small enough to hold in your head. That's the point — the design lever behind almost every decision is the ~1,200 LOC core budget ([ADR-0001](docs/adr/0001-principles.md)).

---

## Vocabulary check — terms this tour leans on

No-fluff definitions, anchored to a PHP/Node background. Skip any you already own.

- **PHP 8 attribute** — `#[Something(...)]`. Native metadata you bolt onto a class/method/property/param, new in **PHP 8.0 (2020)**. It is *inert data, not code that runs* — something must **read** it via Reflection to act on it. An attribute is literally a class: `#[Route('/x')]` is a deferred `new Route('/x')` (see §3 + [src/Attributes/Route.php](src/Attributes/Route.php)). Pre-8, PHP faked this with docblock comments (`@Route`) parsed as strings by Doctrine. Closest thing you know: **TypeScript/Angular decorators** (`@Component({...})`) — but attributes are *passive* (you must reflect to use them) where TS decorators *execute*. Same family as Java annotations / C# attributes (PHP borrowed the `[]`).
- **Reflection** — PHP's runtime API for inspecting code about itself ("what methods/params/attributes does this class have?"). karhu's router, validator, and CLI all **scan** your classes with Reflection to find `#[Route]`/`#[Command]`/validation attributes. It's the mechanism that makes attributes *do* anything.
- **idiomatic** — written in the conventional, natural style a community expects ("how a native speaker phrases it"). About fluency, not correctness. "The most idiomatic file" = the one that best embodies *karhu's own* conventions.
- **idempotent** — an operation you can repeat with the same end state. `DELETE /x` twice → still deleted (2nd is a no-op); `PUT` twice → same; `POST` usually is *not* (2 posts = 2 rows). Distinct from **safe** = *no* state change at all (GET/HEAD/OPTIONS — the CSRF bypass list in §4). Safe ⊂ idempotent. Why it matters: idempotent requests are safe to **retry**. (This is also the founding principle of Ansible.)
- **synchronous / asynchronous** — *sync* = steps run one-after-another, each **blocks** until done, "wait for each then proceed" (classic PHP: request runs top-to-bottom, each DB call waits, script exits). *Async* = you **start** work and carry on, the result arrives later via callback/Promise/`await` (Node's event-loop model).
    - ⚠️ **Common trap:** *synchronous does NOT mean "in parallel"* — it's the opposite: sync is strictly one-at-a-time (the caller is *in step with*, i.e. **waits for**, each operation). The one that feels like juggling many things at once is **async**.
    - And **async ≠ parallel** either: Node is *single-threaded*; async just means the one thread doesn't sit idle during a wait, it interleaves other work (**concurrency**, not parallelism). PHP serves many users at once too — but by running a *pool of separate worker processes* (parallelism), each handling one request fully synchronously. Same goal, opposite strategy.
    - Mental hook: **sync = in-step, wait-for-each, one-at-a-time; async = out-of-step, fire-and-continue, juggle many in flight.**
    - Why it matters here: PHP is synchronous + share-nothing per request, which is *why* karhu can freely use `$_SESSION`, superglobals, and a per-request singleton container — all torn down when the request ends. The same code in wojtek (Node, long-lived process) would be a concurrency bug.
- **DI / auto-wiring** — Dependency Injection: a class declares what it needs in its constructor; something else supplies it. *Auto-wiring* = the container works out how to build those dependencies by reflecting the constructor (§6), so you never hand-assemble them.
- **middleware** — a layer that wraps request handling and can inspect or short-circuit it before the controller runs (§4). CSRF, session, CORS are middleware.
- **DTO** — Data Transfer Object: a plain class whose only job is to carry a set of fields (e.g. a validated form payload). karhu's validator attaches rules to a DTO's properties (§9).

---

## 1. The request lifecycle — the spine

This is the one trace to internalise. Everything else hangs off it.

```
public/index.php                     ← host app (skeleton), <5 lines
   │  new Karhu\App()                → registers self, Router, Container as singletons
   │  $app->router()->scanControllers(config/controllers.php)
   │  $app->pipe(...middleware...)   ← optional
   │  $app->run()
   ▼
App::run()                    src/App.php:80      Request::fromGlobals() → handle() → emit()
   ▼
App::handle()                 src/App.php:72      pipeline.handle(req, dispatch-closure)
   ▼
MiddlewarePipeline::handle()  src/Http/MiddlewarePipeline.php:36
   │   buildRunner() wraps middleware last→first so they run first→last (the "onion")
   ▼   … each mw calls $next($request) …
App::dispatch()  (the terminal handler)   src/App.php:89
   │  Router::match(method, path)   src/Http/Router.php:140  → RouteResult
   │     ├─ !found            → 404
   │     ├─ methodNotAllowed  → 405 + Allow header
   │     └─ found             → params extracted from regex captures
   │  req = req.withRouteParams(params)      (clone; readonly)
   │  container.set(Request::class, req)     (so controllers can inject the live request)
   ▼
App::callHandler()            src/App.php:113
   │  [$class,$method] = explode('::', handler)
   │  $controller = container.get($class)    ← auto-wired here (src/Container/Container.php:69)
   │  $response   = $controller->$method($request)
   │  coerce return: Response as-is | string→body | array→json
   ▼
Response::emit()              src/Http/Response.php:88   http_response_code + headers + echo
```

**Read it top to bottom in the code once.** `App` is only 206 lines and is the map of the whole framework: [src/App.php](src/App.php).

---

## 2. Entry point — the front controller

**Host side** (lives in the *skeleton*, shown in [docs/getting-started.md:27-38](docs/getting-started.md#L27-L38)):

```php
$app = new Karhu\App();
$app->router()->scanControllers(require __DIR__.'/../config/controllers.php');
$app->run();
```

**Framework side** — the constructor at [src/App.php:27-37](src/App.php#L27-L37) does one thing worth noticing:

```php
$this->container->set(self::class, $this);
$this->container->set(Router::class, $this->router);
$this->container->set(Container::class, $this->container);
```

**Why:** it seeds the container with itself so any controller can type-hint `App`, `Router`, or `Container` in its constructor and get the live instances back — no bootstrapping in userland. This is the whole "framework services are just container entries" idea in three lines.

**Gotcha to note now:** `App` does **not** register the `ExceptionHandler`. That's wired explicitly in `index.php` via `(new ExceptionHandler())->register()` (see §8). If you forget it, uncaught exceptions fall back to PHP's default handler. Flag this when you read a host app's `index.php`.

---

## 3. Routing — the signature pattern

Read [src/Http/Router.php](src/Http/Router.php) whole (268 lines); it's the most idiomatic file in the codebase.

**Registration path** — `scanControllers()` → `addRoute()`:
- [Router.php:116-132](src/Http/Router.php#L116-L132) — reflect each controller class, read `#[Route]` attributes off public methods, `$attr->newInstance()` to hydrate the attribute object, register `Class::method` as the handler string.
- [Router.php:84-93](src/Http/Router.php#L84-L93) — the compile step: `{param}` placeholders become `([^/]+)` capture groups via `preg_replace_callback`, and the param **names** are captured positionally into `paramNames`. That positional pairing is what lets `match()` rebuild the `name => value` map later.

**Match path** — `match()` at [Router.php:140-203](src/Http/Router.php#L140-L203):
- Sequential loop, first regex hit wins. **O(n) in route count** — fine at this scale, worth knowing.
- HEAD is auto-allowed for any GET route ([:170](src/Http/Router.php#L170)).
- Path matched but method wrong → collects `allowedMethods` and returns `methodNotAllowed()` (→ 405 + `Allow`).
- OPTIONS with a matched path → also returns `methodNotAllowed()` including `OPTIONS` ([:189-194](src/Http/Router.php#L189-L194)). ⟵ *see the exercise in §12 about how this interacts with the CORS middleware.*

**Named routes / reverse routing** — `urlFor()` at [Router.php:214-237](src/Http/Router.php#L214-L237): it reverses the compiled regex by stripping delimiters and substituting `([^/]+)` groups with param values. Slightly fragile string surgery — note it as a sharp edge.

**Production cache** — `dumpCache()`/`loadCache()` ([:244-261](src/Http/Router.php#L244-L261)) + [RouteCacheCommand](src/Cli/Commands/RouteCacheCommand.php). `bin/karhu route:cache` serialises the compiled table to `cache/routes.php` with `var_export`, so production boots with a single `include` and **zero reflection**.

**The "why" ([ADR-0002](docs/adr/0002-attribute-routing-with-cache.md) + [ADR-0007](docs/adr/0007-opinionated-attribute-only-routing.md)):** routes co-located with handlers (grep an endpoint, jump straight to code, IDE-navigable), one routing style = one code path in the router = low LOC. Reflection is a dev-only cost because the cache makes it a startup `include` in prod. The trade-off they accepted: a one-off health-check endpoint still needs a controller class.

---

## 4. Middleware — the onion

[src/Http/MiddlewarePipeline.php](src/Http/MiddlewarePipeline.php) is only 60 lines and worth understanding cold, because it's a pattern you'll re-recognise everywhere (Express, Laravel, PSR-15).

- A middleware is just `callable(Request $req, callable $next): Response`. No interface to implement — a closure or an `__invoke` object both work.
- `buildRunner()` at [:48-59](src/Http/MiddlewarePipeline.php#L48-L59) wraps the stack **last→first** so execution runs **first→last**, with the controller-dispatch closure as the innermost core:

```php
$stack = $handler;                       // innermost = App::dispatch
foreach (array_reverse($this->middleware) as $mw) {
    $next  = $stack;
    $stack = fn($req) => $mw($req, $next);
}
```

Each layer decides whether to call `$next($request)` (continue inward) or short-circuit with its own `Response` (e.g. auth failure). That's the entire control-flow model.

**Worked example — CSRF** ([src/Middleware/Csrf.php](src/Middleware/Csrf.php)):
- `__invoke()` at [:30-54](src/Middleware/Csrf.php#L30-L54): safe methods (GET/HEAD/OPTIONS) call `$next` immediately; unsafe methods compare stored vs submitted token with `hash_equals` (timing-safe) and short-circuit with `deny()` (403, content-negotiated) on mismatch.
- **Read the comment at [Csrf.php:46-52](src/Middleware/Csrf.php#L46-L52)** — it explains *why the token does NOT rotate per POST* (multi-tab safety: rotating would 403 every other tab). This is exactly the kind of "why" that never shows up in code structure alone.
- Note the dual API: it's both a **middleware** (`__invoke`) and a set of **static helpers** (`Csrf::token()`, `Csrf::field()`) that templates call. Keep this static-facade-alongside-DI duality in mind — it recurs.

Other middleware to skim later, same shape: `Session`, `Cors`, `RequireRole` (RBAC gate — see [ADR-0006](docs/adr/0006-rbac-via-repository-interface.md)).

---

## 5. Dispatch & controller invocation

Back in `App`, two private methods close the loop:

- `dispatch()` [src/App.php:89-108](src/App.php#L89-L108): 404/405 handling, then `withRouteParams()` (returns a **clone** — Request is readonly) and re-registers the enriched Request in the container so a controller ctor can inject the *current* request.
- `callHandler()` [src/App.php:113-135](src/App.php#L113-L135): resolves the controller **through the container** (so its dependencies auto-wire), calls the method, then **coerces the return**: `Response` passes through, `string` → body, `array` → JSON, anything else → empty 200. This return-polymorphism is why the getting-started example can `return ['message' => ...]` and get JSON for free.

---

## 6. The DI container — auto-wiring with a graceful fallback

[src/Container/Container.php](src/Container/Container.php) (205 lines). Four registration modes, one resolution path.

- **Registration:** `set()` (pre-built singleton), `factory()` (lazy, cached after first call), `bind()` (interface → concrete). All in [:38-67](src/Container/Container.php#L38-L67).
- **`get()`** [:69-101](src/Container/Container.php#L69-L101): lookup order = instances → factories → bindings → **auto-wire if the class exists** → throw `NotFoundException`. Everything resolved is cached as a singleton (`$this->instances[$id] = …`). ⟵ **There is no transient/fresh-instance scope.** Worth flagging.
- **Auto-wiring** — `resolve()` [:116-148](src/Container/Container.php#L116-L148): reflect the constructor, recursively resolve each parameter, `newInstanceArgs`. Circular deps are caught by the `$resolving` guard ([:118-124](src/Container/Container.php#L118-L124)).
- **The clever bit** — `resolveParameter()` [:169-204](src/Container/Container.php#L169-L204) and **especially the comment at [:150-168](src/Container/Container.php#L150-L168)**. Read that comment; it's the author's own rationale. Resolution ladder for a typed dependency: try to `get()` it → on `NotFoundException`, fall back to the parameter's **default value** → then to **null if nullable** → else re-throw. The payoff: `__construct(?QueueInterface $q = null)` doesn't explode when nobody bound `QueueInterface`, so CLI commands and controllers stay free of DI boilerplate.
- **`factory()` gets the container as arg 1** ([:44-57](src/Container/Container.php#L44-L57)) so factories resolve siblings without `use ($container)` capture; zero-arg factories still work because PHP discards the extra positional arg. (This was the most recent commit — `e37ce06`.)

---

## 7. Request & Response — PSR-7 *shape*, not compliance

The single biggest LOC-saving decision ([ADR-0005](docs/adr/0005-psr7-shape-not-compliance.md)): implement only the ~10 methods controllers actually call, skip the other ~20. Saves ~300 LOC.

- **[src/Http/Request.php](src/Http/Request.php)** — `readonly`, built from superglobals via `fromGlobals()` ([:61-69](src/Http/Request.php#L61-L69)). Notice: **lazy JSON decoding** of the body ([:112-126](src/Http/Request.php#L112-L126), gated by `$jsonDecoded`), header normalisation to lowercase keys ([:161-180](src/Http/Request.php#L161-L180)), and `withRouteParams()` returning a **clone** ([:148-153](src/Http/Request.php#L148-L153)) to preserve immutability.
- **[src/Http/Response.php](src/Http/Response.php)** — fluent **clone-on-write** (`withStatus`/`withHeader`/`withBody`/`json`/`redirect` each `clone $this`). `emit()` [:88-97](src/Http/Response.php#L88-L97) is the only side-effecting method. `json()` sets content-type and encodes with `JSON_THROW_ON_ERROR`.

Trade-off they accepted: full-PSR-7 middleware/libraries won't drop in without an adapter.

---

## 8. Error handling — two seams at two altitudes

karhu has **two** error paths, and conflating them is the easy mistake. They hook at different places and answer different questions:

| | `Karhu\Error\ExceptionHandler` | `Karhu\Http\ErrorHandler` (v0.1.4) |
|---|---|---|
| Hooks at | the **SAPI boundary**, via `set_exception_handler()` | inside `App::dispatch`, on the response path |
| Catches | *any* uncaught throwable — the last-resort net | a **known** HTTP error condition (today: 404) |
| Supplied by | the framework, wired in `index.php` | **the app**, bound into the container |
| Produces | RFC 7807 `problem+json` or an HTML trace page | whatever the app renders — a branded 404 |

Short version: **ExceptionHandler is for things that went wrong; ErrorHandler is for things that are absent.** A missing page isn't an exceptional condition, so routing it through the 500-shaped net gave you a plaintext `Not Found` and no way to brand it.

### 8a. ExceptionHandler — the last-resort net

[src/Error/ExceptionHandler.php](src/Error/ExceptionHandler.php).

- `register()` [:55-70](src/Error/ExceptionHandler.php#L55-L70): installs `set_exception_handler` + promotes PHP errors to `ErrorException`. **This is the wire-up you call from `index.php`.**
- `handle()` [:31-49](src/Error/ExceptionHandler.php#L31-L49): logs, then negotiates — JSON clients get **`application/problem+json`** (RFC 7807), browsers get an HTML page (full trace only when `APP_ENV=local`).
- **`ForbiddenException` with a `redirectTo`** short-circuits to a 302 ([:38-40](src/Error/ExceptionHandler.php#L38-L40)) — the comment explains the real use case (a kicked household member bounced to `/household/setup` instead of a dead-end 403). That's a mishka-driven feature living in the framework; note it for §13.
- `statusCode()` [:144-153](src/Error/ExceptionHandler.php#L144-L153) maps exception *types* to codes (`InvalidArgumentException` → 400, `ForbiddenException` → 403, else 500). Throwing the right exception type *is* the API for setting status.

### 8b. ErrorHandler — the pluggable 404 seam

Three small files and one wiring method. [src/Http/ErrorHandler.php](src/Http/ErrorHandler.php) is the interface an app implements; [src/Http/DefaultErrorHandler.php](src/Http/DefaultErrorHandler.php) is the bland fallback; [src/Http/NotFoundException.php](src/Http/NotFoundException.php) is what a controller throws.

**Two paths converge on one seam** — that convergence is the whole design:

1. **No route matched** — [App.php:106-112](src/App.php#L106-L112) calls `handleNotFound()`.
2. **A route matched, but the row didn't exist** — the controller throws `NotFoundException`, caught at [App.php:141](src/App.php#L141), which calls the *same* `handleNotFound()`.

**Why that matters:** before this, "no such URL" and "no such invoice" produced different responses through different code, so branding one left the other bare. Now a controller writes `throw new NotFoundException()` instead of hand-assembling a 404 Response, and both routes render identically.

**The two details worth stealing:**

- **A defensive `try`/`catch` around the app's own handler** ([:173-186](src/App.php#L173-L186)). If a bound handler explodes — missing Twig template, a DB failure inside a nav lookup — karhu swallows it and serves `DefaultErrorHandler` anyway. **Why:** the pre-v0.1.4 guarantee was *"an unmatched route always returns Not Found."* Making 404s brandable must not turn a broken template into a 500. The upgrade is not allowed to regress the floor.
- **`DefaultErrorHandler`'s bodies are byte-for-byte the old hard-coded strings.** An app that never binds anything — istrbuddy today — sees *zero* behaviour change. That's how you add a seam to a framework with existing consumers.

`resolveErrorHandler()` ([:193-205](src/App.php#L193-L205)) does the lookup, and its comment records a subtle container fact: `has()` correctly returns `false` for an *interface* with no binding, because `class_exists()` is false for interfaces and so the container's autoload fallback never trips. Worth knowing before you write `has()` against an interface anywhere else.

**Content negotiation moved down a layer too:** `Request::prefersJson()` ([Request.php:156-159](src/Http/Request.php#L156-L159)) is `accepts('application/json') && !accepts('text/html')` — deliberately conservative, so a browser (which accepts both) always gets HTML. The app's handler calls it to decide JSON vs page.

See this seam's other half in the **mishka** tour — `MishkaErrorHandler` is the branded implementation, and it has a session-churn gotcha worth reading: [mishka/CODE-TOUR.md](../mishka/CODE-TOUR.md).

---

## 9. Validation — attribute-driven DTOs, scope-fenced

[src/Http/Validation.php](src/Http/Validation.php) + the `#[Required]`, `#[StringLength]`, `#[Email]`, `#[NumericRange]`, `#[Regex]`, `#[In]` attributes in [src/Attributes/](src/Attributes/).

- `Validation::validate($data, MyDto::class)` [:35-50](src/Http/Validation.php#L35-L50): reflect the DTO's properties, run each property's attributes, return `field => error` map (empty = valid).
- **Read the class docblock [:16-25](src/Http/Validation.php#L16-L25):** "exactly 6 validators ship. No nested validation, no custom rules (subclass to extend)." This is a *deliberate scope fence* to avoid the validation-library-bloat failure mode — the same minimalism philosophy as PSR-shape. `Required` returns early ([:60-65](src/Http/Validation.php#L60-L65)) so a missing field yields one clean error, not a cascade.

Same core trick as routing and CLI: **attributes + reflection**. Once you've seen it in the router, you've seen it everywhere.

---

## 10. The CLI path — a mirror of the HTTP path

Open [bin/karhu](bin/karhu) and [src/Cli/CommandDispatcher.php](src/Cli/CommandDispatcher.php) together. The symmetry is the lesson:

| HTTP | CLI |
|------|-----|
| `public/index.php` | `bin/karhu` |
| `Router::scanControllers()` reads `#[Route]` | `CommandDispatcher::scanCommands()` reads `#[Command]` |
| `Router::match(method, path)` | `dispatch($argv)` on `argv[1]` |
| `callHandler()` resolves controller via container | `callHandler()` resolves command via container |
| Request | `parseArgs()` → `array<string,string|true>` |

- `bin/karhu` [head](bin/karhu#L14-L40): finds the autoloader from both repo and installed-as-dep positions, then optionally loads `config/container.php` so commands with typed ctor deps resolve (that file may return a `Container` or a `callable(Container): void`). This is why §6's default-value fallback matters — commands stay clean.
- `parseArgs()` [:166-189](src/Cli/CommandDispatcher.php#L166-L189): `--k=v` → `['k'=>'v']`, `--flag` → `['flag'=>true]`, bare → positional.
- `RouteCacheCommand` [src/Cli/Commands/RouteCacheCommand.php](src/Cli/Commands/RouteCacheCommand.php) is the canonical example: a command *is* a class with a `#[Command]`-attributed method taking `array $args` and returning an `int` exit code.

---

## 11. Pattern catalog — the "why" index

The reusable mental models, with a note for how each lands coming from your PHP/Node background.

| Pattern | Where | Why it's here | Coming-from note |
|---------|-------|---------------|------------------|
| **Attribute routing, no closures** | Router, `#[Route]` | Co-location + one code path + grep/IDE navigability | Opposite of Slim/Lumen closure DSLs; like Symfony `#[Route]` but *only* that ([ADR-0007](docs/adr/0007-opinionated-attribute-only-routing.md)) |
| **Reflection at dev-time, cache for prod** | `scanControllers` + `route:cache` | Ergonomics of reflection without its per-request cost | Same shape as Symfony/Doctrine metadata caching, hand-rolled ([ADR-0002](docs/adr/0002-attribute-routing-with-cache.md)) |
| **PSR *shape*, not compliance** | Request/Response | ~300 LOC saved; fewer stream/immutability edge cases | You lose drop-in PSR-15 lib interop ([ADR-0005](docs/adr/0005-psr7-shape-not-compliance.md)) |
| **Callable middleware onion** | MiddlewarePipeline | Zero-interface composition; closures *or* `__invoke` objects | Identical mental model to Express `(req,res,next)` and PSR-15 handlers |
| **Auto-wiring container w/ graceful fallback** | Container `resolveParameter` | Keeps controllers/commands DI-boilerplate-free | Like PHP-DI/league-container autowiring, but the default→nullable ladder is the special sauce |
| **Clone-on-write immutability** | Request/Response `with*()` | Predictable, no shared mutable state through the pipeline | The PSR-7 `withX` convention, minus the full contract |
| **Return polymorphism** | `App::callHandler` | Controllers return `Response`/`array`/`string` freely | Like modern framework "return anything, we'll normalise" |
| **Content negotiation + RFC 7807** | ExceptionHandler, Csrf::deny | One handler serves API + browser correctly | problem+json is the REST-standard error envelope |
| **Attributes + reflection everywhere** | Router / Validation / CLI | One meta-programming mechanism, reused 3× | The unifying idea of the whole framework |
| **Static facade alongside DI** | `Csrf::token/field` | Templates need CSRF without container access | Pragmatic escape hatch; the tension is intentional |
| **Zero runtime deps** | `composer.json` require | Supply-chain surface = PHP itself; full control | Radical vs the Composer-maximalist norm ([ADR-0003](docs/adr/0003-zero-runtime-deps.md)) |

---

## 12. Active-recall exercises

Don't read the answers until you've traced it in the source. This is where "review properly" gets built.

1. **A `POST /x` arrives with a bad CSRF token.** Name every file+method it touches and the exact line where the 403 is produced. Then: does the `ExceptionHandler` ever see it? (Hint: middleware short-circuits *return* a Response — they don't throw. Follow [Csrf.php:42-43](src/Middleware/Csrf.php#L42-L43) → `deny()` → back up the pipeline.)
2. **A CORS preflight `OPTIONS /api/thing`.** The router returns `methodNotAllowed` for OPTIONS ([Router.php:189-194](src/Http/Router.php#L189-L194)) → `App::dispatch` turns that into a **405**. So how does a real preflight get a 200/204? Open [src/Middleware/Cors.php](src/Middleware/Cors.php) and find where OPTIONS is intercepted *before* dispatch. Which layer wins, and why is ordering load-bearing?
3. **Controller ctor `__construct(private UserRepositoryInterface $users)` with nobody binding it.** What does the container do — and is that a bug or a feature? Trace [Container.php:169-204](src/Container/Container.php#L169-L204) and reconcile with the comment above it.
4. **You add a route but forget `route:cache` in prod.** What breaks, when, and how would CI catch it? (Tie back to [ADR-0002](docs/adr/0002-attribute-routing-with-cache.md) consequences.)
5. **A controller returns `['ok' => true]`.** Where does it become JSON, and what sets the content-type? (One method, one line.)

---

## 13. Bridge to mishka & istrbuddy

karhu is the *engine*; those two are the *cars*. When you read them next, you're pattern-matching against this doc:
- Their controllers are `#[Route]`-attributed classes → §3.
- Their bootstrap `index.php` wires middleware + `ExceptionHandler::register()` → §2, §8.
- mishka's kicked-member redirect is the concrete consumer of `ForbiddenException::redirectTo` → §8.
- Auth/RBAC flows through `UserRepositoryInterface` + `RequireRole` → the app *implements* the interface karhu only declares ([ADR-0006](docs/adr/0006-rbac-via-repository-interface.md)).
- `examples/istrbuddy/` and [docs/recipes/istrbuddy.md](docs/recipes/istrbuddy.md) are the author's own worked reference — read those as the "expected usage."

The question to carry into mishka: *what does a real app have to supply that the framework deliberately left out?* (Answer preview: a `UserRepositoryInterface` impl, a view layer via karhu-view, DB access via karhu-db, and the container config.)

---

*Tour covers karhu core @ `460cd5c`. Companion docs: [DOCS.md](DOCS.md) (project facts), [docs/adr/](docs/adr/) (decisions), [docs/](docs/) (usage). Next tours: mishka → istrbuddy → ansible.*
