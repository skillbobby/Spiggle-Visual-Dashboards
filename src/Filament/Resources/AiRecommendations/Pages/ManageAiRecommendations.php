<?php

namespace Spiggle\VisualDashboards\Filament\Resources\AiRecommendations\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Spiggle\VisualDashboards\Filament\Resources\AiRecommendations\AiRecommendationResource;

class ManageAiRecommendations extends ManageRecords
{
    protected static string $resource = AiRecommendationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
