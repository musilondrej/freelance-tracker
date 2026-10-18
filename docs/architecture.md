# Architecture

Correct time and money come first. The UI is a thin layer over a tested domain.

Out of scope: multi-tenancy, roles, VAT, time rounding. Currency conversion exists only for overviews.

## Layers

| Folder | Contains |
|---|---|
| `app/Actions` | State changes. One `final` class, `handle()`, one DB transaction. |
| `app/Reporting` | Read-only aggregates in SQL. |
| `app/Models` | Relations, casts, guards in `booted()`, single-record methods. |
| `app/Models/Builders` | Named record sets, e.g. `Task::query()->open()`. |
| `app/Support` | `Money`, `Duration`, `Period`. |
| `app/Filament` | UI only. Collects input and calls Actions. |

## Rules

- Every rule is defined once: a set is a builder method, one record is a model method, a state change is an Action.
- Use an Action when an operation locks rows or touches more models. A simple change of one record stays in the model.
- Rule violations throw `DomainRuleViolation`. Livewire shows it as a toast.
- Filament closures type the builder (`fn (TaskBuilder $q) => $q->open()`), so PHPStan knows the methods.
- Today's date is `Period::localToday()`. `today()` is UTC.
- Integrity lives in the database: CHECK constraints, composite foreign keys, a partial unique index, generated columns.
- No repositories and no interfaces with one implementation.

## Decisions

| Decision | Why |
|---|---|
| Money as `int` minor units | No float errors. |
| Currency only on the client | Snapshots copy it. Currencies are never summed or converted; overviews show one line per currency. |
| Generated `duration_seconds` and `amount` | They cannot drift from the times and the rate. |
| Billing through allocations (entry → item) | Partial billing without double billing. |
| `archived_at` instead of soft deletes | History stays visible in reports. |
| Store UTC, show `Europe/Prague` | Days and months are local. |
| Single user, no `owner_id` | Scoping would be noise. |
