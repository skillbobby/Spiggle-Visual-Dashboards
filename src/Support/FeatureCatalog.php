<?php

namespace Spiggle\VisualDashboards\Support;

class FeatureCatalog
{
    /**
     * @var list<string>
     */
    public const ALL_TYPES = [
        'hero',
        'stat_card',
        'insight_list',
        'table',
        'line',
        'bar',
        'column',
        'stacked_column',
        'scatter',
        'pie',
        'donut',
        'funnel',
        'radialbar',
        'radar',
        'container',
    ];

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return DashletTypes::labels();
    }

    /**
     * @return list<string>
     */
    public static function allowedTypes(): array
    {
        return self::ALL_TYPES;
    }

    public static function typeTitle(string $type): string
    {
        $labels = DashletTypes::labels();

        return $labels[$type] ?? str_replace('_', ' ', $type);
    }

    public static function typeHelperText(string $type): ?string
    {
        return null;
    }

    public static function assertAllowed(string $type): void
    {
        if (! in_array($type, self::allowedTypes(), true)) {
            throw new \InvalidArgumentException("Unknown visual type [{$type}].");
        }
    }
}
