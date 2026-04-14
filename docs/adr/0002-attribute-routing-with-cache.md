# ADR-0002: Attribute Routing with Production Cache
**Status:** Accepted
**Date:** 2026-04-14

## Context
Route definition in micro-frameworks typically uses one of: closure-based
DSLs (Slim, Flight), YAML/INI config files, or PHP 8 attributes.
Karhu needs a mechanism that keeps routes co-located with handler code
while staying within the ~1 200 LOC budget.

## Decision
Routes are declared exclusively via PHP 8 attributes (`#[Route]`,
`#[Get]`, `#[Post]`, etc.) on controller methods. At runtime the
router resolves these through reflection.

To eliminate the reflection cost in production, `bin/karhu route:cache`
serialises the compiled route table to a cache file. When the cache
file is present, the router loads it directly and skips reflection
entirely.

## Consequences
- **Benefit:** routes live next to their handlers -- no separate route
  file to keep in sync.
- **Benefit:** the cache command makes reflection a dev-only cost;
  production cold-start is a single `include`.
- **Trade-off:** developers must remember to regenerate the cache after
  route changes in production (CI can enforce this).
- **Trade-off:** attribute-only routing is less flexible than closures
  for quick prototyping (see ADR-0007 for rationale).
