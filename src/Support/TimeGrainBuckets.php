<?php

namespace Spiggle\VisualDashboards\Support;

use Carbon\Carbon;

class TimeGrainBuckets
{
    /**
     * @param  array<string, mixed>  $normalized
     */
    public static function resolveGrain(array $normalized, string $fallback = 'month'): string
    {
        $grain = strtolower((string) (
            $normalized['filters']['grain']
            ?? data_get($normalized, 'visual.default_grain')
            ?? $fallback
        ));

        return in_array($grain, ['day', 'week', 'month', 'year'], true) ? $grain : $fallback;
    }

    /**
     * @return array{0: Carbon, 1: array<string, string>}
     */
    public static function buckets(string $grain): array
    {
        $now = now();

        return match ($grain) {
            'day' => self::hourlyBuckets($now),
            'week' => self::dailyBuckets($now, 7, 'D'),
            'month' => self::dailyBuckets($now, 30, 'j M'),
            default => self::monthlyBuckets($now),
        };
    }

    public static function bucketKey(string $grain, Carbon $at): string
    {
        return match ($grain) {
            'day' => $at->format('Y-m-d H'),
            'week', 'month' => $at->toDateString(),
            default => $at->format('Y-m'),
        };
    }

    public static function periodAxisTitle(string $grain): string
    {
        return match ($grain) {
            'day' => 'Hour',
            'week', 'month' => 'Day',
            default => 'Month',
        };
    }

    /**
     * @return array{0: Carbon, 1: array<string, string>}
     */
    protected static function hourlyBuckets(Carbon $now): array
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
    protected static function dailyBuckets(Carbon $now, int $days, string $labelFormat): array
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
    protected static function monthlyBuckets(Carbon $now): array
    {
        $start = $now->copy()->subMonths(11)->startOfMonth();
        $buckets = [];

        for ($month = 0; $month < 12; $month++) {
            $at = $start->copy()->addMonths($month);
            $buckets[$at->format('Y-m')] = $at->format('M');
        }

        return [$start, $buckets];
    }
}
