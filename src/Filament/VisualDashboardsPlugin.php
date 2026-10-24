<?php

namespace Spiggle\VisualDashboards\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\CustomDashboardResource;

class VisualDashboardsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'spiggle-visual-dashboards';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                VisualDashboardsCluster::class,
            ])
            ->resources([
                CustomDashboardResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
