# Billing

Amounts are `int` minor units without VAT. Different currencies are never summed.

## Several currencies

Overview cards (dashboard, To invoice) with more than one currency show one figure in CZK with the original amounts underneath; the ČNB rate date is in the page footer. `CzkTotal` converts with the newest ČNB rate up to today; `rates:fetch` stores the fixing daily at 14:45. Without a rate, each currency keeps its own line. The dashboard's work value chart converts each day the same way and stays empty until every used currency has a rate. Statements, PDFs, rates and stored amounts are never converted.

## Figures

| Figure | Definition | Dated by |
|---|---|---|
| Tracked time | Sum of finished entries | Entry start |
| Work value | Hourly entry amounts + ready fixed prices | Entry start, `ready_to_bill_at` |
| To invoice | Unallocated hourly entries + ready fixed tasks not on an active statement | Now |
| Billed | Items of sent statements | `sent_on` |

Draft items show as "in drafts". Payments are tracked in Fakturoid, not here.

Real hourly rate = work value / all tracked time, non-billable included.

## Pricing types

- **Hourly:** value is the sum of entry amounts.
- **Fixed:** value is `fixed_price`. Entries have `amount = 0`. It counts from `ready_to_bill_at`. The price is locked while ready.
- **Non-billable:** time is tracked, value is 0.

Rates and fixed prices are always above zero. Unpaid work uses non-billable pricing, so every statement item has an amount.

## Statements

A statement lists billed work for one client. It is the attachment to an invoice.

```
statements
  └── statement_items
        └── statement_item_time_entry (UNIQUE time_entry_id)
```

Status: `draft` → `sent` → `cancelled`. Only drafts can be edited or deleted.

| Operation | Action |
|---|---|
| Create, optionally from tasks | `CreateStatement` |
| Add unbilled work of tasks | `AddTasksToStatement` |
| Add selected entries | `AddTimeEntriesToStatement` |
| Send | `SendStatement` |
| Cancel | `CancelStatement` |

- An hourly item groups entries by task and rate. Its amount is the sum of the entry amounts.
- A fixed task is billed whole, once.
- Cancelling releases the work. Items stay as history.
- The PDF lists items, entries and the total.

## Project budget

- Consumed = all-time work value.
- Planned = consumed + fixed prices not ready yet.
- Warning from 80 %.

## Recurring tasks

A template creates one normal task per period (monthly, quarterly, yearly).

- Fixed price in advance: ready at the period start.
- Fixed price in arrears: ready the day after the period ends.
- `recurring:generate` runs hourly. It is idempotent and catches up missed periods.
- The schedule cannot change once tasks exist.
- Only a fixed-price template marks its tasks ready. A generated task switched to fixed by hand is marked ready by hand.
