<?php

namespace Spiggle\VisualDashboards\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spiggle\FormBuilder\Events\FormSubmitted;
use Spiggle\FormBuilder\Models\Form;
use Spiggle\FormBuilder\Models\FormSubmission;
use Spiggle\FormBuilder\Support\SchemaNormalizer;
use Spiggle\VisualDashboards\Models\AiAnomaly;
use Spiggle\VisualDashboards\Models\AiInsight;
use Spiggle\VisualDashboards\Models\AiService;
use Spiggle\VisualDashboards\Models\AiUsageReport;

class AiSpendFormSync
{
    public const SPEND_SLUG = 'ai-spend-report';

    public const ALERT_SLUG = 'ai-spend-alert';

    public const INSIGHT_SLUG = 'ai-spend-recommendation';

    public function ensureForms(): void
    {
        if (! class_exists(Form::class) || ! Schema::hasTable((new Form)->getTable())) {
            return;
        }

        $this->upsertForm(self::SPEND_SLUG, 'AI Spend Report', 'Daily token usage and spend by vendor.', [
            ['name' => 'service', 'type' => 'select', 'label' => 'Service', 'required' => true, 'column_span' => 6, 'options' => $this->serviceOptions()],
            ['name' => 'reported_on', 'type' => 'date', 'label' => 'Usage date', 'required' => true, 'column_span' => 6],
            ['name' => 'tokens', 'type' => 'number', 'label' => 'Tokens', 'required' => true, 'column_span' => 6],
            ['name' => 'spend_usd', 'type' => 'number', 'label' => 'Spend (USD)', 'required' => true, 'column_span' => 6],
        ]);

        $this->upsertForm(self::ALERT_SLUG, 'AI Spend Alert', 'Report an unexpected cost spike.', [
            ['name' => 'service', 'type' => 'select', 'label' => 'Service', 'required' => true, 'column_span' => 6, 'options' => $this->serviceOptions()],
            ['name' => 'detected_on', 'type' => 'date', 'label' => 'Detected on', 'required' => true, 'column_span' => 6],
            ['name' => 'amount_usd', 'type' => 'number', 'label' => 'Spike amount (USD)', 'required' => true, 'column_span' => 6],
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'column_span' => 6],
        ]);

