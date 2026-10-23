<?php

namespace Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\CustomDashboardResource;

class EditCustomDashboard extends EditRecord
{
    protected static string $resource = CustomDashboardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->modal(false)
                ->url(fn (): string => static::getResource()::getUrl('view', ['record' => $this->getRecord()])),
            DeleteAction::make(),
        ];
    }
}
