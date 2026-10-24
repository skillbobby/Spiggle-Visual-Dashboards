<?php

namespace Spiggle\VisualDashboards\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spiggle\FormBuilder\Models\Form;
use Spiggle\FormBuilder\Models\FormSubmission;
use Spiggle\VisualDashboards\Models\AiAnomaly;
use Spiggle\VisualDashboards\Models\AiForecast;
use Spiggle\VisualDashboards\Models\AiInsight;
use Spiggle\VisualDashboards\Models\AiService;
use Spiggle\VisualDashboards\Models\AiUsageReport;
use Spiggle\VisualDashboards\Support\AiSpendFormSync;

class AiSpendSeeder extends Seeder
{
    public function run(bool $fresh = false): void
    {
        if (! Schema::hasTable((new AiService)->getTable())) {
            return;
        }

        if ($fresh) {
            $this->forgetFormSubmissions();
            AiUsageReport::query()->delete();
            AiAnomaly::query()->delete();
            AiInsight::query()->delete();
            if (Schema::hasTable((new AiForecast)->getTable())) {
                AiForecast::query()->delete();
            }
            AiService::query()->delete();
        }

        $this->seedServices();

        $sync = app(AiSpendFormSync::class);
        $sync->ensureForms();

        $month = now()->copy()->startOfMonth();

        if (! AiUsageReport::query()->exists()) {
            if (class_exists(FormSubmission::class) && Schema::hasTable((new FormSubmission)->getTable())) {
                $this->seedViaForms($month);
                $sync->syncAllSubmissions();
            } else {
                $this->seedModelsDirectly($month);
            }
        }

        if (AiAnomaly::query()->doesntExist()) {
            $this->seedAnomalies($month);
        }

        if (AiInsight::query()->doesntExist()) {
            $this->seedInsights();
        }

        $this->seedForecast($month);
    }

