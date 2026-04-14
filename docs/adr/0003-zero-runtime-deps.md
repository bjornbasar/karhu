# ADR-0003: Zero Runtime Dependencies
**Status:** Accepted
**Date:** 2026-04-14

## Context
Micro-frameworks often pull in PSR interface packages (`psr/http-message`,
`psr/container`, `psr/log`) even when they implement only a subset of
those interfaces. Each dependency adds supply-chain surface area and
a version-constraint axis that can block upgrades.

## Decision
The `require` section of `composer.json` remains empty. Karhu defines
its own interfaces (`ContainerInterface`, `LoggerInterface`, etc.) whose
method signatures match the corresponding PSR shapes. This means:

- A Karhu `ContainerInterface` has `get()` and `has()` with the same
  signatures as PSR-11, but lives in `Karhu\Contract\`.
- User code that type-hints Karhu's interfaces is structurally
  compatible with PSR equivalents.

## Consequences
- **Benefit:** zero supply-chain risk from runtime dependencies; the
  framework installs with nothing beyond PHP itself.
- **Benefit:** no Composer version conflicts when integrating into
  host applications that pin different PSR package versions.
- **Trade-off:** Karhu's interfaces are not *nominally* PSR-compliant,
  so drop-in interop with PSR-typed libraries requires a thin adapter.
- **Trade-off:** if a PSR interface adds a method, Karhu must manually
  mirror the change -- there is no compile-time contract check.
