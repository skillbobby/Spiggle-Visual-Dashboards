<?php

namespace Spiggle\VisualDashboards\Filament;

use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class VisualDashboardsCluster extends Cluster
{
    protected static ?string $navigationLabel = 'Visual Dashboards';

    protected static ?string $title = 'Visual Dashboards';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $slug = 'visual-dashboards';

    protected static ?int $navigationSort = 35;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;
}
