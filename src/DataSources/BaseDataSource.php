<?php

namespace Spiggle\VisualDashboards\DataSources;

use Spiggle\VisualDashboards\Contracts\DataSource;
use Spiggle\VisualDashboards\Support\QueryAggregator;
use Spiggle\VisualDashboards\Support\QueryConfig;
use Spiggle\VisualDashboards\Support\QueryJoinApplier;
use Spiggle\VisualDashboards\Support\VirtualJoinEnricher;
use Spiggle\VisualDashboards\Support\VisualQueryBuilder;

abstract class BaseDataSource implements DataSource
{
    /**
     * @return array<string, string>
     */
    public function filterSchema(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function columns(array $context = []): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function joinTargets(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function handlesAggregationInChartPayload(array $config): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    protected function finalizeRows(array $rows, array $config): array
    {
        $normalized = QueryConfig::normalize($config);
        $query = VisualQueryBuilder::normalize($config);
        $join = $normalized['join'];

        if (filled($query['joins'] ?? []) || filled($query['related_joins'] ?? [])) {
            $rows = QueryJoinApplier::apply($rows, $config);
        } elseif (($join['target'] ?? '') !== '') {
            $rows = VirtualJoinEnricher::enrich($rows, $join);
        }

        $columns = $normalized['columns'];

        if ($columns === [] || QueryAggregator::shouldAggregate($normalized)) {
            return $rows;
        }

        return array_map(function (array $row) use ($columns): array {
            $picked = [];

            foreach ($columns as $column) {
                $picked[$column] = data_get($row, str_replace('->', '.', $column));
            }

            return $picked;
        }, $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected function toChartPayload(string $type, array $labels, array $values, string $label = 'Value'): array
    {
        if ($type === 'stat_card') {
            return [
                'value' => array_sum($values),
                'label' => $label,
            ];
        }

        if (in_array($type, ['pie', 'donut', 'polar_area'], true)) {
            return [
                'labels' => $labels,
                'datasets' => [[
                    'label' => $label,
                    'data' => $values,
                ]],
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => $label,
                'data' => $values,
            ]],
        ];
    }
}
