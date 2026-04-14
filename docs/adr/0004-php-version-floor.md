# ADR-0004: PHP 8.3+ Version Floor
**Status:** Accepted
**Date:** 2026-04-14

## Context
Choosing a minimum PHP version is a balance between language feature
availability and the audience that can adopt the framework. Karhu
targets developers who run modern PHP and value concise, expressive
code over broad compatibility.

## Decision
The minimum supported version is PHP 8.3. This gives access to:

- **Typed class constants** (8.3) -- used in route attribute enums.
- **Readonly classes** (8.2) -- immutable value objects without per-property boilerplate.
- **Enums** (8.1) -- HTTP method enums, status code enums.
- **Attributes** (8.0) -- the entire routing system (ADR-0002).
- **Named arguments, match expressions, union types** (8.0+).

PHP 8.2 reaches end-of-life in December 2026, so an 8.3 floor gives
Karhu a useful life of at least two years before the floor itself
becomes the oldest supported branch.

## Consequences
- **Benefit:** the codebase uses modern syntax throughout, keeping LOC
  low and intent clear.
- **Benefit:** no polyfills or version-branching logic needed.
- **Trade-off:** projects stuck on PHP 8.1 or 8.2 cannot adopt Karhu.
  This is acceptable given the target audience.
