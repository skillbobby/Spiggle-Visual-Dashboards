<?php

namespace Spiggle\VisualDashboards\Filament\Resources\AiAnomalies\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Spiggle\VisualDashboards\Filament\Resources\AiAnomalies\AiAnomalyResource;

class ManageAiAnomalies extends ManageRecords
{
    protected static string $resource = AiAnomalyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
