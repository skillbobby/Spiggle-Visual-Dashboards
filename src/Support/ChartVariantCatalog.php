<?php

namespace Spiggle\VisualDashboards\Support;

class ChartVariantCatalog
{
    /**
     * @return array<string, string>
     */
    public static function optionsForType(string $type): array
    {
        return match ($type) {
            'donut' => [
                'basic' => 'Basic',
                'rounded' => 'Rounded donut',
                'rounded_spaced' => 'Rounded spaced donut',
            ],
            'column' => [
                'basic' => 'Basic',
                'data_labels' => 'Column with data labels',
                'stacked' => 'Stacked column',
                'rotated_labels' => 'Column with rotated labels',
            ],
            'bar' => [
                'basic' => 'Basic',
                'group' => 'Grouped',
                'stacked' => 'Stacked',
                'race' => 'Bar race',
            ],
            'line' => [
                'basic' => 'Basic',
                'smooth' => 'Smooth line',
                'straight' => 'Straight line',
                'area' => 'Area chart',
                'data_labels' => 'Line with data labels',
            ],
            'pie' => [
                'basic' => 'Basic',
                'rounded' => 'Rounded slices',
                'rounded_spaced' => 'Rounded spaced slices',
            ],
            'stacked_column' => [
                'basic' => 'Basic',
                'data_labels' => 'Stacked with data labels',
                'rotated_labels' => 'Stacked with rotated labels',
            ],
            'funnel' => [
                'basic' => 'Basic',
                'compact' => 'Compact funnel',
                'data_labels' => 'Funnel with data labels',
            ],
            'radialbar' => [
                'basic' => 'Basic gauge',
                'bands' => 'Gauge with bands',
                'custom_label' => 'Gauge with custom label',
                'semi_circle' => 'Semi-circle',
                'live_needle' => 'Live needle',
            ],
            'treemap' => [
                'basic' => 'Basic treemap',
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function supportedTypes(): array
    {
        return [
            'donut',
            'column',
            'bar',
            'line',
            'pie',
            'stacked_column',
            'funnel',
            'radialbar',
            'treemap',
        ];
    }

    public static function supports(string $type): bool
    {
        return self::optionsForType($type) !== [];
    }

    public static function normalize(string $type, mixed $variant): string
    {
        $options = self::optionsForType($type);

        if ($options === []) {
            return 'basic';
        }

        $key = (string) ($variant ?? '');

        if (isset($options[$key])) {
            return $key;
        }

        return array_key_first($options) ?: 'basic';
    }

    public static function effectiveVariant(string $type, mixed $variant): string
    {
        if (! self::supports($type)) {
            return 'basic';
        }

        return self::normalize($type, $variant);
    }

    public static function hint(string $type, string $variant): ?string
    {
        return match ($type.':'.$variant) {
            'column:stacked', 'bar:group', 'bar:stacked' => 'Uses the same data. Add a series / breakdown field on the Data source tab to split bars into groups.',
            'bar:race' => 'Sorts values descending and animates bars on load. Full timeline bar races need a time-series source.',
            'radialbar:bands' => 'Uses a green → amber → red gradient to highlight low, medium, and high readings.',
            'radialbar:custom_label' => 'Shows your dashlet heading (or gauge label) inside the gauge arc.',
            'radialbar:semi_circle' => 'Half-circle gauge — ideal for compact grid slots.',
            'radialbar:live_needle' => 'Animated semi-circle gauge with a prominent live value readout.',
            default => null,
        };
    }
}
