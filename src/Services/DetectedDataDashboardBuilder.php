<?php

namespace Spiggle\VisualDashboards\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Models\Dashlet;

class DetectedDataDashboardBuilder
{
    public const DASHBOARD_NAME = 'Live data';

    public function __construct(
        protected ChartableModelScanner $scanner,
    ) {}

    public function sync(Model $user): CustomDashboard
    {
        $dashboard = CustomDashboard::query()->firstOrCreate(
            [
                'user_id' => $user->getKey(),
                'name' => self::DASHBOARD_NAME,
            ],
            [
                'is_default' => false,
                'is_published' => false,
            ]
        );

        return $this->syncInto($dashboard);
    }

    public function syncInto(CustomDashboard $dashboard): CustomDashboard
    {
        $profiles = array_slice($this->scanner->profiles(), 0, 16);
        $visuals = $this->visuals($profiles);

        $layout = is_array($dashboard->layout_json) ? $dashboard->layout_json : [];
        $layout['filters'] = $this->filters($profiles);

        $dashboard->forceFill([
            'description' => 'Charts are built from models that currently have records.',
            'layout_json' => $layout,
        ])->save();

        $dashboard->dashlets()->delete();

        foreach ($visuals as $visual) {
            Dashlet::query()->create([
                'custom_dashboard_id' => $dashboard->getKey(),
                'type' => $visual['type'],
                'grid_x' => $visual['x'],
                'grid_y' => $visual['y'],
                'grid_w' => $visual['w'],
                'grid_h' => $visual['h'],
                'config' => $visual['config'],
            ]);
        }

        return $dashboard->refresh();
    }

    /**
     * @param  list<array<string, mixed>>  $profiles
     * @return list<array{type: string, x: int, y: int, w: int, h: int, config: array<string, mixed>}>
     */
    protected function visuals(array $profiles): array
    {
        $planned = [];
        $color = 0;

        foreach ($profiles as $index => $profile) {
            $planned[] = ['type' => 'stat_card', 'w' => 3, 'h' => 3, 'config' => $this->countConfig($profile, $color)];
            $color++;

            if ($index >= 8) {
                continue;
            }

            $chart = $this->chartConfig($profile, $color);

            if ($chart === null) {
                continue;
            }

            $planned[] = $chart;
            $color++;
        }

        if ($profiles !== [] && ($profiles[0]['columns'] ?? []) !== []) {
            $planned[] = [
                'type' => 'table',
                'w' => 6,
                'h' => 5,
                'config' => $this->tableConfig($profiles[0], $color),
            ];
        }

        return $this->pack($planned);
    }

