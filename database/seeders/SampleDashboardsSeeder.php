<?php

namespace Spiggle\VisualDashboards\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Models\Dashlet;
use Spiggle\VisualDashboards\Support\VisualTypes;

class SampleDashboardsSeeder extends Seeder
{
    public const CHART_GALLERY_NAME = 'Chart gallery';

    /**
     * Demo dashboards removed in favour of a single chart-type showcase board.
     *
     * @var list<string>
     */
    public const LEGACY_DASHBOARD_NAMES = [
        'Overview',
        'Sample metrics',
        'User analytics',
        'Form operations',
        'Access analytics',
        'AI spend',
    ];

    /**
     * @var list<string>
     */
    public const DEMO_DASHBOARD_NAMES = [
        self::CHART_GALLERY_NAME,
    ];

    public function run(?Model $user = null): Model
    {
        $user = $user ?? $this->resolveUser();

        $this->removeLegacyDashboards($user);
        $this->seedChartGalleryDashboard($user);

        return $user;
    }

    public function removeLegacyDashboards(Model $user): void
    {
        CustomDashboard::query()
            ->where('user_id', $user->getKey())
            ->whereIn('name', self::LEGACY_DASHBOARD_NAMES)
            ->each(fn (CustomDashboard $dashboard): mixed => $dashboard->delete());
    }

    public function seedChartGalleryDashboard(Model $user): ?CustomDashboard
    {
        $submissions = app(\Spiggle\VisualDashboards\DataSources\FormSubmissionDataSource::class);

        if (! $submissions->available()) {
            return null;
        }

        $this->ensureShowcaseFormData($user);

        $form = $this->resolveContactUsForm();

        if ($form) {
            $this->ensureShowcaseAuditData($user, (int) $form->getKey());
        }

        if (! $form) {
            return null;
        }

        $formId = (int) $form->getKey();
        $formLabel = (string) ($form->name ?? 'Contact Us');
        $dashboard = $this->upsertDashboard($user, self::CHART_GALLERY_NAME, isDefault: true);
        $dashlets = [];
        $x = 0;
        $y = 0;
        $rowHeight = 0;

        foreach (VisualTypes::gallery() as $entry) {
            if (! ($entry['enabled'] ?? false)) {
                continue;
            }

            $type = (string) $entry['type'];
            $size = VisualTypes::defaultSize($type);
            $w = (int) $size['w'];
            $h = (int) $size['h'];

            if ($x + $w > 12) {
                $x = 0;
                $y += max(4, $rowHeight);
                $rowHeight = 0;
            }

            $rowHeight = max($rowHeight, $h);
            $label = (string) ($entry['label'] ?? $type);
            $config = $this->chartGalleryConfigForType($type, $formId, $formLabel, $label);
            $dashlets[] = $this->definition('gallery-'.$type, $type, $x, $y, $w, $h, $config);
            $x += $w;
        }

        $this->syncDashlets($dashboard, $dashlets, prune: true);

        return $dashboard;
    }

