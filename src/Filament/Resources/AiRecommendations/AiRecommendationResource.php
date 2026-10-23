<?php

namespace Spiggle\VisualDashboards\Filament\Resources\AiRecommendations;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spiggle\VisualDashboards\Filament\Resources\AiRecommendations\Pages\ManageAiRecommendations;
use Spiggle\VisualDashboards\Filament\Support\AiSpendFormSchema;
use Spiggle\VisualDashboards\Filament\VisualDashboardsCluster;
use Spiggle\VisualDashboards\Models\AiInsight;
use UnitEnum;

class AiRecommendationResource extends Resource
{
    protected static ?string $cluster = VisualDashboardsCluster::class;

    protected static ?string $model = AiInsight::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('visual-dashboards.navigation.group', 'Dashboards');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('visual-dashboards.navigation.sort', 35) + 4;
    }

    public static function getNavigationLabel(): string
    {
        return 'AI insights';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(AiSpendFormSchema::recommendationFields());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('kind')->badge(),
                TextColumn::make('savings_label'),
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
            'index' => ManageAiRecommendations::route('/'),
        ];
    }
}
