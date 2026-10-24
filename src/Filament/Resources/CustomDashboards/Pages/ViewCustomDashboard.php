<?php

namespace Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\CustomDashboardResource;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Services\DashboardExporter;

class ViewCustomDashboard extends ViewRecord
{
    protected static string $resource = CustomDashboardResource::class;

    protected static ?string $title = 'Design dashboard';

    protected string $view = 'visual-dashboards::filament.pages.view-custom-dashboard';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Dashboard settings')
                ->modal(false)
                ->url(fn (): string => static::getResource()::getUrl('edit', ['record' => $this->getRecord()])),
            Action::make('openPublicUrl')
                ->label('Open share URL')
                ->icon('heroicon-o-globe-alt')
                ->url(fn (): ?string => $this->getRecord()->shareUrl())
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->getRecord()->shareUrl())),
            Action::make('exportJson')
                ->label('Export JSON')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): void {
                    /** @var CustomDashboard $dashboard */
                    $dashboard = $this->getRecord();
                    $json = app(DashboardExporter::class)->toJson($dashboard);
                    $path = 'visual-dashboard-exports/dashboard-'.$dashboard->id.'-'.now()->format('Ymd-His').'.json';
                    Storage::disk('local')->put($path, $json);

                    Notification::make()
                        ->title('Dashboard exported')
                        ->body('Saved to storage/app/'.$path)
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getHeading(): string
    {
        /** @var CustomDashboard $record */
        $record = $this->getRecord();

        return $record->displayHeading();
    }

    public function getSubheading(): ?string
    {
        return 'Visual Dashboards design canvas';
    }
}
