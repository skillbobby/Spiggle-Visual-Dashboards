<?php

namespace Spiggle\VisualDashboards\Services;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spiggle\VisualDashboards\Support\ModelRelationshipIntrospector;
use Throwable;

class ChartableModelScanner
{
    /**
     * Eloquent models that currently have rows, with the columns worth charting.
     *
     * @return list<array{
     *     class: class-string<Model>,
     *     table: string,
     *     label: string,
     *     count: int,
     *     categories: list<array{field: string, distinct: int, label: string, join: array<string, mixed>|null}>,
     *     measures: list<array{field: string, currency: bool, label: string}>,
     *     dates: list<string>,
     *     booleans: list<string>,
     *     columns: list<string>
     * }>
     */
    public function profiles(): array
    {
        $profiles = [];

        foreach (app(VisualDashboardDataSourceRegistry::class)->sourcesForGroup('Models') as $descriptor) {
            $class = $descriptor->meta['class'] ?? null;

            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            try {
                $profile = $this->profile($class);
            } catch (Throwable) {
                continue;
            }

            if ($profile !== null) {
                $profiles[] = $profile;
            }
        }

        usort($profiles, static fn (array $left, array $right): int => $right['count'] <=> $left['count']);

        return $profiles;
    }

    /**
     * @param  class-string<Model>  $class
     * @return array{
     *     class: class-string<Model>,
     *     table: string,
     *     label: string,
     *     count: int,
     *     categories: list<array{field: string, distinct: int, label: string, join: array<string, mixed>|null}>,
     *     measures: list<array{field: string, currency: bool, label: string}>,
     *     dates: list<string>,
     *     booleans: list<string>,
     *     columns: list<string>
     * }|null
     */
    protected function profile(string $class): ?array
    {
        /** @var Model $model */
        $model = new $class;
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            return null;
        }

        $count = $model->newQuery()->count();

        if ($count < 1) {
            return null;
        }

        $categories = [];
        $measures = [];
        $dates = [];
        $booleans = [];
        $checkedCategories = 0;

        foreach ($this->columnTypes($table) as $column => $type) {
            $kind = $this->columnKind($column, $type);

            if ($kind === 'boolean') {
                $booleans[] = $column;
            } elseif ($kind === 'date') {
                $dates[] = $column;
            } elseif ($kind === 'measure') {
                $measures[] = [
                    'field' => $column,
                    'currency' => $this->isCurrency($column),
                    'label' => Str::headline($column),
                ];
            } elseif ($kind === 'category' && $checkedCategories < 8) {
                $checkedCategories++;
                $distinct = $this->distinctCount($model, $column);

                if ($distinct >= 2 && $distinct <= 12 && $distinct < $count) {
                    $categories[] = [
                        'field' => $column,
                        'distinct' => $distinct,
                        'label' => Str::headline($column),
                        'join' => null,
                    ];
                }
            }
        }

        foreach ($this->relationCategories($model, $count) as $category) {
            $denormalized = Str::before($category['field'], '.').'_name';
            $categories = array_values(array_filter(
                $categories,
                static fn (array $existing): bool => $existing['field'] !== $denormalized
            ));
            $categories[] = $category;
        }

        usort($categories, function (array $left, array $right): int {
            $penalty = $this->nameFieldPenalty($left['field']) <=> $this->nameFieldPenalty($right['field']);

            return $penalty !== 0 ? $penalty : $right['distinct'] <=> $left['distinct'];
        });

        $businessDates = array_values(array_filter(
            $dates,
            static fn (string $column): bool => ! in_array($column, ['created_at', 'updated_at', 'deleted_at'], true)
        ));

