<?php

namespace Spiggle\VisualDashboards\Support;

class ValueFormatter
{
    /**
     * @return array<string, string>
     */
    public static function numberFormats(): array
    {
        return [
            'number' => 'Number',
            'percent' => 'Percent',
            'currency' => 'Currency',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function colorSchemes(): array
    {
        return [
            'amber' => 'Amber',
            'blue' => 'Blue',
            'emerald' => 'Emerald',
            'rose' => 'Rose',
            'violet' => 'Violet',
            'slate' => 'Slate',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function schemeColors(): array
    {
        return [
            'amber' => '#f59e0b',
            'blue' => '#3b82f6',
            'emerald' => '#10b981',
            'rose' => '#f43f5e',
            'violet' => '#8b5cf6',
            'slate' => '#64748b',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function currencies(): array
    {
        return [
            'USD' => 'USD ($)',
            'EUR' => 'EUR (€)',
            'GBP' => 'GBP (£)',
            'JPY' => 'JPY (¥)',
        ];
    }

    public static function colorForScheme(?string $scheme, ?string $fallback = null): string
    {
        $colors = self::schemeColors();

        if ($scheme && isset($colors[$scheme])) {
            return $colors[$scheme];
        }

        return $fallback ?: '#f59e0b';
    }

    public static function format(mixed $value, string $format = 'number', string $currency = 'USD'): string
    {
        $number = (float) $value;

        return match (self::formatKey($format)) {
            'percent' => number_format($number, 1).'%',
            'currency' => self::currencySymbol(self::normalizeCurrency($currency)).number_format($number, 2),
            default => number_format($number),
        };
    }

    public static function formatKey(string $format): string
    {
        return isset(self::numberFormats()[$format]) ? $format : 'number';
    }

    public static function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return isset(self::currencies()[$currency]) ? $currency : 'USD';
    }

    protected static function currencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            default => '$',
        };
    }
}
