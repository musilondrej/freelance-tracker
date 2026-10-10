# Security policy

## Reporting a vulnerability

Please do not open a public issue. Report it privately through GitHub: **Security → Report a vulnerability** on this repository.

Include what is affected, how to reproduce it and the impact you expect.

## Supported versions

Only the latest `main` is supported.

## Design

- No public registration; accounts are created on the server with `php artisan make:filament-user`.
- MFA (TOTP with recovery codes) is required in production.
- Nonce-based Content Security Policy and hardening headers on every response.
- Business rules and money integrity are enforced by PostgreSQL constraints, not only by the UI.
