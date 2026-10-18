# Database

PostgreSQL 18. Constraints are tested in `tests/Feature/Domain/DatabaseConstraintsTest.php` and the other domain tests. A new constraint gets a test: a case in `DatabaseConstraintsTest`, or a behaviour test next to the rule it protects.

## Conventions

| Value | Type |
|---|---|
| Money | `bigint` minor units |
| Instants | `timestamptz(0)`, UTC |
| Dates | `date` |
| Durations | `integer` seconds |
| Enums | `varchar` + CHECK |

- Foreign keys to records with history use `RESTRICT`.
- `SUM(bigint)` returns `numeric`, so cast to `bigint`.

## Tables

| Table | Key constraints |
|---|---|
| `users` | alert, supplier and preference columns, `workday_hours` 1–24 |
| `clients` | `UNIQUE(code)`, `UNIQUE(id, currency)`, rate > 0 |
| `projects` | `UNIQUE(id, client_id)` |
| `tasks` | `FK (project_id, client_id)`, `UNIQUE(client_id, number)`, `UNIQUE(id, client_id)`, fixed needs `fixed_price`, rate and price > 0 |
| `recurring_tasks` | `FK (project_id, client_id)` |
| `time_entries` | generated `duration_seconds` and `amount`, one running timer |
| `statements` | `FK (client_id, currency)`, draft ⇔ no `sent_on` |
| `statement_items` | `amount > 0` |
| `statement_item_time_entry` | `UNIQUE(time_entry_id)` |
| `notes`, `links` | `FK (project_id, client_id)`, `FK (task_id, client_id)`, `ON DELETE SET NULL` keeps the record on the client; links are http(s) only |

Task keys: `clients.code` + `tasks.number`. One atomic `UPDATE … RETURNING` hands out numbers. Numbers are never reused.

## Migrations

Migrations have no `down()`. Change the schema only with new migrations.
