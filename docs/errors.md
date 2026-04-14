# Error Handling

karhu's `ExceptionHandler` converts exceptions to HTTP responses with content negotiation.

## Setup

```php
// In public/index.php, before $app->run():
$handler = new \Karhu\Error\ExceptionHandler();
$handler->register();
```

## Behaviour

- **JSON clients** (Accept: application/json): RFC 7807 `application/problem+json` response
- **Browsers** (Accept: text/html): HTML error page
- **Dev mode** (`APP_ENV=local`): full stack trace in both formats
- **Production**: generic error message, no internals exposed
- **All errors** logged to stderr with timestamp

## Example JSON error (dev mode)

```json
{
    "type": "about:blank",
    "title": "Internal Server Error",
    "status": 500,
    "detail": "Call to undefined method ...",
    "exception": "Error",
    "file": "/app/src/Controllers/BrokenController.php:42",
    "trace": ["..."]
}
```

## Status code mapping

| Exception type | HTTP status |
|---------------|------------|
| `InvalidArgumentException` | 400 Bad Request |
| Everything else | 500 Internal Server Error |

Extend by subclassing or wrapping the handler.
