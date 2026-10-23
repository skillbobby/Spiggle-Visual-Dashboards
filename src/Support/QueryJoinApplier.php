<?php

namespace Spiggle\VisualDashboards\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

class QueryJoinApplier
{
    /**
     * @var list<string>
     */
    protected const HIDDEN_COLUMNS = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * Enrich primary rows with columns from related Eloquent models.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $rows, array $config): array
    {
        $query = VisualQueryBuilder::normalize($config);
        $joins = self::resolveJoins($query);

        if ($joins === [] || $rows === []) {
            return $rows;
        }

        $modelClass = self::resolveModelClass($config, $query);

        if ($modelClass === null) {
            return $rows;
        }

        try {
            /** @var Model $instance */
            $instance = app($modelClass);
        } catch (Throwable) {
            return $rows;
        }

        $parentKey = $instance->getKeyName();
        $parentIds = collect($rows)
            ->pluck($parentKey)
            ->filter(fn ($id): bool => filled($id))
            ->unique()
            ->values();

        if ($parentIds->isEmpty()) {
            return $rows;
        }

        $relationNames = collect($joins)
            ->pluck('relation')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($relationNames === []) {
            return $rows;
        }

        try {
            $loadedParents = $instance->newQuery()
                ->with($relationNames)
                ->whereIn($parentKey, $parentIds)
                ->get()
                ->keyBy($parentKey);
        } catch (Throwable) {
            return $rows;
        }

        return array_map(function (array $row) use ($loadedParents, $joins, $parentKey): array {
            $parent = $loadedParents->get($row[$parentKey] ?? null);

            if (! $parent instanceof Model) {
                return $row;
            }

            foreach ($joins as $join) {
                $relationName = (string) ($join['relation'] ?? '');

                if ($relationName === '' || ! $parent->relationLoaded($relationName)) {
                    continue;
                }

                $alias = (string) ($join['alias'] ?? $relationName);
                $fields = is_array($join['fields'] ?? null) ? $join['fields'] : [];

                $row = self::mergeRelationIntoRow(
                    $row,
                    $parent->getRelation($relationName),
                    $alias,
                    $fields
                );
            }

            return $row;
        }, $rows);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    protected static function resolveJoins(array $query): array
    {
        if (filled($query['joins'] ?? [])) {
            return is_array($query['joins']) ? array_values($query['joins']) : [];
        }

        return is_array($query['related_joins'] ?? null) ? $query['related_joins'] : [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     */
    protected static function resolveModelClass(array $config, array $query): ?string
    {
        $primary = (string) ($query['primary_source'] ?? '');

        if ($primary !== '') {
            $fromPrimary = ModelRelationshipIntrospector::modelClassForSourceKey($primary);

            if ($fromPrimary !== null) {
                return $fromPrimary;
            }
        }

        $normalized = QueryConfig::normalize($config);
        $dataSource = (string) ($normalized['data_source'] ?? '');

        if ($dataSource !== '') {
            $fromDataSource = ModelRelationshipIntrospector::modelClassForSourceKey($dataSource);

            if ($fromDataSource !== null) {
                return $fromDataSource;
            }
        }

        $tableName = (string) ($normalized['table_name'] ?? '');

        if ($tableName === '') {
            return null;
        }

        return ModelRelationshipIntrospector::modelClassForTable($tableName);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected static function mergeRelationIntoRow(array $row, mixed $related, string $alias, array $fields): array
    {
        if ($related instanceof Collection || is_array($related)) {
            return self::mergeCollectionRelation($row, collect($related), $alias, $fields);
        }

        if ($related instanceof Model) {
            foreach ($related->getAttributes() as $column => $value) {
                if (self::shouldSkipColumn($column, $fields)) {
                    continue;
                }

                $row[$alias.'.'.$column] = $value;
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected static function mergeCollectionRelation(array $row, Collection $collection, string $alias, array $fields): array
    {
        $row[$alias.'.count'] = $collection->count();

        if ($collection->isEmpty()) {
            return $row;
        }

        $columns = $fields !== []
            ? $fields
            : self::columnsFromRelated($collection->first());

        foreach ($columns as $column) {
            if (self::shouldSkipColumn((string) $column, [])) {
                continue;
            }

            $values = $collection->map(function (mixed $item) use ($column): mixed {
                if ($item instanceof Model) {
                    return $item->getAttribute($column);
                }

                if (is_array($item)) {
                    return $item[$column] ?? null;
                }

                if (is_object($item)) {
                    return $item->{$column} ?? null;
                }

                return null;
            });

            $row[$alias.'.'.$column] = self::aggregateCollectionValue($values, (string) $column);
        }

        return $row;
    }

    /**
     * @return list<string>
     */
    protected static function columnsFromRelated(mixed $item): array
    {
        if ($item instanceof Model) {
            return array_keys($item->getAttributes());
        }

        if (is_array($item)) {
            return array_keys($item);
        }

        if (is_object($item)) {
            return array_keys(get_object_vars($item));
        }

        return [];
    }

    protected static function shouldSkipColumn(string $column, array $fields): bool
    {
        if (in_array($column, self::HIDDEN_COLUMNS, true)) {
            return true;
        }

        return $fields !== [] && ! in_array($column, $fields, true);
    }

    protected static function aggregateCollectionValue(Collection $values, string $column): mixed
    {
        $nonNull = $values->filter(fn (mixed $value): bool => $value !== null && $value !== '');

        if ($nonNull->isEmpty()) {
            return null;
        }

        if ($nonNull->every(fn (mixed $value): bool => is_numeric($value))) {
            return $nonNull->sum();
        }

        return $nonNull->unique()->values()->all();
    }
}
