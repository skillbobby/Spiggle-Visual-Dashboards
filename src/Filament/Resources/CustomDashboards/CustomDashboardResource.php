<?php

namespace Spiggle\VisualDashboards\Filament\Resources\CustomDashboards;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Pages\CreateCustomDashboard;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Pages\EditCustomDashboard;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Pages\ListCustomDashboards;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Pages\ViewCustomDashboard;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Schemas\CustomDashboardForm;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Tables\CustomDashboardsTable;
use Spiggle\VisualDashboards\Filament\VisualDashboardsCluster;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Support\DashboardAccess;
use UnitEnum;

class CustomDashboardResource extends Resource
{
    protected static ?string $cluster = VisualDashboardsCluster::class;

    protected static ?string $model = CustomDashboard::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('visual-dashboards.navigation.group', 'Dashboards');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('visual-dashboards.navigation.sort', 35);
    }

    public static function getNavigationLabel(): string
    {
        return (string) config('visual-dashboards.navigation.label', 'Visual Dashboards');
    }

    public static function getModelLabel(): string
    {
        return 'Visual Dashboard';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Visual Dashboards';
    }

    public static function form(Schema $schema): Schema
    {
        return CustomDashboardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomDashboardsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return DashboardAccess::scopeVisibleTo(parent::getEloquentQuery(), auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomDashboards::route('/'),
            'create' => CreateCustomDashboard::route('/create'),
            'view' => ViewCustomDashboard::route('/{record}'),
            'edit' => EditCustomDashboard::route('/{record}/edit'),
        ];
    }
}
