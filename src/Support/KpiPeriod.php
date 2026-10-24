<?php

namespace Spiggle\VisualDashboards\Support;

use Carbon\Carbon;

class KpiPeriod
{
    public const PERIODS = ['total', 'yearly', 'monthly', 'weekly', 'today', 'custom'];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'total' => 'Total (all time)',
            'yearly' => 'Yearly (this year)',
            'monthly' => 'Monthly (this month)',
            'weekly' => 'Weekly (this week)',
            'today' => 'Today',
            'custom' => 'Custom (rolling days)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function valueSizeOptions(): array
    {
        return [
            'sm' => 'Small',
            'md' => 'Medium',
            'lg' => 'Large',
            'xl' => 'Extra large',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function valueWeightOptions(): array
    {
        return [
            'normal' => 'Normal',
            'medium' => 'Medium',
            'semibold' => 'Semibold',
            'bold' => 'Bold',
        ];
    }

    public static function normalize(string $period): string
    {
        return in_array($period, self::PERIODS, true) ? $period : 'monthly';
    }

    public static function comparisonLabel(string $period): string
    {
        return match (self::normalize($period)) {
            'today' => 'than yesterday',
            'weekly' => 'than last week',
            'monthly' => 'than last month',
            'yearly' => 'than last year',
            'custom' => 'than previous period',
            default => 'than previous period',
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{font_size: string, font_weight: string}
     */
    public static function valueStyle(array $settings): array
    {
        $sizeKey = (string) ($settings['value_size'] ?? 'md');
        $weightKey = (string) ($settings['value_weight'] ?? 'bold');

        $fontSize = match ($sizeKey) {
            'sm' => '1.5rem',
            'lg' => '2.25rem',
            'xl' => '2.75rem',
            default => '1.875rem',
        };

        $fontWeight = match ($weightKey) {
            'normal' => '400',
            'medium' => '500',
            'semibold' => '600',
            default => '700',
        };

        return [
            'font_size' => $fontSize,
            'font_weight' => $fontWeight,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function applyToConfig(array $config, int $periodOffset = 0): array
    {
        $visual = is_array($config['visual'] ?? null) ? $config['visual'] : [];
        $kpi = is_array($visual['kpi'] ?? null) ? $visual['kpi'] : [];
        $period = self::normalize((string) ($kpi['period'] ?? 'monthly'));
        $customDays = max(1, (int) ($kpi['custom_days'] ?? 30));

        $filters = is_array($config['filters'] ?? null) ? $config['filters'] : [];
        $filters['kpi_period'] = $period;
        $filters['kpi_custom_days'] = $customDays;
        $filters['kpi_period_offset'] = max(0, $periodOffset);

        unset($filters['period_offset_days'], $filters['days'], $filters['window']);

        $config['filters'] = $filters;
        unset($config['window']);

        return $config;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public static function range(array $filters, ?Carbon $now = null): array
    {
        $now ??= now();
        $period = self::normalize((string) ($filters['kpi_period'] ?? ''));
        $offset = max(0, (int) ($filters['kpi_period_offset'] ?? 0));
        $customDays = max(1, (int) ($filters['kpi_custom_days'] ?? 30));

        if ($period === '' || $period === 'total') {
            return [null, null];
        }

        return match ($period) {
            'today' => self::dayRange($now, $offset),
            'weekly' => self::weekRange($now, $offset),
            'monthly' => self::monthRange($now, $offset),
            'yearly' => self::yearRange($now, $offset),
            'custom' => self::customRange($now, $offset, $customDays),
            default => [null, null],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected static function dayRange(Carbon $now, int $offset): array
    {
        $start = $now->copy()->startOfDay()->subDays($offset);
        $end = $start->copy()->addDay();

        return [$start, $end];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected static function weekRange(Carbon $now, int $offset): array
    {
        $start = $now->copy()->startOfWeek()->subWeeks($offset);
        $end = $start->copy()->addWeek();

        return [$start, $end];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected static function monthRange(Carbon $now, int $offset): array
    {
        $start = $now->copy()->startOfMonth()->subMonths($offset);
        $end = $start->copy()->addMonth();

        return [$start, $end];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected static function yearRange(Carbon $now, int $offset): array
    {
        $start = $now->copy()->startOfYear()->subYears($offset);
        $end = $start->copy()->addYear();

        return [$start, $end];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected static function customRange(Carbon $now, int $offset, int $days): array
    {
        $end = $now->copy()->subDays($offset * $days);
        $start = $end->copy()->subDays($days);

        return [$start, $end];
    }
}
