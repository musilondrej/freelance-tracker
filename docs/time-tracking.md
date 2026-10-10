# Time tracking

## Rate

First set value wins: task, project, client. See `Task::resolveHourlyRate()`.

## Time entry

- Every entry belongs to a task. `ended_at = null` means running.
- On finish, the entry snapshots `pricing_type`, `hourly_rate` and `currency`. Later rate changes do not touch it.
- The database computes `duration_seconds` and `amount`.

```
hourly:  amount = (2 * seconds * rate + 3600) div 7200   -- round half up
other:   amount = 0
```

Totals are sums of entry amounts, never hours × rate.

## History

- An entry on a statement cannot be changed or deleted.
- `Task::recalculateTimeEntries()` re-snapshots unbilled entries.
- Changing a task's pricing type re-snapshots its unbilled entries.
- A task with entries cannot move to another client or project.

## Timer

- A partial unique index allows one running timer.
- The server keeps the time. The browser only counts.
- A run under one second is discarded.

## Alerts

| Alert | Schedule |
|---|---|
| Timer runs too long | `alerts:timer`, every 15 min |
| Recurring period starts soon | `alerts:recurring`, daily 8:00 |
| Task deadlines coming up (one digest) | `alerts:deadlines`, daily 8:00 |

Each alert is sent once, keyed in `sent_alerts`.

## Rules

- Durations are whole seconds, shown as `H:MM`.
- No rounding to 15 minutes.
- An entry belongs to the local day it started.
- A running timer is not in any total.
