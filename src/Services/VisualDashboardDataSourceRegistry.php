<?php

namespace Spiggle\VisualDashboards\Services;

use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use Spiggle\DynamicFields\Services\ModelDiscoverer;
use Spiggle\FormBuilder\Models\Form;
use Spiggle\VisualDashboards\Contracts\DataSource;
use Spiggle\VisualDashboards\DataSources\FormSubmissionDataSource;
use Spiggle\VisualDashboards\Support\DataSourceDescriptor;
use Throwable;

class VisualDashboardDataSourceRegistry
{
    /**
     * @var array<string, string>
     */
    protected const GROUP_DISCOVERERS = [
        'Metrics' => 'discoverRegisteredSources',
        'Models' => 'discoverModels',
        'Filament Resources' => 'discoverFilamentResources',
        'Forms' => 'discoverFormBuilderForms',
        'Tables' => 'discoverDatabaseTables',
    ];

    /**
     * @var array<string, array<int, DataSourceDescriptor>>|null
     */
    protected ?array $groupedCache = null;

    /**
     * @var array<string, array<int, DataSourceDescriptor>>
     */
    protected array $groupSourcesCache = [];

    /**
     * @var array<string, DataSourceDescriptor>
     */
    protected array $descriptorCache = [];

    /**
     * @return list<string>
     */
    public function groupNames(): array
    {
        return array_keys(self::GROUP_DISCOVERERS);
    }

    /**
     * @return array<int, DataSourceDescriptor>
     */
    public function sourcesForGroup(string $group): array
    {
        if (isset($this->groupSourcesCache[$group])) {
            return $this->groupSourcesCache[$group];
        }

        $discoverer = self::GROUP_DISCOVERERS[$group] ?? null;

        if ($discoverer === null || ! method_exists($this, $discoverer)) {
            return $this->groupSourcesCache[$group] = [];
        }

        $items = $this->{$discoverer}();
        usort($items, fn (DataSourceDescriptor $a, DataSourceDescriptor $b): int => strcasecmp($a->label, $b->label));

        foreach ($items as $descriptor) {
            $this->descriptorCache[$descriptor->key] = $descriptor;
        }

        return $this->groupSourcesCache[$group] = $items;
    }

    /**
     * @return array<string, string>
     */
    public function sourceOptionsForGroup(string $group): array
    {
        return collect($this->sourcesForGroup($group))
            ->mapWithKeys(fn (DataSourceDescriptor $descriptor): array => [
                $descriptor->key => $descriptor->label,
            ])
            ->all();
    }

    /**
     * @return array<string, array<int, DataSourceDescriptor>>
     */
    public function grouped(): array
    {
        if ($this->groupedCache !== null) {
            return $this->groupedCache;
        }

        $groups = [];

        foreach ($this->groupNames() as $group) {
            $items = $this->sourcesForGroup($group);

            if ($items !== []) {
                $groups[$group] = $items;
            }
        }

        ksort($groups);

        return $this->groupedCache = $groups;
    }

    /**
     * @return array<int, DataSourceDescriptor>
     */
    public function all(): array
    {
        return collect($this->grouped())
            ->flatten(1)
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function flatOptions(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (DataSourceDescriptor $descriptor): array => [
                $descriptor->key => $descriptor->label.' ('.$descriptor->group.')',
            ])
            ->all();
    }

    public function find(string $key): ?DataSourceDescriptor
    {
        if (isset($this->descriptorCache[$key])) {
            return $this->descriptorCache[$key];
        }

        foreach ($this->groupNames() as $group) {
            foreach ($this->sourcesForGroup($group) as $descriptor) {
                if ($descriptor->key === $key) {
                    return $descriptor;
                }
            }
        }

        return null;
    }

    public function groupForKey(string $key): ?string
    {
        $descriptor = $this->find($key);

        return $descriptor?->group;
    }

