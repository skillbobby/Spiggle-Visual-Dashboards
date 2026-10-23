<?php

namespace Spiggle\VisualDashboards\DataSources;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spiggle\VisualDashboards\Support\QueryConfig;
use Spiggle\VisualDashboards\Support\QueryFilterApplier;

class DatabaseTableDataSource extends BaseDataSource
{
    /**
     * @var list<string>
     */
    protected array $hiddenColumns = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    public function __construct(protected ?string $table = null) {}

    public function forTable(string $table): static
    {
        $clone = clone $this;
        $clone->table = $table;

        return $clone;
    }

    public function key(): string
    {
        return 'table:'.(string) $this->table;
    }

    public function label(): string
    {
        return Str::headline((string) $this->table);
    }

    /**
     * @return array<string, string>
     */
    public function filterSchema(): array
    {
        return [
            'days' => 'Days back',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function columns(array $context = []): array
    {
        $table = $this->safeTable();

        if ($table === null) {
            return [];
        }

        $options = [];

        foreach (Schema::getColumnListing($table) as $column) {
            if (in_array($column, $this->hiddenColumns, true)) {
                continue;
            }

            $options[$column] = Str::headline($column);
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function previewRows(array $config = [], int $limit = 25): array
    {
        $table = $this->safeTable();

        if ($table === null) {
            return [];
        }

        $normalized = QueryConfig::normalize($config);
        $select = array_keys($this->columns());

        if ($select === []) {
            return [];
        }

        $query = DB::table($table)->select($select);

        $this->applyWindow($query, $normalized, $table);
        QueryFilterApplier::apply($query, $normalized, $table);

        if (Schema::hasColumn($table, 'id')) {
            $query->orderByDesc('id');
        }

        $rows = $query
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();

        return $this->finalizeRows($rows, $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function chartPayload(string $type, array $config = []): array
    {
        $rows = $this->previewRows($config, (int) config('visual-dashboards.query.max_rows', 500));
        $columns = array_keys($this->columns());
        $labelColumn = $this->firstPresent($rows, ['name', 'email', 'title', 'label', $columns[0] ?? 'id']);
        $valueColumn = $this->firstNumericColumn($rows, $columns);

        return $this->toChartPayload(
            $type,
            array_map(static fn (array $row): string => (string) ($row[$labelColumn] ?? ''), $rows),
            array_map(static fn (array $row): float => (float) ($row[$valueColumn] ?? 1), $rows),
            $this->label()
        );
    }

    /**
     * @return list<string>
     */
    public static function allowedTables(): array
    {
        $allowlist = config('visual-dashboards.discovery.tables');

        if ($allowlist === null) {
            $allowlist = config('visual-dashboards.entities.tables', []);
        }

        if (! is_array($allowlist)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static function (mixed $table): ?string {
                $name = (string) $table;

                if ($name === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $name) || ! Schema::hasTable($name)) {
                    return null;
                }

                return $name;
            },
            $allowlist
        )));
    }

    /**
     * @return array<string, string>
     */
    public static function tableOptions(): array
    {
        $options = [];

        foreach (self::allowedTables() as $table) {
            $options[$table] = Str::headline($table);
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function applyWindow($query, array $normalized, string $table): void
    {
        $days = QueryConfig::windowDays($normalized);

        if ($days === null || ! Schema::hasColumn($table, 'created_at')) {
            return;
        }

        $query->where($table.'.created_at', '>=', now()->subDays($days));
    }

    protected function safeTable(): ?string
    {
        $table = (string) $this->table;

        if ($table === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $table) || ! Schema::hasTable($table)) {
            return null;
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

        if (is_array($exclude) && in_array($table, $exclude, true)) {
            return null;
        }

        if (in_array($table, self::allowedTables(), true)) {
            return $table;
        }

        // Tables discovered via Models / Filament / Tables registry are model-backed or explicitly listed.
        return \Spiggle\VisualDashboards\Support\ModelRelationshipIntrospector::modelClassForTable($table) !== null
            ? $table
            : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $candidates
     */
    protected function firstPresent(array $rows, array $candidates): string
    {
        $first = $rows[0] ?? [];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && array_key_exists($candidate, $first)) {
                return $candidate;
            }
        }

        return (string) array_key_first($first);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    protected function firstNumericColumn(array $rows, array $columns): string
    {
        $first = $rows[0] ?? [];

        foreach ($columns as $column) {
            if (isset($first[$column]) && is_numeric($first[$column]) && ! in_array($column, ['id', 'user_id'], true)) {
                return $column;
            }
        }

        return $columns[0] ?? 'id';
    }
}
