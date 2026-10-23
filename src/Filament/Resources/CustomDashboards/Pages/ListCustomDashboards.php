<?php

namespace Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Spiggle\VisualDashboards\Database\Seeders\SampleDashboardsSeeder;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\CustomDashboardResource;

class ListCustomDashboards extends ListRecords
{
    protected static string $resource = CustomDashboardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('installChartGallery')
                ->label('Install chart gallery')
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Install chart gallery')
                ->modalDescription('Creates a dashboard with one visual for each chart type, wired to your Contact Us form submissions. Removes old demo dashboards for your account.')
                ->action(function (): void {
                    $user = auth()->user();

                    if ($user === null) {
                        return;
                    }

                    $seeder = new SampleDashboardsSeeder;
                    $seeder->removeLegacyDashboards($user);
                    $dashboard = $seeder->seedChartGalleryDashboard($user);

                    if ($dashboard === null) {
                        Notification::make()
                            ->title('Could not create chart gallery')
                            ->body('Form submissions must be available and at least one form must exist.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Chart gallery ready')
                        ->body($dashboard->dashlets()->count().' chart types on "'.$dashboard->name.'".')
                        ->success()
                        ->send();

                    $this->redirect(CustomDashboardResource::getUrl('view', ['record' => $dashboard]));
                }),
            CreateAction::make()
                ->modal(false)
                ->url(fn (): string => static::getResource()::getUrl('create')),
        ];
    }
}
