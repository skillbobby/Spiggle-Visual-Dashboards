<?php

namespace Spiggle\VisualDashboards\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Spiggle\DynamicFields\Services\ModelDiscoverer;
use Throwable;

class ModelRelationshipIntrospector
{
    /**
     * Discover Eloquent relationships for a model class.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function relationsForModel(string $modelClass): array
    {
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        try {
            /** @var Model $instance */
            $instance = app($modelClass);
        } catch (Throwable) {
            return [];
        }

        $relations = [];
        $reflection = new ReflectionClass($instance);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()
                || $method->getNumberOfRequiredParameters() > 0
                || $method->getDeclaringClass()->getName() === Model::class
            ) {
                continue;
            }

            $name = $method->getName();

            if (str_starts_with($name, 'get') || str_starts_with($name, 'set')) {
                continue;
            }

            try {
                $return = $method->invoke($instance);
            } catch (Throwable) {
                continue;
            }

            if (! $return instanceof Relation) {
                continue;
            }

            if ($return instanceof MorphTo
                || $return instanceof MorphOne
                || $return instanceof MorphMany
                || $return instanceof MorphToMany
            ) {
                continue;
            }

            if (! ($return instanceof BelongsTo
                || $return instanceof HasMany
                || $return instanceof HasOne
                || $return instanceof BelongsToMany)
            ) {
                continue;
            }

            try {
                $type = match (true) {
                    $return instanceof BelongsTo => 'belongsTo',
                    $return instanceof HasMany => 'hasMany',
                    $return instanceof HasOne => 'hasOne',
                    $return instanceof BelongsToMany => 'belongsToMany',
                    default => class_basename($return),
                };

                $related = $return->getRelated();
                $relatedClass = $related::class;
                $relatedTable = $related->getTable();

                $fields = self::columnsForTable($relatedTable);
                $localKey = match (true) {
                    $return instanceof BelongsTo => $return->getForeignKeyName(),
                    $return instanceof HasMany, $return instanceof HasOne => $return->getLocalKeyName(),
                    $return instanceof BelongsToMany => $instance->getKeyName(),
                    default => 'id',
                };

                $foreignKey = match (true) {
                    $return instanceof BelongsTo => $return->getForeignKeyName(),
                    $return instanceof HasMany, $return instanceof HasOne => $return->getForeignKeyName(),
                    $return instanceof BelongsToMany => $return->getForeignPivotKeyName(),
                    default => 'id',
                };

                $relations[] = [
                    'name' => $name,
                    'key' => $name,
                    'type' => $type,
                    'relation_type' => $type,
                    'related_model' => $relatedClass,
                    'related_label' => class_basename($relatedClass),
                    'label' => class_basename($relatedClass),
                    'related_table' => $relatedTable,
                    'foreign_key' => $foreignKey,
                    'local_key' => $localKey,
                    'owner_key' => method_exists($return, 'getOwnerKeyName') ? $return->getOwnerKeyName() : 'id',
                    'alias' => $name,
                    'fields' => $fields,
                ];
            } catch (Throwable) {
                continue;
            }
        }

        usort($relations, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $relations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function relationsForSourceKey(string $sourceKey): array
    {
        $modelClass = self::modelClassForSourceKey($sourceKey);

        if ($modelClass === null) {
            return [];
        }

        return self::relationsForModel($modelClass);
    }

    public static function modelClassForSourceKey(string $sourceKey): ?string
    {
        if (str_starts_with($sourceKey, 'model:')) {
            $class = substr($sourceKey, 6);

            return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
        }

        if (str_starts_with($sourceKey, 'table:')) {
            return self::modelClassForTable(substr($sourceKey, 6));
        }

        if (str_starts_with($sourceKey, 'filament:')) {
            $resourceClass = substr($sourceKey, 9);

            if (! class_exists($resourceClass)) {
                return null;
            }

            if (! is_subclass_of($resourceClass, \Filament\Resources\Resource::class)) {
                return null;
            }

            $modelClass = $resourceClass::getModel();

            return is_string($modelClass) && class_exists($modelClass) && is_subclass_of($modelClass, Model::class)
                ? $modelClass
                : null;
        }

        return null;
    }

    public static function modelClassForTable(string $table): ?string
    {
        if (class_exists(ModelDiscoverer::class)) {
            foreach (app(ModelDiscoverer::class)->discover() as $class => $label) {
                try {
                    if (app($class)->getTable() === $table) {
                        return $class;
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        $paths = config('visual-dashboards.discovery.model_paths', ['app/Models']);

        foreach ($paths as $relative) {
            $absolute = base_path($relative);

            if (! is_dir($absolute)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute));

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $class = self::classFromPath($file->getPathname(), $absolute, $relative);

                if (! $class || ! is_subclass_of($class, Model::class)) {
                    continue;
                }

                try {
                    if (app($class)->getTable() === $table) {
                        return $class;
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    protected static function classFromPath(string $path, string $baseDir, string $relativeBase): ?string
    {
        $relative = Str::after($path, $baseDir.DIRECTORY_SEPARATOR);
        $relative = str_replace(['/', '\\'], '\\', $relative);
        $classPath = Str::beforeLast($relative, '.php');

        $namespaceRoot = str_replace('/', '\\', trim($relativeBase, '/\\'));
        $namespaceRoot = collect(explode('\\', $namespaceRoot))
            ->map(fn (string $part) => Str::studly($part))
            ->implode('\\');

        return $namespaceRoot.'\\'.$classPath;
    }

    /**
     * @return array<string, string>
     */
    protected static function columnsForTable(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = [];

        foreach (Schema::getColumnListing($table) as $column) {
            $columns[$column] = Str::headline(str_replace('_', ' ', $column));
        }

        return $columns;
    }
}
