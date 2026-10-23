<?php

namespace Spiggle\VisualDashboards\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spiggle\VisualDashboards\Models\AiAnomaly;
use Spiggle\VisualDashboards\Models\AiForecast;
use Spiggle\VisualDashboards\Models\AiInsight;
use Spiggle\VisualDashboards\Models\AiService;
use Spiggle\VisualDashboards\Models\AiUsageReport;

class AiSpendAggregator
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?Carbon $month = null, mixed $user = null): array
    {
        $month = ($month ?? now())->copy()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $today = now();
        $elapsedEnd = $today->lt($end) ? $today->copy()->endOfDay() : $end;

        if (! Schema::hasTable('dashlet_ai_usage_reports')) {
            return $this->emptySnapshot($month, $user);
        }

        $reports = AiUsageReport::query()
            ->with('service')
            ->whereBetween('reported_on', [$start->toDateString(), $end->toDateString()])
            ->get();

        $previous = AiUsageReport::query()
            ->with('service')
            ->whereBetween('reported_on', [
                $start->copy()->subMonth()->toDateString(),
                $start->copy()->subDay()->toDateString(),
            ])
            ->get();

        $services = $this->serviceRows($reports, $previous);
        $totalSpend = (float) $reports->sum('spend_usd');
        $totalTokens = (int) $reports->sum('tokens');
        $previousSpend = (float) $previous->sum('spend_usd');
        $spendDelta = $previousSpend > 0 ? (($totalSpend - $previousSpend) / $previousSpend) * 100 : 0.0;

        $daysElapsed = max(1, (int) $start->diffInDays($elapsedEnd) + 1);
        $daysInMonth = (int) $month->daysInMonth;
        $recent = $reports
            ->filter(fn (AiUsageReport $row): bool => $row->reported_on?->gte($elapsedEnd->copy()->subDays(6)->startOfDay()))
            ->groupBy(fn (AiUsageReport $row): string => $row->reported_on?->toDateString() ?? '')
            ->map(fn (Collection $day): float => (float) $day->sum('spend_usd'));
        $recentDaily = $recent->count() > 0 ? (float) $recent->avg() : ($totalSpend / $daysElapsed);
        $projected = $totalSpend + ($recentDaily * max(0, $daysInMonth - $daysElapsed));

        if (Schema::hasTable('dashlet_ai_forecasts')) {
            $forecast = AiForecast::query()
                ->where('year', $month->year)
                ->where('month', $month->month)
                ->first();

            if ($forecast) {
                $projected = (float) $forecast->projected_spend;
            }
        }

        $anomalies = AiAnomaly::query()
            ->with('service')
            ->whereBetween('detected_on', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('amount_usd')
            ->orderBy('detected_on')
            ->get()
            ->unique(fn (AiAnomaly $row): string => ($row->service_id ?? 0).'|'.$row->title)
            ->values();

        $growing = collect($services)->sortByDesc('trend')->first();
        $best = collect($services)->filter(fn (array $row): bool => $row['tokens'] > 0)->sortBy('rate')->first();
        $worst = collect($services)->filter(fn (array $row): bool => $row['tokens'] > 0)->sortByDesc('rate')->first();
        $topShare = collect($services)->sortByDesc('tokens')->first();

        $firstAnomaly = $anomalies->first();

        return [
            'month_label' => $month->format('F Y'),
            'greeting' => $this->greeting($user),
            'kpis' => [
                [
                    'key' => 'spend',
                    'label' => 'Total Spend',
                    'value' => $this->money($totalSpend),
                    'hint' => 'this month',
                    'delta' => $this->signedPercent($spendDelta),
                    'up' => $spendDelta >= 0,
                    'chart' => 'apexcharts',
                ],
                [
                    'key' => 'projected',
                    'label' => 'Projected Spend',
                    'value' => $this->money($projected),
                    'hint' => 'expected by month-end',
                    'delta' => $this->money($projected - $totalSpend, true),
                    'up' => $projected >= $totalSpend,
                    'chart' => 'apexcharts',
                ],
                [
                    'key' => 'anomalies',
                    'label' => 'Anomaly Alert',
                    'value' => (string) $anomalies->count().' anomal'.($anomalies->count() === 1 ? 'y' : 'ies'),
                    'hint' => $firstAnomaly
                        ? $this->money((float) $firstAnomaly->amount_usd, true).' spike ('.$firstAnomaly->detected_on?->format('M j').' — '.($firstAnomaly->service?->name ?? 'service').')'
                        : 'No spikes this month',
                    'delta' => null,
                    'up' => $anomalies->isNotEmpty(),
                    'chart' => null,
                ],
                [
                    'key' => 'growing',
                    'label' => 'Top Growing Service',
                    'value' => (string) ($growing['name'] ?? '—'),
                    'hint' => isset($growing) ? $growing['tokens_label'].' tkn | '.$this->money($growing['rate'] / 1000).' / 1K tkn' : '',
                    'delta' => isset($growing) ? $this->signedPercent((float) $growing['trend']) : null,
                    'up' => (float) ($growing['trend'] ?? 0) >= 0,
                    'logo' => $growing['initials'] ?? 'AI',
                    'color' => $growing['color'] ?? '#111827',
                    'chart' => null,
                ],
            ],
            'services' => $services,
            'donut' => [
                'labels' => array_column($services, 'name'),
                'values' => array_map(fn (array $row): int => (int) $row['tokens'], $services),
                'colors' => array_column($services, 'color'),
                'center_name' => $topShare['name'] ?? '—',
                'center_tokens' => $topShare['tokens_label'] ?? '0',
                'center_share' => $totalTokens > 0 ? round((($topShare['tokens'] ?? 0) / $totalTokens) * 100).'%' : '0%',
                'center_initials' => $topShare['initials'] ?? 'AI',
                'center_color' => $topShare['color'] ?? '#22c55e',
                'engine' => 'apexcharts',
            ],
            'totals' => [
                'tokens' => $this->tokenLabel($totalTokens).' tkn',
                'avg_rate' => $totalTokens > 0 ? $this->money(($totalSpend / $totalTokens) * 1_000_000) : $this->money(0),
                'best' => $best ? $best['name'].' ('.$this->money((float) $best['rate']).' / 1M)' : '—',
                'worst' => $worst ? $worst['name'].' ('.$this->money((float) $worst['rate']).' / 1M)' : '—',
            ],
            'insights' => AiInsight::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->unique('title')
                ->values()
                ->map(fn (AiInsight $insight): array => [
                    'kind' => $insight->kind,
                    'title' => $insight->title,
                    'body' => $insight->body,
                    'action_label' => $insight->action_label ?: 'View details',
                    'savings_label' => $insight->savings_label,
                ])
                ->all(),
            'weekly_spend' => $this->weeklySpend($reports, $start),
            'rate_series' => [
                'labels' => array_column($services, 'name'),
                'values' => array_map(fn (array $row): float => round((float) $row['rate'], 2), $services),
                'colors' => array_column($services, 'color'),
            ],
        ];
    }

    /**
     * @param  Collection<int, AiUsageReport>  $reports
     * @param  Collection<int, AiUsageReport>  $previous
     * @return list<array<string, mixed>>
     */
    protected function serviceRows(Collection $reports, Collection $previous): array
    {
        $current = $reports->groupBy('service_id');
        $prior = $previous->groupBy('service_id');
        $services = AiService::query()->where('is_active', true)->orderBy('sort_order')->get();

        return $services->map(function (AiService $service) use ($current, $prior): array {
            $rows = $current->get($service->id, collect());
            $old = $prior->get($service->id, collect());
            $tokens = (int) $rows->sum('tokens');
            $spend = (float) $rows->sum('spend_usd');
            $oldSpend = (float) $old->sum('spend_usd');
            $trend = $oldSpend > 0 ? (($spend - $oldSpend) / $oldSpend) * 100 : ($spend > 0 ? 100.0 : 0.0);
            $listRate = (float) ($service->cost_per_million ?? 0);
            $rate = $listRate > 0 ? $listRate : ($tokens > 0 ? ($spend / $tokens) * 1_000_000 : 0.0);

            return [
                'id' => $service->id,
                'name' => $service->name,
                'slug' => $service->slug,
                'color' => $service->color,
                'initials' => $service->initials ?: Str::upper(Str::substr($service->name, 0, 1)),
                'tokens' => $tokens,
                'tokens_label' => $this->tokenLabel($tokens),
                'spend' => $spend,
                'spend_label' => $this->money($spend),
                'rate' => $rate,
                'rate_label' => $this->money($rate),
                'trend' => $trend,
                'trend_label' => $this->signedPercent($trend),
                'up' => $trend >= 0,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, AiUsageReport>  $reports
     * @return list<float>
     */
    protected function weeklySpend(Collection $reports, Carbon $start): array
    {
        $history = AiUsageReport::query()
            ->where('reported_on', '>=', $start->copy()->subWeeks(8)->toDateString())
            ->get();

        if ($history->isEmpty()) {
            $history = $reports;
        }

        $weeks = [];

        for ($i = 0; $i < 8; $i++) {
            $from = $start->copy()->subWeeks(7 - $i)->startOfWeek();
            $to = $from->copy()->endOfWeek();
            $weeks[] = round((float) $history
                ->filter(fn (AiUsageReport $row): bool => $row->reported_on?->between($from, $to))
                ->sum('spend_usd'), 2);
        }

        return $weeks;
    }

    protected function greeting(mixed $user): string
    {
        $hour = (int) now()->format('G');
        $hello = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $name = 'there';

        if (is_object($user)) {
            $custom = is_array($user->custom_fields ?? null) ? $user->custom_fields : [];
            $name = (string) ($custom['first_name'] ?? Str::of((string) ($user->name ?? ''))->before(' '));
            $name = trim($name) !== '' ? $name : 'there';
        }

        return $hello.', '.$name;
    }

    protected function money(float $amount, bool $signed = false): string
    {
        $prefix = '';

        if ($signed) {
            $prefix = $amount > 0 ? '+' : ($amount < 0 ? '−' : '');
            $amount = abs($amount);
        }

        $decimals = abs($amount - round($amount)) < 0.005 ? 0 : 2;

        return $prefix.'$'.number_format($amount, $decimals);
    }

    protected function signedPercent(float $value): string
    {
        $sign = $value > 0 ? '+' : ($value < 0 ? '' : '');

        return $sign.number_format($value, 0).'%';
    }

    protected function tokenLabel(int $tokens): string
    {
        if ($tokens >= 1_000_000) {
            return rtrim(rtrim(number_format($tokens / 1_000_000, 1), '0'), '.').'M';
        }

        if ($tokens >= 1_000) {
            return rtrim(rtrim(number_format($tokens / 1_000, 1), '0'), '.').'K';
        }

        return (string) $tokens;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptySnapshot(Carbon $month, mixed $user): array
    {
        return [
            'month_label' => $month->format('F Y'),
            'greeting' => $this->greeting($user),
            'kpis' => [],
            'services' => [],
            'donut' => ['labels' => [], 'values' => [], 'colors' => [], 'center_name' => '—', 'center_tokens' => '0', 'center_share' => '0%', 'center_initials' => 'AI', 'center_color' => '#22c55e', 'engine' => 'apexcharts'],
            'totals' => ['tokens' => '0 tkn', 'avg_rate' => '$0', 'best' => '—', 'worst' => '—'],
            'insights' => [],
            'weekly_spend' => [0, 0, 0, 0, 0, 0, 0, 0],
            'rate_series' => ['labels' => [], 'values' => [], 'colors' => []],
        ];
    }
}
