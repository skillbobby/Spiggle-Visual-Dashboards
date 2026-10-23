<?php

namespace Spiggle\VisualDashboards\DataSources;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spiggle\VisualDashboards\Support\AiSpendAggregator;
use Spiggle\VisualDashboards\Support\QueryConfig;

class AiSpendDataSource extends BaseDataSource
{
    public function key(): string
    {
        return 'ai_spend';
    }

    public function label(): string
    {
        return 'AI spend';
    }

    public function available(): bool
    {
        return Schema::hasTable('dashlet_ai_usage_reports') && Schema::hasTable('dashlet_ai_services');
    }

    /**
     * @return array<string, string>
     */
    public function filterSchema(): array
    {
        return [
            'metric' => 'Metric',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function columns(array $context = []): array
    {
        return [
            'Service' => 'Service',
            'Change' => 'Change',
            '$/1M' => '$/1M',
            'Tokens / Spent' => 'Tokens / Spent',
            'title' => 'Title',
            'body' => 'Body',
            'action_label' => 'Action',
            'savings_label' => 'Savings',
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    public function previewRows(array $config = [], int $limit = 25): array
    {
        if (! $this->available()) {
            return [];
        }

        $snapshot = app(AiSpendAggregator::class)->snapshot(null, auth()->user());
        $metric = $this->metric($config);

        $rows = match ($metric) {
            'insights' => array_map(static fn (array $row): array => [
                'title' => $row['title'] ?? '',
                'body' => $row['body'] ?? '',
                'action_label' => $row['action_label'] ?? 'View details',
                'savings_label' => $row['savings_label'] ?? '',
            ], $snapshot['insights'] ?? []),
            default => array_map(static fn (array $row): array => [
                'Service' => $row['name'],
                'Change' => $row['trend_label'],
                '$/1M' => $row['rate_label'],
                'Tokens / Spent' => $row['tokens_label'].' / '.$row['spend_label'],
                'up' => $row['up'] ? '1' : '0',
            ], $snapshot['services'] ?? []),
        };

        return $this->finalizeRows(array_slice($rows, 0, $limit), $config);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function chartPayload(string $type, array $config = []): array
    {
        if (! $this->available()) {
            return $this->emptyPayload($type);
        }

        $snapshot = app(AiSpendAggregator::class)->snapshot(null, auth()->user());
        $metric = $this->metric($config);

        if ($type === 'hero') {
            return [
                'greeting' => (string) ($snapshot['greeting'] ?? 'Hello'),
                'month_label' => (string) ($snapshot['month_label'] ?? now()->format('F Y')),
            ];
        }

        if ($type === 'insight_list') {
            return [
                'items' => $snapshot['insights'] ?? [],
            ];
        }

        if ($type === 'stat_card') {
            return $this->kpiPayload($snapshot, $metric);
        }

        $services = $snapshot['services'] ?? [];
        $labels = array_column($services, 'name');
        $colors = array_column($services, 'color');
        $values = match ($metric) {
            'tokens' => array_column($services, 'tokens'),
            'rate' => array_map(static fn (array $row): float => (float) $row['rate'], $services),
            default => array_map(static fn (array $row): float => (float) $row['spend'], $services),
        };

        $chart = $this->toChartPayload($type, $labels, $values, Str::headline($metric));
        $chart['colors'] = $colors;

        if (in_array($type, ['donut', 'pie'], true)) {
            $donut = $snapshot['donut'] ?? [];
            $totals = $snapshot['totals'] ?? [];
            $chart['center'] = [
                'name' => $donut['center_name'] ?? '—',
                'detail' => ($donut['center_tokens'] ?? '0').' ('.($donut['center_share'] ?? '0%').')',
                'initials' => $donut['center_initials'] ?? 'AI',
                'color' => $donut['center_color'] ?? '#22c55e',
            ];
            $chart['summary'] = [
                'Total' => $totals['tokens'] ?? '0 tkn',
                'Avg $/1M' => $totals['avg_rate'] ?? '$0',
                'Best' => $totals['best'] ?? '—',
                'Worst' => $totals['worst'] ?? '—',
            ];
        }

        return $chart;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function kpiPayload(array $snapshot, string $metric): array
    {
        $kpi = collect($snapshot['kpis'] ?? [])->firstWhere('key', $metric)
            ?? collect($snapshot['kpis'] ?? [])->first();

        if (! is_array($kpi)) {
            return ['value' => 0, 'label' => 'AI spend', 'formatted' => '$0'];
        }

        $payload = [
            'value' => is_numeric($kpi['value'] ?? null)
                ? (float) $kpi['value']
                : (float) preg_replace('/[^0-9.]/', '', (string) ($kpi['value'] ?? 0)),
            'label' => (string) ($kpi['label'] ?? Str::headline($metric)),
            'formatted' => (string) ($kpi['value'] ?? '0'),
            'hint' => (string) ($kpi['hint'] ?? ''),
            'delta' => $kpi['delta'] ?? null,
            'up' => (bool) ($kpi['up'] ?? false),
            'sparkline' => $snapshot['weekly_spend'] ?? [],
            'badge' => match ($metric) {
                'projected' => 'Forecast your month-end AI costs.',
                'anomalies' => 'Detect unexpected spikes.',
                default => null,
            },
        ];

        if ($metric === 'growing') {
            $payload['formatted'] = trim((string) ($kpi['delta'] ?? '').' usage');
            $payload['logo'] = $kpi['logo'] ?? null;
            $payload['logo_color'] = $kpi['color'] ?? null;
            $payload['sparkline'] = [];
            $payload['badge'] = null;
        }

        if ($metric === 'anomalies') {
            $payload['sparkline'] = [];
            $payload['badge'] = 'Detect unexpected spikes.';
        }

        if ($metric === 'spend') {
            $payload['badge'] = null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function metric(array $config): string
    {
        $normalized = QueryConfig::normalize($config);

        return (string) ($normalized['filters']['metric'] ?? 'spend');
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyPayload(string $type): array
    {
        return match ($type) {
            'hero' => ['greeting' => 'Hello', 'month_label' => now()->format('F Y')],
            'insight_list' => ['items' => []],
            'stat_card' => ['value' => 0, 'label' => 'AI spend', 'formatted' => '$0'],
            default => $this->toChartPayload($type, ['None'], [0], 'AI spend'),
        };
    }
}
