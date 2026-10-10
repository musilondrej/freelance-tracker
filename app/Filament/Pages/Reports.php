<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Enums\PricingType;
use App\Enums\ReportRange;
use App\Filament\Actions\ExportActions;
use App\Filament\Widgets\Reports\ReportBreakdown;
use App\Filament\Widgets\Reports\ReportRates;
use App\Filament\Widgets\Reports\ReportStats;
use App\Models\Builders\ProjectBuilder;
use App\Models\Client;
use App\Models\Project;
use App\Reporting\CsvExport;
use App\Reporting\ReportFilters;
use App\Support\Period;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Time and financial reports for a period. All money comes from historical snapshots and is
 * reported per currency. Filters live in the URL and the session; the widgets read them.
 */
class Reports extends Page
{
    use HasFiltersForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('Reports');
    }

    public function getTitle(): string
    {
        return __('Reports');
    }

    public function getSubheading(): string
    {
        $period = ReportFilters::from($this->filters)->period;

        return __('Period').': '.CarbonImmutable::parse($period->fromDate())->format(Period::DATE_FORMAT)
            .' – '.CarbonImmutable::parse($period->toDate())->format(Period::DATE_FORMAT);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 3, 'xl' => 5])
            ->components([
                Select::make('range')->label(__('Period'))->options(ReportRange::class)
                    ->default(ReportRange::ThisMonth)->selectablePlaceholder(false),
                DatePicker::make('from')->label(__('From'))
                    ->visible(fn (Get $get): bool => self::isCustom($get('range'))),
                DatePicker::make('to')->label(__('Until'))
                    ->visible(fn (Get $get): bool => self::isCustom($get('range'))),
                Select::make('client')->label(__('Client'))->placeholder(__('All clients'))
                    ->options(fn (): array => Client::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->afterStateUpdated(fn (Set $set) => $set('project', null)),
                Select::make('project')->label(__('Project'))->placeholder(__('All projects'))
                    ->options(fn (Get $get): array => Project::query()
                        ->when(filled($get('client')), fn (ProjectBuilder $query) => $query->where('client_id', $get('client')))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
                Select::make('pricing')->label(__('Pricing'))->placeholder(__('All pricing types'))->options(PricingType::class),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('filtersForm'),
            ...$this->getWidgetsSchemaComponents([
                ReportStats::class,
                ReportBreakdown::make(['dimension' => 'client_id']),
                ReportBreakdown::make(['dimension' => 'project_id']),
                ReportRates::class,
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    public function getWidgetData(): array
    {
        return ['pageFilters' => $this->filters];
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportActions::make(fn (): StreamedResponse => $this->export()),
        ];
    }

    private function export(): StreamedResponse
    {
        $report = ReportFilters::from($this->filters);
        $rows = [];

        foreach ([__('Client') => $report->breakdown('client_id'), __('Project') => $report->breakdown('project_id')] as $type => $lines) {
            foreach ($lines as $line) {
                $t = $line->totals;
                $rows[] = [
                    $type, $line->name, $t->currency->value,
                    CsvExport::hours($t->trackedSeconds()), CsvExport::hours($t->billableSeconds),
                    CsvExport::amount($t->workValue), CsvExport::amount($t->billed),
                    CsvExport::amount($t->unbilled),
                ];
            }
        }

        return CsvExport::download(
            'financni-prehled-'.$report->period->fromDate().'-'.$report->period->toDate().'.csv',
            [__('Type'), __('Name'), __('Currency'), __('Hours'), __('Billable hours'), __('Work value'), __('Invoiced'), __('To invoice (now)')],
            $rows,
        );
    }

    private static function isCustom(mixed $range): bool
    {
        return ($range instanceof ReportRange ? $range : ReportRange::tryFrom((string) $range)) === ReportRange::Custom;
    }
}
