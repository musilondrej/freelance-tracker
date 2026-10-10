<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Enums\Currency;
use App\Enums\PricingType;
use App\Models\Builders\ArchivableBuilder;
use App\Models\Builders\ProjectBuilder;
use App\Models\Client;
use App\Models\Project;
use App\Support\Money;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

final class WorkFields
{
    /**
     * Linked client and project selects. The project list follows the client, and picking a project sets its client.
     *
     * @param  Closure(?Model): bool  $locked
     * @return array{Select, Select}
     */
    public static function clientAndProject(Closure $locked, string $lockedMessage): array
    {
        return [
            Select::make('client_id')
                ->label(__('Client'))
                ->relationship('client', 'name', fn (ArchivableBuilder $query) => $query->active())
                ->default(fn (): ?int => request()->integer('client') ?: self::project(request()->integer('project'))?->client_id)
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                    $project = self::project($get('project_id'));

                    if ($project instanceof Project && $project->client_id !== (int) $state) {
                        $set('project_id', null);
                    }
                })
                ->disabled(fn (?Model $record): bool => $locked($record))
                ->helperText(fn (?Model $record) => $locked($record) ? $lockedMessage : null),
            Select::make('project_id')
                ->label(__('Project'))
                ->options(fn (Get $get): array => Project::query()
                    ->active()
                    ->when($get('client_id'), fn (ProjectBuilder $query, mixed $clientId) => $query->where('client_id', $clientId))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->default(fn (): ?int => request()->integer('project') ?: null)
                ->searchable()
                ->live()
                ->placeholder(__('No project'))
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    if (($project = self::project($state)) instanceof Project) {
                        $set('client_id', $project->client_id);
                    }
                })
                ->disabled(fn (?Model $record): bool => $locked($record)),
        ];
    }

    public static function pricing(Get $get): ?PricingType
    {
        $state = $get('pricing_type');

        return $state instanceof PricingType ? $state : PricingType::tryFrom((string) $state);
    }

    public static function currency(Get $get): string
    {
        return self::client($get('client_id'))?->currency->value ?? '';
    }

    /** "CZK/h" after a rate input, from a currency field's state (enum or code). */
    public static function rateSuffix(mixed $currency): string
    {
        $code = $currency instanceof Currency ? $currency->value : (string) $currency;

        return $code === '' ? '/h' : $code.'/h';
    }

    /** Rate the task inherits from the project or client, formatted for a placeholder. */
    public static function inheritedRate(Get $get): ?string
    {
        $client = self::client($get('client_id'));

        if (! $client instanceof Client) {
            return null;
        }

        return Money::toDecimal(self::project($get('project_id'))?->resolveHourlyRate() ?? $client->hourly_rate);
    }

    private static function client(mixed $clientId): ?Client
    {
        return is_numeric($clientId) && (int) $clientId > 0 ? Client::query()->find((int) $clientId) : null;
    }

    private static function project(mixed $projectId): ?Project
    {
        return is_numeric($projectId) && (int) $projectId > 0 ? Project::query()->find((int) $projectId) : null;
    }
}
