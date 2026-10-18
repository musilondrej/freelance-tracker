<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Actions\Billing\AddTasksToStatement;
use App\Actions\Billing\AddTimeEntriesToStatement;
use App\Actions\Billing\CancelStatement;
use App\Actions\Billing\CreateStatement;
use App\Actions\Billing\SendStatement;
use App\Enums\StatementStatus;
use App\Exceptions\DomainRuleViolation;
use App\Filament\Resources\Statements\StatementResource;
use App\Models\Client;
use App\Models\Statement;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Reporting\UnbilledWork;
use App\Support\Money;
use App\Support\Period;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StatementActions
{
    public static function downloadPdf(): Action
    {
        return Action::make('downloadPdf')
            ->label(__('Download PDF'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn (Statement $record): StreamedResponse => response()->streamDownload(function () use ($record): void {
                echo Pdf::loadView('pdf.statement', [
                    'statement' => $record->load(['client', 'items.task.client', 'items.timeEntries']),
                    'supplier' => Filament::auth()->user(),
                ])
                    ->setOption('isFontSubsettingEnabled', true)
                    ->output();
            }, "vykaz-prace-{$record->id}.pdf"));
    }

    public static function send(): Action
    {
        return Action::make('send')
            ->label(__('Mark as sent'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (Statement $record): bool => $record->isDraft())
            ->modalDescription(__('The work becomes billed and the items can no longer be changed.'))
            ->fillForm(fn (): array => ['sent_on' => Period::localToday()->toDateString()])
            ->schema([DatePicker::make('sent_on')->label(__('Sent'))->required()])
            ->action(fn (Statement $record, array $data, SendStatement $send) => $send->handle($record, CarbonImmutable::parse($data['sent_on'])))
            ->successNotificationTitle(__('Sent'));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label(__('Cancel statement'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Statement $record): bool => $record->isSent())
            ->requiresConfirmation()
            ->modalDescription(__('The statement stays in history but stops counting. Its time entries and fixed prices become billable again.'))
            ->action(fn (Statement $record, CancelStatement $cancel) => $cancel->handle($record))
            ->successNotificationTitle(__('Cancelled'));
    }

    /** Draft: add all unbilled work of selected tasks of the statement's client. */
    public static function addTasks(): Action
    {
        return Action::make('addTasks')
            ->label(__('Add tasks'))
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (Statement $record): bool => $record->isDraft())
            ->schema(fn (Statement $record): array => [
                CheckboxList::make('task_ids')
                    ->label(__('Tasks with unbilled work'))
                    ->options(fn (): array => UnbilledWork::tasks()
                        ->where('tasks.client_id', $record->client_id)
                        ->orderBy('title')
                        ->get()
                        ->mapWithKeys(fn (Task $task): array => [$task->id => $task->title.' — '.Money::format(UnbilledWork::amount($task), $record->currency)])
                        ->all())
                    ->required()
                    ->bulkToggleable(),
                self::untilField(),
            ])
            ->action(function (Statement $record, array $data, AddTasksToStatement $addTasks): void {
                $addTasks->handle($record, Task::query()->whereKey($data['task_ids'])->get(), self::until($data));
            })
            ->successNotificationTitle(__('Added'));
    }

    /** "To invoice" list: create a draft from selected tasks of one client and open it. */
    public static function createFromTasks(): BulkAction
    {
        return BulkAction::make('createStatement')
            ->label(__('Create statement'))
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->schema([self::untilField()])
            ->action(function (Collection $records, array $data, Component $livewire, CreateStatement $createStatement): void {
                /** @var Collection<int, Task> $records */
                $client = self::singleClient($records->pluck('client_id')->unique()->all());
                $statement = $createStatement->handle($client, $records, self::until($data));

                Notification::make()->success()->title(__('Statement created'))->send();
                $livewire->redirect(StatementResource::getUrl('view', ['record' => $statement]), navigate: true);
            })
            ->deselectRecordsAfterCompletion();
    }

    /** Time entries: add selected entries to a new or existing draft. */
    public static function addTimeEntriesToStatement(): BulkAction
    {
        return BulkAction::make('addToStatement')
            ->label(__('Add to statement'))
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->schema(fn (Collection $records): array => [
                Select::make('statement_id')
                    ->label(__('Statement'))
                    ->placeholder(__('New draft'))
                    ->options(fn (): array => Statement::query()
                        ->where('status', StatementStatus::Draft)
                        ->whereIn('client_id', TimeEntry::query()->whereKey($records->modelKeys())->join('tasks', 'tasks.id', '=', 'time_entries.task_id')->select('tasks.client_id'))
                        ->get()
                        ->mapWithKeys(fn (Statement $statement): array => [$statement->id => StatementResource::title($statement)])
                        ->all()),
            ])
            ->action(function (Collection $records, array $data, Component $livewire, CreateStatement $createStatement, AddTimeEntriesToStatement $addEntries): void {
                /** @var Collection<int, TimeEntry> $records */
                $records->load('task');
                $client = self::singleClient($records->pluck('task.client_id')->unique()->all());

                $statement = filled($data['statement_id'])
                    ? Statement::query()->findOrFail((int) $data['statement_id'])
                    : $createStatement->handle($client);

                $addEntries->handle($statement, $records->modelKeys());

                Notification::make()->success()->title(__('Added to :statement', ['statement' => StatementResource::title($statement)]))->send();
                $livewire->redirect(StatementResource::getUrl('view', ['record' => $statement]), navigate: true);
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function untilField(): DatePicker
    {
        return DatePicker::make('until')
            ->label(__('Hourly entries up to and including'))
            ->default(fn (): string => Period::localToday()->subMonthNoOverflow()->endOfMonth()->toDateString())
            ->helperText(__('Later entries stay billable. Empty = all.'));
    }

    /**
     * End of the given local day, so entries on that day are included.
     *
     * @param  array<string, mixed>  $data
     */
    private static function until(array $data): ?CarbonImmutable
    {
        return filled($data['until']) ? Period::day((string) $data['until'])->end : null;
    }

    /**
     * @param  array<mixed>  $clientIds
     */
    private static function singleClient(array $clientIds): Client
    {
        if (count($clientIds) !== 1) {
            throw DomainRuleViolation::because('A statement is for one client. Select work of a single client.');
        }

        return Client::query()->whereKey(Arr::first($clientIds))->firstOrFail();
    }
}
