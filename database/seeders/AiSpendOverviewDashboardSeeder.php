<?php

namespace Spiggle\VisualDashboards\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Models\Dashlet;

/**
 * Seeds the AI spend presentation board used by the Filament Overview page only.
 */
class AiSpendOverviewDashboardSeeder
{
    public const DASHBOARD_NAME = 'Overview';

    public function run(Model $user): CustomDashboard
    {
        (new AiSpendSeeder)->run();

        $this->ensureOverviewUserName($user);

        $dashboard = $this->upsertDashboard($user);
        $visual = ['border' => true, 'chart_engine' => 'apexcharts'];

        $this->syncDashlets($dashboard, [
            $this->definition('overview-hero', 'hero', 0, 0, 12, 2, [
                'title' => 'Greeting',
                'heading' => 'Greeting',
                'subheading' => '',
                'source_kind' => 'model',
                'data_source' => 'ai_spend',
                'filters' => [],
                'visual' => [...$visual, 'border' => false],
            ]),
            $this->definition('overview-spend', 'stat_card', 0, 2, 3, 3, [
                'title' => 'Total Spend',
                'heading' => 'Total Spend',
                'subheading' => 'Logged usage this month',
                'source_kind' => 'model',
                'data_source' => 'ai_spend',
                'filters' => ['metric' => 'spend'],
                'visual' => [...$visual, 'color' => '#e11d48', 'number_format' => 'currency'],
            ]),
            $this->definition('overview-projected', 'stat_card', 3, 2, 3, 3, [
                'title' => 'Projected Spend',
                'heading' => 'Projected Spend',
                'subheading' => 'Forecast from usage + month-end model',
                'source_kind' => 'model',
                'data_source' => 'ai_spend',
                'filters' => ['metric' => 'projected'],
                'visual' => [...$visual, 'color' => '#0f766e', 'number_format' => 'currency'],
            ]),
            $this->definition('overview-anomalies', 'stat_card', 6, 2, 3, 3, [
                'title' => 'Anomaly Alert',
                'heading' => 'Anomaly Alert',
                'subheading' => 'Spikes reported on the alert form',
                'source_kind' => 'model',
                'data_source' => 'ai_spend',
                'filters' => ['metric' => 'anomalies'],
                'visual' => [...$visual, 'color' => '#111827'],
            ]),
            $this->definition('overview-growing', 'stat_card', 9, 2, 3, 3, [
                'title' => 'Top Growing Service',
                'heading' => 'Top Growing Service',
                'subheading' => 'Highest month-over-month spend change',
                'source_kind' => 'model',
                'data_source' => 'ai_spend',
                'filters' => ['metric' => 'growing'],
                'visual' => [...$visual, 'color' => '#22c55e'],
            ]),
            $this->definition('overview-services', 'table', 0, 5, 5, 7, [
                'title' => 'Services Overview',
                'heading' => 'Services Overview',
                'subheading' => 'Tokens and spend collected from usage forms',
                'source_kind' => 'model',
                'data_source' => 'ai_spend',
                'filters' => [],
                'visual' => [...$visual, 'color' => '#0f172a'],
            ]),
            $this->definition('overview-donut', 'donut', 5, 5, 4, 7, [
                'title' => 'Token share',
                'heading' => 'Token share',
                'subheading' => 'Current month tokens by service',
                'source_kind' => 'model',
                'data_source' => 'ai_spend',
                'filters' => ['metric' => 'tokens'],
                'visual' => [...$visual, 'color' => '#22c55e'],
            ]),
            $this->definition('overview-insights', 'insight_list', 9, 5, 3, 7, [
                'title' => 'AI Insights & Recommendations',
                'heading' => 'AI Insights & Recommendations',
                'subheading' => 'Actionable savings suggestions',
                'source_kind' => 'model',
                'data_source' => 'ai_spend',
                'filters' => ['metric' => 'insights'],
                'visual' => [...$visual, 'color' => '#16a34a'],
            ]),
        ]);

        return $dashboard;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function definition(string $seedKey, string $type, int $x, int $y, int $w, int $h, array $config): array
    {
        return [
            'seed_key' => $seedKey,
            'type' => $type,
            'grid_x' => $x,
            'grid_y' => $y,
            'grid_w' => $w,
            'grid_h' => $h,
            'config' => $config,
        ];
    }

    protected function upsertDashboard(Model $user): CustomDashboard
    {
        /** @var CustomDashboard $dashboard */
        $dashboard = CustomDashboard::query()->firstOrCreate(
            [
                'user_id' => $user->getKey(),
                'name' => self::DASHBOARD_NAME,
            ],
            [
                'is_default' => false,
                'is_published' => true,
            ]
        );

        if (! $dashboard->is_published) {
            $dashboard->update(['is_published' => true]);
        }

        return $dashboard;
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     */
    protected function syncDashlets(CustomDashboard $dashboard, array $definitions): void
    {
        foreach ($definitions as $definition) {
            $seedKey = (string) $definition['seed_key'];
            $config = array_merge($definition['config'], ['seed_key' => $seedKey]);

            $dashlet = Dashlet::query()
                ->where('custom_dashboard_id', $dashboard->id)
                ->where('config->seed_key', $seedKey)
                ->first();

            $attributes = [
                'type' => $definition['type'],
                'grid_x' => $definition['grid_x'],
                'grid_y' => $definition['grid_y'],
                'grid_w' => $definition['grid_w'],
                'grid_h' => $definition['grid_h'],
                'config' => $config,
            ];

            if ($dashlet) {
                $dashlet->update($attributes);

                continue;
            }

            Dashlet::query()->create(array_merge($attributes, [
                'custom_dashboard_id' => $dashboard->id,
            ]));
        }
    }

    protected function ensureOverviewUserName(Model $user): void
    {
        if (
            method_exists($user, 'setCustomFieldValue')
            && class_exists(\Spiggle\DynamicFields\Models\CustomField::class)
            && \Spiggle\DynamicFields\Models\CustomField::query()
                ->where('target_model', $user::class)
                ->where('name', 'first_name')
                ->exists()
        ) {
            $current = method_exists($user, 'getCustomFieldValue')
                ? trim((string) $user->getCustomFieldValue('first_name'))
                : '';

            if ($current === '') {
                $user->setCustomFieldValue('first_name', 'Ada');
            }

            return;
        }

        $custom = is_array($user->custom_fields ?? null) ? $user->custom_fields : [];

        if (trim((string) ($custom['first_name'] ?? '')) === '' && trim((string) ($user->name ?? '')) === '') {
            $user->forceFill(['name' => 'Ada Lovelace'])->save();
        }
    }
}
