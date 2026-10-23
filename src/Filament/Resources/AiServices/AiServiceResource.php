<?php

namespace Spiggle\VisualDashboards\Filament\Resources\AiServices;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spiggle\VisualDashboards\Filament\Resources\AiServices\Pages\ManageAiServices;
use Spiggle\VisualDashboards\Filament\Support\AiSpendFormSchema;
use Spiggle\VisualDashboards\Filament\VisualDashboardsCluster;
use Spiggle\VisualDashboards\Models\AiService;
use UnitEnum;

class AiServiceResource extends Resource
{
    protected static ?string $cluster = VisualDashboardsCluster::class;

    protected static ?string $model = AiService::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('visual-dashboards.navigation.group', 'Dashboards');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('visual-dashboards.navigation.sort', 35) + 1;
    }

    public static function getNavigationLabel(): string
    {
        return 'AI services';
    }

    public static function getModelLabel(): string
    {
        return 'AI service';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(AiSpendFormSchema::serviceFields());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('vendor_key')->badge(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAiServices::route('/'),
        ];
    }
}
