<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Actions\ArchiveActions;
use App\Filament\Actions\TaskActions;
use App\Filament\Resources\Tasks\TaskResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    /**
     * Two columns like a Jira issue: the relation tabs in the wide column, the infolist as a side panel.
     * On narrow screens the panel moves below, as in Jira.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 1, 'xl' => 3])
            ->components([
                Group::make([$this->getRelationManagersContentComponent()])->columnSpan(['default' => 1, 'xl' => 2]),
                $this->getInfolistContentComponent(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            TaskActions::startTimer(),
            TaskActions::markReadyToBill(),
            EditAction::make(),
            ActionGroup::make([
                TaskActions::unmarkReadyToBill(),
                TaskActions::recalculateTimeEntries(),
                ...ArchiveActions::make(),
            ]),
        ];
    }
}