        $this->upsertForm(self::INSIGHT_SLUG, 'AI Spend Recommendation', 'A savings action for the spend board.', [
            ['name' => 'kind', 'type' => 'select', 'label' => 'Kind', 'required' => true, 'column_span' => 6, 'options' => [
                ['label' => 'Savings', 'value' => 'savings'],
                ['label' => 'Waste', 'value' => 'waste'],
                ['label' => 'Error handling', 'value' => 'errors'],
            ]],
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'column_span' => 6],
            ['name' => 'body', 'type' => 'textarea', 'label' => 'Details', 'required' => true, 'column_span' => 12],
            ['name' => 'action_label', 'type' => 'text', 'label' => 'Button label', 'required' => false, 'column_span' => 6],
            ['name' => 'savings_label', 'type' => 'text', 'label' => 'Savings label', 'required' => false, 'column_span' => 6],
            ['name' => 'sort_order', 'type' => 'number', 'label' => 'Sort order', 'required' => false, 'column_span' => 6],
        ]);
    }

    public function syncSubmission(FormSubmission $submission): void
    {
        $slug = (string) ($submission->form?->slug ?? '');
        $payload = is_array($submission->data) ? $submission->data : [];

        match ($slug) {
            self::SPEND_SLUG => $this->syncUsage($submission, $payload),
            self::ALERT_SLUG => $this->syncAnomaly($submission, $payload),
            self::INSIGHT_SLUG => $this->syncInsight($submission, $payload),
            default => null,
        };
    }

    public function syncAllSubmissions(): void
    {
        if (! class_exists(FormSubmission::class) || ! Schema::hasTable((new FormSubmission)->getTable())) {
            return;
        }

        FormSubmission::query()
            ->with('form')
            ->whereHas('form', fn ($query) => $query->whereIn('slug', [
                self::SPEND_SLUG,
                self::ALERT_SLUG,
                self::INSIGHT_SLUG,
            ]))
            ->orderBy('id')
            ->each(fn (FormSubmission $submission) => $this->syncSubmission($submission));
    }

    public function handleSubmitted(FormSubmitted $event): void
    {
        $this->syncSubmission($event->submission->loadMissing('form'));
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    protected function upsertForm(string $slug, string $name, string $description, array $fields): Form
    {
        $schema = SchemaNormalizer::normalize([
            [
                'label' => $name,
                'description' => $description,
                'fields' => $fields,
            ],
        ]);

        return Form::withoutEvents(function () use ($slug, $name, $description, $schema): Form {
            /** @var Form $form */
            $form = Form::query()->firstOrNew(['slug' => $slug]);
            $form->forceFill([
                'uuid' => $form->uuid ?: (string) Str::uuid(),
                'name' => $name,
                'slug' => $slug,
                'base_path' => $form->base_path ?: $slug,
                'description' => $description,
                'container_type' => 'single',
                'schema_version' => '1.0',
                'schema' => $schema,
                'settings' => ['label_position' => 'above'],
                'is_published' => true,
                'is_active' => true,
                'success_message' => 'Saved. The AI Spend dashboard will pick this up.',
            ]);
            $form->save();

            return $form;
        });
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    protected function serviceOptions(): array
    {
        if (! Schema::hasTable((new AiService)->getTable())) {
            return [];
        }

        return AiService::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (AiService $service): array => [
                'label' => $service->name,
                'value' => $service->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function syncUsage(FormSubmission $submission, array $payload): void
    {
        $service = $this->serviceFromPayload($payload);

        if (! $service) {
            return;
        }

        $reportedOn = Carbon::parse((string) ($payload['reported_on'] ?? $payload['usage_date'] ?? now()))->toDateString();
        $report = AiUsageReport::query()
            ->where('service_id', $service->id)
            ->whereDate('reported_on', $reportedOn)
            ->first();

        $values = [
            'tokens' => (int) ($payload['tokens'] ?? 0),
            'spend_usd' => (float) ($payload['spend_usd'] ?? $payload['spend'] ?? 0),
            'form_submission_id' => $submission->id,
        ];

        if ($report) {
            $report->update($values);

            return;
        }

        AiUsageReport::query()->create([
            'service_id' => $service->id,
            'reported_on' => $reportedOn,
            ...$values,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function syncAnomaly(FormSubmission $submission, array $payload): void
    {
        $service = $this->serviceFromPayload($payload);

        AiAnomaly::query()->updateOrCreate(
            ['form_submission_id' => $submission->id],
            [
                'service_id' => $service?->id,
                'detected_on' => Carbon::parse((string) ($payload['detected_on'] ?? $payload['occurred_on'] ?? now()))->toDateString(),
                'amount_usd' => (float) ($payload['amount_usd'] ?? $payload['amount'] ?? 0),
                'title' => (string) ($payload['title'] ?? 'Spend spike'),
                'note' => $payload['note'] ?? null,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function syncInsight(FormSubmission $submission, array $payload): void
    {
        AiInsight::query()->updateOrCreate(
            ['form_submission_id' => $submission->id],
            [
                'kind' => (string) ($payload['kind'] ?? 'switch'),
                'title' => (string) ($payload['title'] ?? 'Recommendation'),
                'body' => (string) ($payload['body'] ?? ''),
                'action_label' => $payload['action_label'] ?? 'View details',
                'savings_label' => $payload['savings_label'] ?? $payload['impact_label'] ?? null,
                'sort_order' => (int) ($payload['sort_order'] ?? 0),
                'is_active' => true,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function serviceFromPayload(array $payload): ?AiService
    {
        $slug = Str::slug((string) ($payload['service'] ?? ''));

        if ($slug === '') {
            return null;
        }

        return AiService::query()->where('slug', $slug)->first();
    }
}
