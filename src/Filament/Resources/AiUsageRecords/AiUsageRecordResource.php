<?php

namespace Spiggle\VisualDashboards\Filament\Resources\AiUsageRecords;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spiggle\VisualDashboards\Filament\Resources\AiUsageRecords\Pages\ManageAiUsageRecords;
use Spiggle\VisualDashboards\Filament\Support\AiSpendFormSchema;
use Spiggle\VisualDashboards\Filament\VisualDashboardsCluster;
use Spiggle\VisualDashboards\Models\AiUsageReport;
use UnitEnum;

class AiUsageRecordResource extends Resource
{
    protected static ?string $cluster = VisualDashboardsCluster::class;

    protected static ?string $model = AiUsageReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('visual-dashboards.navigation.group', 'Dashboards');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('visual-dashboards.navigation.sort', 35) + 2;
    }

    public static function getNavigationLabel(): string
    {
        return 'AI usage';
    }

    public static function getModelLabel(): string
    {
        return 'Usage record';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(AiSpendFormSchema::usageFields());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service.name')->searchable()->sortable(),
                TextColumn::make('reported_on')->date()->sortable(),
                TextColumn::make('tokens')->numeric()->sortable(),
                TextColumn::make('spend_usd')->money('USD')->sortable(),
            ])
            ->defaultSort('reported_on', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAiUsageRecords::route('/'),
        ];
    }
}
