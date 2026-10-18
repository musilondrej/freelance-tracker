<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Filament\Support\CsvExport;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\Duration;
use App\Support\Period;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of the current table view, with its filters, search and sorting.
 */
final class ExportActions
{
    public static function timeEntries(): Action
    {
        return self::make(fn (HasTable $livewire): StreamedResponse => CsvExport::download(
            'casove-zaznamy-'.Period::localToday()->toDateString().'.csv',
            [
                __('Date'), __('Start'), __('End'), __('Duration'), __('Hours'), __('Client'), __('Project'), __('Key'), __('Task'),
                __('Note'), __('Pricing'), __('Rate'), __('Value'), __('Currency'), __('On statement'),
            ],
            // The table query already eager loads and adds `is_allocated` (TimeEntriesTable).
            $livewire->getTableQueryForExport()
                ->lazy()
                ->map(fn (Model $entry): array => self::timeEntryRow($entry)),
        ));
    }

    public static function tasks(): Action
    {
        return self::make(fn (HasTable $livewire): StreamedResponse => CsvExport::download(
            'ukoly-'.Period::localToday()->toDateString().'.csv',
            [
                __('Key'), __('Task'), __('Client'), __('Project'), __('Status'), __('Priority'), __('Pricing'), __('Deadline'),
                __('Hours'), __('Value'), __('Currency'), __('Ready to bill'), __('Completed'),
            ],
            // The table query already eager loads and adds the tracking sums (TasksTable).
            $livewire->getTableQueryForExport()
                ->lazy()
                ->map(fn (Model $task): array => self::taskRow($task)),
        ));
    }

    /**
     * @return array<int, string|null>
     */
    private static function timeEntryRow(Model $entry): array
    {
        assert($entry instanceof TimeEntry);
        $tz = Period::timezone();

        return [
            $entry->started_at->setTimezone($tz)->format('Y-m-d'),
            $entry->started_at->setTimezone($tz)->format('H:i'),
            $entry->ended_at?->setTimezone($tz)->format('H:i'),
            $entry->duration_seconds === null ? null : Duration::format($entry->duration_seconds),
            CsvExport::hours($entry->duration_seconds),
            $entry->task->client->name,
            $entry->task->project?->name,
            $entry->task->key(),
            $entry->task->title,
            $entry->note,
            $entry->pricing_type?->getLabel(),
            CsvExport::amount($entry->hourly_rate),
            CsvExport::amount($entry->amount),
            $entry->currency?->value,
            $entry->getAttribute('is_allocated') ? __('Yes') : __('No'),
        ];
    }

    /**
     * @return array<int, string|null>
     */
    private static function taskRow(Model $task): array
    {
        assert($task instanceof Task);
        $tz = Period::timezone();

        return [
            $task->key(),
            $task->title,
            $task->client->name,
            $task->project?->name,
            $task->status->getLabel(),
            $task->priority->getLabel(),
            $task->pricing_type->getLabel(),
            $task->due_on?->format('Y-m-d'),
            CsvExport::hours($task->trackedSeconds()),
            CsvExport::amount($task->value()),
            $task->client->currency->value,
            $task->ready_to_bill_at?->setTimezone($tz)->format('Y-m-d'),
            $task->completed_at?->setTimezone($tz)->format('Y-m-d'),
        ];
    }

    /** The export closure gets Filament's injected arguments, like `$livewire`. */
    public static function make(Closure $export): Action
    {
        return Action::make('exportCsv')
            ->label(__('Export CSV'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action($export);
    }
}
