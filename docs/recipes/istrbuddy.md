# Recipe: IsTrBuddy Example App

A complete example demonstrating karhu's full M2 stack in a single file.

See [`examples/istrbuddy/app.php`](https://github.com/bjornbasar/karhu/blob/main/examples/istrbuddy/app.php) for the full source.

## What it demonstrates

- Session + CSRF + CORS middleware pipeline
- RBAC-gated routes (admin can delete, editor can create, public can read)
- AbstractResourceController with verb-based dispatch
- Validation with attribute-based DTOs
- PasswordHasher authentication
- Content negotiation (JSON/HTML from same controller)

## Running it

```bash
git clone https://github.com/bjornbasar/karhu.git
cd karhu
composer install
php -S localhost:8080 examples/istrbuddy/app.php
```

## Try the API

```bash
# List issues (public)
curl http://localhost:8080/issues

# Login as admin
curl -X POST http://localhost:8080/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"admin123"}'

# Create an issue (requires editor or admin role + CSRF token)
# In a real app, get the CSRF token from Csrf::token() first
```