    /**
     * Resolve all filterable / selectable fields for a registry source key.
     *
     * @return array<string, string>
     */
    public function fieldsForKey(string $key): array
    {
        $descriptor = $this->find($key);

        if (! $descriptor) {
            return [];
        }

        return $this->fieldsForDescriptor($descriptor);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function relatedSourcesFor(string $sourceKey): array
    {
        return app(RelatedSourceDiscoverer::class)->discoverForSourceKey($sourceKey);
    }

    /**
     * Resolve a registry key into dashlet query config fragments.
     *
     * @return array<string, mixed>
     */
    public function configForKey(string $key, ?string $field = null): array
    {
        $descriptor = $this->find($key);

        if (! $descriptor) {
            return [
                'source_kind' => 'registered',
                'data_source' => $key,
            ];
        }

        return match ($descriptor->kind) {
            'registered' => [
                'source_kind' => 'registered',
                'data_source' => $descriptor->meta['registry_key'] ?? $key,
            ],
            'model' => [
                'source_kind' => 'model',
                'table_name' => $descriptor->meta['table'] ?? '',
                'data_source' => $key,
                'columns' => $field ? [$field] : [],
            ],
            'filament_resource' => [
                'source_kind' => 'table',
                'table_name' => $descriptor->meta['table'] ?? '',
                'data_source' => 'filament:'.$descriptor->meta['resource'],
                'columns' => $field ? [$field] : [],
            ],
            'form' => [
                'source_kind' => 'form',
                'data_source' => 'form_submissions',
                'filters' => [
                    'form_id' => $descriptor->meta['form_id'] ?? null,
                ],
                'columns' => $field ? [$field] : [],
            ],
            'table' => [
                'source_kind' => 'table',
                'table_name' => $descriptor->meta['table'] ?? $key,
                'data_source' => 'table:'.$descriptor->meta['table'],
                'columns' => $field ? [$field] : [],
            ],
            default => [
                'source_kind' => 'registered',
                'data_source' => $key,
            ],
        };
    }

    /**
     * @return array<int, DataSourceDescriptor>
     */
    protected function discoverRegisteredSources(): array
    {
        $registry = app(DashletRegistry::class);
        $descriptors = [];

        foreach ($registry->allDataSources() as $source) {
            if (! $source instanceof DataSource) {
                continue;
            }

            $descriptors[] = new DataSourceDescriptor(
                key: 'registered:'.$source->key(),
                label: $source->label(),
                group: 'Metrics',
                kind: 'registered',
                fields: $source->columns(),
                meta: ['registry_key' => $source->key()],
            );
        }

        return $descriptors;
    }

    /**
     * @return array<int, DataSourceDescriptor>
     */
    protected function discoverModels(): array
    {
        $models = $this->modelClassMap();
        $descriptors = [];

        foreach ($models as $class => $label) {
            try {
                /** @var Model $instance */
                $instance = app($class);
                $table = $instance->getTable();
                $fields = $this->columnsForTable($table);

                $descriptors[] = new DataSourceDescriptor(
                    key: 'model:'.$class,
                    label: class_basename($class),
                    group: 'Models',
                    kind: 'model',
                    fields: $fields,
                    meta: ['class' => $class, 'table' => $table],
                );
            } catch (Throwable) {
                continue;
            }
        }

        return $descriptors;
    }

    /**
     * @return array<string, string>
     */
    protected function modelClassMap(): array
    {
        if (class_exists(ModelDiscoverer::class)) {
            return app(ModelDiscoverer::class)->discover();
        }

        return $this->fallbackModelDiscovery();
    }

    /**
     * @return array<string, string>
     */
    protected function fallbackModelDiscovery(): array
    {
        $paths = config('visual-dashboards.discovery.model_paths', ['app/Models']);
        $exclude = config('visual-dashboards.discovery.exclude_models', []);
        $found = [];

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

                $class = $this->classFromPath($file->getPathname(), $absolute, $relative);

                if (! $class || in_array($class, $exclude, true) || ! $this->isEloquentModel($class)) {
                    continue;
                }

                $found[$class] = class_basename($class).' ('.$class.')';
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * @return array<int, DataSourceDescriptor>
     */
    protected function discoverFilamentResources(): array
    {
        if (! class_exists(Filament::class)) {
            return [];
        }

        $descriptors = [];
        $panel = Filament::getCurrentPanel() ?? Filament::getDefaultPanel();

        if (! $panel) {
            return [];
        }

        foreach ($panel->getResources() as $resourceClass) {
            if (! is_subclass_of($resourceClass, Resource::class)) {
                continue;
            }

            $modelClass = $resourceClass::getModel();

            if (! is_string($modelClass) || ! class_exists($modelClass) || ! $this->isEloquentModel($modelClass)) {
                continue;
            }

            try {
                /** @var Model $instance */
                $instance = app($modelClass);
                $table = $instance->getTable();
                $label = $resourceClass::getPluralModelLabel() ?: class_basename($modelClass);

                $descriptors[] = new DataSourceDescriptor(
                    key: 'filament:'.$resourceClass,
                    label: $label,
                    group: 'Filament Resources',
                    kind: 'filament_resource',
                    fields: $this->columnsForTable($table),
                    meta: [
                        'resource' => $resourceClass,
                        'model' => $modelClass,
                        'table' => $table,
                    ],
                );
            } catch (Throwable) {
                continue;
            }
        }

        return $descriptors;
    }

    /**
     * @return array<int, DataSourceDescriptor>
     */
    protected function discoverFormBuilderForms(): array
    {
        if (! class_exists(Form::class)) {
            return [];
        }

        $table = (string) config('form-builder.tables.forms', 'form_builder_forms');

        if (! Schema::hasTable($table)) {
            return [];
        }

        $descriptors = [];

        Form::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->each(function ($form) use (&$descriptors): void {
                $descriptors[] = new DataSourceDescriptor(
                    key: 'form:'.$form->getKey(),
                    label: (string) $form->name,
                    group: 'Forms',
                    kind: 'form',
                    fields: $this->fieldsForForm((int) $form->getKey()),
                    meta: [
                        'form_id' => (int) $form->getKey(),
                        'slug' => (string) ($form->slug ?? ''),
                    ],
                );
            });

        return $descriptors;
    }

    /**
     * @return array<int, DataSourceDescriptor>
     */
    protected function discoverDatabaseTables(): array
    {
        $allowlist = config('visual-dashboards.discovery.tables');

        if ($allowlist === null) {
            $allowlist = config('visual-dashboards.entities.tables', ['users']);
        }

        $tables = collect($allowlist);

        foreach ($this->modelClassMap() as $class => $label) {
            try {
                /** @var Model $instance */
                $instance = app($class);
                $tables->push($instance->getTable());
            } catch (Throwable) {
                continue;
            }
        }

        $exclude = config('visual-dashboards.discovery.exclude_tables', [
            'migrations',
            'password_reset_tokens',
            'personal_access_tokens',
            'failed_jobs',
            'jobs',
            'job_batches',
            'cache',
            'cache_locks',
            'sessions',
        ]);

        $descriptors = [];

        foreach ($tables->unique()->filter()->values() as $table) {
            if (! is_string($table) || in_array($table, $exclude, true) || ! Schema::hasTable($table)) {
                continue;
            }

            $descriptors[] = new DataSourceDescriptor(
                key: 'table:'.$table,
                label: Str::headline(str_replace('_', ' ', $table)),
                group: 'Tables',
                kind: 'table',
                fields: $this->columnsForTable($table),
                meta: ['table' => $table],
            );
        }

        return $descriptors;
    }

    /**
     * @return array<string, string>
     */
    protected function fieldsForDescriptor(DataSourceDescriptor $descriptor): array
    {
        return match ($descriptor->kind) {
            'form' => $this->fieldsForForm((int) ($descriptor->meta['form_id'] ?? 0)),
            'registered' => $this->fieldsForRegisteredSource($descriptor),
            default => $descriptor->fields,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function fieldsForRegisteredSource(DataSourceDescriptor $descriptor): array
    {
        $registryKey = (string) ($descriptor->meta['registry_key'] ?? '');

        if ($registryKey === '') {
            return $descriptor->fields;
        }

        try {
            return app(DashletRegistry::class)->dataSource($registryKey)->columns();
        } catch (Throwable) {
            return $descriptor->fields;
        }
    }

    /**
     * @return array<string, string>
     */
    protected function fieldsForForm(int $formId): array
    {
        if ($formId <= 0) {
            return [];
        }

        try {
            $source = app(FormSubmissionDataSource::class);

            if (! $source->available()) {
                return [];
            }

            return $source->columns(['filters' => ['form_id' => $formId]]);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    protected function columnsForTable(string $table): array
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

    protected function classFromPath(string $path, string $baseDir, string $relativeBase): ?string
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

    protected function isEloquentModel(string $class): bool
    {
        try {
            if (! class_exists($class)) {
                return false;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                return false;
            }

            return $reflection->isSubclassOf(Model::class);
        } catch (Throwable) {
            return false;
        }
    }
}
