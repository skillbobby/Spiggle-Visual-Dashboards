<?php

namespace Spiggle\VisualDashboards\Support;

class VisualQueryBuilder
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function normalize(array $config): array
    {
        $query = is_array($config['query'] ?? null) ? $config['query'] : [];

        return [
            'primary_source' => (string) ($query['primary_source'] ?? $config['data_source'] ?? ''),
            'transforms' => is_array($query['transforms'] ?? null) ? array_values($query['transforms']) : [],
            'joins' => is_array($query['joins'] ?? null) ? array_values($query['joins']) : [],
            'related_joins' => is_array($query['related_joins'] ?? null) ? array_values($query['related_joins']) : [],
            'selected_fields' => is_array($query['selected_fields'] ?? null) ? array_values($query['selected_fields']) : [],
            'remove_duplicates' => (bool) ($query['remove_duplicates'] ?? false),
            'applied' => (bool) ($query['applied'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public static function mergeIntoConfig(array $config, array $query): array
    {
        $config['query'] = self::normalize(array_merge($config, ['query' => $query]));

        if (filled($query['primary_source'] ?? '')) {
            $config['primary_source'] = $query['primary_source'];
        }

        $config['remove_duplicates'] = (bool) ($query['remove_duplicates'] ?? false);

        // Always replace filter_groups from current transforms. Leaving the previous
        // value when transforms are empty left Include-rows filters stuck in preview/query.
        $config['filter_groups'] = self::transformsToFilterGroups($query['transforms'] ?? []);

        if (filled($query['selected_fields'] ?? [])) {
            $config['columns'] = array_values($query['selected_fields']);
        }

        if (filled($query['joins'] ?? []) || filled($query['related_joins'] ?? [])) {
            $joins = filled($query['joins'] ?? [])
                ? array_values($query['joins'])
                : array_values($query['related_joins']);

            $config['join'] = $joins[0] ?? [];
            $config['joins'] = $joins;
            $config['related_joins'] = $joins;
        } else {
            $config['join'] = [];
            $config['joins'] = [];
            $config['related_joins'] = [];
        }

        return $config;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public static function applyTransforms(array $rows, array $query): array
    {
        return QueryTransformApplier::apply($rows, $query);
    }

    /**
     * @param  array<int, array<string, mixed>>  $transforms
     * @return array<string, mixed>
     */
    public static function transformsToFilterGroups(array $transforms): array
    {
        $conditions = [];

        foreach ($transforms as $transform) {
            if (! is_array($transform) || ($transform['type'] ?? '') !== 'filter') {
                continue;
            }

            $normalized = QueryTransformApplier::normalizeTransform($transform);
            $field = trim((string) ($normalized['column'] ?? ''));

            if ($field === '') {
                continue;
            }

            $operator = (string) ($normalized['operator'] ?? 'equals');
            $value = $normalized['value'] ?? null;
            $needsValue = ! in_array($operator, ['is_empty', 'is_not_empty', 'is_null', 'is_not_null'], true);

            if ($needsValue && (is_null($value) || (is_string($value) && trim($value) === ''))) {
                continue;
            }

            // Relation join fields (e.g. users.email) are enriched post-fetch; form JSON
            // fields (data.*) can be filtered at the database layer.
            if (str_contains($field, '.') && ! str_starts_with($field, 'data.')) {
                continue;
            }

            $conditions[] = [
                'field' => $field,
                'operator' => $operator,
                'value' => is_string($value) ? trim($value) : $value,
            ];
        }

        if ($conditions === []) {
            return [];
        }

        return ['conditions' => $conditions];
    }
}
