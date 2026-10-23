<?php

namespace Spiggle\VisualDashboards\DataSources;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spiggle\VisualDashboards\Support\QueryConfig;
use Spiggle\VisualDashboards\Support\QueryFilterApplier;

class FileCsvDataSource extends BaseDataSource
{
    /**
     * @param  array<string, mixed>  $file
     */
    public function __construct(protected array $file = []) {}

    /**
     * @param  array<string, mixed>  $file
     */
    public function configure(array $file): static
    {
        $clone = clone $this;
        $clone->file = $file;

        return $clone;
    }

    public function key(): string
    {
        return 'file_csv';
    }

    public function label(): string
    {
        return 'CSV file';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function columns(array $context = []): array
    {
        $rows = $this->previewRows($context, 1);
        $options = [];

        foreach (array_keys($rows[0] ?? []) as $column) {
            $options[(string) $column] = Str::headline((string) $column);
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function previewRows(array $config = [], int $limit = 25): array
    {
        $normalized = QueryConfig::normalize($config);
        $file = $normalized['file'] !== [] ? $normalized['file'] : $this->file;
        $rows = $this->readCsv($file);
        $rows = $this->filterRows($rows, $normalized);

        return $this->finalizeRows(array_slice($rows, 0, $limit), $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function chartPayload(string $type, array $config = []): array
    {
        $rows = $this->previewRows($config, (int) config('visual-dashboards.query.max_rows', 500));
        $labelColumn = array_key_first($rows[0] ?? ['label' => null]) ?: 'label';
        $valueColumn = $this->numericColumn($rows[0] ?? []);

        return $this->toChartPayload(
            $type,
            array_map(static fn (array $row): string => (string) ($row[$labelColumn] ?? ''), $rows),
            array_map(static fn (array $row): float => (float) ($row[$valueColumn] ?? 1), $rows),
            'CSV'
        );
    }

    /**
     * @param  array<string, mixed>  $file
     * @return array<int, array<string, mixed>>
     */
    protected function readCsv(array $file): array
    {
        $path = $this->storedPath($file['path'] ?? null);

        if ($path === null || ! is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers) || $headers === []) {
            fclose($handle);

            return [];
        }

        $headers = array_map(static fn (mixed $header): string => Str::slug((string) $header, '_'), $headers);
        $rows = [];
        $max = (int) config('visual-dashboards.query.max_rows', 500);

        while (($record = fgetcsv($handle)) !== false && count($rows) < $max) {
            if ($record === [null] || $record === false) {
                continue;
            }

            $row = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $value = $record[$index] ?? null;
                $row[$header] = is_numeric($value) ? $value + 0 : $value;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $normalized
     * @return array<int, array<string, mixed>>
     */
    protected function filterRows(array $rows, array $normalized): array
    {
        $groups = $normalized['filter_groups'] ?? [];
        $conditions = is_array($groups['conditions'] ?? null) ? $groups['conditions'] : [];
        $orConditions = is_array($groups['or_conditions'] ?? null) ? $groups['or_conditions'] : [];

        if ($conditions === [] && $orConditions === []) {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($conditions, $orConditions): bool {
            foreach ($conditions as $condition) {
                if (! is_array($condition) || ! QueryFilterApplier::rowMatches($row, $condition)) {
                    return false;
                }
            }

            if ($orConditions === []) {
                return true;
            }

            foreach ($orConditions as $condition) {
                if (is_array($condition) && QueryFilterApplier::rowMatches($row, $condition)) {
                    return true;
                }
            }

            return false;
        }));
    }

    protected function storedPath(mixed $path): ?string
    {
        $path = is_array($path) ? (string) (array_values($path)[0] ?? '') : (string) $path;
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $absolute = Storage::disk('local')->path($path);

        return is_file($absolute) ? $absolute : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function numericColumn(array $row): string
    {
        foreach ($row as $key => $value) {
            if (is_numeric($value) && $key !== 'id') {
                return (string) $key;
            }
        }

        return (string) (array_key_first($row) ?: 'value');
    }
}
