# Code map

Where features live. All UI is the Filament panel; there are no web routes.

| Feature | Model, rules | UI |
|---|---|---|
| Clients | `Models/Client.php` | `Filament/Resources/Clients/` |
| Projects, budgets | `Models/Project.php`, `Models/Builders/ProjectBuilder.php`, `Reporting/ProjectStats.php` | `Filament/Resources/Projects/` |
| Tasks, checklists | `Models/Task.php`, `Models/Builders/TaskBuilder.php` | `Filament/Resources/Tasks/`, `Filament/Actions/TaskActions.php` |
| Kanban | `Actions/Tasks/MoveTask.php` | `Filament/Pages/TaskBoard.php` |
| Recurring tasks | `Models/RecurringTask.php`, `Actions/Recurring/` | `Filament/Resources/RecurringTasks/` |
| Time entries, timer | `Models/TimeEntry.php`, `Actions/TimeTracking/` | `Filament/Resources/TimeEntries/`, `Livewire/TimerIndicator.php` |
| Statements, PDF | `Models/Statement*.php`, `Actions/Billing/` | `Filament/Resources/Statements/`, `Filament/Actions/StatementActions.php`, `views/pdf/statement.blade.php` |
| To invoice | `Reporting/UnbilledWork.php` | `Filament/Pages/Unbilled.php` |
| Reports, dashboard | `Reporting/` (`ReportFilters`, `FinancialSummary`, `RateHistory`) | `Filament/Pages/Reports.php`, `Filament/Widgets/Reports/`, `Filament/Widgets/` |
| Notes, links | `Models/Note.php`, `Models/Link.php`, `Models/Concerns/BelongsToClientWork.php` | `Filament/RelationManagers/` |
| Alerts | `Actions/Alerts/`, `Notifications/`, `routes/console.php` | `Filament/Pages/Auth/EditProfile.php` |
| Profile, preferences | `Models/User.php` | `Filament/Pages/Auth/EditProfile.php` |

Paths are under `app/`, except `views/` (`resources/views/`) and `routes/`.

Shared pieces: `Support/` (Money, Duration, Period), `Filament/Forms/` (money and duration inputs, client/project selects), archiving (`Models/Concerns/Archivable.php`, `Filament/Actions/ArchiveActions.php`), rule violations shown as toasts (`Providers/AppServiceProvider.php`).
