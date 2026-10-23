<?php

namespace Spiggle\VisualDashboards\Facades;

use Illuminate\Support\Facades\Facade;
use Spiggle\VisualDashboards\Services\DashletRegistry;
use Spiggle\VisualDashboards\Services\DataEngine;

/**
 * @method static \Spiggle\VisualDashboards\Contracts\DataSource resolve(string $key)
 * @method static array<int, array<string, mixed>> preview(string $sourceKey, array $filters = [], int $limit = 25)
 * @method static array<string, mixed> chart(string $sourceKey, string $type, array $filters = [])
 * @method static array<string, string> dataSourceLabels()
 *
 * @see \Spiggle\VisualDashboards\Services\DataEngine
 * @see \Spiggle\VisualDashboards\Services\DashletRegistry
 */
class VisualDashboards extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DataEngine::class;
    }

    public static function sources(): DashletRegistry
    {
        return app(DashletRegistry::class);
    }
}
