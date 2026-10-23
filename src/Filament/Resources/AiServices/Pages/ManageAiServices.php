<?php

namespace Spiggle\VisualDashboards\Filament\Resources\AiServices\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Spiggle\VisualDashboards\Filament\Resources\AiServices\AiServiceResource;

class ManageAiServices extends ManageRecords
{
    protected static string $resource = AiServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Connect service'),
        ];
    }
}
