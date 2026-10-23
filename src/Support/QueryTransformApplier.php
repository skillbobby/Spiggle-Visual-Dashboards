<?php

namespace Spiggle\VisualDashboards\Support;

class QueryTransformApplier
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $rows, array $query): array
    {
        $transforms = is_array($query['transforms'] ?? null) ? $query['transforms'] : [];

        foreach ($transforms as $transform) {
            if (! is_array($transform)) {
                continue;
            }

            $rows = self::applyTransform($rows, self::normalizeTransform($transform));
        }

        if (($query['remove_duplicates'] ?? false) === true && ! self::hasDedupeTransform($transforms)) {
            $rows = self::dedupeRows($rows, []);
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $transform
     * @return array<int, array<string, mixed>>
     */
    public static function applyTransform(array $rows, array $transform): array
    {
        $type = (string) ($transform['type'] ?? 'filter');

        return match ($type) {
            'filter' => self::filterRows($rows, $transform),
            'cast' => self::castRows($rows, $transform),
            'rename' => self::renameRows($rows, $transform),
            'format' => self::formatRows($rows, $transform),
            'dedupe' => self::dedupeRows($rows, self::dedupeColumns($transform)),
            default => $rows,
        };
    }

    /**
     * @param  array<string, mixed>  $transform
     * @return array<string, mixed>
     */
    public static function normalizeTransform(array $transform): array
    {
        $type = (string) ($transform['type'] ?? 'filter');

        if ($type === 'filter') {
            $column = trim((string) ($transform['column'] ?? $transform['field'] ?? ''));

            return [
                'type' => 'filter',
                'column' => $column,
                'field' => $column,
                'operator' => (string) ($transform['operator'] ?? 'equals'),
                'value' => $transform['value'] ?? null,
            ];
        }

        if ($type === 'cast') {
            $column = trim((string) ($transform['column'] ?? $transform['field'] ?? ''));
            $dataType = (string) ($transform['data_type'] ?? $transform['cast'] ?? 'text');

            return [
                'type' => 'cast',
                'column' => $column,
                'field' => $column,
                'data_type' => $dataType,
                'cast' => $dataType,
            ];
        }

        if ($type === 'rename') {
            return [
                'type' => 'rename',
                'from' => trim((string) ($transform['from'] ?? '')),
                'to' => trim((string) ($transform['to'] ?? '')),
            ];
        }

        if ($type === 'format') {
            $column = trim((string) ($transform['field'] ?? $transform['column'] ?? ''));

            return [
                'type' => 'format',
                'field' => $column,
                'column' => $column,
                'function' => (string) ($transform['function'] ?? ''),
                'format' => (string) ($transform['format'] ?? ''),
            ];
        }

        if ($type === 'dedupe') {
            $columns = $transform['columns'] ?? [];

            return [
                'type' => 'dedupe',
                'columns' => is_array($columns) ? array_values(array_filter($columns, fn (mixed $col): bool => filled($col))) : [],
            ];
        }

        return $transform;
    }

    /**
     * @param  array<int, array<string, mixed>>  $transforms
     */
    protected static function hasDedupeTransform(array $transforms): bool
    {
        foreach ($transforms as $transform) {
            if (is_array($transform) && ($transform['type'] ?? '') === 'dedupe') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $transform
     * @return array<int, string>
     */
    protected static function dedupeColumns(array $transform): array
    {
        $columns = $transform['columns'] ?? [];

        return is_array($columns) ? array_values(array_filter($columns, fn (mixed $col): bool => filled($col))) : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $transform
     * @return array<int, array<string, mixed>>
     */
    protected static function filterRows(array $rows, array $transform): array
    {
        $column = (string) ($transform['column'] ?? $transform['field'] ?? '');

        if ($column === '') {
            return $rows;
        }

        $condition = [
            'field' => $column,
            'operator' => (string) ($transform['operator'] ?? 'equals'),
            'value' => $transform['value'] ?? null,
        ];

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => QueryFilterApplier::rowMatches($row, $condition)
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $transform
     * @return array<int, array<string, mixed>>
     */
    protected static function castRows(array $rows, array $transform): array
    {
        $column = (string) ($transform['column'] ?? $transform['field'] ?? '');
        $dataType = (string) ($transform['data_type'] ?? $transform['cast'] ?? 'text');

        if ($column === '' || $dataType === 'text') {
            return $rows;
        }

        return array_map(function (array $row) use ($column, $dataType): array {
            if (! array_key_exists($column, $row)) {
                return $row;
            }

            $row[$column] = match ($dataType) {
                'number' => is_numeric($row[$column]) ? (float) $row[$column] : null,
                'date' => filled($row[$column]) ? (string) $row[$column] : null,
                default => (string) ($row[$column] ?? ''),
            };

            return $row;
        }, $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $transform
     * @return array<int, array<string, mixed>>
     */
    protected static function formatRows(array $rows, array $transform): array
    {
        $column = (string) ($transform['field'] ?? $transform['column'] ?? '');
        $function = (string) ($transform['function'] ?? '');
        $format = (string) ($transform['format'] ?? '');

        if ($column === '' || $function === '') {
            return $rows;
        }

        return array_map(function (array $row) use ($column, $function, $format): array {
            if (! array_key_exists($column, $row)) {
                return $row;
            }

            $row[$column] = FieldValueFormatter::apply($row[$column], $function, $format);

            return $row;
        }, $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $transform
     * @return array<int, array<string, mixed>>
     */
    protected static function renameRows(array $rows, array $transform): array
    {
        $from = (string) ($transform['from'] ?? '');
        $to = (string) ($transform['to'] ?? '');

        if ($from === '' || $to === '') {
            return $rows;
        }

        return array_map(function (array $row) use ($from, $to): array {
            if (! array_key_exists($from, $row)) {
                return $row;
            }

            $row[$to] = $row[$from];
            unset($row[$from]);

            return $row;
        }, $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $columns
     * @return array<int, array<string, mixed>>
     */
    protected static function dedupeRows(array $rows, array $columns): array
    {
        $seen = [];

        return array_values(array_filter($rows, function (array $row) use ($columns, &$seen): bool {
            if ($columns === []) {
                $key = json_encode($row);
            } else {
                $subset = [];

                foreach ($columns as $column) {
                    $subset[$column] = data_get($row, $column);
                }

                $key = json_encode($subset);
            }

            if (isset($seen[$key])) {
                return false;
            }

            $seen[$key] = true;

            return true;
        }));
    }
}