        return [
            'class' => $class,
            'table' => $table,
            'label' => Str::plural(Str::headline(class_basename($class))),
            'count' => $count,
            'categories' => $categories,
            'measures' => $measures,
            'dates' => $businessDates !== [] ? $businessDates : $dates,
            'booleans' => $booleans,
            'columns' => $this->displayColumns($categories, $measures, $businessDates !== [] ? $businessDates : $dates),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function columnTypes(string $table): array
    {
        try {
            $columns = Schema::getColumns($table);
        } catch (Throwable) {
            return array_fill_keys(Schema::getColumnListing($table), '');
        }

        $types = [];

        foreach ($columns as $column) {
            $name = (string) ($column['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $types[$name] = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));
        }

        return $types;
    }

    protected function columnKind(string $column, string $type): string
    {
        if (
            in_array($column, ['id', 'password', 'remember_token', 'email', 'slug', 'uuid', 'token'], true)
            || str_ends_with($column, '_id')
            || str_starts_with($column, 'two_factor')
            || in_array($column, ['description', 'notes', 'body', 'content', 'payload', 'metadata', 'settings', 'data'], true)
        ) {
            return 'ignore';
        }

        if (str_starts_with($column, 'is_') || str_contains($type, 'bool')) {
            return 'boolean';
        }

        if (str_contains($type, 'json') || str_contains($type, 'blob') || str_contains($type, 'text')) {
            return 'ignore';
        }

        if (
            in_array($column, ['created_at', 'updated_at', 'deleted_at', 'email_verified_at'], true)
            || str_contains($type, 'date')
            || str_contains($type, 'time')
            || preg_match('/(_at|_on|_date)$/', $column) === 1
        ) {
            return 'date';
        }

        if (
            preg_match('/int|decimal|numeric|float|double|real/', $type) === 1
            || preg_match('/(cost|price|budget|salary|amount|tax|shipping|spend|fee|qty|quantity|total|days|rate|progress)$/', $column) === 1
        ) {
            return 'measure';
        }

        if ($type === '' || str_contains($type, 'char') || str_contains($type, 'string') || str_contains($type, 'enum')) {
            return 'category';
        }

        return 'ignore';
    }

    protected function distinctCount(Model $model, string $column): int
    {
        try {
            return $model->newQuery()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->limit(13)
                ->pluck($column)
                ->map(static function (mixed $value): string {
                    if ($value instanceof BackedEnum) {
                        return (string) $value->value;
                    }

                    return trim((string) $value);
                })
                ->filter(static fn (string $value): bool => $value !== '')
                ->unique()
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return list<array{field: string, distinct: int, label: string, join: array<string, mixed>|null}>
     */
    protected function relationCategories(Model $model, int $count): array
    {
        $categories = [];

        foreach (ModelRelationshipIntrospector::relationsForModel($model::class) as $relation) {
            if (($relation['type'] ?? '') !== 'belongsTo') {
                continue;
            }

            $name = (string) ($relation['name'] ?? '');
            $foreignKey = (string) ($relation['foreign_key'] ?? '');
            $relatedTable = (string) ($relation['related_table'] ?? '');

            if ($name === '' || $foreignKey === '' || ! Schema::hasTable($relatedTable) || ! Schema::hasColumn($relatedTable, 'name')) {
                continue;
            }

            $distinct = $this->distinctCount($model, $foreignKey);

            if ($distinct < 2 || $distinct > 12 || $distinct >= $count) {
                continue;
            }

            $categories[] = [
                'field' => $name.'.name',
                'distinct' => $distinct,
                'label' => Str::headline($name),
                'join' => [
                    'relation' => $name,
                    'alias' => (string) ($relation['alias'] ?? $name),
                    'type' => 'belongsTo',
                    'fields' => ['name'],
                ],
            ];
        }

        return $categories;
    }

    /**
     * @param  list<array{field: string, distinct: int, label: string, join: array<string, mixed>|null}>  $categories
     * @param  list<array{field: string, currency: bool, label: string}>  $measures
     * @param  list<string>  $dates
     * @return list<string>
     */
    protected function displayColumns(array $categories, array $measures, array $dates): array
    {
        $columns = [];

        foreach ($categories as $category) {
            if (! str_contains($category['field'], '.')) {
                $columns[] = $category['field'];
            }
        }

        foreach ($dates as $date) {
            $columns[] = $date;
        }

        foreach ($measures as $measure) {
            $columns[] = $measure['field'];
        }

        return array_slice(array_values(array_unique($columns)), 0, 5);
    }

    protected function nameFieldPenalty(string $field): int
    {
        if (str_contains($field, '.')) {
            return 0;
        }

        return $field === 'name' || str_ends_with($field, '_name') ? 1 : 0;
    }

    protected function isCurrency(string $column): bool
    {
        return preg_match('/(cost|price|budget|salary|amount|tax|shipping|spend|fee)$/', $column) === 1;
    }
}
