# karhu

**Minimal PHP microframework — attribute-routed, zero dependencies, PHP 8.3+.**

```php
final class Hello {
    #[Route('/hello/{name}')]
    public function greet(Request $request): Response {
        return (new Response())->json(['hi' => $request->routeParams()['name']]);
    }
}
```

## Why karhu?

- **Zero runtime Composer dependencies** — `require` section is empty
- **~1200 LOC core** — the lightest framework with attribute routing + middleware + DI
- **Attribute-only routing** — `#[Route('/path')]`, no YAML, no closure chains
- **PHP 8.3+** — readonly properties, enums, attributes, match expressions

## Quick install

```bash
composer create-project bjornbasar/karhu-skeleton my-app
cd my-app
php -S localhost:8080 -t public
```

## What's included

| Component | Description |
|-----------|-------------|
| Router | Attribute-scanned, regex-compiled, route groups, named routes, urlFor |
| Request/Response | PSR-7 shape, JSON auto-decode, redirect helper |
| Middleware | PSR-15 shape pipeline, CSRF, CORS, Session shipped |
| Container | PSR-11 shape auto-wiring DI |
| Auth | PasswordHasher (argon2id), RBAC, RequireRole middleware |
| Validation | 6 attribute validators: Required, StringLength, NumericRange, Email, Regex, In |
| CLI | Attribute-registered commands via `bin/karhu` |
| Config | PHP arrays + env override, dot notation |
| Error handling | RFC 7807 problem+json, dev/prod branching |