    /**
     * @return array<string, mixed>
     */
    protected function chartGalleryConfigForType(string $type, int $formId, string $formLabel, string $label): array
    {
        $submissions = $this->wizardFormSubmissionConfig($formId, [
            'title' => $label,
            'heading' => $label,
            'subheading' => $formLabel.' submissions',
            'filters' => ['form_id' => $formId, 'days' => '90'],
            'window' => 'last_90_days',
        ]);

        $audit = $this->wizardFormAuditLogConfig($formId, [
            'title' => $label,
            'heading' => $label,
            'subheading' => $formLabel.' audit activity',
            'filters' => ['form_id' => $formId, 'days' => '90'],
            'window' => 'last_90_days',
        ]);

        return match ($type) {
            'stat_card' => array_replace_recursive($submissions, [
                'mapping' => ['y' => 'value'],
                'subheading' => 'Total '.$formLabel.' submissions',
                'visual' => [
                    'color_scheme' => 'violet',
                    'kpi' => [
                        'icon' => 'inbox',
                        'metric_label' => 'Total submissions',
                        'period' => 'monthly',
                        'custom_days' => 30,
                        'value_size' => 'lg',
                        'value_weight' => 'bold',
                        'comparison' => [
                            'enabled' => true,
                            'mode' => 'previous_period',
                            'delta' => null,
                            'up' => true,
                            'higher_is_better' => true,
                            'label' => 'than last month',
                        ],
                    ],
                ],
            ]),
            'table' => $this->wizardFormSubmissionTableConfig($formId, ['id', 'status', 'form_name', 'created_at'], [
                'title' => $label,
                'heading' => $label,
                'subheading' => $formLabel.' submission rows',
                'filters' => ['form_id' => $formId, 'days' => '90'],
                'window' => 'last_90_days',
            ]),
            'line' => array_replace_recursive($audit, [
                'group_by' => ['created_at'],
                'mapping' => ['x' => 'created_at', 'y' => 'value'],
                'filters' => ['form_id' => $formId, 'days' => '90', 'grain' => 'month'],
                'visual' => [
                    'color' => '#6366f1',
                    'border' => true,
                    'color_scheme' => 'violet',
                    'period_grains' => ['day', 'week', 'month', 'year'],
                    'period_filter' => true,
                ],
                'subheading' => 'Audit events over time',
            ]),
            'bar' => array_replace_recursive($audit, [
                'group_by' => ['action'],
                'mapping' => ['x' => 'action', 'y' => 'value'],
                'visual' => [
                    'color' => '#3b82f6',
                    'border' => true,
                    'color_scheme' => 'blue',
                ],
                'subheading' => 'Audit events by action',
            ]),
            'column' => array_replace_recursive($submissions, [
                'group_by' => ['status'],
                'mapping' => ['x' => 'status', 'y' => 'value'],
                'visual' => [
                    'color' => '#10b981',
                    'border' => true,
                    'color_scheme' => 'emerald',
                ],
                'subheading' => 'Submissions by status',
            ]),
            'pie' => array_replace_recursive($audit, [
                'group_by' => ['action'],
                'mapping' => ['x' => 'action', 'y' => 'value'],
                'visual' => [
                    'color' => '#f43f5e',
                    'border' => true,
                    'color_scheme' => 'rose',
                ],
                'subheading' => 'Audit mix by action',
            ]),
            'donut' => array_replace_recursive($submissions, [
                'group_by' => ['status'],
                'mapping' => ['x' => 'status', 'y' => 'value'],
                'visual' => [
                    'color' => '#8b5cf6',
                    'border' => true,
                    'color_scheme' => 'violet',
                ],
                'subheading' => 'Submission status split',
            ]),
            'stacked_column' => array_replace_recursive($audit, [
                'group_by' => ['created_at', 'action'],
                'mapping' => ['x' => 'created_at', 'y' => 'value', 'series' => 'action'],
                'filters' => ['form_id' => $formId, 'days' => '90', 'grain' => 'week'],
                'visual' => [
                    'color' => '#0ea5e9',
                    'border' => true,
                    'color_scheme' => 'sky',
                    'period_grains' => ['day', 'week', 'month'],
                    'period_filter' => true,
                ],
                'subheading' => 'Audit events stacked by action',
            ]),
            'funnel' => array_replace_recursive($audit, [
                'group_by' => ['action'],
                'mapping' => ['x' => 'action', 'y' => 'value'],
                'visual' => [
                    'color' => '#f59e0b',
                    'border' => true,
                    'color_scheme' => 'amber',
                ],
                'subheading' => 'Submission lifecycle funnel',
            ]),
            'radialbar' => array_replace_recursive($audit, [
                'group_by' => [],
                'mapping' => ['y' => 'value'],
                'aggregations' => [
                    ['fn' => 'COUNT', 'field' => '*', 'alias' => 'value'],
                ],
                'visual' => [
                    'color' => '#64748b',
                    'border' => true,
                    'color_scheme' => 'slate',
                    'chart_variant' => 'semi_circle',
                ],
                'subheading' => 'Audit event volume',
            ]),
            default => array_replace_recursive($submissions, [
                'group_by' => ['status'],
                'mapping' => ['x' => 'status', 'y' => 'value'],
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function wizardFormSubmissionConfig(?int $formId, array $overrides = []): array
    {
        $primarySource = $formId ? "form:{$formId}" : 'registered:form_submissions';

        $base = [
            'source_kind' => $formId ? 'form' : 'model',
            'data_source' => 'form_submissions',
            'filters' => array_filter(['form_id' => $formId]),
            'query' => [
                'applied' => true,
                'primary_source' => $primarySource,
                'transforms' => [],
            ],
            'group_by' => [],
            'aggregations' => [
                ['fn' => 'COUNT', 'field' => '*', 'alias' => 'value'],
            ],
            'mapping' => ['y' => 'value'],
            'visual' => ['border' => true, 'chart_engine' => 'apexcharts', 'color_scheme' => 'amber'],
        ];

        return array_replace_recursive($base, $overrides);
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    protected function wizardFormSubmissionTableConfig(?int $formId, array $columns, array $overrides = []): array
    {
        $config = $this->wizardFormSubmissionConfig($formId, $overrides);
        unset($config['aggregations'], $config['group_by'], $config['mapping']);
        $config['aggregations'] = [];
        $config['group_by'] = [];
        $config['mapping'] = [];
        $config['columns'] = $columns;

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    protected function wizardFormAuditLogConfig(?int $formId, array $overrides = []): array
    {
        $base = [
            'source_kind' => 'registered',
            'data_source' => 'form_audit_logs',
            'filters' => array_filter(['form_id' => $formId]),
            'query' => [
                'applied' => true,
                'primary_source' => 'registered:form_audit_logs',
                'transforms' => [],
            ],
            'group_by' => [],
            'aggregations' => [
                ['fn' => 'COUNT', 'field' => '*', 'alias' => 'value'],
            ],
            'mapping' => ['y' => 'value'],
            'visual' => ['border' => true, 'chart_engine' => 'apexcharts', 'color_scheme' => 'amber'],
        ];

        return array_replace_recursive($base, $overrides);
    }

    protected function ensureShowcaseAuditData(Model $user, int $formId): void
    {
        $auditSource = app(\Spiggle\VisualDashboards\DataSources\FormAuditLogDataSource::class);

        if (! $auditSource->available()) {
            return;
        }

        $table = (string) config('form-builder.tables.audit_logs', 'form_builder_audit_logs');

        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return;
        }

        if (\Illuminate\Support\Facades\DB::table($table)->where('form_id', $formId)->count() >= 16) {
            return;
        }

        $actions = [
            'submission.created',
            'submission.created',
            'submission.created',
            'submission.created',
            'submission.reviewed',
            'submission.reviewed',
            'submission.archived',
            'submission.exported',
            'form.updated',
            'form.published',
        ];

        foreach (range(0, 23) as $offset) {
            \Illuminate\Support\Facades\DB::table($table)->insert([
                'form_id' => $formId,
                'user_id' => $offset % 3 === 0 ? $user->getKey() : null,
                'action' => $actions[$offset % count($actions)],
                'payload' => json_encode(['demo' => true, 'offset' => $offset]),
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subDays($offset % 28)->subHours($offset),
                'updated_at' => now()->subDays($offset % 28),
            ]);
        }
    }

    protected function resolveContactUsForm(): ?\Spiggle\FormBuilder\Models\Form
    {
        if (! class_exists(\Spiggle\FormBuilder\Models\Form::class)) {
            return null;
        }

        $form = \Spiggle\FormBuilder\Models\Form::query()
            ->where('slug', 'contact-us')
            ->orWhere('slug', 'contact_us')
            ->orWhere('name', 'like', '%Contact Us%')
            ->orderBy('id')
            ->first();

        return $form ?? \Spiggle\FormBuilder\Models\Form::query()->orderBy('id')->first();
    }

    protected function ensureShowcaseFormData(Model $user): void
    {
        if (! class_exists(\Spiggle\FormBuilder\Models\Form::class)) {
            return;
        }

        if (class_exists(\Spiggle\FormBuilder\Database\Seeders\SampleFormsSeeder::class)
            && \Spiggle\FormBuilder\Models\Form::query()->doesntExist()) {
            (new \Spiggle\FormBuilder\Database\Seeders\SampleFormsSeeder)->run();
        }

        if (\Spiggle\FormBuilder\Models\Form::query()->doesntExist()) {
            \Spiggle\FormBuilder\Models\Form::withoutEvents(function (): void {
                \Spiggle\FormBuilder\Models\Form::factory()->create();
            });
        }

        $form = \Spiggle\FormBuilder\Models\Form::query()->orderBy('id')->first();

        if (! $form) {
            return;
        }

        $submissionTable = (string) config('form-builder.tables.submissions', 'form_builder_submissions');

        if (\Illuminate\Support\Facades\Schema::hasTable($submissionTable)
            && \Illuminate\Support\Facades\DB::table($submissionTable)->count() < 8) {
            $statuses = ['new', 'new', 'new', 'reviewed', 'reviewed', 'archived'];

            foreach (range(0, 11) as $offset) {
                \Illuminate\Support\Facades\DB::table($submissionTable)->insert([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'form_id' => $form->id,
                    'status' => $statuses[$offset % count($statuses)],
                    'data' => json_encode(['email' => 'demo'.$offset.'@example.com', 'message' => 'Showcase submission '.$offset]),
                    'user_id' => $offset % 3 === 0 ? $user->getKey() : null,
                    'ip_address' => '127.0.0.1',
                    'created_at' => now()->subDays($offset),
                    'updated_at' => now()->subDays($offset),
                ]);
            }
        }
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

    protected function upsertDashboard(Model $user, string $name, bool $isDefault, bool $publish = true): CustomDashboard
    {
        /** @var CustomDashboard $dashboard */
        $dashboard = CustomDashboard::query()->firstOrCreate(
            [
                'user_id' => $user->getKey(),
                'name' => $name,
            ],
            [
                'is_default' => $isDefault,
                'is_published' => $publish,
            ]
        );

        $updates = [];

        if ($dashboard->is_default !== $isDefault) {
            $updates['is_default'] = $isDefault;
        }

        if ($publish && ! $dashboard->is_published) {
            $updates['is_published'] = true;
        }

        if ($updates !== []) {
            $dashboard->update($updates);
        }

        return $dashboard;
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     */
    protected function syncDashlets(CustomDashboard $dashboard, array $definitions, bool $prune = false): void
    {
        $keep = [];

        foreach ($definitions as $definition) {
            $seedKey = (string) $definition['seed_key'];
            $keep[] = $seedKey;
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

        if ($prune) {
            $dashboard->dashlets()->get()->each(function (Dashlet $dashlet) use ($keep): void {
                $key = (string) data_get($dashlet->normalizedConfig(), 'seed_key');

                if ($key === '' || ! in_array($key, $keep, true)) {
                    $dashlet->delete();
                }
            });
        }
    }

    public function resolveUser(): Model
    {
        $modelClass = config('visual-dashboards.user_model', 'App\\Models\\User');

        if (! class_exists($modelClass)) {
            throw new RuntimeException("Dashlet user model [{$modelClass}] is not available.");
        }

        $query = $modelClass::query();

        if (method_exists($modelClass, 'roles')) {
            $admin = (clone $query)
                ->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['super_admin', 'admin']))
                ->orderBy('id')
                ->first();

            if ($admin instanceof Model) {
                return $admin;
            }
        }

        $user = $query->orderBy('id')->first();

        if (! $user instanceof Model) {
            throw new RuntimeException('No users found. Create a user before running dashlet:seed.');
        }

        return $user;
    }
}
