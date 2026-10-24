<?php

namespace Spiggle\VisualDashboards\Support;

class KpiCardPresenter
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function configForQuery(array $config): array
    {
        return KpiPeriod::applyToConfig($config, 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function enrich(array $payload, array $config): array
    {
        $visual = is_array($config['visual'] ?? null) ? $config['visual'] : [];
        $settings = KpiCardCatalog::normalizeSettings($visual);
        $icon = (string) ($settings['icon'] ?? '');
        $valueStyle = KpiPeriod::valueStyle($settings);

        $computed = null;

        if (($settings['comparison']['mode'] ?? '') === 'previous_period') {
            $computed = StatCardComparison::computePreviousPeriod(
                self::configForQuery($config),
                (float) ($payload['value'] ?? 0)
            );
        }

        $comparison = KpiCardCatalog::comparisonPresentation($settings, $computed);

        $payload['kpi'] = [
            'icon' => $icon,
            'metric_label' => (string) ($settings['metric_label'] ?? ''),
            'period' => (string) ($settings['period'] ?? 'monthly'),
            'period_label' => KpiPeriod::options()[$settings['period'] ?? 'monthly'] ?? 'Monthly',
            'icon_svg' => KpiCardCatalog::iconSvg($icon),
            'icon_color' => ValueFormatter::colorForScheme(
                $visual['color_scheme'] ?? null,
                $visual['color'] ?? '#6366f1'
            ),
            'value_style' => $valueStyle,
            'comparison' => $comparison,
        ];

        if ($comparison !== null) {
            $payload['delta'] = $comparison['delta'];
            $payload['hint'] = $comparison['label'];
            $payload['up'] = $comparison['up'];
            $payload['comparison_favorable'] = $comparison['favorable'];
        }

        return $payload;
    }
}
