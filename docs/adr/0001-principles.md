# ADR-0001: Core Architectural Principles
**Status:** Accepted
**Date:** 2026-04-14

## Context
Karhu enters a crowded PHP micro-framework space (Slim, Flight, Lumen).
To justify its existence it needs a clear, measurable identity that
guides every subsequent design decision.

## Decision
The following principles are adopted as hard constraints:

1. **Zero runtime dependencies** -- `composer.json` `require` section stays empty.
2. **~1 200 LOC core** -- the `src/` directory must not exceed roughly 1 200 lines of production code (tests excluded).
3. **PHP 8.3+ floor** -- leverage the latest stable language features; no polyfills.
4. **TDD-first** -- every public method ships with a test; coverage gate in CI.
5. **Docs-first** -- ADRs and DOCS.md are written *before* or *alongside* the code they describe, never after.

These five constraints are ordered by priority: if two conflict, the
higher-numbered principle yields to the lower-numbered one.

## Consequences
- Features that would push LOC past the budget are extracted to optional
  packages (e.g. `karhu-db`).
- The zero-dep rule forbids pulling in PSR interface packages, so Karhu
  must define its own compatible shapes (see ADR-0003, ADR-0005).
- TDD-first slows initial velocity but catches regressions early,
  which matters for a project with one maintainer.
- Docs-first ensures the ADR trail stays current and reviewable.
