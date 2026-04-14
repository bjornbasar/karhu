# Contributing to karhu

Thank you for considering contributing to karhu.

## Development setup

```bash
git clone git@github.com:bjornbasar/karhu.git
cd karhu
composer install
```

## Running checks

```bash
composer check       # runs cs-check + analyse + test
composer test        # PHPUnit only
composer analyse     # PHPStan level 8
composer cs-fix      # auto-fix code style
```

## Pull request process

1. Fork the repo and create a branch from `main`.
2. Write your code with tests (TDD preferred).
3. Ensure `composer check` passes (code style, PHPStan level 8, all tests green).
4. One commit per logical change; message format: `type(scope): summary`.
5. Open a PR against `main` with a clear description.

## Commit message types

- `feat`: new feature
- `fix`: bug fix
- `docs`: documentation only
- `chore`: tooling, CI, dependencies
- `refactor`: code change that neither fixes a bug nor adds a feature
- `test`: adding or correcting tests

## Code style

karhu follows [PER-CS2.0](https://www.php-fig.org/per/coding-style/). Run `composer cs-fix` to auto-format.

## What belongs in core

karhu's core is intentionally minimal (~1200 LOC). New features should generally be separate packages (`karhu-*`) rather than additions to the core. If you're unsure, open an issue to discuss before writing code.

## Reporting bugs

Open a GitHub issue with:
- karhu version
- PHP version
- Steps to reproduce
- Expected vs actual behaviour
