<?php

namespace Spiggle\VisualDashboards\Support;

class QueryConfig
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function normalize(array $config): array
    {
        $legacyFilters = is_array($config['filters'] ?? null) ? $config['filters'] : [];
        $window = (string) ($config['window'] ?? $legacyFilters['window'] ?? '');

        if ($window !== '') {
            $legacyFilters['window'] = $window;
            $days = self::daysForWindow($window, $legacyFilters['days'] ?? null);

            if ($days !== null) {
                $legacyFilters['days'] = (string) $days;
            }
        }

        $file = is_array($config['file'] ?? null) ? $config['file'] : [];

        if (filled($config['file_path'] ?? null) && blank($file['path'] ?? null)) {
            $file['path'] = $config['file_path'];
        }

        return [
            'source_kind' => (string) ($config['source_kind'] ?? 'model'),
            'data_source' => (string) ($config['data_source'] ?? ''),
            'table_name' => (string) ($config['table_name'] ?? ''),
            'api' => is_array($config['api'] ?? null) ? $config['api'] : [],
            'file' => $file,
            'filters' => $legacyFilters,
            'window' => $window,
            'filter_groups' => is_array($config['filter_groups'] ?? null) ? $config['filter_groups'] : [],
            'join' => is_array($config['join'] ?? null) ? $config['join'] : [],
            'columns' => is_array($config['columns'] ?? null) ? array_values($config['columns']) : [],
            'group_by' => is_array($config['group_by'] ?? null) ? array_values($config['group_by']) : [],
            'aggregations' => is_array($config['aggregations'] ?? null) ? array_values($config['aggregations']) : [],
            'calculated_fields' => is_array($config['calculated_fields'] ?? null) ? array_values($config['calculated_fields']) : [],
            'mapping' => is_array($config['mapping'] ?? null) ? $config['mapping'] : [],
            'query' => is_array($config['query'] ?? null) ? $config['query'] : [],
            'related_joins' => is_array($config['related_joins'] ?? null) ? array_values($config['related_joins']) : [],
            'joins' => is_array($config['joins'] ?? null) ? array_values($config['joins']) : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    public static function fromFormData(array $formData): array
    {
        return self::normalize([
            'source_kind' => $formData['source_kind'] ?? 'model',
            'data_source' => $formData['data_source'] ?? '',
            'table_name' => $formData['table_name'] ?? '',
            'api' => $formData['api'] ?? [],
            'file' => [
                'path' => $formData['file_path'] ?? data_get($formData, 'file.path'),
            ],
            'filters' => $formData['filters'] ?? [],
            'window' => $formData['window'] ?? data_get($formData, 'filters.window'),
            'filter_groups' => $formData['filter_groups'] ?? [],
            'join' => $formData['join'] ?? [],
            'columns' => $formData['columns'] ?? [],
            'group_by' => $formData['group_by'] ?? [],
            'aggregations' => $formData['aggregations'] ?? [],
            'calculated_fields' => $formData['calculated_fields'] ?? [],
            'mapping' => $formData['mapping'] ?? [],
            'query' => $formData['query'] ?? [],
            'related_joins' => $formData['related_joins'] ?? [],
            'joins' => $formData['joins'] ?? [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function windowDays(array $config): ?int
    {
        $filters = is_array($config['filters'] ?? null) ? $config['filters'] : [];

        return self::daysForWindow(
            (string) ($config['window'] ?? $filters['window'] ?? ''),
            $filters['days'] ?? null
        );
    }

    public static function daysForWindow(string $window, mixed $customDays = null): ?int
    {
        return match ($window) {
            'last_7_days' => 7,
            'last_30_days' => 30,
            'last_90_days' => 90,
            'custom' => max(1, (int) ($customDays ?: 14)),
            default => filled($customDays) ? max(1, (int) $customDays) : null,
        };
    }
}
