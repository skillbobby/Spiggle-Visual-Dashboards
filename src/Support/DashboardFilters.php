<?php

namespace Spiggle\VisualDashboards\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spiggle\VisualDashboards\Models\CustomDashboard;

class DashboardFilters
{
    /**
     * @return list<array{id: string, label: string, control: string, fields: list<string>}>
     */
    public static function definitions(CustomDashboard $dashboard): array
    {
        $layout = is_array($dashboard->layout_json) ? $dashboard->layout_json : [];
        $filters = is_array($layout['filters'] ?? null) ? $layout['filters'] : [];
        $normalized = [];

        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $id = trim((string) ($filter['id'] ?? ''));
            $fields = array_values(array_filter(array_map(
                static fn (mixed $field): string => self::fieldKey((string) $field),
                is_array($filter['fields'] ?? null) ? $filter['fields'] : []
            )));

            if ($id === '' || $fields === []) {
                continue;
            }

            $control = ($filter['control'] ?? '') === 'date' ? 'date' : 'select';
            $label = trim((string) ($filter['label'] ?? ''));

            $normalized[] = [
                'id' => $id,
                'label' => $label !== '' ? $label : Str::headline($fields[0]),
                'control' => $control,
                'fields' => $fields,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<array{id: string, label: string, control: string, fields: list<string>}>  $definitions
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function apply(array $config, array $definitions, array $values): array
    {
        $table = self::tableName($config);

        if ($table === null) {
            return $config;
        }

        $groups = is_array($config['filter_groups'] ?? null) ? $config['filter_groups'] : [];
        $conditions = is_array($groups['conditions'] ?? null) ? $groups['conditions'] : [];

        foreach ($definitions as $definition) {
            $value = trim((string) ($values[$definition['id']] ?? ''));

            if ($value === '') {
                continue;
            }

            $field = self::matchingField($table, $definition['fields']);

            if ($field === null) {
                continue;
            }

            $conditions[] = [
                'field' => $field,
                'operator' => $definition['control'] === 'date' ? 'on_date' : 'equals',
                'value' => $value,
            ];
        }

        $groups['conditions'] = array_values($conditions);
        $config['filter_groups'] = $groups;

        return $config;
    }

    /**
     * @param  list<array{id: string, label: string, control: string, fields: list<string>}>  $definitions
     * @param  list<string>  $tables
     * @return array<string, list<string>>
     */
    public static function options(array $definitions, array $tables): array
    {
        $options = [];

        foreach ($definitions as $definition) {
            if ($definition['control'] !== 'select') {
                $options[$definition['id']] = [];

                continue;
            }

            $values = [];

            foreach ($tables as $table) {
                $field = self::matchingField($table, $definition['fields']);

                if ($field === null) {
                    continue;
                }

                foreach (self::valuesForField($table, $field) as $value) {
                    $values[$value] = $value;
                }
            }

            $list = array_values($values);
            sort($list);
            $options[$definition['id']] = $list;
        }

        return $options;
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, string>
     */
    public static function fieldsForTables(array $tables): array
    {
        $fields = [];

        foreach ($tables as $table) {
            if (! self::isSafeIdentifier($table) || ! Schema::hasTable($table)) {
                continue;
            }

            foreach (Schema::getColumnListing($table) as $column) {
                if (! self::isSafeIdentifier($column) || in_array($column, ['password', 'remember_token'], true)) {
                    continue;
                }

                $fields[$column] = Str::headline($column);
            }

            foreach (self::relationFields($table) as $field => $label) {
                $fields[$field] = $label;
            }
        }

        ksort($fields);

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function tableName(array $config): ?string
    {
        $dataSource = (string) ($config['data_source'] ?? '');
        $modelClass = ModelRelationshipIntrospector::modelClassForSourceKey($dataSource)
            ?? ModelRelationshipIntrospector::modelClassForSourceKey((string) data_get($config, 'query.primary_source', ''));

        if ($modelClass !== null) {
            $table = (new $modelClass)->getTable();

            return self::isSafeIdentifier($table) && Schema::hasTable($table) ? $table : null;
        }

        $table = (string) ($config['table_name'] ?? '');

        if ($table === '' && str_starts_with($dataSource, 'table:')) {
            $table = substr($dataSource, 6);
        }

        return self::isSafeIdentifier($table) && Schema::hasTable($table) ? $table : null;
    }

    /**
     * @param  list<string>  $fields
     */
    public static function matchingField(string $table, array $fields): ?string
    {
        if (! self::isSafeIdentifier($table) || ! Schema::hasTable($table)) {
            return null;
        }

        $modelClass = ModelRelationshipIntrospector::modelClassForTable($table);

        foreach ($fields as $field) {
            if (self::isRelationPath($field) && self::relationExists($modelClass, $field)) {
                return $field;
            }

            $column = self::column($field);

            if ($column !== '' && Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function valuesForField(string $table, string $field): array
    {
        if (self::isRelationPath($field)) {
            [$relation, $column] = explode('.', $field, 2);
            $modelClass = ModelRelationshipIntrospector::modelClassForTable($table);

            if ($modelClass === null) {
                return [];
            }

            /** @var Model $model */
            $model = new $modelClass;

            if (! method_exists($model, $relation)) {
                return [];
            }

            $related = $model->{$relation}()->getRelated();

            return $related->newQuery()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->limit(100)
                ->pluck($column)
                ->map(static fn (mixed $value): string => trim((string) ($value instanceof \BackedEnum ? $value->value : $value)))
                ->filter(static fn (string $value): bool => $value !== '')
                ->values()
                ->all();
        }

        if (! self::isSafeIdentifier($table) || ! self::isSafeIdentifier($field) || ! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->distinct()
            ->orderBy($field)
            ->limit(100)
            ->pluck($field)
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function relationFields(string $table): array
    {
        $modelClass = ModelRelationshipIntrospector::modelClassForTable($table);

        if ($modelClass === null) {
            return [];
        }

        $fields = [];

        foreach (ModelRelationshipIntrospector::relationsForModel($modelClass) as $relation) {
            if (($relation['type'] ?? '') !== 'belongsTo') {
                continue;
            }

            $name = (string) ($relation['name'] ?? '');
            $relatedTable = (string) ($relation['related_table'] ?? '');

            if ($name === '' || ! Schema::hasTable($relatedTable) || ! Schema::hasColumn($relatedTable, 'name')) {
                continue;
            }

            $fields[$name.'.name'] = Str::headline($name).' name';
        }

        return $fields;
    }

    public static function isRelationPath(string $field): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $field) === 1;
    }

    public static function fieldKey(string $field): string
    {
        $field = trim($field);

        return self::isRelationPath($field) ? $field : self::column($field);
    }

    /**
     * @param  class-string<Model>|null  $modelClass
     */
    protected static function relationExists(?string $modelClass, string $field): bool
    {
        if ($modelClass === null || ! self::isRelationPath($field)) {
            return false;
        }

        [$relation, $column] = explode('.', $field, 2);
        /** @var Model $model */
        $model = new $modelClass;

        if (! method_exists($model, $relation)) {
            return false;
        }

        $related = $model->{$relation}()->getRelated();

        return Schema::hasColumn($related->getTable(), $column);
    }

    public static function column(string $field): string
    {
        $field = trim($field);

        return self::isSafeIdentifier($field) ? $field : '';
    }

    public static function isSafeIdentifier(string $value): bool
    {
        return $value !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }
}
