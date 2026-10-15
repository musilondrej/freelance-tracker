<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Enums\PricingType;
use App\Filament\Actions\StatementActions;
use App\Filament\Actions\TaskActions;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Widgets\FinanceOverview;
use App\Livewire\Concerns\RefreshesOnTimerChange;
use App\Models\Task;
use App\Reporting\UnbilledWork;
use App\Support\Duration;
use App\Support\Money;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Work that is not on any statement yet, per task. Select tasks of one client to create a draft.
 */
class Unbilled extends Page implements HasTable
{
    use InteractsWithTable;
    use RefreshesOnTimerChange;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'unbilled';

    public static function getNavigationLabel(): string
    {
        return __('To invoice');
    }

    public function getTitle(): string|Htmlable
    {
        return __('To invoice');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = UnbilledWork::tasks()->count();

        return $count > 0 ? (string) $count : null;
    }

    protected function getHeaderWidgets(): array
    {
        return [FinanceOverview::make(['onlyUnbilled' => true])];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(UnbilledWork::tasks()->with(['client', 'project']))
            ->defaultSort('client_id')
            ->columns([
                TextColumn::make('title')
                    ->label(__('Task'))
                    ->searchable()
                    ->wrap()
                    ->weight('medium')
                    ->url(fn (Task $record): string => TaskResource::getUrl('view', ['record' => $record])),
                TextColumn::make('client.name')->label(__('Client'))->sortable()
                    ->description(fn (Task $record): ?string => $record->project?->name),
                TextColumn::make('unbilled_seconds')
                    ->label(__('Unbilled time'))
                    ->formatStateUsing(fn (mixed $state): ?string => (int) $state > 0 ? Duration::format((int) $state).' h' : null)
                    ->alignEnd(),
                TextColumn::make('unbilled_since')
                    ->label(__('Oldest work'))
                    ->date(),
                TextColumn::make('unbilled_amount')
                    ->label(__('To invoice'))
                    ->state(fn (Task $record): int => UnbilledWork::amount($record))
                    ->formatStateUsing(fn (int $state, Task $record): string => Money::format($state, $record->client->currency))
                    ->description(fn (Task $record): string => $record->pricing_type->getLabel())
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->filters([
                SelectFilter::make('client')->label(__('Client'))->relationship('client', 'name')->searchable()->preload(),
                SelectFilter::make('pricing_type')->label(__('Pricing'))->options([
                    PricingType::Hourly->value => PricingType::Hourly->getLabel(),
                    PricingType::Fixed->value => PricingType::Fixed->getLabel(),
                ]),
            ])
            ->recordActions([
                TaskActions::unmarkReadyToBill()->iconButton(),
            ])
            ->toolbarActions([
                StatementActions::createFromTasks(),
            ])
            ->emptyStateHeading(__('Everything is billed'))
            ->emptyStateDescription(__('Tracked hourly work and fixed-price tasks marked as ready to bill appear here.'))
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }
}
