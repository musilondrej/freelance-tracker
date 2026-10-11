# Development

## Environment

Sail runs PHP 8.4 and PostgreSQL 18. The queue is `sync`.

| Variable | Default |
|---|---|
| `APP_PORT` | `127.0.0.1:8080` |
| `FORWARD_DB_PORT` | `127.0.0.1:5433` |
| `APP_LOCALE` | `cs` |

Ports bind to localhost only.

```bash
vendor/bin/sail up -d
vendor/bin/sail npm run dev
vendor/bin/sail artisan schedule:work
```

## Checks

`vendor/bin/sail composer check` runs Pint, Rector (dry run), PHPStan level 8 on app and tests, and PHPUnit on PostgreSQL, including `tests/Unit/ArchitectureTest.php` for the layer rules. CI also reports coverage and audits Composer and npm dependencies. The pre-commit hook runs Pint and PHPStan; `composer install` enables it. Outside production, lazy loading is an error.

## Deploy

1. `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES` when behind a reverse proxy.
2. `composer install --no-dev -o`, `npm ci && npm run build`.
3. `php artisan migrate --force && php artisan optimize && php artisan filament:optimize`.
4. Cron: `* * * * * php artisan schedule:run`.
5. Queue: `QUEUE_CONNECTION=database` and a supervised worker, e.g. `php artisan queue:work --tries=3 --max-time=3600`. Alerts are sent through it. Restart it on deploy with `php artisan queue:restart`.
6. Set `MAIL_*` for e-mail alerts.
7. Backups: install `postgresql-client` (for `pg_dump`) and set `BACKUP_DISKS` to include an off-server disk such as S3. The database is backed up nightly at 02:00; a failure or a backup older than a day is mailed to `BACKUP_NOTIFY_MAIL`.
8. `php artisan make:filament-user`. MFA is optional; turn it on in the profile.
