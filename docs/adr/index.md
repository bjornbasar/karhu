# Architecture Decision Records

| ADR | Title | Summary |
|-----|-------|---------|
| [0001](0001-principles.md) | Core Architectural Principles | Zero deps, ~1 200 LOC, PHP 8.3+, TDD-first, docs-first |
| [0002](0002-attribute-routing-with-cache.md) | Attribute Routing with Cache | PHP 8 attributes for route definitions; `bin/karhu route:cache` for production |
| [0003](0003-zero-runtime-deps.md) | Zero Runtime Dependencies | Empty `require` section; own interfaces matching PSR shapes |
| [0004](0004-php-version-floor.md) | PHP 8.3+ Version Floor | Maximise feature availability with 2+ year useful life |
| [0005](0005-psr7-shape-not-compliance.md) | PSR-7 Shape, Not Compliance | Implement the methods controllers call, skip the other ~15; saves ~300 LOC |
| [0006](0006-rbac-via-repository-interface.md) | RBAC via Repository Interface | Auth queries through `UserRepositoryInterface`, decoupled from `karhu-db` |
| [0007](0007-opinionated-attribute-only-routing.md) | Attribute-Only Routing | No closures, no route files -- intentional differentiation from Slim/Flight/Lumen |
