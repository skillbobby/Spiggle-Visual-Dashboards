<?php

namespace Spiggle\VisualDashboards\Support;

class RadialBarPresenter
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function chartFromValue(float $value, array $config, string $measureLabel = 'Value'): array
    {
        $visual = is_array($config['visual'] ?? null) ? $config['visual'] : [];
        $min = (float) ($visual['gauge_min'] ?? 0);
        $max = (float) ($visual['gauge_max'] ?? 0);

        if ($max <= $min) {
            $max = self::resolveAutoMax($value);
        }

        $span = max($max - $min, 1);
        $percent = min(100, max(0, (($value - $min) / $span) * 100));

        $heading = (string) ($config['heading'] ?? $config['title'] ?? '');
        $customLabel = (string) ($visual['gauge_label'] ?? '');

        return [
            'radialbar' => true,
            'value' => $value,
            'min' => $min,
            'max' => $max,
            'percent' => round($percent, 1),
            'label' => $measureLabel,
            'series_label' => $customLabel !== '' ? $customLabel : ($heading !== '' ? $heading : $measureLabel),
            'formatted' => ValueFormatter::format(
                $value,
                (string) ($visual['number_format'] ?? 'number'),
                (string) ($visual['currency'] ?? 'USD')
            ),
        ];
    }

    protected static function resolveAutoMax(float $value): float
    {
        if ($value <= 0) {
            return 100;
        }

        if ($value <= 100) {
            return 100;
        }

        return max(ceil($value * 1.25), $value);
    }
}
