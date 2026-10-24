<?php

namespace Spiggle\VisualDashboards\Filament;

use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\CustomDashboardResource;
use UnitEnum;

class VisualDashboardsCluster extends Cluster
{
    protected static ?string $navigationLabel = 'Visual Dashboards';

    protected static ?string $title = 'Visual Dashboards';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $slug = 'visual-dashboards';

    protected static ?int $navigationSort = 35;

    protected static bool $shouldRegisterSubNavigation = false;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = config('visual-dashboards.navigation.group');

        return is_string($group) || $group instanceof UnitEnum ? $group : null;
    }

    public static function registerNavigationItems(): void
    {
        parent::registerNavigationItems();

        if (! static::shouldRegisterNavigation() || ! static::canAccess()) {
            return;
        }

        $items = static::childNavigationItems();

        if ($items === []) {
            return;
        }

        Filament::getCurrentOrDefaultPanel()
            ->navigationItems($items);
    }

    public function mount(): void
    {
        redirect(CustomDashboardResource::getUrl('index'));
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    /**
     * @return array<int, NavigationItem>
     */
    protected static function childNavigationItems(): array
    {
        $items = [];

        foreach (static::getClusteredComponents() as $component) {
            if (! $component::shouldRegisterNavigation() || ! $component::canAccess()) {
                continue;
            }

            foreach ($component::getNavigationItems() as $item) {
                $items[] = $item
                    ->parentItem(static::class)
                    ->group(static::getNavigationGroup());
            }
        }

        return $items;
    }
}
