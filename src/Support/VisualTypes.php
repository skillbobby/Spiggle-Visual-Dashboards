<?php

namespace Spiggle\VisualDashboards\Support;

class VisualTypes
{
    /**
     * Chart gallery entries for the Visual Dashboards insert panel.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function gallery(): array
    {
        return [
            'stat_card' => [
                'type' => 'stat_card',
                'label' => 'KPI Card',
                'description' => 'A single metric with optional trend and sparkline.',
                'ideal_usage' => 'Key performance indicators & totals',
                'icon' => 'kpi',
                'enabled' => true,
                'default_w' => 3,
                'default_h' => 3,
            ],
            'table' => [
                'type' => 'table',
                'label' => 'Data Table',
                'description' => 'Raw structured data in rows and columns.',
                'ideal_usage' => 'Exact metrics & drill-down lists',
                'icon' => 'table',
                'enabled' => true,
                'default_w' => 6,
                'default_h' => 4,
            ],
            'line' => [
                'type' => 'line',
                'label' => 'Line Chart',
                'description' => 'Visualize trends over time.',
                'ideal_usage' => 'Continuous trends & time series',
                'icon' => 'line',
                'enabled' => true,
                'default_w' => 6,
                'default_h' => 4,
            ],
            'bar' => [
                'type' => 'bar',
                'label' => 'Bar Chart',
                'description' => 'Compare values across categories.',
                'ideal_usage' => 'Categorical values & rankings',
                'icon' => 'bar',
                'enabled' => true,
                'default_w' => 6,
                'default_h' => 5,
            ],
            'column' => [
                'type' => 'column',
                'label' => 'Column Chart',
                'description' => 'Vertical bars for category comparison.',
                'ideal_usage' => 'Side-by-side category comparison',
                'icon' => 'column',
                'enabled' => true,
                'default_w' => 6,
                'default_h' => 4,
            ],
            'pie' => [
                'type' => 'pie',
                'label' => 'Pie Chart',
                'description' => 'Show proportions of a whole.',
                'ideal_usage' => 'Static proportions of a whole',
                'icon' => 'pie',
                'enabled' => true,
                'default_w' => 4,
                'default_h' => 4,
            ],
            'donut' => [
                'type' => 'donut',
                'label' => 'Donut Chart',
                'description' => 'Proportions with a center hole.',
                'ideal_usage' => 'Proportions with center label',
                'icon' => 'donut',
                'enabled' => true,
                'default_w' => 4,
                'default_h' => 4,
            ],
            'stacked_column' => [
                'type' => 'stacked_column',
                'label' => 'Stacked Area',
                'description' => 'Cumulative totals and components.',
                'ideal_usage' => 'Cumulative totals & components',
                'icon' => 'stacked',
                'enabled' => true,
                'default_w' => 8,
                'default_h' => 4,
            ],
            'funnel' => [
                'type' => 'funnel',
                'label' => 'Funnel Chart',
                'description' => 'Multi-stage conversion and drop-offs.',
                'ideal_usage' => 'Conversion funnels',
                'icon' => 'funnel',
                'enabled' => true,
                'default_w' => 6,
                'default_h' => 4,
            ],
            'radialbar' => [
                'type' => 'radialbar',
                'label' => 'Radial Gauge',
                'description' => 'Single-value gauge with arc, bands, and semi-circle styles.',
                'ideal_usage' => 'KPIs, capacity & progress',
                'icon' => 'radialbar',
                'enabled' => true,
                'default_w' => 4,
                'default_h' => 4,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(static::gallery())
            ->mapWithKeys(fn (array $entry): array => [$entry['type'] => $entry['label']])
            ->all();
    }

    public static function defaultSize(string $type): array
    {
        $entry = static::gallery()[$type] ?? null;

        return [
            'w' => (int) ($entry['default_w'] ?? 4),
            'h' => (int) ($entry['default_h'] ?? 4),
        ];
    }

    /**
     * Chart-type-specific field slot labels and wiring for the data-source wizard.
     *
     * Slot `wire` values map to Livewire properties on VisualDashboardCanvas:
     * - editSourceField → columns[0] / measure
     * - editGroupBy → group_by[0] / primary dimension
     * - editBreakdown → mapping.series / secondary dimension or color breakdown
     *
     * @return array<string, mixed>
     */
    public static function fieldBlueprint(string $type): array
    {
        $entry = static::gallery()[$type] ?? null;
        $label = $entry['label'] ?? 'Visual';
        $icon = $entry['icon'] ?? 'default';

        $dimension = static fn (
            string $slotLabel,
            string $description,
            bool $required = false,
            bool $optional = false,
        ): array => [
            'id' => 'dimension',
            'wire' => 'editGroupBy',
            'label' => $slotLabel,
            'badge' => '',
            'role' => 'dimension',
            'description' => $description,
            'required' => $required,
            'optional' => $optional,
            'placeholder' => $optional ? 'None (single series)' : 'Select a field…',
        ];

        $measure = static fn (
            string $slotLabel,
            string $description,
            string $badge = '',
        ): array => [
            'id' => 'measure',
            'wire' => 'editSourceField',
            'label' => $slotLabel,
            'badge' => $badge,
            'role' => 'measure',
            'description' => $description,
            'required' => true,
            'optional' => false,
            'placeholder' => 'Select a field…',
        ];

        $breakdown = static fn (
            string $slotLabel,
            string $description,
            bool $required = false,
        ): array => [
            'id' => 'breakdown',
            'wire' => 'editBreakdown',
            'label' => $slotLabel,
            'badge' => '',
            'role' => 'breakdown',
            'description' => $description,
            'required' => $required,
            'optional' => ! $required,
            'placeholder' => $required ? 'Select a field…' : 'None (single series)',
        ];

        $aggregation = static fn (
            string $slotLabel = 'Measure aggregation',
            string $description = 'How numeric values are combined for each category.',
        ): array => [
            'visible' => true,
            'label' => $slotLabel,
            'description' => $description,
        ];

        return match ($type) {
            'stat_card' => [
                'type' => $type,
                'label' => $label,
                'icon' => $icon,
                'intro' => 'Summarize rows into one headline number.',
                'slots' => [
                    $measure('KPI value', 'The metric shown as the large number on the card.'),
                ],
                'aggregation' => $aggregation('How to summarize', 'Count rows, or sum / average a numeric field.'),
                'gauge_thresholds' => false,
            ],
            'table' => [
                'type' => $type,
                'label' => $label,
                'icon' => $icon,
                'intro' => 'Choose which source columns appear in the table. Leave none selected to show all columns.',
                'slots' => [],
                'aggregation' => ['visible' => false],
                'gauge_thresholds' => false,
            ],
            'bar' => [
                'type' => $type,
                'label' => $label,
                'icon' => $icon,
                'intro' => 'Each bar is a category. Bar length is the aggregated value.',
                'slots' => [
                    $dimension('Category (bars)', 'What each bar represents — status, name, date, and so on.', required: true),
                    $measure('Value (bar length)', 'Numeric field, or Row count to tally rows per category.'),
                    $breakdown('Split bars by', 'Optional. Color-groups bars (for example by action type).'),
                ],
                'aggregation' => $aggregation('How to summarize', 'How values are combined for each bar.'),
                'gauge_thresholds' => false,
            ],
            'column' => [
                'type' => $type,
                'label' => $label,
                'icon' => $icon,
                'intro' => 'Each column is a category. Column height is the aggregated value.',
                'slots' => [
                    $dimension('Category (X-axis)', 'What each column represents.', required: true),
                    $measure('Value (height)', 'Numeric field, or Row count to tally rows per category.'),
                    $breakdown('Split columns by', 'Optional. Color-groups columns into a series.'),
                ],
                'aggregation' => $aggregation('How to summarize', 'How values are combined for each column.'),
                'gauge_thresholds' => false,
            ],
            'pie', 'donut' => [
                'type' => $type,
                'label' => $label,
                'icon' => $icon,
                'intro' => 'Each slice is a category. Slice size is the aggregated value.',
                'slots' => [
                    $dimension('Slice label', 'What each slice represents (status, type, form, …).', required: true),
                    $measure('Slice size', 'Numeric values, or Row count for volume per slice.'),
                ],
                'aggregation' => $aggregation('How to summarize', 'How slice sizes are calculated from matching rows.'),
                'gauge_thresholds' => false,
            ],
            'line' => [
                'type' => $type,
                'label' => $label,
                'icon' => $icon,
                'intro' => 'Plot a value along an ordered axis — usually a date.',
                'slots' => [
                    $dimension('X-axis / dimension', 'Date or ordered field plotted left to right.', optional: true),
                    $measure('Y-axis / measure', 'Numeric values plotted vertically, or Row count.'),
                    $breakdown('Series / group by', 'Optional. Draw a separate line for each value (for example by action).'),
                ],
                'aggregation' => $aggregation('How to summarize', 'How values are combined at each X-axis point.'),
                'gauge_thresholds' => false,
            ],
            'stacked_column' => [
                'type' => $type,
                'label' => $label,
                'icon' => $icon,
                'intro' => 'Stack values over time. Each color is a category in the stack.',
                'slots' => [
                    $dimension('X-axis', 'Date or ordered field along the horizontal axis.', optional: true),
                    $measure('Stacked value', 'Numeric values stacked vertically, or Row count.'),
                    $breakdown('Stack by', 'Field that defines each colored stack segment.', required: true),
                ],
                'aggregation' => $aggregation('How to summarize', 'How values are combined in each stack segment.'),
                'gauge_thresholds' => false,
            ],
            'funnel' => [
                'type' => $type,
                'label' => $label,
                'icon' => $icon,
                'intro' => 'Each stage is a step. Stage size is the volume that reached it.',
                'slots' => [
                    $dimension('Funnel stage', 'Labels for each step in the funnel.', required: true),
                    $measure('Stage volume', 'Numeric values that size each stage, or Row count.'),
                ],
                'aggregation' => $aggregation('How to summarize', 'How stage volumes are calculated.'),
                'gauge_thresholds' => false,
            ],
            'radialbar' => [
                'type' => $type,
                'label' => $label,
                'icon' => $icon,
                'intro' => 'Summarize rows into one reading on a gauge.',
                'slots' => [
                    $measure('Gauge value', 'The metric shown on the arc.'),
                ],
                'aggregation' => $aggregation('How to summarize', 'How the gauge reading is calculated from rows.'),
                'gauge_thresholds' => false,
            ],
            default => [
                'type' => $type,
                'label' => $label,
                'icon' => $icon,
                'intro' => 'Choose the value to plot and how to group it.',
                'slots' => [
                    $measure('Value', 'Primary numeric or categorical field.'),
                    $dimension('Group by', 'Optional field to group results.', optional: true),
                ],
                'aggregation' => $aggregation(),
                'gauge_thresholds' => false,
            ],
        };
    }

