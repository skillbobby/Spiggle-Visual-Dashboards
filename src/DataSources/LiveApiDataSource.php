<?php

namespace Spiggle\VisualDashboards\DataSources;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spiggle\VisualDashboards\Support\QueryConfig;
use Spiggle\VisualDashboards\Support\QueryFilterApplier;
use Throwable;

class LiveApiDataSource extends BaseDataSource
{
    /**
     * @param  array<string, mixed>  $api
     */
    public function __construct(protected array $api = []) {}

    /**
     * @param  array<string, mixed>  $api
     */
    public function configure(array $api): static
    {
        $clone = clone $this;
        $clone->api = $api;

        return $clone;
    }

    public function key(): string
    {
        return 'live_api';
    }

    public function label(): string
    {
        return 'Live API';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function columns(array $context = []): array
    {
        $rows = $this->previewRows($context, 5);
        $first = $rows[0] ?? [];
        $options = [];

        foreach (array_keys($first) as $column) {
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
        $api = $normalized['api'] !== [] ? $normalized['api'] : $this->api;
        $rows = $this->fetchRows($api);
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
            'API'
        );
    }

    /**
     * @param  array<string, mixed>  $api
     * @return array<int, array<string, mixed>>
     */
    protected function fetchRows(array $api): array
    {
        $url = trim((string) ($api['url'] ?? ''));

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with(strtolower($url), 'http')) {
            return [];
        }

        $headers = [];

        foreach (is_array($api['headers'] ?? null) ? $api['headers'] : [] as $key => $value) {
            $name = trim((string) $key);

            if ($name !== '') {
                $headers[$name] = (string) $value;
            }
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders($headers)
                ->get($url);

            if (! $response->successful()) {
                return [];
            }

            $payload = $response->json();
        } catch (Throwable) {
            return [];
        }

        $path = trim((string) ($api['json_path'] ?? ''));
        $data = $path === '' ? $payload : data_get($payload, $path);

        if (! is_array($data)) {
            return [];
        }

        if ($data !== [] && ! array_is_list($data)) {
            $data = [$data];
        }

        $rows = [];

        foreach ($data as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

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
