<?php

declare(strict_types=1);

use App\Actions\Alerts\AlertForgottenTimer;
use App\Actions\Alerts\RemindUpcomingDeadlines;
use App\Actions\Alerts\RemindUpcomingRecurring;
use App\Actions\ExchangeRates\FetchCnbRates;
use App\Actions\Recurring\GenerateRecurringTasks;
use App\Support\Period;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('recurring:generate', function (GenerateRecurringTasks $generate): void {
    $this->info(__(':count tasks generated', ['count' => $generate->handle()]));
})->purpose('Generate tasks of recurring periods that have started (idempotent)');

Artisan::command('alerts:timer', function (AlertForgottenTimer $alert): void {
    $this->info(__(':count alerts sent', ['count' => $alert->handle()]));
})->purpose('Alert about a timer running longer than the user\'s limit (once per entry)');

Artisan::command('alerts:deadlines', function (RemindUpcomingDeadlines $remind): void {
    $this->info(__(':count alerts sent', ['count' => $remind->handle()]));
})->purpose('Warn about task deadlines coming up (once per task and deadline)');

Artisan::command('alerts:recurring', function (RemindUpcomingRecurring $remind): void {
    $this->info(__(':count alerts sent', ['count' => $remind->handle()]));
})->purpose('Remind of recurring periods starting soon (once per period)');

Artisan::command('rates:fetch', function (FetchCnbRates $fetch): void {
    $this->info(__(':count exchange rates stored', ['count' => $fetch->handle()]));
})->purpose('Store today\'s ČNB exchange rates (idempotent)');

// All commands are idempotent, so overlapping or missed runs are harmless.
Schedule::command('recurring:generate')->hourly()->withoutOverlapping();
Schedule::command('alerts:timer')->everyFifteenMinutes()->withoutOverlapping();
// ČNB publishes the fixing around 14:30 on business days.
Schedule::command('rates:fetch')->dailyAt('14:45')->timezone(Period::timezone());
// Nightly database backup, old backups pruned, and an alert when the newest one is too old.
Schedule::command('backup:run --only-db')->dailyAt('02:00')->timezone(Period::timezone())->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('02:30')->timezone(Period::timezone());
Schedule::command('backup:monitor')->dailyAt('09:00')->timezone(Period::timezone());
Schedule::command('alerts:deadlines')->dailyAt('08:00')->timezone(Period::timezone());
Schedule::command('alerts:recurring')->dailyAt('08:00')->timezone(Period::timezone());
