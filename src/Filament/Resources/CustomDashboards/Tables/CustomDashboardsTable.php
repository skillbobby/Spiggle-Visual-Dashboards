<?php

namespace Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\CustomDashboardResource;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Services\DashboardExporter;
use Spiggle\VisualDashboards\Services\DashboardImporter;

class CustomDashboardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (CustomDashboard $record): string => CustomDashboardResource::getUrl('view', ['record' => $record])),
                TextColumn::make('slug')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Owner')
                    ->sortable(),
                IconColumn::make('is_published')
                    ->boolean()
                    ->label('Public link'),
                TextColumn::make('viewer_roles')
                    ->label('Viewer roles')
                    ->badge()
                    ->formatStateUsing(function (mixed $state): string|array {
                        if (! is_array($state) || $state === []) {
                            return [];
                        }

                        return array_values(array_filter(array_map(
                            static fn (mixed $role): string => trim((string) $role),
                            $state
                        )));
                    })
                    ->placeholder('All viewers')
                    ->toggleable(),
                TextColumn::make('public_url')
                    ->label('Share URL')
                    ->state(fn (CustomDashboard $record): string => $record->shareUrl() ?: '—')
                    ->url(fn (CustomDashboard $record): ?string => $record->shareUrl())
                    ->openUrlInNewTab()
                    ->toggleable()
                    ->limit(40),
                TextColumn::make('dashlets_count')
                    ->counts('dashlets')
                    ->label('Visuals'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modal(false)
                    ->url(fn (CustomDashboard $record): string => CustomDashboardResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->modal(false)
                    ->url(fn (CustomDashboard $record): string => CustomDashboardResource::getUrl('edit', ['record' => $record])),
                Action::make('exportJson')
                    ->label('Export JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (CustomDashboard $record): void {
                        $json = app(DashboardExporter::class)->toJson($record);
                        $path = 'dashlet-exports/dashboard-'.$record->id.'-'.now()->format('Ymd-His').'.json';
                        Storage::disk('local')->put($path, $json);

                        Notification::make()
                            ->title('Dashboard exported')
                            ->body('Saved to storage/app/'.$path)
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                Action::make('importJson')
                    ->label('Import JSON')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        FileUpload::make('json_file')
                            ->label('Dashboard JSON')
                            ->acceptedFileTypes(['application/json', 'text/json'])
                            ->required(),
                        TextInput::make('name')
                            ->label('Dashboard name')
                            ->placeholder('Leave blank to use JSON name'),
                        Toggle::make('replace')
                            ->label('Replace existing dashboard with the same name')
                            ->inline(false),
                    ])
                    ->action(function (array $data): void {
                        $path = Storage::disk('local')->path((string) $data['json_file']);
                        $payload = app(DashboardImporter::class)->decodeJson((string) file_get_contents($path));
                        $userId = (int) (auth()->id() ?: 1);
                        $dashboard = app(DashboardImporter::class)->import(
                            $payload,
                            $userId,
                            (bool) ($data['replace'] ?? false),
                            filled($data['name'] ?? null) ? (string) $data['name'] : null,
                        );

                        Notification::make()
                            ->title('Dashboard imported')
                            ->body($dashboard->name.' is ready with '.$dashboard->dashlets()->count().' visual(s).')
                            ->success()
                            ->send();
                    }),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No visual dashboards yet')
            ->emptyStateDescription('Create a dashboard, then design it on the canvas. Insert a KPI card from the chart gallery to get started.')
            ->emptyStateIcon('heroicon-o-chart-bar-square');
    }
}
