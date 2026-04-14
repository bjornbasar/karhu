# ADR-0005: PSR-7 Shape, Not Full Compliance
**Status:** Accepted
**Date:** 2026-04-14

## Context
PSR-7 (`psr/http-message`) defines ~30 methods across
`RequestInterface`, `ResponseInterface`, `StreamInterface`, and related
classes. In practice, most controller code touches fewer than half of
these. Implementing the full surface would push Karhu's core well past
the ~1 200 LOC target (estimated ~1 500 LOC).

## Decision
Karhu's `Request` and `Response` classes implement the PSR-7 *shape* --
the methods controllers actually call day-to-day -- without claiming
full PSR-7 compliance. Covered methods include:

- `Request`: `getMethod()`, `getUri()`, `getQueryParams()`,
  `getParsedBody()`, `getHeader()`, `getAttribute()`.
- `Response`: `withStatus()`, `withHeader()`, `withBody()`,
  `getBody()`, `getStatusCode()`.

Methods outside this set (e.g. `withProtocolVersion()`,
`withRequestTarget()`) are not implemented.

## Consequences
- **Benefit:** this is the single biggest LOC lever in the framework,
  saving roughly 300 lines of production code.
- **Benefit:** simpler internals mean fewer edge-case bugs in stream
  handling and immutability cloning.
- **Trade-off:** middleware or libraries that rely on the full PSR-7
  contract will not work out of the box; an adapter or the
  `nyholm/psr7` bridge would be needed.
