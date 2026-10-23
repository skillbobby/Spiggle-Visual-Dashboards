<?php

namespace Spiggle\VisualDashboards\DataSources;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spiggle\VisualDashboards\Support\QueryConfig;
use Spiggle\VisualDashboards\Support\QueryFilterApplier;

class UserMetricsDataSource extends BaseDataSource
{
    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Users';
    }

    /**
     * @return array<string, string>
     */
    public function filterSchema(): array
    {
        return [
            'days' => 'Days back',
            'email' => 'Email contains',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function columns(array $context = []): array
    {
        $columns = [
            'id' => 'ID',
            'name' => 'Name',
            'email' => 'Email',
            'created_at' => 'Created at',
        ];

        return array_filter(
            $columns,
            fn (string $label, string $column): bool => Schema::hasColumn($this->userTable(), $column),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function previewRows(array $config = [], int $limit = 25): array
    {
        $normalized = QueryConfig::normalize($config);
        $filters = $normalized['filters'];
        $days = QueryConfig::windowDays($normalized) ?? max(1, (int) ($filters['days'] ?? 14));
        $table = $this->userTable();
        $select = array_intersect(
            ['id', 'name', 'email', 'created_at'],
            array_keys($this->columns())
        );

        if ($select === []) {
            $select = ['id', 'created_at'];
        }

        $query = DB::table($table)
            ->select($select)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at');

        if (filled($filters['email'] ?? null)) {
            $query->where('email', 'like', '%'.$filters['email'].'%');
        }

        QueryFilterApplier::apply($query, $normalized, $table);

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
        $normalized = QueryConfig::normalize($config);
        $filters = $normalized['filters'];
        $days = QueryConfig::windowDays($normalized) ?? max(1, (int) ($filters['days'] ?? 14));
        $table = $this->userTable();

        $query = DB::table($table)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('day')
            ->orderBy('day');

        if (filled($filters['email'] ?? null)) {
            $query->where('email', 'like', '%'.$filters['email'].'%');
        }

        QueryFilterApplier::apply($query, $normalized, $table);

        $rows = $query
            ->limit(100)
            ->get()
            ->map(fn ($row): array => ['day' => $row->day, 'total' => (int) $row->total])
            ->all();

        $labels = array_column($rows, 'day');
        $values = array_column($rows, 'total');

        if ($values === []) {
            $labels = [now()->toDateString()];
            $values = [0];
        }

        return $this->toChartPayload($type, $labels, $values, 'New users');
    }

    protected function userTable(): string
    {
        $model = config('visual-dashboards.user_model', 'App\\Models\\User');

        return (new $model)->getTable();
    }
}
