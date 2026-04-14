# ADR-0007: Opinionated Attribute-Only Routing
**Status:** Accepted
**Date:** 2026-04-14

## Context
Most PHP micro-frameworks offer multiple ways to define routes: closure
callbacks, route files, YAML, and sometimes attributes. This flexibility
is a selling point, but it also means routes scatter across the project
and the framework must support every style.

## Decision
Karhu supports **only** attribute-based routing. There are no closure
routes, no `routes.php` file, and no configuration-based definitions.
Every route is a `#[Get]`, `#[Post]`, (etc.) attribute on a controller
method.

This is a deliberate differentiation from Slim 4, Flight, and Lumen,
all of which centre on closure-based routing.

## Consequences
- **Benefit:** routes are always co-located with their handler logic;
  grepping the codebase shows every endpoint at a glance.
- **Benefit:** one routing style means one code path in the router,
  keeping the implementation simple and the LOC count low.
- **Benefit:** IDEs can jump from attribute to handler instantly --
  no indirection through a route file.
- **Trade-off:** quick one-off endpoints (e.g. a health-check closure)
  require a controller class, adding minor ceremony.
- **Trade-off:** developers accustomed to `$app->get('/...', fn() => ...)`
  will need to adjust their workflow.
