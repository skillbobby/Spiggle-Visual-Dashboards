<?php

namespace Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\CustomDashboardResource;

class ListCustomDashboards extends ListRecords
{
    protected static string $resource = CustomDashboardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modal(false)
                ->url(fn (): string => static::getResource()::getUrl('create')),
        ];
    }
}
