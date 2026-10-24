<?php

namespace Spiggle\VisualDashboards\Support;

class VirtualMeasures
{
    public const ROW_COUNT = '__row_count__';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::ROW_COUNT => 'Row count',
        ];
    }

    public static function isVirtual(string $field): bool
    {
        return $field === self::ROW_COUNT;
    }

    public static function normalizeField(string $field): string
    {
        return $field;
    }

    public static function aggregationField(string $field, string $aggregation): string
    {
        if (strtoupper($aggregation) === 'COUNT' && self::isVirtual($field)) {
            return '*';
        }

        return $field;
    }
}
