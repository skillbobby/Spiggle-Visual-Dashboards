<?php

namespace Spiggle\VisualDashboards\DataSources;

use Spiggle\VisualDashboards\Support\QueryConfig;
use Spiggle\VisualDashboards\Support\QueryFilterApplier;

class SampleMetricsDataSource extends BaseDataSource
{
    public function key(): string
    {
        return 'sample_metrics';
    }

    public function label(): string
    {
        return 'Sample metrics';
    }

    /**
     * @return array<string, string>
     */
    public function filterSchema(): array
    {
        return [
            'region' => 'Region',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function columns(array $context = []): array
    {
        return [
            'label' => 'Label',
            'value' => 'Value',
            'region' => 'Region',
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function previewRows(array $config = [], int $limit = 25): array
    {
        $normalized = QueryConfig::normalize($config);
        $region = (string) ($normalized['filters']['region'] ?? 'all');
        $rows = [
            ['label' => 'Alpha', 'value' => 42, 'region' => 'north'],
            ['label' => 'Beta', 'value' => 28, 'region' => 'north'],
            ['label' => 'Gamma', 'value' => 35, 'region' => 'south'],
            ['label' => 'Delta', 'value' => 19, 'region' => 'south'],
            ['label' => 'Epsilon', 'value' => 51, 'region' => 'east'],
        ];

        if ($region !== 'all' && $region !== '') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['region'] === $region));
        }

        $rows = $this->applyFilterGroups($rows, $normalized['filter_groups']);

        return $this->finalizeRows(array_slice($rows, 0, $limit), $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function chartPayload(string $type, array $config = []): array
    {
        $rows = $this->previewRows($config, 100);

        return $this->toChartPayload(
            $type,
            array_column($rows, 'label'),
            array_column($rows, 'value'),
            'Sample value'
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $groups
     * @return array<int, array<string, mixed>>
     */
    protected function applyFilterGroups(array $rows, array $groups): array
    {
        $conditions = is_array($groups['conditions'] ?? null) ? $groups['conditions'] : [];
        $orConditions = is_array($groups['or_conditions'] ?? null) ? $groups['or_conditions'] : [];

        if ($conditions === [] && $orConditions === []) {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($conditions, $orConditions): bool {
            foreach ($conditions as $condition) {
                if (! is_array($condition) || ! QueryFilterApplier::rowMatches($row, $condition)) {
                    return false;
                }
            }

            if ($orConditions === []) {
                return true;
            }

            foreach ($orConditions as $condition) {
                if (is_array($condition) && QueryFilterApplier::rowMatches($row, $condition)) {
                    return true;
                }
            }

            return false;
        }));
    }

}
