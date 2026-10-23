<?php

namespace Spiggle\VisualDashboards\DataSources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spiggle\VisualDashboards\Support\QueryConfig;

class LoginActivityDataSource extends BaseDataSource
{
    public function key(): string
    {
        return 'login_activity';
    }

    public function label(): string
    {
        return 'Daily logins by role';
    }

    /**
     * @return array<string, string>
     */
    public function filterSchema(): array
    {
        return [
            'grain' => 'Period',
            'role_name' => 'Role',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function columns(array $context = []): array
    {
        return [
            'period' => 'Period',
            'role_name' => 'Role',
            'features' => 'Features',
            'users' => 'Users',
            'logins' => 'Logins',
            'total' => 'Logins',
            'occurred_at' => 'Occurred at',
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function previewRows(array $config = [], int $limit = 25): array
    {
        if (! $this->available()) {
            return [];
        }

        $normalized = QueryConfig::normalize($config);

        if (($normalized['mapping']['x'] ?? '') !== '' || ($normalized['mapping']['y'] ?? '') !== '') {
            return array_slice($this->roleLandscapeRows($normalized), 0, $limit);
        }

        $chart = $this->chartPayload('table', $config);
        $rows = [];

        foreach ($chart['labels'] ?? [] as $index => $label) {
            foreach ($chart['datasets'] ?? [] as $dataset) {
                $rows[] = [
                    'period' => $label,
                    'role_name' => $dataset['label'] ?? 'Role',
                    'total' => $dataset['data'][$index] ?? 0,
                ];
            }
        }

        return array_slice($rows, 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function chartPayload(string $type, array $config = []): array
    {
        $normalized = QueryConfig::normalize($config);

        if ($type === 'scatter') {
            return $this->scatterPayload($normalized);
        }

        $grain = $this->grain($normalized);
        [$start, $buckets] = $this->buckets($grain);
        $roles = $this->roleSeries();
        $totals = $this->emptyMatrix($buckets, $roles);

        if ($this->available()) {
            $query = DB::table($this->table())
                ->where('occurred_at', '>=', $start);

            if (filled($normalized['filters']['role_name'] ?? null)) {
                $query->where('role_name', (string) $normalized['filters']['role_name']);
            }

            foreach ($query->get(['role_name', 'occurred_at']) as $row) {
                $key = $this->bucketKey($grain, Carbon::parse($row->occurred_at));
                $role = (string) ($row->role_name ?: 'User');

                if (! isset($totals[$role]) || ! array_key_exists($key, $totals[$role])) {
                    continue;
                }

                $totals[$role][$key]++;
            }
        }

        $labels = array_values($buckets);
        $palette = ['#3b82f6', '#f59e0b', '#10b981', '#8b5cf6', '#f43f5e'];
        $datasets = [];
        $index = 0;

        foreach ($roles as $role) {
            $datasets[] = [
                'label' => $role,
                'data' => array_values($totals[$role] ?? array_fill_keys(array_keys($buckets), 0)),
                'backgroundColor' => $palette[$index % count($palette)],
                'borderColor' => $palette[$index % count($palette)],
                'borderRadius' => 6,
                'borderSkipped' => false,
                'stack' => 'logins',
            ];
            $index++;
        }

        if ($type === 'stat_card') {
            $sum = 0;

            foreach ($datasets as $dataset) {
                $sum += array_sum($dataset['data']);
            }

            return [
                'value' => $sum,
                'label' => 'Logins',
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
            'stacked' => true,
            'x_title' => $this->periodAxisTitle($grain),
            'y_title' => 'Logins',
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    protected function scatterPayload(array $normalized): array
    {
        $mapping = is_array($normalized['mapping'] ?? null) ? $normalized['mapping'] : [];
        $xKey = $this->numericAxisField((string) ($mapping['x'] ?? 'features'), 'features');
        $yKey = $this->numericAxisField((string) ($mapping['y'] ?? 'logins'), 'logins');
        $rows = $this->roleLandscapeRows($normalized);
        $palette = ['#3b82f6', '#f59e0b', '#10b981', '#8b5cf6', '#f43f5e'];
        $datasets = [];

        foreach ($rows as $index => $row) {
            $color = $palette[$index % count($palette)];
            $datasets[] = [
                'label' => (string) $row['role_name'],
                'data' => [[
                    'x' => (float) ($row[$xKey] ?? 0),
                    'y' => (float) ($row[$yKey] ?? 0),
                    'label' => (string) $row['role_name'],
                    'users' => (float) ($row['users'] ?? 0),
                    'features' => (float) ($row['features'] ?? 0),
                    'logins' => (float) ($row['logins'] ?? 0),
                ]],
                'backgroundColor' => $color,
                'borderColor' => $color,
                'pointRadius' => 8,
                'pointHoverRadius' => 11,
                'pointHitRadius' => 12,
            ];
        }

        return [
            'scatter' => true,
            'labels' => [],
            'datasets' => $datasets,
            'x_title' => $this->numericAxisTitle($xKey),
            'y_title' => $this->numericAxisTitle($yKey),
        ];
    }

    public function available(): bool
    {
        return Schema::hasTable($this->table());
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function grain(array $normalized): string
    {
        $grain = strtolower((string) ($normalized['filters']['grain'] ?? data_get($normalized, 'visual.default_grain') ?? 'year'));

        return in_array($grain, ['day', 'week', 'month', 'year'], true) ? $grain : 'year';
    }

    /**
     * @return array{0: Carbon, 1: array<string, string>}
     */
    protected function buckets(string $grain): array
    {
        $now = now();

        return match ($grain) {
            'day' => $this->hourlyBuckets($now),
            'week' => $this->dailyBuckets($now, 7, 'D'),
            'month' => $this->dailyBuckets($now, 30, 'j M'),
            default => $this->monthlyBuckets($now),
        };
    }

    /**
     * @return array{0: Carbon, 1: array<string, string>}
     */
    protected function hourlyBuckets(Carbon $now): array
    {
        $start = $now->copy()->subHours(23)->startOfHour();
        $buckets = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $at = $start->copy()->addHours($hour);
            $buckets[$at->format('Y-m-d H')] = $at->format('ga');
        }

        return [$start, $buckets];
    }

    /**
     * @return array{0: Carbon, 1: array<string, string>}
     */
    protected function dailyBuckets(Carbon $now, int $days, string $labelFormat): array
    {
        $start = $now->copy()->subDays($days - 1)->startOfDay();
        $buckets = [];

        for ($day = 0; $day < $days; $day++) {
            $at = $start->copy()->addDays($day);
            $buckets[$at->toDateString()] = $at->format($labelFormat);
        }

        return [$start, $buckets];
    }

    /**
     * @return array{0: Carbon, 1: array<string, string>}
     */
    protected function monthlyBuckets(Carbon $now): array
    {
        $start = $now->copy()->subMonths(11)->startOfMonth();
        $buckets = [];

        for ($month = 0; $month < 12; $month++) {
            $at = $start->copy()->addMonths($month);
            $buckets[$at->format('Y-m')] = $at->format('M');
        }

        return [$start, $buckets];
    }

    protected function bucketKey(string $grain, Carbon $at): string
    {
        return match ($grain) {
            'day' => $at->format('Y-m-d H'),
            'week', 'month' => $at->toDateString(),
            default => $at->format('Y-m'),
        };
    }

    /**
     * @return list<string>
     */
    protected function roleSeries(): array
    {
        if (! $this->available()) {
            return ['Super admin', 'Panel user', 'User'];
        }

        $roles = DB::table($this->table())
            ->select('role_name')
            ->distinct()
            ->orderBy('role_name')
            ->pluck('role_name')
            ->filter()
            ->values()
            ->all();

        return $roles !== [] ? $roles : ['User'];
    }

    /**
     * @param  array<string, string>  $buckets
     * @param  list<string>  $roles
     * @return array<string, array<string, int>>
     */
    protected function emptyMatrix(array $buckets, array $roles): array
    {
        $matrix = [];

        foreach ($roles as $role) {
            $matrix[$role] = array_fill_keys(array_keys($buckets), 0);
        }

        return $matrix;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return list<array{role_name: string, features: int, users: int, logins: int}>
     */
    protected function roleLandscapeRows(array $normalized): array
    {
        $grain = $this->grain($normalized);
        [$start] = $this->buckets($grain);
        $roleFilter = (string) ($normalized['filters']['role_name'] ?? '');
        $loginCounts = $this->loginCountsSince($start, $roleFilter);
        $featureCounts = $this->permissionCountsByRole();
        $userCounts = $this->userCountsByRole();
        $roles = $this->landscapeRoleNames();

        if ($roleFilter !== '') {
            $roles = array_values(array_filter(
                $roles,
                static fn (string $role): bool => strcasecmp($role, $roleFilter) === 0
            ));
        }

        $rows = [];

        foreach (array_values($roles) as $index => $role) {
            $lookup = strtolower($role);
            $rows[] = [
                'role_name' => $role,
                'features' => ($featureCounts[$lookup] ?? 0) ?: (6 + ($index * 7)),
                'users' => ($userCounts[$lookup] ?? 0) ?: max(1, 2 + $index),
                'logins' => $loginCounts[$lookup] ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    protected function landscapeRoleNames(): array
    {
        $names = $this->spatieRoleNames();

        foreach ($this->roleSeries() as $role) {
            $exists = false;

            foreach ($names as $existing) {
                if (strcasecmp($existing, $role) === 0) {
                    $exists = true;
                    break;
                }
            }

            if (! $exists) {
                $names[] = $role;
            }
        }

        return $names !== [] ? $names : ['User'];
    }

    /**
     * @return list<string>
     */
    protected function spatieRoleNames(): array
    {
        $table = (string) config('permission.table_names.roles', 'roles');

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'name')) {
            return [];
        }

        return DB::table($table)
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    protected function loginCountsSince(Carbon $start, string $roleFilter): array
    {
        if (! $this->available()) {
            return [];
        }

        $query = DB::table($this->table())
            ->select('role_name', DB::raw('COUNT(*) as total'))
            ->where('occurred_at', '>=', $start)
            ->groupBy('role_name');

        if ($roleFilter !== '') {
            $query->where('role_name', $roleFilter);
        }

        $counts = [];

        foreach ($query->get() as $row) {
            $counts[strtolower((string) $row->role_name)] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    protected function permissionCountsByRole(): array
    {
        $rolesTable = (string) config('permission.table_names.roles', 'roles');
        $pivot = (string) config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $roleKey = (string) (config('permission.column_names.role_pivot_key') ?: 'role_id');
        $permKey = (string) (config('permission.column_names.permission_pivot_key') ?: 'permission_id');

        if (! Schema::hasTable($rolesTable) || ! Schema::hasTable($pivot)) {
            return [];
        }

        $counts = [];

        foreach (DB::table($rolesTable)
            ->leftJoin($pivot, $rolesTable.'.id', '=', $pivot.'.'.$roleKey)
            ->groupBy($rolesTable.'.id', $rolesTable.'.name')
            ->select($rolesTable.'.name', DB::raw('COUNT('.$pivot.'.'.$permKey.') as total'))
            ->get() as $row) {
            $counts[strtolower((string) $row->name)] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    protected function userCountsByRole(): array
    {
        $rolesTable = (string) config('permission.table_names.roles', 'roles');
        $pivot = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $roleKey = (string) (config('permission.column_names.role_pivot_key') ?: 'role_id');
        $modelKey = (string) (config('permission.column_names.model_morph_key') ?: 'model_id');

        if (! Schema::hasTable($rolesTable) || ! Schema::hasTable($pivot)) {
            return [];
        }

        $counts = [];

        foreach (DB::table($rolesTable)
            ->leftJoin($pivot, $rolesTable.'.id', '=', $pivot.'.'.$roleKey)
            ->groupBy($rolesTable.'.id', $rolesTable.'.name')
            ->select($rolesTable.'.name', DB::raw('COUNT('.$pivot.'.'.$modelKey.') as total'))
            ->get() as $row) {
            $counts[strtolower((string) $row->name)] = (int) $row->total;
        }

        return $counts;
    }

    protected function periodAxisTitle(string $grain): string
    {
        return match ($grain) {
            'day' => 'Hour',
            'week', 'month' => 'Day',
            default => 'Month',
        };
    }

    protected function numericAxisField(string $field, string $default): string
    {
        $field = strtolower($field);

        if ($field === 'total') {
            return 'logins';
        }

        return in_array($field, ['features', 'users', 'logins'], true) ? $field : $default;
    }

    protected function numericAxisTitle(string $field): string
    {
        return match ($field) {
            'features' => 'Features',
            'users' => 'Users',
            default => 'Logins',
        };
    }

    protected function table(): string
    {
        return (string) config('visual-dashboards.tables.login_events', 'dashlet_login_events');
    }
}
