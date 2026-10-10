# Contributing

## Setup

Requires only Docker; see the Quick start in the [README](README.md). Run PHP, Composer and npm through Sail.

## Before you open a pull request

- `vendor/bin/sail composer check` passes: Pint, Rector, PHPStan and PHPUnit on PostgreSQL.
- New behaviour has a test. A new database constraint gets a test too.
- Read [docs/architecture.md](docs/architecture.md). In short: state changes live in `app/Actions`, aggregates in `app/Reporting`, record sets in the query builders, and Filament classes only collect input.
- Money is always an `int` in minor units; never sum different currencies.
- UI text is English in code (`__('…')`) and translated in `lang/cs.json`.
