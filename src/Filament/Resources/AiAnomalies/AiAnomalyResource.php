<?php

namespace Spiggle\VisualDashboards\Filament\Resources\AiAnomalies;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spiggle\VisualDashboards\Filament\Resources\AiAnomalies\Pages\ManageAiAnomalies;
use Spiggle\VisualDashboards\Filament\Support\AiSpendFormSchema;
use Spiggle\VisualDashboards\Filament\VisualDashboardsCluster;
use Spiggle\VisualDashboards\Models\AiAnomaly;
use UnitEnum;

class AiAnomalyResource extends Resource
{
    protected static ?string $cluster = VisualDashboardsCluster::class;

    protected static ?string $model = AiAnomaly::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('visual-dashboards.navigation.group', 'Dashboards');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('visual-dashboards.navigation.sort', 35) + 3;
    }

    public static function getNavigationLabel(): string
    {
        return 'AI anomalies';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(AiSpendFormSchema::anomalyFields());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('service.name'),
                TextColumn::make('detected_on')->date()->sortable(),
                TextColumn::make('amount_usd')->money('USD'),
            ])
            ->defaultSort('detected_on', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAiAnomalies::route('/'),
        ];
    }
}
