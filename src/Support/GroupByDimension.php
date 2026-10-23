<?php

namespace Spiggle\VisualDashboards\Support;

use Carbon\Carbon;

class GroupByDimension
{
    public static function isTemporal(string $field): bool
    {
        return preg_match('/(_at|_date|date)$/i', $field) === 1;
    }

    public static function isForeignKey(string $field): bool
    {
        return preg_match('/_id$/i', $field) === 1 && $field !== 'id';
    }

    public static function normalizeValue(string $field, mixed $value, array $config): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($field === 'user_id') {
            return 'User '.$value;
        }

        if ($field === 'form_id') {
            return 'Form '.$value;
        }

        if (! self::isTemporal($field)) {
            return $value;
        }

        try {
            $at = Carbon::parse((string) $value);
        } catch (\Throwable) {
            return $value;
        }

        $grain = strtolower((string) (
            $config['filters']['grain']
            ?? data_get($config, 'visual.default_grain')
            ?? 'day'
        ));

        return match ($grain) {
            'week' => $at->copy()->startOfWeek()->toDateString(),
            'month' => $at->format('Y-m'),
            'year' => $at->format('Y'),
            default => $at->toDateString(),
        };
    }

    public static function displayLabel(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return match ($field) {
                'user_id' => 'Unknown user',
                'form_id' => 'Unknown form',
                default => 'Unknown',
            };
        }

        return (string) $value;
    }
}
