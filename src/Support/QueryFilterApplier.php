<?php

namespace Spiggle\VisualDashboards\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class QueryFilterApplier
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function apply(EloquentBuilder|QueryBuilder $query, array $config, string $table = ''): EloquentBuilder|QueryBuilder
    {
        $groups = $config['filter_groups'] ?? [];
        $conditions = is_array($groups['conditions'] ?? null) ? $groups['conditions'] : [];
        $orConditions = is_array($groups['or_conditions'] ?? null) ? $groups['or_conditions'] : [];

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            self::applyCondition($query, $condition, $table);
        }

        if ($orConditions !== []) {
            $query->where(function ($nested) use ($orConditions, $table): void {
                foreach ($orConditions as $index => $condition) {
                    if (! is_array($condition)) {
                        continue;
                    }

                    $method = $index === 0 ? 'where' : 'orWhere';
                    $nested->{$method}(function ($branch) use ($condition, $table): void {
                        self::applyCondition($branch, $condition, $table);
                    });
                }
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    protected static function applyCondition(EloquentBuilder|QueryBuilder $query, array $condition, string $table): void
    {
        $field = trim((string) ($condition['field'] ?? ''));

        if ($field === '') {
            return;
        }

        $operator = (string) ($condition['operator'] ?? 'equals');
        $value = $condition['value'] ?? null;
        $column = self::resolveColumn($field, $table);

        match ($operator) {
            'not_equals' => self::whereNotEqualsInsensitive($query, $column, $value),
            'contains' => $query->where($column, 'like', '%'.$value.'%'),
            'gt' => $query->where($column, '>', $value),
            'gte' => $query->where($column, '>=', $value),
            'lt' => $query->where($column, '<', $value),
            'lte' => $query->where($column, '<=', $value),
            'is_empty' => $query->where(function ($nested) use ($column): void {
                $nested->whereNull($column)->orWhere($column, '=', '');
            }),
            'is_not_empty' => $query->where(function ($nested) use ($column): void {
                $nested->whereNotNull($column)->where($column, '!=', '');
            }),
            'is_null' => $query->whereNull($column),
            'is_not_null' => $query->whereNotNull($column),
            'on_date' => $query->whereDate($column, (string) $value),
            default => self::whereEqualsInsensitive($query, $column, $value),
        };
    }

    protected static function whereEqualsInsensitive(EloquentBuilder|QueryBuilder $query, string $column, mixed $value): void
    {
        if (str_contains($column, '->')) {
            $query->where($column, '=', $value);

            return;
        }

        $wrapped = self::wrapIdentifier($query, $column);
        $query->whereRaw("LOWER({$wrapped}) = ?", [strtolower((string) $value)]);
    }

    protected static function whereNotEqualsInsensitive(EloquentBuilder|QueryBuilder $query, string $column, mixed $value): void
    {
        if (str_contains($column, '->')) {
            $query->where($column, '!=', $value);

            return;
        }

        $wrapped = self::wrapIdentifier($query, $column);
        $query->whereRaw("LOWER({$wrapped}) != ?", [strtolower((string) $value)]);
    }

    protected static function wrapIdentifier(EloquentBuilder|QueryBuilder $query, string $column): string
    {
        $grammar = $query->getGrammar();
        $parts = explode('.', $column);

        return collect($parts)
            ->map(fn (string $part): string => $grammar->wrap($part))
            ->implode('.');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $condition
     */
    public static function rowMatches(array $row, array $condition): bool
    {
        $field = (string) ($condition['field'] ?? '');
        $operator = (string) ($condition['operator'] ?? 'equals');
        $value = $condition['value'] ?? null;
        $actual = data_get($row, $field);

        if (is_array($actual)) {
            $stringValues = array_map(static fn (mixed $item): string => (string) $item, $actual);

            return match ($operator) {
                'not_equals' => ! in_array((string) $value, $stringValues, true),
                'contains' => collect($stringValues)->contains(
                    fn (string $item): bool => str_contains(strtolower($item), strtolower((string) $value))
                ),
                'is_empty' => $stringValues === [],
                'is_not_empty' => $stringValues !== [],
                'is_null' => $stringValues === [],
                'is_not_null' => $stringValues !== [],
                default => in_array((string) $value, $stringValues, true),
            };
        }

        return match ($operator) {
            'not_equals' => ! self::valuesEqual($actual, $value),
            'contains' => str_contains(strtolower((string) $actual), strtolower((string) $value)),
            'gt' => (float) $actual > (float) $value,
            'gte' => (float) $actual >= (float) $value,
            'lt' => (float) $actual < (float) $value,
            'lte' => (float) $actual <= (float) $value,
            'is_empty' => blank($actual),
            'is_not_empty' => filled($actual),
            'is_null' => $actual === null || $actual === '',
            'is_not_null' => $actual !== null && $actual !== '',
            default => self::valuesEqual($actual, $value),
        };
    }

    protected static function valuesEqual(mixed $actual, mixed $expected): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual == (float) $expected;
        }

        return strcasecmp((string) $actual, (string) $expected) === 0;
    }

    protected static function resolveColumn(string $field, string $table): string
    {
        if (str_contains($field, '->')) {
            return $field;
        }

        if (str_starts_with($field, 'data.') && $table !== '') {
            return $table.'.data->'.substr($field, 5);
        }

        if ($table !== '' && ! str_contains($field, '.')) {
            return $table.'.'.$field;
        }

        return $field;
    }
}
