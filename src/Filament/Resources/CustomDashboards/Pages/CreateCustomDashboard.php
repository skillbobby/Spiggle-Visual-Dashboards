<?php

namespace Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Pages;

use Filament\Resources\Pages\CreateRecord;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\CustomDashboardResource;

class CreateCustomDashboard extends CreateRecord
{
    protected static string $resource = CustomDashboardResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