    protected function seedServices(): void
    {
        $services = [
            ['name' => 'OpenAI', 'slug' => 'openai', 'vendor_key' => 'openai', 'color' => '#22c55e', 'initials' => 'OA', 'cost_per_million' => 98],
            ['name' => 'Google AI', 'slug' => 'google-ai', 'vendor_key' => 'google', 'color' => '#22d3ee', 'initials' => 'G', 'cost_per_million' => 88],
            ['name' => 'Azure', 'slug' => 'azure', 'vendor_key' => 'azure', 'color' => '#3b82f6', 'initials' => 'Az', 'cost_per_million' => 100],
            ['name' => 'Amazon Bedrock', 'slug' => 'amazon-bedrock', 'vendor_key' => 'bedrock', 'color' => '#8b5cf6', 'initials' => 'AB', 'cost_per_million' => 100],
            ['name' => 'Replicate', 'slug' => 'replicate', 'vendor_key' => 'replicate', 'color' => '#f43f5e', 'initials' => 'R', 'cost_per_million' => 100],
        ];

        foreach ($services as $index => $service) {
            AiService::query()->updateOrCreate(
                ['slug' => $service['slug']],
                [
                    ...$service,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    protected function forgetFormSubmissions(): void
    {
        if (! class_exists(Form::class) || ! class_exists(FormSubmission::class)) {
            return;
        }

        if (! Schema::hasTable((new FormSubmission)->getTable())) {
            return;
        }

        $ids = Form::query()->whereIn('slug', [
            AiSpendFormSync::SPEND_SLUG,
            AiSpendFormSync::ALERT_SLUG,
            AiSpendFormSync::INSIGHT_SLUG,
        ])->pluck('id');

        FormSubmission::query()->whereIn('form_id', $ids)->delete();
    }

    protected function seedViaForms(Carbon $month): void
    {
        $spendForm = Form::query()->where('slug', AiSpendFormSync::SPEND_SLUG)->first();
        $alertForm = Form::query()->where('slug', AiSpendFormSync::ALERT_SLUG)->first();
        $insightForm = Form::query()->where('slug', AiSpendFormSync::INSIGHT_SLUG)->first();

        if (! $spendForm) {
            $this->seedModelsDirectly($month);

            return;
        }

        $targets = $this->monthTargets();
        $previous = $month->copy()->subMonth();

        FormSubmission::withoutEvents(function () use ($spendForm, $month, $previous, $targets, $alertForm, $insightForm): void {
            foreach (AiService::query()->get() as $service) {
                $target = $targets[$service->slug] ?? ['tokens' => 100000, 'spend' => 20, 'growth' => 0];
                $this->submitMonth($spendForm->id, $service->slug, $month, $month->daysInMonth, (int) $target['tokens'], (float) $target['spend']);
                $factor = 1 + (((float) $target['growth']) / 100);
                $this->submitMonth(
                    $spendForm->id,
                    $service->slug,
                    $previous,
                    $previous->daysInMonth,
                    $factor != 0.0 ? (int) round($target['tokens'] / $factor) : (int) $target['tokens'],
                    $factor != 0.0 ? round($target['spend'] / $factor, 2) : (float) $target['spend']
                );
            }

            if ($alertForm) {
                foreach ($this->anomalyPayloads($month) as $payload) {
                    $this->submit($alertForm->id, $payload);
                }
            }

            if ($insightForm) {
                foreach ($this->insightPayloads() as $index => $payload) {
                    $this->submit($insightForm->id, [...$payload, 'sort_order' => $index + 1]);
                }
            }
        });
    }

    protected function seedModelsDirectly(Carbon $month): void
    {
        $targets = $this->monthTargets();
        $previous = $month->copy()->subMonth();

        foreach (AiService::query()->get() as $service) {
            $target = $targets[$service->slug] ?? ['tokens' => 100000, 'spend' => 20, 'growth' => 0];
            $this->insertMonth($service->id, $month, $month->daysInMonth, (int) $target['tokens'], (float) $target['spend']);
            $factor = 1 + (((float) $target['growth']) / 100);
            $this->insertMonth(
                $service->id,
                $previous,
                $previous->daysInMonth,
                $factor != 0.0 ? (int) round($target['tokens'] / $factor) : (int) $target['tokens'],
                $factor != 0.0 ? round($target['spend'] / $factor, 2) : (float) $target['spend']
            );
        }

        $this->seedAnomalies($month);
        $this->seedInsights();
    }

    protected function seedAnomalies(Carbon $month): void
    {
        if (AiAnomaly::query()->exists()) {
            return;
        }

        foreach ($this->anomalyPayloads($month) as $payload) {
            AiAnomaly::query()->create([
                'service_id' => AiService::query()->where('slug', $payload['service'])->value('id'),
                'detected_on' => $payload['detected_on'],
                'amount_usd' => $payload['amount_usd'],
                'title' => $payload['title'],
            ]);
        }
    }

    protected function seedInsights(): void
    {
        if (AiInsight::query()->exists()) {
            return;
        }

        foreach ($this->insightPayloads() as $index => $payload) {
            AiInsight::query()->create([
                ...$payload,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * @return array<string, array{tokens: int, spend: float, growth: float}>
     */
    protected function monthTargets(): array
    {
        return [
            'openai' => ['tokens' => 8_300_000, 'spend' => 820, 'growth' => 12],
            'google-ai' => ['tokens' => 2_500_000, 'spend' => 250, 'growth' => -5],
            'azure' => ['tokens' => 1_200_000, 'spend' => 120, 'growth' => 0],
            'amazon-bedrock' => ['tokens' => 900_000, 'spend' => 90, 'growth' => 7],
            'replicate' => ['tokens' => 500_000, 'spend' => 50, 'growth' => -2],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function anomalyPayloads(Carbon $month): array
    {
        return [
            [
                'service' => 'openai',
                'detected_on' => $month->copy()->day(1)->toDateString(),
                'amount_usd' => 200,
                'title' => '+$200 spike',
            ],
            [
                'service' => 'replicate',
                'detected_on' => $month->copy()->day(min(12, $month->daysInMonth))->toDateString(),
                'amount_usd' => 48,
                'title' => 'Idle GPU burst',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function insightPayloads(): array
    {
        return [
            [
                'kind' => 'savings',
                'title' => 'Switch to GPT-4-mini',
                'body' => 'You’re over budget on GPT-4 usage by 15%.',
                'action_label' => 'View details',
                'savings_label' => 'Save $200/month',
            ],
            [
                'kind' => 'waste',
                'title' => 'Pause idle GPUs (Replicate)',
                'body' => 'Idle GPUs incur costs without active jobs.',
                'action_label' => 'View details',
                'savings_label' => 'Stop $50/day waste',
            ],
            [
                'kind' => 'errors',
                'title' => 'Fix API error handling',
                'body' => 'Most retries are from malformed prompts.',
                'action_label' => 'View details',
                'savings_label' => 'Reduce token retries (20%)',
            ],
        ];
    }

    protected function seedForecast(Carbon $month): void
    {
        if (! Schema::hasTable((new AiForecast)->getTable())) {
            return;
        }

        AiForecast::query()->updateOrCreate(
            ['year' => $month->year, 'month' => $month->month],
            ['projected_spend' => 1400]
        );
    }

    protected function submitMonth(int $formId, string $slug, Carbon $month, int $days, int $tokens, float $spend): void
    {
        foreach ($this->dailyShares($days, $tokens, $spend) as $day => $row) {
            $this->submit($formId, [
                'service' => $slug,
                'reported_on' => $month->copy()->day($day)->toDateString(),
                'tokens' => $row['tokens'],
                'spend_usd' => $row['spend'],
            ]);
        }
    }

    protected function insertMonth(int $serviceId, Carbon $month, int $days, int $tokens, float $spend): void
    {
        foreach ($this->dailyShares($days, $tokens, $spend) as $day => $row) {
            AiUsageReport::query()->updateOrCreate(
                [
                    'service_id' => $serviceId,
                    'reported_on' => $month->copy()->day($day)->toDateString(),
                ],
                [
                    'tokens' => $row['tokens'],
                    'spend_usd' => $row['spend'],
                ]
            );
        }
    }

    /**
     * @return array<int, array{tokens: int, spend: float}>
     */
    protected function dailyShares(int $days, int $tokens, float $spend): array
    {
        $weights = [];
        $sum = 0.0;

        for ($day = 1; $day <= $days; $day++) {
            $weight = 0.65 + (0.35 * (0.5 + 0.5 * sin(($day / max($days, 1)) * 6.2)));
            $weights[$day] = $weight;
            $sum += $weight;
        }

        $out = [];
        $tokenUsed = 0;
        $spendUsed = 0.0;

        for ($day = 1; $day <= $days; $day++) {
            $share = $weights[$day] / $sum;
            $dayTokens = $day === $days ? ($tokens - $tokenUsed) : (int) round($tokens * $share);
            $daySpend = $day === $days ? round($spend - $spendUsed, 2) : round($spend * $share, 2);
            $tokenUsed += $dayTokens;
            $spendUsed += $daySpend;
            $out[$day] = ['tokens' => max(0, $dayTokens), 'spend' => max(0, $daySpend)];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function submit(int $formId, array $data): void
    {
        FormSubmission::query()->create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $formId,
            'status' => 'new',
            'data' => $data,
            'ip_address' => '127.0.0.1',
        ]);
    }
}
