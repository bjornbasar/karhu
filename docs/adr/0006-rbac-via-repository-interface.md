# ADR-0006: RBAC via Repository Interface
**Status:** Accepted
**Date:** 2026-04-14

## Context
Milestone 2 (M2) introduces role-based access control. RBAC needs to
look up users and their roles, which typically means a database query.
However, the core framework has no database layer -- persistence lives
in the optional `karhu-db` package.

## Decision
RBAC queries users through a `UserRepositoryInterface` defined in
`Karhu\Contract\Auth\`. The interface declares:

- `findById(string $id): ?UserInterface`
- `findByCredentials(string $identifier, string $password): ?UserInterface`

The application (or `karhu-db`) provides the concrete implementation
and registers it in the container. The RBAC middleware resolves the
interface at runtime, never referencing a specific database package.

## Consequences
- **Benefit:** the auth milestone is fully decoupled from the optional
  database package; RBAC ships even if `karhu-db` is not installed.
- **Benefit:** users can back the repository with any storage mechanism
  (database, API call, static array for testing).
- **Trade-off:** the application must wire the binding manually; there
  is no auto-discovery of the repository implementation.
- **Trade-off:** the interface is deliberately minimal -- advanced
  queries (pagination, search) are left to the consuming application.
