<?php

namespace Spiggle\VisualDashboards\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

class FieldValueFormatter
{
    /**
     * @return array<string, string>
     */
    public static function functionLabels(): array
    {
        return [
            'date' => 'Format date',
            'uppercase' => 'Uppercase',
            'lowercase' => 'Lowercase',
            'title' => 'Title case',
            'trim' => 'Trim whitespace',
            'ucfirst' => 'Capitalize first letter',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function dateFormatPresets(): array
    {
        return [
            'F j, Y' => 'August 28, 2026',
            'M j, Y' => 'Aug 28, 2026',
            'Y-m-d' => '2026-08-28',
            'm/d/Y' => '08/28/2026',
            'D, M j, Y' => 'Thu, Aug 28, 2026',
            'M j, Y g:i A' => 'Aug 28, 2026 2:30 PM',
            'custom' => 'Custom…',
        ];
    }

    public static function apply(mixed $value, string $function, string $format = ''): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return match ($function) {
            'date' => self::formatDate($value, $format),
            'uppercase' => Str::upper((string) $value),
            'lowercase' => Str::lower((string) $value),
            'title' => Str::title(Str::lower((string) $value)),
            'trim' => trim((string) $value),
            'ucfirst' => Str::ucfirst(Str::lower((string) $value)),
            default => $value,
        };
    }

    protected static function formatDate(mixed $value, string $format): mixed
    {
        $format = trim($format);

        if ($format === '' || $format === 'custom') {
            return $value;
        }

        try {
            return Carbon::parse((string) $value)->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }
}
