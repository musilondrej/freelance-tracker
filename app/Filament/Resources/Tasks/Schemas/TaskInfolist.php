<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\PricingType;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\RecurringTasks\RecurringTaskResource;
use App\Models\Task;
use App\Support\Duration;
use App\Support\Money;
use App\Support\Period;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class TaskInfolist
{
    /** Side panel of the task page, see ViewTask::content(). */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Details'))
                    ->inlineLabel()
                    ->schema([
                        TextEntry::make('status')->label(__('Status'))->badge(),
                        TextEntry::make('priority')->label(__('Priority'))->badge(),
                        TextEntry::make('due_on')->label(__('Deadline'))->date()
                            ->placeholder(__('No deadline'))
                            ->color(fn (Task $record): ?string => $record->isOverdue() ? 'danger' : null),
                        TextEntry::make('completed_at')->label(__('Completed'))->date()
                            ->visible(fn (mixed $state): bool => filled($state)),
                        TextEntry::make('client.name')->label(__('Client'))
                            ->url(fn (Task $record): string => ClientResource::getUrl('view', ['record' => $record->client_id])),
                        TextEntry::make('project.name')->label(__('Project'))
                            ->url(fn (Task $record): string => ProjectResource::getUrl('view', ['record' => $record->project_id]))
                            ->visible(fn (Task $record): bool => $record->project_id !== null),
                        TextEntry::make('recurringTask.title')
                            ->label(__('Recurring'))
                            ->visible(fn (Task $record): bool => $record->recurring_task_id !== null)
                            ->url(fn (Task $record): string => RecurringTaskResource::getUrl('edit', ['record' => $record->recurring_task_id]))
                            ->helperText(fn (Task $record) => $record->period_start && $record->period_end
                                ? $record->period_start->format(Period::DATE_FORMAT).' – '.$record->period_end->format(Period::DATE_FORMAT)
                                : null),
                    ]),
                Section::make(__('Time and money'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('value')
                            ->label(__('Value'))
                            ->state(fn (Task $record): string => self::money($record, $record->value()))
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->columnSpanFull()
                            ->helperText(fn (Task $record) => match (true) {
                                $record->pricing_type !== PricingType::Fixed => null,
                                $record->isFixedPriceBilled() => __('Billed'),
                                $record->isReadyToBill() => __('Ready to bill since :date', [
                                    'date' => $record->ready_to_bill_at->setTimezone(Period::timezone())->format(Period::DATE_FORMAT),
                                ]),
                                default => __('Not ready to bill yet'),
                            }),
                        TextEntry::make('pricing_type')->label(__('Pricing'))->badge(),
                        TextEntry::make('rate')
                            ->label(__('Rate'))
                            ->visible(fn (Task $record): bool => $record->pricing_type === PricingType::Hourly)
                            ->state(fn (Task $record): string => self::money($record, $record->resolveHourlyRate()).'/h')
                            ->helperText(fn (Task $record) => $record->hourly_rate === null ? __('Inherited') : null),
                        TextEntry::make('tracked')
                            ->label(__('Tracked'))
                            ->state(fn (Task $record): string => Duration::hours($record->trackedSeconds())
                                .($record->estimated_seconds ? ' / '.Duration::hours($record->estimated_seconds) : ''))
                            ->color(fn (Task $record): ?string => $record->estimated_seconds && $record->trackedSeconds() > $record->estimated_seconds ? 'warning' : null),
                        TextEntry::make('effective_rate')
                            ->label(__('Effective rate'))
                            ->visible(fn (Task $record): bool => $record->effectiveHourlyRate($record->trackedSeconds()) !== null)
                            ->state(fn (Task $record): ?string => ($rate = $record->effectiveHourlyRate($record->trackedSeconds())) === null ? null : self::money($record, $rate).'/h'),
                    ]),
                Section::make(__('Description'))
                    ->schema([
                        TextEntry::make('description')->hiddenLabel()->markdown()->prose()->placeholder(__('No description')),
                    ]),
            ]);
    }

    private static function money(Task $task, int $amount): string
    {
        return Money::format($amount, $task->client->currency);
    }
}
