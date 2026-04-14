# IsTrBuddy — Issue Tracking Buddy

A karhu dogfood reference app demonstrating M2 features end-to-end:

- **Session** middleware with secure cookie defaults
- **CSRF** protection on state-changing routes
- **CORS** with configurable origins
- **RBAC** role-based access control (admin can delete; editors can create)
- **AbstractResourceController** verb-based dispatch
- **Validation** attribute-based input validation
- **PasswordHasher** argon2id authentication
- **Content negotiation** — JSON and HTML from the same controller

## Routes

| Method | Path | Action | Auth |
|--------|------|--------|------|
| POST | `/login` | Authenticate | Public |
| POST | `/logout` | End session | Any logged in |
| GET | `/issues` | List all | Public |
| GET | `/issues/{id}` | Show one | Public |
| POST | `/issues` | Create new | editor, admin |
| DELETE | `/issues/{id}` | Delete | admin only |

## History

Named after Peopsquik's sample app "IsTrBuddy" (Issue Tracking Buddy,
2018). The original Peopsquik framework had the app name but no
implemented controllers — this is the first real implementation, built
on karhu to validate the M2 auth + REST + middleware stack.

## Before / After

| Concern | Peopsquik (2018) | karhu (2026) |
|---------|-----------------|--------------|
| Routing | `modules.ini` + filesystem convention | `#[Route('/issues/{id}')]` attribute |
| Auth | `Core_ACL` with raw SQL | `Rbac` via `UserRepositoryInterface` |
| Passwords | (not implemented) | `PasswordHasher` with argon2id |
| CSRF | (not implemented) | `Csrf` middleware with signed tokens |
| Session | Raw `$_SESSION` | `Session` middleware with secure defaults |
| Validation | Manual checks | `#[Required]`, `#[StringLength]`, etc. |
| Content negotiation | Fixed HTML via Smarty | `Accept` header → JSON or HTML |