    /**
     * Axis / mapping labels for a chart type (used by the canvas and the legacy wizard).
     *
     * @return array{x: string, y: string, series: string, x_help: ?string, y_help: ?string, series_help: ?string, show_series: bool}
     */
    public static function mappingLabels(string $type): array
    {
        $blueprint = static::fieldBlueprint($type);
        $labels = [
            'x' => 'Category',
            'y' => 'Value',
            'series' => 'Series / group by',
            'x_help' => null,
            'y_help' => null,
            'series_help' => null,
            'show_series' => true,
        ];

        foreach ($blueprint['slots'] ?? [] as $slot) {
            $role = (string) ($slot['role'] ?? '');
            $label = (string) ($slot['label'] ?? '');
            $help = (string) ($slot['description'] ?? '');

            if ($role === 'dimension') {
                $labels['x'] = $label;
                $labels['x_help'] = $help !== '' ? $help : null;
            }

            if ($role === 'measure') {
                $labels['y'] = $label;
                $labels['y_help'] = $help !== '' ? $help : null;
            }

            if ($role === 'breakdown') {
                $labels['series'] = $label;
                $labels['series_help'] = $help !== '' ? $help : null;
            }
        }

        $labels['show_series'] = collect($blueprint['slots'] ?? [])->contains(
            fn (array $slot): bool => ($slot['role'] ?? '') === 'breakdown'
        );

        return $labels;
    }

