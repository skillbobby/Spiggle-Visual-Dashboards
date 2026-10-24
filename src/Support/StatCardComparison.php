<?php

namespace Spiggle\VisualDashboards\Support;

use Spiggle\VisualDashboards\Services\DataEngine;

class StatCardComparison
{
    /**
     * @param  array<string, mixed>  $config
     * @return array{delta: float, up: bool}|null
     */
    public static function computePreviousPeriod(array $config, float $currentValue): ?array
    {
        $visual = is_array($config['visual'] ?? null) ? $config['visual'] : [];
        $settings = KpiCardCatalog::normalizeSettings($visual);
        $period = KpiPeriod::normalize((string) ($settings['period'] ?? 'monthly'));

        if ($period === 'total') {
            return null;
        }

        if ($currentValue <= 0) {
            return null;
        }

        $previousConfig = KpiPeriod::applyToConfig($config, 1);

        try {
            $previousValue = app(DataEngine::class)->statValueFromConfig($previousConfig);
        } catch (\Throwable) {
            return null;
        }

        if ($previousValue <= 0) {
            return [
                'delta' => 100.0,
                'up' => true,
            ];
        }

        $change = (($currentValue - $previousValue) / $previousValue) * 100;

        return [
            'delta' => abs(round($change, 1)),
            'up' => $change >= 0,
        ];
    }
}