    /**
     * @param  list<array{type: string, w: int, h: int, config: array<string, mixed>}>  $planned
     * @return list<array{type: string, x: int, y: int, w: int, h: int, config: array<string, mixed>}>
     */
    protected function pack(array $planned): array
    {
        $x = 0;
        $y = 0;
        $rowHeight = 0;
        $placed = [];

        foreach ($planned as $visual) {
            $width = (int) $visual['w'];
            $height = (int) $visual['h'];

            if ($x + $width > 12) {
                $x = 0;
                $y += $rowHeight;
                $rowHeight = 0;
            }

            $placed[] = [
                'type' => $visual['type'],
                'x' => $x,
                'y' => $y,
                'w' => $width,
                'h' => $height,
                'config' => $visual['config'],
            ];

            $x += $width;
            $rowHeight = max($rowHeight, $height);
        }

        return $placed;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    protected function countConfig(array $profile, int $color): array
    {
        $config = $this->baseConfig($profile, $profile['label'], 'Records', $color, 'number', [
            ['fn' => 'COUNT', 'field' => '*', 'alias' => 'value'],
        ]);
        $date = $profile['dates'][0] ?? null;

        $config['mapping'] = ['y' => 'value'];
        $config['visual']['kpi'] = [
            'metric_label' => $profile['label'],
            'period' => 'total',
            'value_suffix' => '',
            'sparkline_field' => is_string($date) ? $date : '',
            'sparkline_grain' => 'month',
            'supporting' => [],
        ];
        $config['seed_key'] = $this->seedKey($profile, 'count');

        return $config;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array{type: string, w: int, h: int, config: array<string, mixed>}|null
     */
    protected function chartConfig(array $profile, int $color): ?array
    {
        $category = $profile['categories'][0] ?? null;
        $series = $profile['categories'][1] ?? null;

        if (is_array($category)) {
            $useSeries = is_array($series) && ($series['join'] ?? null) === null && ($series['distinct'] ?? 0) <= 6;
            $type = $useSeries || ($category['distinct'] ?? 0) < 6 ? 'bar' : 'treemap';
            $groupBy = [$category['field']];
            $mapping = ['x' => $category['field'], 'y' => 'value'];

            if ($useSeries && $type === 'bar') {
                $groupBy[] = $series['field'];
                $mapping['series'] = $series['field'];
            }

            $config = $this->baseConfig(
                $profile,
                $profile['label'].' by '.$category['label'],
                $useSeries && $type === 'bar' ? 'Split by '.$series['label'] : $category['label'],
                $color,
                'number',
                [['fn' => 'COUNT', 'field' => '*', 'alias' => 'value']],
                $groupBy,
                $category['join'] ?? null,
            );
            $config['mapping'] = $mapping;
            $config['seed_key'] = $this->seedKey($profile, $type);
            $config['visual']['chart_variant'] = $type === 'bar' && $useSeries ? 'group' : 'basic';

            return ['type' => $type, 'w' => 6, 'h' => 5, 'config' => $config];
        }

        $boolean = $profile['booleans'][0] ?? null;

        if (is_string($boolean)) {
            $label = Str::headline(str_starts_with($boolean, 'is_') ? substr($boolean, 3) : $boolean);
            $config = $this->baseConfig(
                $profile,
                $label.' rate',
                $profile['label'],
                $color,
                'percent',
                [
                    ['fn' => 'SUM', 'field' => $boolean, 'alias' => 'part'],
                    ['fn' => 'COUNT', 'field' => '*', 'alias' => 'whole'],
                ],
            );
            $config['calculated_fields'] = [
                ['name' => 'rate', 'expression' => 'part / whole'],
                ['name' => 'rate_pct', 'expression' => 'rate * 100'],
            ];
            $config['mapping'] = ['y' => 'rate_pct'];
            $config['seed_key'] = $this->seedKey($profile, 'rate');
            $config['visual']['chart_variant'] = 'bands';
            $config['visual']['gauge_min'] = 0;
            $config['visual']['gauge_max'] = 100;

            return ['type' => 'radialbar', 'w' => 4, 'h' => 5, 'config' => $config];
        }

        $date = $profile['dates'][0] ?? null;

        if (! is_string($date)) {
            return null;
        }

        $config = $this->baseConfig(
            $profile,
            $profile['label'].' over time',
            Str::headline($date),
            $color,
            'number',
            [['fn' => 'COUNT', 'field' => '*', 'alias' => 'value']],
            [$date],
        );
        $config['mapping'] = ['x' => $date, 'y' => 'value'];
        $config['filters'] = ['grain' => 'month'];
        $config['seed_key'] = $this->seedKey($profile, 'line');
        $config['visual']['default_grain'] = 'month';
        $config['visual']['period_filter'] = true;
        $config['visual']['period_grains'] = ['day', 'week', 'month'];
        $config['visual']['chart_variant'] = 'smooth';

        return ['type' => 'line', 'w' => 6, 'h' => 5, 'config' => $config];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    protected function tableConfig(array $profile, int $color): array
    {
        $config = $this->baseConfig($profile, 'Recent '.$profile['label'], 'Latest records', $color, 'number');
        $config['columns'] = $profile['columns'];
        $config['aggregations'] = [];
        $config['mapping'] = [];
        $config['seed_key'] = $this->seedKey($profile, 'table');

        return $config;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  list<array<string, mixed>>  $aggregations
     * @param  list<string>  $groupBy
     * @param  array<string, mixed>|null  $join
     * @return array<string, mixed>
     */
    protected function baseConfig(
        array $profile,
        string $title,
        string $subheading,
        int $color,
        string $format,
        array $aggregations = [],
        array $groupBy = [],
        ?array $join = null,
    ): array {
        $source = 'model:'.$profile['class'];
        $schemes = ['amber', 'blue', 'violet', 'emerald', 'rose', 'cyan'];

        return [
            'title' => $title,
            'heading' => $title,
            'subheading' => $subheading,
            'source_kind' => 'model',
            'data_source' => $source,
            'table_name' => $profile['table'],
            'query' => [
                'applied' => true,
                'primary_source' => $source,
                'transforms' => [],
                'joins' => $join === null ? [] : [$join],
                'related_joins' => $join === null ? [] : [$join],
            ],
            'filters' => [],
            'filter_groups' => ['conditions' => []],
            'group_by' => $groupBy,
            'aggregations' => $aggregations,
            'calculated_fields' => [],
            'mapping' => [],
            'columns' => [],
            'visual' => [
                'color_scheme' => $schemes[$color % count($schemes)],
                'border' => true,
                'number_format' => $format,
                'currency' => 'USD',
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $profiles
     * @return list<array{id: string, label: string, control: string, fields: list<string>}>
     */
    protected function filters(array $profiles): array
    {
        /** @var array<string, array{label: string, models: int}> $fields */
        $fields = [];

        foreach ($profiles as $profile) {
            foreach ($profile['categories'] as $category) {
                $field = (string) $category['field'];
                $fields[$field] ??= ['label' => (string) $category['label'], 'models' => 0];
                $fields[$field]['models']++;
            }
        }

        uasort($fields, static function (array $left, array $right): int {
            return $right['models'] <=> $left['models'];
        });

        $shared = array_filter($fields, static fn (array $field): bool => $field['models'] >= 2);
        $chosen = $shared !== [] ? $shared : $fields;
        $filters = [];

        foreach (array_slice($chosen, 0, 4, true) as $field => $meta) {
            $filters[] = [
                'id' => Str::slug(str_replace('.', '-', (string) $field), '_'),
                'label' => $meta['label'],
                'control' => 'select',
                'fields' => [(string) $field],
            ];
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    protected function seedKey(array $profile, string $kind): string
    {
        return 'detected-'.Str::kebab(class_basename((string) $profile['class'])).'-'.$kind;
    }
}
