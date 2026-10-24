<?php

namespace Spiggle\VisualDashboards\Support;

class CalculatedFields
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $rows, array $fields): array
    {
        $fields = array_values(array_filter(
            $fields,
            static fn (mixed $field): bool => is_array($field) && filled($field['name'] ?? null) && filled($field['expression'] ?? null)
        ));

        if ($fields === []) {
            return $rows;
        }

        return array_map(function (array $row) use ($fields): array {
            foreach ($fields as $field) {
                $name = (string) $field['name'];
                $row[$name] = self::evaluate((string) $field['expression'], $row);
            }

            return $row;
        }, $rows);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function evaluate(string $expression, array $row): float|int|null
    {
        $expression = trim($expression);

        if ($expression === '' || ! preg_match('/^[a-zA-Z0-9_+\-*\/.\s]+$/', $expression)) {
            return null;
        }

        if (! preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*([+\-*\/])\s*([a-zA-Z_][a-zA-Z0-9_]*|[0-9.]+)$/', $expression, $matches)) {
            return null;
        }

        $left = self::numeric($matches[1], $row);
        $right = self::numeric($matches[3], $row);

        if ($left === null || $right === null) {
            return null;
        }

        return match ($matches[2]) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => abs($right) < 0.0000001 ? null : $left / $right,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected static function numeric(string $token, array $row): ?float
    {
        if (array_key_exists($token, $row)) {
            return is_numeric($row[$token]) ? (float) $row[$token] : null;
        }

        return is_numeric($token) ? (float) $token : null;
    }
}
