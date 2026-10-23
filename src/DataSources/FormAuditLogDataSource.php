<?php

namespace Spiggle\VisualDashboards\DataSources;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spiggle\VisualDashboards\Support\PeriodRangeFilter;
use Spiggle\VisualDashboards\Support\QueryConfig;
use Spiggle\VisualDashboards\Support\QueryFilterApplier;

class FormAuditLogDataSource extends BaseDataSource
{
    public function key(): string
    {
        return 'form_audit_logs';
    }

    public function label(): string
    {
        return 'Form audit log';
    }

    /**
     * @return array<string, string>
     */
    public function filterSchema(): array
    {
        return [
            'form_id' => 'Form',
            'action' => 'Action',
            'days' => 'Days back',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function columns(array $context = []): array
    {
        return [
            'id' => 'ID',
            'form_id' => 'Form ID',
            'user_id' => 'User ID',
            'action' => 'Action',
            'ip_address' => 'IP address',
            'created_at' => 'Created at',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function joinTargets(): array
    {
        return [
            'users' => 'Users',
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
        $query = $this->baseQuery($normalized);
        QueryFilterApplier::apply($query, $normalized, $this->auditTable());

        $rows = $query
            ->select(['id', 'form_id', 'user_id', 'action', 'ip_address', 'created_at'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();

        return $this->finalizeRows($rows, $config);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function chartPayload(string $type, array $config = []): array
    {
        if (! $this->available()) {
            return $this->toChartPayload($type, ['None'], [0], 'Audit events');
        }

        $normalized = QueryConfig::normalize($config);
        $query = $this->baseQuery($normalized);
        QueryFilterApplier::apply($query, $normalized, $this->auditTable());

        $rows = $query
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
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

        return $this->toChartPayload($type, $labels, $values, 'Audit events');
    }

    public function available(): bool
    {
        return class_exists(\Spiggle\FormBuilder\Models\FormAuditLog::class)
            && Schema::hasTable($this->auditTable());
    }

    /**
     * @return array<string, string>
     */
    public function actionOptions(): array
    {
        if (! $this->available()) {
            return [];
        }

        $fromDb = DB::table($this->auditTable())
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->filter()
            ->mapWithKeys(fn (string $action): array => [$action => $action])
            ->all();

        return $fromDb;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function baseQuery(array $normalized): \Illuminate\Database\Query\Builder
    {
        $filters = $normalized['filters'];
        $query = DB::table($this->auditTable());

        if (filled($filters['form_id'] ?? null)) {
            $query->where('form_id', (int) $filters['form_id']);
        }

        if (filled($filters['action'] ?? null)) {
            $query->where('action', (string) $filters['action']);
        }

        PeriodRangeFilter::applyCreatedAtWindow($query, $normalized);

        return $query;
    }

    protected function auditTable(): string
    {
        return (string) config('form-builder.tables.audit_logs', 'form_builder_audit_logs');
    }
}
