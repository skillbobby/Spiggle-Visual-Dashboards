<?php

namespace Spiggle\VisualDashboards\Filament\Resources\AiUsageRecords\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Spiggle\VisualDashboards\Filament\Resources\AiUsageRecords\AiUsageRecordResource;

class ManageAiUsageRecords extends ManageRecords
{
    protected static string $resource = AiUsageRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Log usage'),
        ];
    }
}
