# Maintenance Policy

**Status:** Active development (pre-1.0)

## Current phase

karhu is in early alpha. The API surface may change between minor versions until v1.0 stabilises the interface.

## What to expect

- **Security patches:** applied promptly for any version in active use.
- **PHP floor bumps:** the PHP version requirement will track supported PHP releases. When the current floor reaches EOL, the next minor karhu release bumps the floor.
- **Bug fixes:** accepted via PR; must include tests and pass `composer check`.
- **New features in core:** rare. karhu core is intentionally ~1200 LOC. New capabilities should generally ship as separate `karhu-*` packages.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup and PR process.

## Archive criteria

If no commit lands in 12 months and no security issue is pending, the repo will receive an `ARCHIVED.md` and a README sunset notice — the same treatment applied to chukwu and Peopsquik. This is not defeatist; it's honest, and users deserve clarity about what they're depending on.

## Versioning

[Semantic Versioning 2.0.0](https://semver.org/). Pre-1.0 releases (`0.x`) may include breaking changes in minor versions.
