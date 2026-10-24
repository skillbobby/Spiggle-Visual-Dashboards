<?php

namespace Spiggle\VisualDashboards\Services;

use Spiggle\VisualDashboards\Contracts\DataSource;
use Spiggle\VisualDashboards\DataSources\DatabaseTableDataSource;
use Spiggle\VisualDashboards\DataSources\FileCsvDataSource;
use Spiggle\VisualDashboards\DataSources\LiveApiDataSource;
use Spiggle\VisualDashboards\Support\FeatureCatalog;
use Spiggle\VisualDashboards\Support\KpiCardPresenter;
use Spiggle\VisualDashboards\Support\ModelRelationshipIntrospector;
use Spiggle\VisualDashboards\Support\QueryAggregator;
use Spiggle\VisualDashboards\Support\QueryConfig;
use Spiggle\VisualDashboards\Support\RadialBarPresenter;
use Spiggle\VisualDashboards\Support\ValueFormatter;
use Spiggle\VisualDashboards\Support\VirtualMeasures;
use Spiggle\VisualDashboards\Support\VisualQueryBuilder;

class DataEngine
{
    public function __construct(
        protected DashletRegistry $registry,
    ) {}

    public function resolve(string $key): DataSource
    {
        return $this->registry->dataSource($key);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function resolveFromConfig(array $config): DataSource
    {
        $normalized = QueryConfig::normalize($config);

        return match ($normalized['source_kind']) {
            'model' => $this->resolveModelSource($normalized),
            'table' => $this->resolveTableSource($normalized),
            'api' => app(LiveApiDataSource::class)->configure($normalized['api']),
            'file' => app(FileCsvDataSource::class)->configure($normalized['file']),
            default => $this->resolve((string) ($normalized['data_source'] ?: 'sample_metrics')),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function resolveModelSource(array $config): DataSource
    {
        $class = $this->modelClassFromConfig($config);

        if ($class !== null) {
            return app(DatabaseTableDataSource::class)->forModel($class);
        }

        return $this->resolve((string) ($config['data_source'] ?: 'sample_metrics'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function resolveTableSource(array $config): DataSource
    {
        $class = ModelRelationshipIntrospector::modelClassForTable((string) ($config['table_name'] ?? ''));

        if ($class !== null) {
            return app(DatabaseTableDataSource::class)->forModel($class);
        }

        return app(DatabaseTableDataSource::class)->forTable((string) ($config['table_name'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function modelClassFromConfig(array $config): ?string
    {
        foreach ([
            (string) ($config['data_source'] ?? ''),
            (string) data_get($config, 'query.primary_source', ''),
        ] as $key) {
            $class = ModelRelationshipIntrospector::modelClassForSourceKey($key);

            if ($class !== null) {
                return $class;
            }
        }

        return ModelRelationshipIntrospector::modelClassForTable((string) ($config['table_name'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function preview(string $sourceKey, array $config = [], int $limit = 25): array
    {
        $config['data_source'] = $config['data_source'] ?? $sourceKey;

        return $this->previewFromConfig($config, $limit);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function previewFromConfig(array $config, int $limit = 25): array
    {
        $rows = $this->filteredRowsFromConfig($config, $limit);

        return QueryAggregator::apply($rows, $config);
    }

    /**
     * Return raw rows after source fetch and transform filters, without aggregation.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function filteredRowsFromConfig(array $config, int $limit = 25): array
    {
        $normalized = QueryConfig::normalize($config);
        $rows = $this->resolveFromConfig($normalized)->previewRows($normalized, $limit);
        $query = VisualQueryBuilder::normalize($normalized);

        if ($query['applied']) {
            $rows = VisualQueryBuilder::applyTransforms($rows, $query);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function filteredRowCountFromConfig(array $config, int $limit = 500): int
    {
        return count($this->filteredRowsFromConfig($config, $limit));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function chart(string $sourceKey, string $type, array $config = []): array
    {
        $config['data_source'] = $config['data_source'] ?? $sourceKey;

        return $this->chartFromConfig($type, $config);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function chartFromConfig(string $type, array $config): array
    {
        FeatureCatalog::assertAllowed($type);

        $normalized = QueryConfig::normalize($config);

        if (in_array($type, ['hero', 'insight_list'], true)) {
            return $this->resolveFromConfig($normalized)->chartPayload($type, $normalized);
        }

        $limit = (int) config('visual-dashboards.query.max_rows', 500);

        if (QueryAggregator::shouldAggregate($normalized)) {
            $source = $this->resolveFromConfig($normalized);

            if ($source->handlesAggregationInChartPayload($normalized)) {
                return $source->chartPayload($type, $normalized);
            }

            $rows = $this->previewFromConfig($normalized, $limit);

            return $this->chartFromRows($type, $rows, $normalized);
        }

        return $this->resolveFromConfig($normalized)->chartPayload($type, $normalized);
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    public function previewPayload(string $sourceKey, string $type, array $formData): array
    {
        $formData['data_source'] = $formData['data_source'] ?? $sourceKey;
        $formData['type'] = $type;

        return $this->previewPayloadFromForm($formData);
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    public function previewPayloadFromForm(array $formData): array
    {
        $type = (string) ($formData['type'] ?: 'stat_card');
        $config = QueryConfig::fromFormData($formData);
        $visual = is_array($formData['visual'] ?? null) ? $formData['visual'] : [];

        if ($type === 'stat_card') {
            $statConfig = KpiCardPresenter::configForQuery($formData);
            $chart = $this->chartFromConfig('stat_card', QueryConfig::normalize($statConfig));

            return KpiCardPresenter::enrich([
                'type' => 'stat_card',
                'value' => $chart['value'] ?? 0,
                'label' => $chart['label'] ?? 'Total',
                'stat_label' => $chart['label'] ?? 'Total',
                'formatted' => $chart['formatted'] ?? ValueFormatter::format(
                    $chart['value'] ?? 0,
                    (string) ($visual['number_format'] ?? 'number'),
                    (string) ($visual['currency'] ?? 'USD')
                ),
                'hint' => $chart['hint'] ?? null,
                'delta' => $chart['delta'] ?? null,
                'up' => (bool) ($chart['up'] ?? false),
                'badge' => $chart['badge'] ?? null,
            ], $statConfig);
        }

        if ($type === 'hero') {
            $chart = $this->chartFromConfig('hero', $config);

            return [
                'type' => 'hero',
                'greeting' => $chart['greeting'] ?? 'Hello',
                'month_label' => $chart['month_label'] ?? now()->format('F Y'),
            ];
        }

        if ($type === 'insight_list') {
            $chart = $this->chartFromConfig('insight_list', $config);

            return [
                'type' => 'insight_list',
                'items' => $chart['items'] ?? [],
            ];
        }

        if ($type === 'table') {
            return array_merge(
                ['type' => 'table'],
                $this->tablePayloadFromConfig($config, (int) config('visual-dashboards.preview.limit', 50)),
            );
        }

        return [
            'type' => $type,
            'chart' => $this->chartFromConfig($type, $config),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function statValueFromConfig(array $config): float
    {
        $chart = $this->chartFromConfig('stat_card', QueryConfig::normalize($config));

        return (float) ($chart['value'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{rows: array<int, array<string, mixed>>, columns: array<int, string>}
     */
    public function tablePayloadFromConfig(array $config, int $limit = 50): array
    {
        $tableConfig = QueryConfig::normalize($config);
        $tableConfig['aggregations'] = [];
        $tableConfig['group_by'] = [];

        $rows = $this->filteredRowsFromConfig($tableConfig, $limit);
        $columns = is_array($tableConfig['columns'] ?? null)
            ? array_values(array_filter($tableConfig['columns']))
            : [];

        if ($columns === [] && $rows !== []) {
            $columns = array_keys($rows[0]);
        }

        return [
            'rows' => $rows,
            'columns' => $columns,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function chartFromRows(string $type, array $rows, array $config): array
    {
        $mapping = is_array($config['mapping'] ?? null) ? $config['mapping'] : [];
        $groupBy = $config['group_by'] ?? [];
        $aggregations = $config['aggregations'] ?? [];
        $x = (string) ($mapping['x'] ?? ($groupBy[0] ?? array_key_first($rows[0] ?? []) ?? 'label'));
        $y = (string) ($mapping['y'] ?? ($aggregations[0]['alias'] ?? $this->firstNumericKey($rows[0] ?? []) ?? 'value'));
        $series = (string) ($mapping['series'] ?? '');

        if ($type === 'stat_card') {
            $total = array_sum(array_map(static fn (array $row): float => (float) ($row[$y] ?? 0), $rows));

            return [
                'value' => $total,
                'label' => $y !== '' ? $y : 'Total',
            ];
        }

        if ($type === 'radialbar') {
            $total = array_sum(array_map(static fn (array $row): float => (float) ($row[$y] ?? 0), $rows));

            return RadialBarPresenter::chartFromValue($total, $config, $this->measureLabel($config, $y));
        }

        if ($type === 'scatter') {
            $points = [];

            foreach ($rows as $row) {
                $points[] = [
                    'x' => (float) ($row[$x] ?? 0),
                    'y' => (float) ($row[$y] ?? 0),
                    'label' => (string) ($row[$series] ?? $row[$x] ?? ''),
                ];
            }

            return [
                'scatter' => true,
                'labels' => [],
                'datasets' => [[
                    'label' => $series !== '' ? $series : ($y !== '' ? $y : 'Value'),
                    'data' => $points,
                    'pointRadius' => 7,
                    'pointHoverRadius' => 10,
                ]],
                'x_title' => $x !== '' ? $x : 'X',
                'y_title' => $y !== '' ? $y : 'Y',
            ];
        }

        if ($series !== '') {
            return $this->chartFromSeries($rows, $x, $y, $series);
        }

        return [
            'labels' => array_map(static fn (array $row): string => (string) ($row[$x] ?? ''), $rows),
            'datasets' => [[
                'label' => $this->measureLabel($config, $y),
                'data' => array_map(static fn (array $row): float => (float) ($row[$y] ?? 0), $rows),
            ]],
        ];
    }

    /**
     * Pivot rows into one dataset per series value, aligned on the category axis.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected function chartFromSeries(array $rows, string $x, string $y, string $series): array
    {
        $labels = [];
        $datasets = [];

        foreach ($rows as $row) {
            $label = (string) ($row[$x] ?? '');
            $seriesKey = trim((string) ($row[$series] ?? ''));
            $seriesKey = $seriesKey !== '' ? $seriesKey : 'Series';
            $labels[$label] = $label;
            $datasets[$seriesKey][$label] = (float) ($row[$y] ?? 0);
        }

        $labelList = array_values($labels);
        sort($labelList);
        ksort($datasets);

        return [
            'labels' => $labelList,
            'datasets' => array_map(function (array $points, string $label) use ($labelList): array {
                return [
                    'label' => $label,
                    'data' => array_map(static fn (string $axis): float => (float) ($points[$axis] ?? 0), $labelList),
                ];
            }, $datasets, array_keys($datasets)),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function measureLabel(array $config, string $fallback): string
    {
        $aggregations = is_array($config['aggregations'] ?? null) ? $config['aggregations'] : [];
        $fn = strtoupper((string) ($aggregations[0]['fn'] ?? ''));
        $field = (string) ($aggregations[0]['field'] ?? '');

        if ($fn === 'COUNT' || $field === '*' || VirtualMeasures::isVirtual($field)) {
            return 'Count';
        }

        return $fallback !== '' && $fallback !== 'value' ? $fallback : 'Value';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function firstNumericKey(array $row): ?string
    {
        foreach ($row as $key => $value) {
            if (is_numeric($value)) {
                return (string) $key;
            }
        }

        return null;
    }
}
