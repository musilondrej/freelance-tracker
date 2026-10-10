# Freelance Tracker – agent guidelines

Single-user app for a freelance developer: clients, projects, tasks (kanban, checklists), time tracking, statements of work. Laravel 13, Filament 5, Livewire 4, PostgreSQL 18, PHPUnit 13, Larastan level 8, Pint. Local env runs in Sail.

Read `docs/architecture.md`, `docs/billing.md` and `docs/time-tracking.md` before touching money, rates, time entries or billing.

## Commands

- `vendor/bin/sail up -d`: app on http://localhost:8080, Postgres on port 5433.
- `vendor/bin/sail composer check`: runs Pint, Rector (dry run), PHPStan and PHPUnit, including architecture tests. It must pass before every commit.
- `vendor/bin/sail composer rector`: applies Rector's refactorings.
- `vendor/bin/sail composer lint`: fixes code style.
- `vendor/bin/sail artisan make:filament-user`: creates an account. There is no public registration.

## Rules

- **Money:** always `int` in minor units (haléře/cents), in the DB as `bigint`. Never float or decimal.
- **Currency:** lives only on the client. Never sum different currencies, except overview totals converted to CZK by `App\Reporting\CzkTotal` (ČNB rate, its date in the page footer). Statements and stored amounts are never converted.
- **Time entries:**
  - `duration_seconds` and `amount` are Postgres generated columns. `#[Refreshes]` on `TimeEntry` reloads them after saving.
  - The rate snapshot is written only by `TimeEntry::booted()` and `Task::recalculateTimeEntries()`.
  - A time entry with a billing allocation is immutable.
- **Domain logic** lives in `app/Actions` (one `final` class, `handle()`, wrapped in a DB transaction) and in `app/Reporting`. Filament and Livewire classes only collect input and call those.
- **Every rule is defined once:**
  - a set of records = a method on the model's query builder (`app/Models/Builders`, wired by `#[UseEloquentBuilder]`, e.g. `Task::query()->open()`);
  - one record = a model method (`isOverdue()`, `value()`, `isDraft()`);
  - aggregates = `app/Reporting`, composed from builder methods;
  - state changes = Actions when they lock rows or touch more models; a simple change of one record stays a model method (`markReadyToBill()`, `archive()`).
  Filament closures type the builder (`fn (TaskBuilder $query) => $query->open()`) so PHPStan sees the methods. Today's local date is `Period::localToday()`, never `today()`.
- **Rule violations:** throw `App\Exceptions\DomainRuleViolation` with a translatable message.
- **Code style:**
  - Every file starts with `declare(strict_types=1)`.
  - Use backed enums with Filament `HasLabel`/`HasColor`.
  - Type everything: relation generics, `@property` on models.
- **Language:** UI is Czech via `__('English text')`, translated in `lang/cs.json`. Code, DB and enum values are English.
- **Tests** run against PostgreSQL. DB constraints are part of the design and are tested.
- **Public repository:** no secrets, personal data or real client data anywhere.
