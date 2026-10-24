<?php

namespace Spiggle\VisualDashboards\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class PeriodRangeFilter
{
    /**
     * @param  array<string, mixed>  $normalized
     */
    public static function applyCreatedAtWindow(EloquentBuilder|QueryBuilder $query, array $normalized, string $column = 'created_at'): void
    {
        $filters = is_array($normalized['filters'] ?? null) ? $normalized['filters'] : [];
        $kpiPeriod = (string) ($filters['kpi_period'] ?? '');

        if ($kpiPeriod !== '') {
            self::applyKpiPeriod($query, $filters, $column);

            return;
        }

        $days = QueryConfig::windowDays($normalized) ?? (int) ($filters['days'] ?? 0);
        $offset = (int) ($filters['period_offset_days'] ?? 0);

        if ($days <= 0) {
            return;
        }

        if ($offset > 0) {
            $query->where($column, '>=', now()->subDays($days + $offset));
            $query->where($column, '<', now()->subDays($offset));

            return;
        }

        $query->where($column, '>=', now()->subDays($days));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected static function applyKpiPeriod(EloquentBuilder|QueryBuilder $query, array $filters, string $column): void
    {
        [$start, $end] = KpiPeriod::range($filters);

        if ($start === null) {
            return;
        }

        $query->where($column, '>=', $start);

        if ($end !== null) {
            $query->where($column, '<', $end);
        }
    }
}
