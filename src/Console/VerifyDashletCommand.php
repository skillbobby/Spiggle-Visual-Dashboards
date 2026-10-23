<?php

namespace Spiggle\VisualDashboards\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spiggle\VisualDashboards\DataSources\FormAuditLogDataSource;
use Spiggle\VisualDashboards\DataSources\FormSubmissionDataSource;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Models\Dashlet;
use Spiggle\VisualDashboards\Services\DashboardExporter;
use Spiggle\VisualDashboards\Services\DashboardImporter;
use Spiggle\VisualDashboards\Services\DashletRegistry;
use Spiggle\VisualDashboards\Services\DataEngine;
use Spiggle\VisualDashboards\Services\LayoutManager;
use Spiggle\VisualDashboards\Support\FeatureCatalog;

class VerifyDashletCommand extends Command
{
    protected $signature = 'visual-dashboards:verify';

    protected $aliases = ['dashlet:verify'];

    protected $description = 'Exercise visual dashboard schema, data engine, layout sync, and chart rendering';

    public function handle(): int
    {
        $failures = 0;

        $failures += $this->assertTrue(
            Schema::hasTable(config('visual-dashboards.tables.dashboards', 'custom_dashboards')),
            'dashboards table exists'
        );
        $failures += $this->assertTrue(
            Schema::hasTable(config('visual-dashboards.tables.dashlets', 'dashlets')),
            'dashlets table exists'
        );

        $labels = app(DashletRegistry::class)->dataSourceLabels();
        $failures += $this->assertTrue(isset($labels['sample_metrics']), 'sample data source registered');
        $failures += $this->assertTrue(isset($labels['users']), 'users data source registered');
        $failures += $this->assertTrue(isset($labels['ai_spend']), 'AI spend data source registered');
        $failures += $this->assertTrue(
            Schema::hasTable(config('visual-dashboards.tables.ai_services', 'dashlet_ai_services')),
            'AI services table exists'
        );

        $formSource = app(FormSubmissionDataSource::class);
        if ($formSource->available()) {
            $failures += $this->assertTrue(isset($labels['form_submissions']), 'form submissions data source registered');
        } else {
            $this->warn('Form Builder not installed — skipping form submissions source check.');
        }

        $auditSource = app(FormAuditLogDataSource::class);
        if ($auditSource->available()) {
            $failures += $this->assertTrue(isset($labels['form_audit_logs']), 'form audit log data source registered');
        } else {
            $this->warn('Form Builder audit log not available — skipping audit source check.');
        }

        $createPage = file_get_contents(
            dirname(__DIR__).'/Filament/Resources/CustomDashboards/Pages/CreateCustomDashboard.php'
        ) ?: '';
        $failures += $this->assertTrue(str_contains($createPage, "getUrl('view'"), 'create dashboard redirects to view page');

        $gridView = file_get_contents(
            dirname(__DIR__, 2).'/resources/views/livewire/dashboard-grid.blade.php'
        ) ?: '';
        $failures += $this->assertTrue(str_contains($gridView, 'dashletGrid'), 'dashboard grid uses Alpine wrapper');
        $failures += $this->assertTrue(str_contains($gridView, 'updateLayout'), 'dashboard grid persists layout via Livewire');
        $failures += $this->assertTrue(str_contains($gridView, 'dashlet-grid-empty'), 'dashboard grid shows empty state');
        $failures += $this->assertTrue(str_contains($gridView, 'apexcharts'), 'dashboard grid loads ApexCharts');
        $failures += $this->assertTrue(! str_contains($gridView, 'chart.js'), 'dashboard grid does not load Chart.js');
        $failures += $this->assertTrue(! str_contains($gridView, 'Highcharts'), 'dashboard grid does not load Highcharts');
        $failures += $this->assertTrue(str_contains($gridView, 'queuePersistLayout'), 'dashboard grid debounces layout saves');
        $failures += $this->assertTrue(str_contains($gridView, 'openEditDashlet'), 'dashboard grid edit calls Livewire on the grid component');
        $failures += $this->assertTrue(str_contains($gridView, 'openDeleteDashlet'), 'dashboard grid remove calls Livewire on the grid component');
        $failures += $this->assertTrue(str_contains($gridView, '@mousedown.stop.prevent'), 'edit/remove buttons stop header drag mousedown');
        $failures += $this->assertTrue(str_contains($gridView, 'canPlace'), 'dashboard grid rejects overlapping drag/resize');
        $failures += $this->assertTrue(str_contains($gridView, 'firstFreeSlot'), 'dashboard grid finds a free slot when packing');
        $failures += $this->assertTrue(in_array('stacked_column', FeatureCatalog::allowedTypes(), true), 'stacked column is available');
        $failures += $this->assertTrue(in_array('hero', FeatureCatalog::allowedTypes(), true), 'hero greeting is available');
        $failures += $this->assertTrue(in_array('insight_list', FeatureCatalog::allowedTypes(), true), 'insight list is available');
        $failures += $this->assertTrue(in_array('scatter', FeatureCatalog::allowedTypes(), true), 'scatter is available');

        $formSchema = file_get_contents(
            dirname(__DIR__).'/Filament/Support/DashletFormSchema.php'
        ) ?: '';
        $failures += $this->assertTrue(str_contains($formSchema, 'function steps'), 'add dashlet form uses Filament wizard steps');
        $failures += $this->assertTrue(str_contains($formSchema, "Step::make('Data source')"), 'wizard step 1 is data source');
        $failures += $this->assertTrue(str_contains($formSchema, "Step::make('Filter data')"), 'wizard step 2 is filter data');
        $failures += $this->assertTrue(str_contains($formSchema, "Step::make('Transform')"), 'wizard step 3 is transform');
        $failures += $this->assertTrue(str_contains($formSchema, "Step::make('Visualization')"), 'wizard step 4 is visualization');
        $failures += $this->assertTrue(str_contains($formSchema, "Step::make('Finalize')"), 'wizard step 5 is finalize');
        $failures += $this->assertTrue(str_contains($formSchema, 'previewHtml'), 'finalize step includes live data preview');
        $failures += $this->assertTrue(str_contains($formSchema, 'source_kind'), 'wizard selects dataset type');
        $failures += $this->assertTrue(str_contains($formSchema, 'filter_groups'), 'wizard supports filter groups');
        $failures += $this->assertTrue(str_contains($formSchema, 'isAbsolute: true'), 'filter condition fields read entity via absolute Get');
        $failures += $this->assertTrue(str_contains($formSchema, "Radio::make('source_kind')"), 'data source uses Filament radio cards');
        $failures += $this->assertTrue(str_contains($formSchema, "ToggleButtons::make('type')"), 'visualization uses Filament toggle buttons');
        $failures += $this->assertTrue(str_contains($formSchema, "Section::make('Live preview')"), 'preview is a Filament section on finalize');
        $failures += $this->assertTrue(str_contains($formSchema, 'access_roles'), 'finalize configures per-dashlet roles');

        $viewPage = file_get_contents(
            dirname(__DIR__).'/Filament/Resources/CustomDashboards/Pages/ViewCustomDashboard.php'
        ) ?: '';
        $viewBlade = file_get_contents(
            dirname(__DIR__, 2).'/resources/views/filament/pages/view-custom-dashboard.blade.php'
        ) ?: '';
        $canvasPhp = file_get_contents(
            dirname(__DIR__).'/Livewire/VisualDashboardCanvas.php'
        ) ?: '';
        $visualGrid = file_get_contents(
            dirname(__DIR__, 2).'/resources/views/livewire/partials/visual-grid.blade.php'
        ) ?: '';
        $visualGridScript = file_get_contents(
            dirname(__DIR__, 2).'/resources/views/livewire/partials/visual-grid-script.blade.php'
        ) ?: '';
        $failures += $this->assertTrue(str_contains($viewBlade, 'visual-dashboards.visual-dashboard-canvas'), 'view page uses visual dashboard canvas');
        $failures += $this->assertTrue(str_contains($canvasPhp, 'function openGallery'), 'canvas exposes chart gallery');
        $failures += $this->assertTrue(str_contains($canvasPhp, 'function saveProperties'), 'canvas exposes properties panel');
        $failures += $this->assertTrue(str_contains($canvasPhp, 'function saveDataSource'), 'canvas exposes data source panel');
        $failures += $this->assertTrue(str_contains($canvasPhp, 'function setGrain'), 'canvas Livewire exposes setGrain');
        $failures += $this->assertTrue(str_contains($visualGrid, 'dashlet-grain'), 'visual grid shows period grain toggle');
        $failures += $this->assertTrue(str_contains($visualGrid, 'Select data source'), 'visual grid shows centered select data source widget');
        $failures += $this->assertTrue(! str_contains($visualGrid, '>Select source</button>'), 'visual grid does not show select source link in card header');
        $failures += $this->assertTrue(str_contains($visualGridScript, 'isUnconfigured'), 'visual grid exposes unconfigured helper');
        $failures += $this->assertTrue(str_contains($visualGridScript, 'applyGrain'), 'visual grid supports in-widget Day/Week/Month/Year');
        $failures += $this->assertTrue(str_contains($canvasPhp, 'function deleteVisualAction'), 'canvas delete visual is a Filament action');
        $failures += $this->assertTrue(str_contains($canvasPhp, 'function openDeleteVisual'), 'canvas exposes openDeleteVisual');
        $failures += $this->assertTrue(str_contains($canvasPhp, 'Remove this visual from the dashboard?'), 'canvas delete confirmation uses clear copy');
        $failures += $this->assertTrue(! str_contains($visualGrid, "confirm('Remove this visual"), 'visual grid does not use browser confirm for delete');

        $gridPhp = file_get_contents(
            dirname(__DIR__).'/Livewire/DashboardGrid.php'
        ) ?: '';
        $failures += $this->assertTrue(str_contains($gridPhp, 'function editDashletAction'), 'edit dashlet is a Filament action on the grid');
        $failures += $this->assertTrue(str_contains($gridPhp, 'function deleteDashletAction'), 'delete dashlet is a Filament action on the grid');
        $failures += $this->assertTrue(str_contains($gridPhp, 'requiresConfirmation'), 'delete dashlet requires confirmation');
        $failures += $this->assertTrue(str_contains($gridPhp, 'Delete this dashlet?'), 'delete confirmation uses clear copy');
        $failures += $this->assertTrue(str_contains($gridView, 'applyGrain'), 'dashboard grid supports in-dashlet Day/Week/Month/Year');
        $failures += $this->assertTrue(str_contains($gridPhp, 'function setGrain'), 'grid Livewire exposes setGrain');

        $resource = file_get_contents(
            dirname(__DIR__).'/Filament/Resources/CustomDashboards/CustomDashboardResource.php'
        ) ?: '';
        $failures += $this->assertTrue(str_contains($resource, 'Visual Dashboards'), 'navigation label is Visual Dashboards');

        $previewView = file_get_contents(
            dirname(__DIR__, 2).'/resources/views/filament/components/dashlet-viz-preview.blade.php'
        ) ?: '';
        $failures += $this->assertTrue(str_contains($previewView, 'vdPanelPreviewChart'), 'visual preview renders chart preview helper');
        $failures += $this->assertTrue(str_contains($previewView, 'vd-panel-preview-chart'), 'visual preview refreshes while the chart host is ignored by Livewire');
        $failures += $this->assertTrue(str_contains($previewView, 'wire:loading'), 'visual preview shows loading indicator');
        $failures += $this->assertTrue(str_contains($gridView, 'grid-column:'), 'dashboard grid positions cards with CSS grid columns');

        DB::beginTransaction();

        try {
            $dashboard = CustomDashboard::factory()->create([
                'name' => 'Verify Dashboard',
            ]);

            $dashlet = Dashlet::factory()->for($dashboard, 'dashboard')->create([
                'type' => 'bar',
                'config' => [
                    'title' => 'Verify Dashlet',
                    'heading' => 'Verify Dashlet',
                    'subheading' => 'Health check',
                    'source_kind' => 'model',
                    'data_source' => 'sample_metrics',
                    'filters' => [],
                    'filter_groups' => [],
                    'visual' => ['color' => '#f59e0b', 'border' => true],
                ],
            ]);

            $preview = app(DataEngine::class)->preview('sample_metrics');
            $failures += $this->assertTrue($preview !== [], 'data preview returns rows');

            $livePreview = app(DataEngine::class)->previewPayload('sample_metrics', 'bar', [
                'filters' => [],
                'filter_groups' => [],
            ]);
            $failures += $this->assertTrue(isset($livePreview['chart']), 'live preview payload includes chart data');

            $chart = app(DataEngine::class)->chart('sample_metrics', 'bar');
            $failures += $this->assertTrue(isset($chart['labels']) && isset($chart['datasets']), 'chart payload has labels and datasets');

            $payload = app(DashletRegistry::class)->renderPayload($dashlet);
            $failures += $this->assertTrue(($payload['title'] ?? '') === 'Verify Dashlet', 'dashlet render payload includes title');

            app(LayoutManager::class)->sync($dashboard, [[
                'id' => $dashlet->id,
                'x' => 2,
                'y' => 1,
                'w' => 6,
                'h' => 5,
            ]]);

            $dashlet->refresh();
            $failures += $this->assertTrue($dashlet->grid_x === 2 && $dashlet->grid_w === 6, 'layout sync updates grid coordinates');

            $second = Dashlet::factory()->for($dashboard, 'dashboard')->create([
                'type' => 'stat_card',
                'grid_x' => 0,
                'grid_y' => 0,
                'grid_w' => 4,
                'grid_h' => 4,
            ]);

            $resolved = app(LayoutManager::class)->resolve([
                ['id' => $dashlet->id, 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
                ['id' => $second->id, 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 4],
            ]);
            $failures += $this->assertTrue(! app(LayoutManager::class)->hasOverlap($resolved), 'layout resolver prevents overlapping cards');

            $items = app(LayoutManager::class)->gridItems($dashboard);
            $failures += $this->assertTrue($items->count() === 2, 'layout manager returns dashlet items');

            $export = app(DashboardExporter::class)->export($dashboard);
            $failures += $this->assertTrue(($export['dashboard']['name'] ?? '') === 'Verify Dashboard', 'dashboard export includes metadata');

            $imported = app(DashboardImporter::class)->import($export, (int) $dashboard->user_id, replace: false, nameOverride: 'Verify Import');
            $failures += $this->assertTrue($imported->dashlets()->count() === 2, 'dashboard import recreates dashlets');

            $proChart = app(DataEngine::class)->chart('sample_metrics', 'funnel');
            $failures += $this->assertTrue(isset($proChart['labels']), 'funnel chart available');
        } finally {
            DB::rollBack();
        }

        if ($failures === 0) {
            $this->info('Visual Dashboards verify: all checks passed.');

            return self::SUCCESS;
        }

        $this->error("Visual Dashboards verify: {$failures} check(s) failed.");

        return self::FAILURE;
    }

    protected function assertTrue(bool $condition, string $label): int
    {
        if ($condition) {
            $this->info("PASS  {$label}");

            return 0;
        }

        $this->error("FAIL  {$label}");

        return 1;
    }
}
