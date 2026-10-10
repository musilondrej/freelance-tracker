<?php

declare(strict_types=1);

namespace App\Providers;

use App\Exceptions\DomainRuleViolation;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        // Outside production, throw on lazy loading and on unknown or missing attributes.
        Model::shouldBeStrict(! $this->app->isProduction());

        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Used by the Filament profile page. The leaked-password check calls the HIBP API, so only in production.
        Password::defaults(fn (): Password => $this->app->isProduction() ? Password::min(12)->uncompromised() : Password::min(12));

        // Filament and Livewire put Vite's nonce on their inline scripts, so it must be the CSP header's nonce.
        Vite::useCspNonce($this->app->make('csp-nonce'));

        FilamentTimezone::set(Period::timezone());

        // Business rule violations and blocked deletes from any Livewire/Filament action become a toast instead of an error page.
        Livewire::listen('exception', function (mixed $component, Throwable $exception, callable $stopPropagation): void {
            $message = match (true) {
                $exception instanceof DomainRuleViolation => $exception->getMessage(),
                // Records with history are protected by foreign keys: 23001 = RESTRICT, 23503 = NO ACTION.
                $exception instanceof QueryException && in_array($exception->getCode(), ['23001', '23503'], true) => Str::of($exception->getSql())->ltrim()->lower()->startsWith('delete')
                    ? (string) __('It is still in use, so it cannot be deleted. Archive it instead.')
                    : (string) __('It refers to a record that does not fit, for example a project of another client.'),
                default => null,
            };

            if ($message !== null) {
                Notification::make()->danger()->title($message)->send();
                $stopPropagation();
            }
        });

        // Needed despite the panel's databaseTransactions(): Postgres aborts the whole transaction on a
        // failed statement, so a delete blocked by a foreign key needs its own savepoint to show a toast.
        DeleteAction::configureUsing(fn (DeleteAction $action) => $action->databaseTransaction());

        Table::configureUsing(fn (Table $table) => $table
            ->paginationPageOptions([25, 50, 100])
            ->defaultDateDisplayFormat(Period::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(Period::DATETIME_FORMAT));

        Schema::configureUsing(fn (Schema $schema) => $schema
            ->defaultDateDisplayFormat(Period::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(Period::DATETIME_FORMAT));
    }
}