    /**
     * Whether a persisted dashlet config has enough wiring to render on the canvas.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $rawConfig
     */
    public static function isPersistedConfigReady(string $type, array $config, array $rawConfig = []): bool
    {
        if (blank($config['data_source'] ?? '') && blank($config['table_name'] ?? '')) {
            return false;
        }

        if (in_array($type, ['hero', 'insight_list'], true)) {
            return true;
        }

        $query = VisualQueryBuilder::normalize($config);

        if (! $query['applied']) {
            return false;
        }

        $blueprintConfig = $rawConfig !== []
            ? array_replace_recursive($rawConfig, $config)
            : $config;

        return static::configSatisfiesBlueprint($type, $blueprintConfig);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function configSatisfiesBlueprint(string $type, array $config): bool
    {
        if ($type === 'table') {
            return true;
        }

        $blueprint = static::fieldBlueprint($type);
        $values = static::configValuesFromPersistedConfig($type, $config);
        $periodGrains = data_get($config, 'visual.period_grains');

        foreach ($blueprint['slots'] as $slot) {
            if (($slot['required'] ?? false) && blank($values[$slot['wire']] ?? '')) {
                return false;
            }
        }

        foreach ($blueprint['slots'] as $slot) {
            if (($slot['role'] ?? '') === 'measure' && blank($values[$slot['wire']] ?? '')) {
                return false;
            }
        }

        if (
            in_array($type, ['bar', 'column', 'pie', 'donut', 'funnel'], true)
            && blank($values['editGroupBy'] ?? '')
        ) {
            return false;
        }

        if (
            in_array($type, ['line', 'stacked_column', 'area'], true)
            && blank($values['editGroupBy'] ?? '')
            && ! (is_array($periodGrains) && $periodGrains !== [])
            && ! (bool) data_get($config, 'visual.period_filter')
        ) {
            return false;
        }

        return true;
    }

    /**
     * Map persisted config keys to wizard Livewire property names.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    public static function configValuesFromPersistedConfig(string $type, array $config): array
    {
        if ($type === 'radialbar') {
            return [
                'editSourceField' => static::measureFieldFromConfig($config),
                'editGroupBy' => '',
                'editBreakdown' => '',
            ];
        }

        return [
            'editSourceField' => static::measureFieldFromConfig($config),
            'editGroupBy' => (string) (($config['group_by'][0] ?? '') ?: ''),
            'editBreakdown' => (string) (data_get($config, 'mapping.series') ?: ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function measureFieldFromConfig(array $config): string
    {
        $aggField = (string) (data_get($config, 'aggregations.0.field') ?: '');

        if ($aggField !== '' && $aggField !== '*') {
            return $aggField;
        }

        if ($aggField === '*') {
            return VirtualMeasures::ROW_COUNT;
        }

        if (VirtualMeasures::isVirtual($aggField)) {
            return VirtualMeasures::normalizeField($aggField);
        }

        return (string) (($config['columns'][0] ?? '') ?: '');
    }

    /**
     * Inline SVG preview for chart gallery cards.
     */
    public static function iconSvg(string $icon): string
    {
        return match ($icon) {
            'kpi' => '<svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="5" y="8" width="38" height="22" rx="3" stroke="currentColor" stroke-width="1.75"/><path d="M14 22V16M24 22V12M34 22V15" stroke="currentColor" stroke-width="2.25" stroke-linecap="round"/></svg>',
            'table' => '<svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="5" y="7" width="38" height="22" rx="2" stroke="currentColor" stroke-width="1.75"/><path d="M5 14h38M5 21h38M19 7v22M31 7v22" stroke="currentColor" stroke-width="1.25"/></svg>',
            'line' => '<svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M8 28h32" stroke="currentColor" stroke-width="1.25" opacity=".35"/><polyline points="8,24 16,16 23,20 31,10 40,14" stroke="currentColor" stroke-width="2.25" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'bar' => '<svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M8 28h32" stroke="currentColor" stroke-width="1.25" opacity=".35"/><rect x="11" y="18" width="5" height="10" rx="1" fill="currentColor"/><rect x="21" y="12" width="5" height="16" rx="1" fill="currentColor"/><rect x="31" y="16" width="5" height="12" rx="1" fill="currentColor"/></svg>',
            'column' => '<svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M8 28h32" stroke="currentColor" stroke-width="1.25" opacity=".35"/><rect x="12" y="14" width="7" height="14" rx="1" fill="currentColor"/><rect x="22" y="9" width="7" height="19" rx="1" fill="currentColor"/><rect x="32" y="17" width="7" height="11" rx="1" fill="currentColor"/></svg>',
            'pie' => '<svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M24 6A12 12 0 1 1 12 18" stroke="currentColor" stroke-width="1.75" fill="none"/><path d="M24 6v12l10.4 6A12 12 0 0 0 24 6z" fill="currentColor"/></svg>',
            'donut' => '<svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="24" cy="18" r="11" stroke="currentColor" stroke-width="5" opacity=".22"/><path d="M24 7a11 11 0 0 1 9.5 5.5" stroke="currentColor" stroke-width="5" stroke-linecap="round" fill="none"/><path d="M33.5 12.5A11 11 0 1 1 24 7" stroke="currentColor" stroke-width="5" stroke-linecap="round" fill="none" opacity=".55"/></svg>',
            'stacked' => '<svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M8 28h32" stroke="currentColor" stroke-width="1.25" opacity=".35"/><rect x="12" y="18" width="7" height="10" rx="1" fill="currentColor" opacity=".35"/><rect x="12" y="11" width="7" height="7" rx="1" fill="currentColor"/><rect x="22" y="16" width="7" height="12" rx="1" fill="currentColor" opacity=".35"/><rect x="22" y="9" width="7" height="7" rx="1" fill="currentColor"/><rect x="32" y="20" width="7" height="8" rx="1" fill="currentColor" opacity=".35"/><rect x="32" y="13" width="7" height="7" rx="1" fill="currentColor"/></svg>',
            'funnel' => '<svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M10 9h28l-8 9v9H18v-9L10 9z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" fill="none"/></svg>',
            'radialbar' => '<svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M11 27a13 13 0 0 1 26 0" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" opacity=".25"/><path d="M11 27a13 13 0 0 1 18-11.5" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/></svg>',
            default => '<svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="7" y="9" width="34" height="18" rx="3" stroke="currentColor" stroke-width="1.75"/><circle cx="24" cy="18" r="3.5" fill="currentColor"/></svg>',
        };
    }
}
