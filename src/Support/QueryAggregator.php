<?php

namespace Spiggle\VisualDashboards\Support;

class QueryAggregator
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $rows, array $config): array
    {
        $groupBy = array_values(array_filter(
            is_array($config['group_by'] ?? null) ? $config['group_by'] : [],
            static fn (mixed $field): bool => filled($field)
        ));
        $aggregations = array_values(array_filter(
            is_array($config['aggregations'] ?? null) ? $config['aggregations'] : [],
            static fn (mixed $aggregation): bool => is_array($aggregation) && filled($aggregation['fn'] ?? null)
        ));

        if ($groupBy === [] && $aggregations === []) {
            return CalculatedFields::apply($rows, is_array($config['calculated_fields'] ?? null) ? $config['calculated_fields'] : []);
        }

        if ($aggregations === []) {
            $aggregations = [[
                'fn' => 'COUNT',
                'field' => '*',
                'alias' => 'count',
            ]];
        }

        $buckets = [];

        foreach ($rows as $row) {
            $dimensions = [];

            foreach ($groupBy as $field) {
                $field = (string) $field;
                $raw = $row[$field] ?? null;
                $dimensions[$field] = GroupByDimension::normalizeValue($field, $raw, $config);
            }

            $key = json_encode($dimensions);
            $buckets[$key] ??= [
                'dimensions' => $dimensions,
                'rows' => [],
            ];
            $buckets[$key]['rows'][] = $row;
        }

        $aggregated = [];

        foreach ($buckets as $bucket) {
            $item = $bucket['dimensions'];

            foreach ($aggregations as $index => $aggregation) {
                $alias = (string) ($aggregation['alias'] ?? strtolower((string) $aggregation['fn']).'_'.$index);
                $item[$alias] = self::reduce($bucket['rows'], $aggregation);
            }

            $aggregated[] = $item;
        }

        return CalculatedFields::apply($aggregated, is_array($config['calculated_fields'] ?? null) ? $config['calculated_fields'] : []);
    }

    public static function shouldAggregate(array $config): bool
    {
        $groupBy = $config['group_by'] ?? [];
        $aggregations = $config['aggregations'] ?? [];

        return (is_array($groupBy) && $groupBy !== []) || (is_array($aggregations) && $aggregations !== []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $aggregation
     */
    protected static function reduce(array $rows, array $aggregation): float|int|null
    {
        $fn = strtoupper((string) ($aggregation['fn'] ?? 'COUNT'));
        $field = (string) ($aggregation['field'] ?? '*');

        if ($fn === 'COUNT') {
            return count($rows);
        }

        $values = array_values(array_filter(
            array_map(static fn (array $row): mixed => $field === '*' ? 1 : ($row[$field] ?? null), $rows),
            static fn (mixed $value): bool => is_numeric($value)
        ));

        if ($values === []) {
            return $fn === 'SUM' ? 0 : null;
        }

        $numeric = array_map(static fn (mixed $value): float => (float) $value, $values);

        return match ($fn) {
            'SUM' => array_sum($numeric),
            'AVG' => array_sum($numeric) / count($numeric),
            'MIN' => min($numeric),
            'MAX' => max($numeric),
            default => count($rows),
        };
    }
}
