<?php

namespace Spiggle\VisualDashboards\Support;

class DashletTypes
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'hero' => 'Hero / Greeting',
            'stat_card' => 'KPI / Stat card',
            'insight_list' => 'Insight list',
            'table' => 'Pivot / Table',
            'line' => 'Line chart',
            'bar' => 'Bar chart',
            'column' => 'Column chart',
            'stacked_column' => 'Stacked column',
            'scatter' => 'Scatter (X / Y)',
            'pie' => 'Pie chart',
            'donut' => 'Donut chart',
            'funnel' => 'Funnel chart',
            'radialbar' => 'Radial gauge',
            'treemap' => 'Treemap',
            'radar' => 'Radar chart',
            'container' => 'Container group',
        ];
    }

    public static function chartJsType(string $type): string
    {
        return match ($type) {
            'column', 'stacked_column' => 'bar',
            'scatter' => 'scatter',
            'donut' => 'doughnut',
            'radialbar' => 'doughnut',
            default => in_array($type, ['stat_card', 'container', 'hero', 'insight_list'], true) ? 'bar' : $type,
        };
    }
}
