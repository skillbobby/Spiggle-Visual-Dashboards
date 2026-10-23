<?php

namespace Spiggle\VisualDashboards\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Models\Dashlet;
use Spiggle\VisualDashboards\Services\DataEngine;
use Spiggle\VisualDashboards\Services\LayoutManager;
use Spiggle\VisualDashboards\Services\RelatedSourceDiscoverer;
use Spiggle\VisualDashboards\Services\VisualDashboardDataSourceRegistry;
use Spiggle\VisualDashboards\Support\ChartVariantCatalog;
use Spiggle\VisualDashboards\Support\DashboardAccess;
use Spiggle\VisualDashboards\Support\DataSourceDescriptor;
use Spiggle\VisualDashboards\Support\FieldValueFormatter;
use Spiggle\VisualDashboards\Support\GroupByDimension;
use Spiggle\VisualDashboards\Support\KpiCardCatalog;
use Spiggle\VisualDashboards\Support\KpiPeriod;
use Spiggle\VisualDashboards\Support\ValueFormatter;
use Spiggle\VisualDashboards\Support\VisualQueryBuilder;
use Spiggle\VisualDashboards\Support\VirtualMeasures;
use Spiggle\VisualDashboards\Support\VisualTypes;
use Throwable;

class VisualDashboardCanvas extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public int $dashboardId;

    public bool $galleryOpen = false;

    public bool $panelOpen = false;

    public string $panelTab = 'properties';

    public ?int $selectedVisualId = null;

    public string $editHeading = '';

    public string $editSubheading = '';

    public string $editColorScheme = 'amber';

    public bool $editBorder = true;

    public string $editNumberFormat = 'number';

    public string $editCurrency = 'USD';

    public string $editChartVariant = 'basic';

    public string $formatTab = 'title';

    public string $editTitleFont = 'inherit';

    public string $editTitleSize = 'md';

    public string $editTitleColor = '';

    public string $editLegendShow = 'auto';

    public string $editLegendPosition = 'bottom';

    public bool $editDataLabels = false;

    public bool $editShowXAxisTitle = true;

    public bool $editShowYAxisTitle = true;

    public string $editKpiIcon = '';

    public string $editKpiMetricLabel = '';

    public string $editKpiPeriod = 'monthly';

    public string $editKpiCustomDays = '30';

    public string $editKpiValueSize = 'md';

    public string $editKpiValueWeight = 'bold';

    public bool $editKpiComparisonEnabled = false;

    public string $editKpiComparisonMode = 'manual';

    public string $editKpiComparisonDelta = '';

    public bool $editKpiComparisonUp = true;

    public bool $editKpiHigherIsBetter = true;

    public string $editKpiComparisonLabel = 'than last period';

    public string $editSourceGroup = '';

    public string $editSourceKey = '';

    public string $editSourceField = '';

    public string $editGroupBy = '';

    public string $editBreakdown = '';

    public string $editAggregation = 'COUNT';

    public string $editTimeGrain = 'day';

    public string $editDataStep = 'source';

    public bool $editQueryApplied = false;

    public bool $editRemoveDuplicates = false;

    public string $panelNotice = '';

    /**
     * @var array<int, string>
     */
    public array $editDedupeColumns = [];

    /**
     * @var array<int, string>
     */
    public array $editTableColumns = [];

    public bool $editOverridesOpen = false;

    /**
     * @var array<int, array<string, string>>
     */
    public array $editFieldFormats = [];

    /**
     * @var array<int, array<string, string>>
     */
    public array $editRenames = [];

    /**
     * @var array<string, mixed>
     */
    public array $transformSnapshot = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $editTransforms = [];

    /**
     * @var array<int, string>
     */
    public array $editRelatedJoins = [];

    /**
     * Period grains exposed on the dashlet header (day, week, month, year).
     *
     * @var array<int, string>
     */
    public array $editPeriodGrains = [];

    /**
     * @var array<int, string>
     */
    public array $grains = [];

    /**
     * @var array<int, array<string, int>>
     */
    public array $layout = [];

    public ?int $cachedTransformPreviewRowCount = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $cachedPreviewPayload = null;

    public function mount(int $dashboardId): void
    {
        $dashboard = CustomDashboard::query()->findOrFail($dashboardId);

        abort_unless(
            DashboardAccess::canViewInApp(auth()->user(), $dashboard),
            403
        );

        $this->dashboardId = $dashboardId;
        $this->refreshLayout();
    }

    public function openGallery(): void
    {
        $this->galleryOpen = true;
        $this->panelOpen = false;
    }

    public function closeGallery(): void
    {
        $this->galleryOpen = false;
    }

    public function addVisual(string $type): void
    {
        if (! collect(VisualTypes::gallery())->first(fn (array $entry): bool => $entry['type'] === $type && ($entry['enabled'] ?? false))) {
            return;
        }

        $size = VisualTypes::defaultSize($type);
        $slot = app(LayoutManager::class)->nextSlot($this->dashboard(), $size['w']);

        $visual = Dashlet::query()->create([
            'custom_dashboard_id' => $this->dashboardId,
            'type' => $type,
            'grid_x' => $slot['x'],
            'grid_y' => $slot['y'],
            'grid_w' => $slot['w'],
            'grid_h' => $slot['h'],
            'config' => [
                'heading' => VisualTypes::labels()[$type] ?? 'Visual',
                'subheading' => '',
                'source_kind' => '',
                'data_source' => '',
                'table_name' => '',
                'columns' => [],
                'group_by' => [],
                'aggregations' => [],
                'visual' => [
                    'color_scheme' => 'amber',
                    'border' => true,
                    'chart_engine' => 'apexcharts',
                ],
            ],
        ]);

        $this->galleryOpen = false;
        $this->panelNotice = '';
        $this->selectVisual($visual->getKey(), 'datasource');
        $this->refreshLayout();
        $this->dispatch('visual-dashboard-refresh');
    }

    public function selectVisual(int $visualId, string $tab = 'datasource'): void
    {
        if (! $this->visualBelongsToDashboard($visualId)) {
            return;
        }

        $visual = $this->dashboard()->dashlets()->whereKey($visualId)->firstOrFail();
        $config = $visual->normalizedConfig();

        $this->selectedVisualId = $visualId;
        $this->panelOpen = true;
        $this->panelTab = in_array($tab, ['properties', 'datasource'], true) ? $tab : 'datasource';
        $this->editHeading = (string) ($config['heading'] ?? '');
        $this->editSubheading = (string) ($config['subheading'] ?? '');
        $this->editColorScheme = (string) data_get($config, 'visual.color_scheme', 'amber');
        $this->editBorder = (bool) data_get($config, 'visual.border', true);
        $this->editNumberFormat = (string) data_get($config, 'visual.number_format', 'number');
        $this->editCurrency = (string) data_get($config, 'visual.currency', 'USD');
        $this->editChartVariant = ChartVariantCatalog::normalize(
            $visual->type,
            data_get($config, 'visual.chart_variant')
        );
        $this->loadFormatSettingsFromConfig($config);
        $this->loadKpiSettingsFromConfig($config);
        $this->formatTab = $this->defaultFormatTabForType($visual->type);
        $this->editSourceKey = $this->resolveSourceKey($config);
        $this->editSourceGroup = $this->resolveSourceGroup($config);
        $aggField = (string) (data_get($config, 'aggregations.0.field') ?: '');

        if ($aggField === '*' || VirtualMeasures::isVirtual($aggField)) {
            $this->editSourceField = VirtualMeasures::ROW_COUNT;
        } elseif ($aggField !== '') {
            $this->editSourceField = $aggField;
        } else {
            $this->editSourceField = VirtualMeasures::normalizeField((string) (($config['columns'][0] ?? '') ?: ''));
        }

        $this->editTableColumns = $visual->type === 'table'
            ? array_values($config['columns'] ?? [])
            : [];

        if ($visual->type === 'radialbar') {
            $this->editGroupBy = '';
            $this->editBreakdown = '';
        } else {
            $this->editGroupBy = (string) (($config['group_by'][0] ?? '') ?: '');
            $this->editBreakdown = (string) (data_get($config, 'mapping.series') ?: '');
        }

        $this->editAggregation = strtoupper((string) (data_get($config, 'aggregations.0.fn') ?: 'COUNT'));
        $this->editTimeGrain = $this->timeGrainFromConfig($config);
        $this->editPeriodGrains = $this->periodGrainsFromConfig($config);
        $query = VisualQueryBuilder::normalize($config);
        $this->editRelatedJoins = collect($query['joins'] ?: $query['related_joins'])->pluck('relation')->filter()->values()->all();
        $this->editQueryApplied = (bool) $query['applied'];
        $this->loadTransformStateFromQuery($query);
        $this->editDataStep = $this->editSourceKey === '' ? 'source' : 'transform';
        $this->transformSnapshot = $this->captureTransformState();
        $this->refreshPreviewCache();
    }

    public function deleteVisual(int $visualId): void
    {
        if (! $this->visualBelongsToDashboard($visualId)) {
            return;
        }

        $this->dashboard()->dashlets()->whereKey($visualId)->delete();

        if ($this->selectedVisualId === $visualId) {
            $this->closePanel();
        }

        $this->refreshLayout();
        $this->dispatch('visual-dashboard-refresh');
    }

    public function openDeleteVisual(int $visualId): void
    {
        if (! $this->visualBelongsToDashboard($visualId)) {
            return;
        }

        $this->mountAction('deleteVisual', ['visualId' => $visualId]);
    }

    public function deleteVisualAction(): Action
    {
        return Action::make('deleteVisual')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Remove this visual from the dashboard?')
            ->modalDescription('This removes the visual from the dashboard. This cannot be undone.')
            ->modalSubmitActionLabel('Remove')
            ->action(function (array $arguments): void {
                $visualId = (int) ($arguments['visualId'] ?? 0);

                if ($visualId < 1) {
                    return;
                }

                $this->deleteVisual($visualId);
            });
    }

    public function closePanel(): void
    {
        $this->panelOpen = false;
        $this->selectedVisualId = null;
        $this->panelNotice = '';
        $this->cachedTransformPreviewRowCount = null;
        $this->cachedPreviewPayload = null;
    }

    public function setPanelTab(string $tab): void
    {
        if (in_array($tab, ['properties', 'datasource'], true)) {
            $this->panelTab = $tab;
            $this->refreshPreviewCache();
        }
    }

    public function changeVisualType(string $type): void
    {
        $visual = $this->selectedVisual();

        if (! $visual) {
            return;
        }

        if (! collect(VisualTypes::gallery())->first(
            fn (array $entry): bool => $entry['type'] === $type && ($entry['enabled'] ?? false)
        )) {
            return;
        }

        if ($visual->type === $type) {
            return;
        }

        $previousType = $visual->type;
        $previousLabel = (string) (VisualTypes::labels()[$previousType] ?? $previousType);
        $newLabel = (string) (VisualTypes::labels()[$type] ?? $type);

        $this->adaptMappingsForType($type);

        if ($this->editHeading === '' || $this->editHeading === $previousLabel) {
            $this->editHeading = $newLabel;
        }

        $this->editChartVariant = ChartVariantCatalog::normalize($type, $this->editChartVariant);
        $this->formatTab = $this->defaultFormatTabForType($type);

        $visual->update(['type' => $type]);

        $config = $visual->fresh()->normalizedConfig();
        $config['heading'] = $this->editHeading;
        $config['title'] = $this->editHeading;
        $config['subheading'] = $this->editSubheading;
        $config['visual'] = array_replace(
            is_array($config['visual'] ?? null) ? $config['visual'] : [],
            $this->buildVisualPanelConfig()
        );

        if ($this->editSourceKey !== '') {
            $config = $this->buildDataSourceConfig($config);
        }

        $visual->update(['config' => $config]);

        $this->panelNotice = '';
        $this->refreshPreviewCache();
        $this->refreshLayout();
        $this->dispatch('visual-dashboard-refresh');
    }

    /**
     * Keep compatible field bindings when switching chart types; clear slots the new type does not use.
     */
    protected function adaptMappingsForType(string $type): void
    {
        $wires = collect(VisualTypes::fieldBlueprint($type)['slots'] ?? [])
            ->pluck('wire')
            ->filter()
            ->values()
            ->all();

        if (! in_array('editGroupBy', $wires, true)) {
            $this->editGroupBy = '';
        }

        if (! in_array('editBreakdown', $wires, true)) {
            $this->editBreakdown = '';
        }

        if ($type === 'table') {
            $this->editSourceField = $this->editSourceField !== '' && ! VirtualMeasures::isVirtual($this->editSourceField)
                ? $this->editSourceField
                : '';
            $this->editAggregation = 'COUNT';
        }

        if (in_array($type, ['pie', 'donut', 'funnel', 'stat_card', 'radialbar'], true)) {
            $this->editBreakdown = '';
        }

        if (in_array($type, ['stat_card', 'radialbar'], true)) {
            $this->editGroupBy = '';
        }
    }

    /**
     * @return array<string, string>
     */
    public function visualTypeOptions(): array
    {
        return collect(VisualTypes::gallery())
            ->filter(fn (array $entry): bool => (bool) ($entry['enabled'] ?? false))
            ->mapWithKeys(fn (array $entry): array => [
                $entry['type'] => (string) ($entry['label'] ?? $entry['type']),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function periodGrainOptions(): array
    {
        return [
            'day' => 'Day',
            'week' => 'Week',
            'month' => 'Month',
            'year' => 'Year',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function timeGrainOptions(): array
    {
        return [
            'day' => 'Daily',
            'week' => 'Weekly',
            'month' => 'Monthly',
            'year' => 'Yearly',
        ];
    }

    public function selectedDimensionIsTemporal(): bool
    {
        return GroupByDimension::isTemporal($this->editGroupBy);
    }

    public function supportsPeriodGrains(): bool
    {
        return in_array($this->selectedVisual()?->type ?? '', ['line', 'stacked_column', 'area'], true);
    }

    public function supportsTimeBucket(): bool
    {
        $type = $this->selectedVisual()?->type ?? '';

        if (in_array($type, ['stat_card', 'table', 'radialbar', 'pie', 'donut', 'funnel'], true)) {
            return false;
        }

        return $this->selectedDimensionIsTemporal();
    }

    /**
     * @return array<string, string>
     */
    public function dimensionFieldOptions(): array
    {
        return collect($this->allFieldOptions())
            ->filter(fn (string $label, string $key): bool => $this->isDimensionField($key))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function measureFieldOptions(): array
    {
        $measures = VirtualMeasures::options();

        foreach ($this->allFieldOptions() as $key => $label) {
            if ($this->isMeasureField($key) && ! VirtualMeasures::isVirtual($key)) {
                $measures[$key] = $label;
            }
        }

        return $measures;
    }

    protected function isDimensionField(string $key): bool
    {
        if (VirtualMeasures::isVirtual($key)) {
            return false;
        }

        if (preg_match('/(_at|_date|status|name|slug|form_name|type|category|stage|action)$/i', $key) === 1) {
            return true;
        }

        if (preg_match('/_id$/i', $key) === 1 && $key !== 'id') {
            return true;
        }

        if (str_starts_with($key, 'data.')) {
            return true;
        }

        return $key !== 'id';
    }

    protected function isMeasureField(string $key): bool
    {
        if (VirtualMeasures::isVirtual($key)) {
            return true;
        }

        if ($key === 'id') {
            return false;
        }

        if (GroupByDimension::isForeignKey($key)) {
            return false;
        }

        if (preg_match('/(_count|_total|amount|price|qty|quantity|score|value|tokens|spend)$/i', $key) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    protected function periodGrainsFromConfig(array $config): array
    {
        $grains = data_get($config, 'visual.period_grains');

        if (is_array($grains) && $grains !== []) {
            return array_values(array_filter(
                $grains,
                fn (mixed $grain): bool => is_string($grain) && in_array($grain, ['day', 'week', 'month', 'year'], true)
            ));
        }

        if ((bool) data_get($config, 'visual.period_filter')) {
            return ['day', 'week', 'month', 'year'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function timeGrainFromConfig(array $config): string
    {
        $grain = strtolower((string) (
            data_get($config, 'filters.grain')
            ?: data_get($config, 'visual.default_grain')
            ?: ($this->periodGrainsFromConfig($config)[0] ?? 'day')
        ));

        return isset($this->timeGrainOptions()[$grain]) ? $grain : 'day';
    }

    public function setDataStep(string $step): void
    {
        if (! in_array($step, ['source', 'transform'], true)) {
            return;
        }

        if ($step === 'transform' && $this->editDataStep !== 'transform') {
            $this->snapshotTransformState();
        }

        $this->editDataStep = $step;
    }

    public function addRenameRow(): void
    {
        $this->editRenames[] = ['from' => '', 'to' => ''];
    }

    public function removeRenameRow(int $index): void
    {
        unset($this->editRenames[$index]);
        $this->editRenames = array_values($this->editRenames);
    }

    public function addFieldFormatRow(): void
    {
        $this->editFieldFormats[] = ['field' => '', 'function' => '', 'format' => '', 'custom_format' => ''];
    }

    public function removeFieldFormatRow(int $index): void
    {
        unset($this->editFieldFormats[$index]);
        $this->editFieldFormats = array_values($this->editFieldFormats);
    }

    /**
     * @return array<string, string>
     */
    public function fieldFormatFunctionOptions(): array
    {
        return FieldValueFormatter::functionLabels();
    }

    /**
     * @return array<string, string>
     */
    public function fieldFormatDatePresets(): array
    {
        return FieldValueFormatter::dateFormatPresets();
    }

    /**
     * Fields available for display overrides (respects visible column selection).
     *
     * @return array<string, string>
     */
    public function resultFieldOptions(): array
    {
        $options = $this->allFieldOptions();

        if ($this->selectedVisual()?->type !== 'table' || $this->editTableColumns === []) {
            return $options;
        }

        return array_intersect_key($options, array_flip($this->editTableColumns));
    }

    public function overridesSummary(): string
    {
        $parts = [];

        if ($this->selectedVisual()?->type === 'table') {
            $total = count($this->allFieldOptions());
            $parts[] = $this->editTableColumns === []
                ? "All {$total} columns"
                : count($this->editTableColumns).' columns';
        }

        if ($this->editRemoveDuplicates) {
            $parts[] = 'Dedupe';
        }

        $renameCount = collect($this->editRenames)
            ->filter(fn (array $rename): bool => filled($rename['from'] ?? '') && filled($rename['to'] ?? ''))
            ->count();

        if ($renameCount > 0) {
            $parts[] = $renameCount.' '.Str::plural('rename', $renameCount);
        }

        $formatCount = collect($this->editFieldFormats)
            ->filter(fn (array $row): bool => filled($row['field'] ?? '') && filled($row['function'] ?? ''))
            ->count();

        if ($formatCount > 0) {
            $parts[] = $formatCount.' '.Str::plural('format', $formatCount);
        }

        return $parts === [] ? 'No overrides' : implode(' · ', $parts);
    }

    public function cancelDataTransform(): void
    {
        if ($this->transformSnapshot !== []) {
            $this->restoreTransformSnapshot();
        } else {
            $this->editQueryApplied = false;
        }

        $this->editDataStep = 'transform';
    }

    public function snapshotTransformState(): void
    {
        $this->transformSnapshot = $this->captureTransformState();
    }

    public function addTransformRow(): void
    {
        $this->editTransforms[] = ['type' => 'filter', 'field' => '', 'operator' => 'equals', 'value' => ''];
    }

    public function removeTransformRow(int $index): void
    {
        unset($this->editTransforms[$index]);
        $this->editTransforms = array_values($this->editTransforms);

        if ($this->editTransforms === []) {
            $this->editTransforms = [['type' => 'filter', 'field' => '', 'operator' => 'equals', 'value' => '']];
        }
    }

    public function updatedEditSourceGroup(): void
    {
        $this->editSourceKey = '';
        $this->editSourceField = '';
        $this->editTableColumns = [];
        $this->editGroupBy = '';
        $this->editBreakdown = '';
        $this->editDataStep = 'source';
        $this->editQueryApplied = false;
        $this->editRelatedJoins = [];
        $this->resetTransformState();
        $this->refreshPreviewCache();
    }

    public function updatedEditSourceKey(): void
    {
        $this->editSourceField = '';
        $this->editTableColumns = [];
        $this->editGroupBy = '';
        $this->editBreakdown = '';
        $this->editDataStep = 'transform';
        $this->editQueryApplied = false;
        $this->editRelatedJoins = [];
        $this->resetTransformState();
        $this->snapshotTransformState();
        $this->refreshPreviewCache();
    }

    public function updatedEditRelatedJoins(): void
    {
        $this->refreshPreviewCache();
    }

    public function saveProperties(): void
    {
        $visual = $this->selectedVisual();

        if (! $visual) {
            return;
        }

        $config = $visual->normalizedConfig();
        $config['heading'] = $this->editHeading;
        $config['title'] = $this->editHeading;
        $config['subheading'] = $this->editSubheading;
        $config['visual']['color_scheme'] = $this->editColorScheme;
        $config['visual']['border'] = $this->editBorder;
        $config['visual']['number_format'] = $this->normalizedNumberFormat();
        $config['visual']['currency'] = $this->normalizedCurrency();

        if (ChartVariantCatalog::supports($visual->type)) {
            $config['visual']['chart_variant'] = ChartVariantCatalog::normalize(
                $visual->type,
                $this->editChartVariant
            );
        } else {
            unset($config['visual']['chart_variant']);
        }

        $config['visual']['kpi'] = $this->buildKpiVisualConfig();
        $config['visual'] = array_replace($config['visual'], $this->formatVisualConfig());

        $visual->update(['config' => $config]);
        $this->panelNotice = '';
        $this->refreshLayout();
        $this->dispatch('visual-dashboard-refresh');
    }

    public function setFormatTab(string $tab): void
    {
        $allowed = array_keys($this->formatTabs());

        if (in_array($tab, $allowed, true)) {
            $this->formatTab = $tab;
        }
    }

    /**
     * @return array<string, string>
     */
    public function formatTabs(): array
    {
        $type = $this->selectedVisual()?->type ?? '';
        $tabs = [
            'title' => 'Title',
            'colors' => 'Colors',
        ];

        if ($this->supportsValueFormat()) {
            $tabs['labels'] = 'Labels';
        }

        if (ChartVariantCatalog::supports($type)) {
            $tabs['series'] = 'Series';
        }

        if ($this->supportsLegendFormat($type)) {
            $tabs['legend'] = 'Legend';
        }

        if ($this->supportsAxesFormat($type)) {
            $tabs['axes'] = 'Axes';
        }

        $tabs['area'] = 'Chart area';

        if ($type === 'stat_card') {
            $tabs['kpi'] = 'KPI';
        }

        return $tabs;
    }

    protected function defaultFormatTabForType(string $type): string
    {
        $tabs = ['title', 'colors'];

        if (! in_array($type, ['hero', 'insight_list', 'table', 'container'], true)) {
            $tabs[] = 'labels';
        }

        if (ChartVariantCatalog::supports($type)) {
            $tabs[] = 'series';
        }

        if ($this->supportsLegendFormat($type)) {
            $tabs[] = 'legend';
        }

        if ($this->supportsAxesFormat($type)) {
            $tabs[] = 'axes';
        }

        $tabs[] = 'area';

        if ($type === 'stat_card') {
            $tabs[] = 'kpi';
        }

        if (in_array($this->formatTab, $tabs, true)) {
            return $this->formatTab;
        }

        return $tabs[0] ?? 'title';
    }

    protected function supportsLegendFormat(string $type): bool
    {
        return in_array($type, ['line', 'bar', 'column', 'stacked_column', 'pie', 'donut', 'area'], true);
    }

    protected function supportsAxesFormat(string $type): bool
    {
        return in_array($type, ['line', 'bar', 'column', 'stacked_column', 'area', 'funnel'], true);
    }

    /**
     * @return array<string, string>
     */
    public function titleFontOptions(): array
    {
        return [
            'inherit' => '(inherited font)',
            'sans' => 'Sans serif',
            'serif' => 'Serif',
            'mono' => 'Monospace',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function titleSizeOptions(): array
    {
        return [
            'sm' => 'Small',
            'md' => 'Medium',
            'lg' => 'Large',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function legendShowOptions(): array
    {
        return [
            'auto' => 'Auto',
            'always' => 'Always show',
            'never' => 'Hide',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function legendPositionOptions(): array
    {
        return [
            'bottom' => 'Bottom',
            'top' => 'Top',
            'right' => 'Right',
            'left' => 'Left',
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function loadFormatSettingsFromConfig(array $config): void
    {
        $this->editTitleFont = (string) data_get($config, 'visual.title.font', 'inherit');
        $this->editTitleSize = (string) data_get($config, 'visual.title.size', 'md');
        $this->editTitleColor = (string) data_get($config, 'visual.title.color', '');
        $this->editLegendShow = (string) data_get($config, 'visual.legend.show', 'auto');
        $this->editLegendPosition = (string) data_get($config, 'visual.legend.position', 'bottom');
        $this->editDataLabels = (bool) data_get($config, 'visual.data_labels.enabled', false);
        $this->editShowXAxisTitle = (bool) data_get($config, 'visual.axes.show_x_title', true);
        $this->editShowYAxisTitle = (bool) data_get($config, 'visual.axes.show_y_title', true);

        if (! array_key_exists($this->editTitleFont, $this->titleFontOptions())) {
            $this->editTitleFont = 'inherit';
        }

        if (! array_key_exists($this->editTitleSize, $this->titleSizeOptions())) {
            $this->editTitleSize = 'md';
        }

        if (! array_key_exists($this->editLegendShow, $this->legendShowOptions())) {
            $this->editLegendShow = 'auto';
        }

        if (! array_key_exists($this->editLegendPosition, $this->legendPositionOptions())) {
            $this->editLegendPosition = 'bottom';
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatVisualConfig(): array
    {
        return [
            'title' => [
                'font' => $this->editTitleFont,
                'size' => $this->editTitleSize,
                'color' => $this->editTitleColor,
            ],
            'legend' => [
                'show' => $this->editLegendShow,
                'position' => $this->editLegendPosition,
            ],
            'data_labels' => [
                'enabled' => $this->editDataLabels,
            ],
            'axes' => [
                'show_x_title' => $this->editShowXAxisTitle,
                'show_y_title' => $this->editShowYAxisTitle,
            ],
        ];
    }

    public function saveDataSource(): void
    {
        if ($this->editSourceKey === '') {
            $this->panelNotice = 'Select a source first.';

            return;
        }

        if (! $this->hasValidDataSourceSelection()) {
            $this->panelNotice = 'Select a source, configure fields, then save.';

            return;
        }

        $this->panelNotice = '';
        $this->editQueryApplied = true;
        $this->transformSnapshot = $this->captureTransformState();
        $this->persistDataSource();
        $this->refreshPreviewCache();
    }

    public function removeSelectedVisual(): void
    {
        if ($this->selectedVisualId === null) {
            return;
        }

        $this->openDeleteVisual($this->selectedVisualId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function setGrain(int $visualId, string $grain): array
    {
        if (! in_array($grain, ['day', 'week', 'month', 'year'], true) || ! $this->visualBelongsToDashboard($visualId)) {
            return $this->gridItems();
        }

        $this->grains[$visualId] = $grain;

        return $this->refreshLayout();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function refreshLayout(): array
    {
        $items = $this->gridItems();

        $this->layout = collect($items)
            ->map(fn (array $item): array => [
                'id' => (int) $item['id'],
                'x' => (int) $item['x'],
                'y' => (int) $item['y'],
                'w' => (int) $item['w'],
                'h' => (int) $item['h'],
            ])
            ->values()
            ->all();

        return $items;
    }

    /**
     * @param  array<int, array<string, int>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function updateLayout(array $items, bool $silent = true): array
    {
        $manager = app(LayoutManager::class);
        $resolved = $manager->resolve($items);
        $manager->sync($this->dashboard(), $resolved);

        return $this->refreshLayout();
    }

    /**
     * @return array<string, string>
     */
    public function sourceGroupOptions(): array
    {
        return collect(app(VisualDashboardDataSourceRegistry::class)->groupNames())
            ->mapWithKeys(fn (string $group): array => [$group => $group])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function sourceOptionsForGroup(): array
    {
        if ($this->editSourceGroup === '') {
            return [];
        }

        return app(VisualDashboardDataSourceRegistry::class)->sourceOptionsForGroup($this->editSourceGroup);
    }

    public function selectedSourceSummaryLabel(): string
    {
        if ($this->editSourceGroup === '' || $this->editSourceKey === '') {
            return '';
        }

        $sourceLabel = $this->sourceOptionsForGroup()[$this->editSourceKey]
            ?? app(VisualDashboardDataSourceRegistry::class)->find($this->editSourceKey)?->label
            ?? $this->editSourceKey;

        return $this->editSourceGroup.' → '.$sourceLabel;
    }

    /**
     * @return array<string, string>
     */
    public function sourceFieldOptions(): array
    {
        if ($this->editSourceKey === '') {
            return [];
        }

        return app(VisualDashboardDataSourceRegistry::class)->fieldsForKey($this->editSourceKey);
    }

    /**
     * @return array<string, string>
     */
    public function aggregationOptions(): array
    {
        return [
            'COUNT' => 'Count',
            'SUM' => 'Sum',
            'AVG' => 'Average',
            'MIN' => 'Minimum',
            'MAX' => 'Maximum',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filterOperatorOptions(): array
    {
        return [
            'equals' => 'Equals',
            'not_equals' => 'Not equals',
            'contains' => 'Contains',
            'gt' => 'Greater than',
            'gte' => 'Greater or equal',
            'lt' => 'Less than',
            'lte' => 'Less or equal',
            'is_empty' => 'Is empty',
            'is_not_empty' => 'Is not empty',
            'is_null' => 'Is null',
            'is_not_null' => 'Is not null',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filterValueOptions(string $column): array
    {
        if ($column === '' || $this->editSourceKey === '') {
            return [];
        }

        if (str_contains($column, '.')) {
            [$relationName, $fieldName] = explode('.', $column, 2);
            $relation = collect($this->relatedSourceOptions())->first(
                fn (array $entry): bool => ($entry['name'] ?? '') === $relationName
            );

            if (! is_array($relation)) {
                return [];
            }

            $table = (string) ($relation['related_table'] ?? '');

            if ($table === '') {
                return [];
            }

            return $this->distinctColumnValues($table, $fieldName);
        }

        $descriptor = app(VisualDashboardDataSourceRegistry::class)->find($this->editSourceKey);

        if ($descriptor instanceof DataSourceDescriptor) {
            $descriptorOptions = $this->filterValueOptionsForDescriptor($descriptor, $column);

            if ($descriptorOptions !== null) {
                return $descriptorOptions;
            }
        }

        $table = (string) ($descriptor?->meta['table'] ?? '');

        if ($table !== '') {
            return $this->distinctColumnValues($table, $column);
        }

        try {
            $visual = $this->selectedVisual();

            if (! $visual) {
                return [];
            }

            $config = $this->buildDataSourceConfig($visual->normalizedConfig());
            $config['query']['applied'] = true;
            $rows = app(DataEngine::class)->filteredRowsFromConfig($config, 200);

            return collect($rows)
                ->pluck($column)
                ->filter(fn (mixed $value): bool => filled($value))
                ->unique()
                ->sort()
                ->take(50)
                ->mapWithKeys(fn (mixed $value): array => [(string) $value => (string) $value])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function transformPreviewRowCount(): ?int
    {
        return $this->cachedTransformPreviewRowCount;
    }

    public function updatedEditTransforms(): void
    {
        $this->refreshPreviewCache();
    }

    public function updatedEditRemoveDuplicates(): void
    {
        $this->refreshPreviewCache();
    }

    public function updatedEditDedupeColumns(): void
    {
        $this->refreshPreviewCache();
    }

    public function updatedEditRenames(): void
    {
        $this->refreshPreviewCache();
    }

    public function updatedEditTableColumns(): void
    {
        $this->refreshPreviewCache();
    }

    public function selectAllTableColumns(): void
    {
        $this->editTableColumns = array_keys($this->allFieldOptions());
        $this->refreshPreviewCache();
    }

    public function clearTableColumns(): void
    {
        $this->editTableColumns = [];
        $this->refreshPreviewCache();
    }

    public function updatedEditChartVariant(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditNumberFormat(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditCurrency(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditKpiIcon(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditKpiMetricLabel(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditKpiPeriod(): void
    {
        if ($this->editKpiComparisonMode === 'previous_period') {
            $this->editKpiComparisonLabel = KpiPeriod::comparisonLabel($this->editKpiPeriod);
        }

        $this->refreshPreviewCache();
    }

    public function updatedEditKpiCustomDays(): void
    {
        $this->refreshPreviewCache();
    }

    public function updatedEditKpiValueSize(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditKpiValueWeight(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditKpiComparisonEnabled(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditKpiComparisonMode(): void
    {
        if ($this->editKpiComparisonMode === 'previous_period') {
            $this->editKpiComparisonLabel = KpiPeriod::comparisonLabel($this->editKpiPeriod);
        }

        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditKpiComparisonDelta(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditKpiComparisonUp(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditKpiHigherIsBetter(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditKpiComparisonLabel(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    /**
     * @return array<string, string>
     */
    public function chartVariantOptions(): array
    {
        $type = $this->selectedVisual()?->type ?? '';

        return ChartVariantCatalog::optionsForType($type);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildVisualPanelConfig(): array
    {
        $visual = $this->selectedVisual();
        $type = $visual?->type ?? '';

        return [
            'color_scheme' => $this->editColorScheme,
            'border' => $this->editBorder,
            'number_format' => $this->normalizedNumberFormat(),
            'currency' => $this->normalizedCurrency(),
            'kpi' => $this->buildKpiVisualConfig(),
            ...$this->formatVisualConfig(),
            ...(ChartVariantCatalog::supports($type)
                ? ['chart_variant' => ChartVariantCatalog::normalize($type, $this->editChartVariant)]
                : []),
        ];
    }

    public function supportsValueFormat(): bool
    {
        $type = $this->selectedVisual()?->type ?? '';

        return ! in_array($type, ['hero', 'insight_list', 'table', 'container'], true);
    }

    /**
     * @return array<string, string>
     */
    public function numberFormatOptions(): array
    {
        return ValueFormatter::numberFormats();
    }

    /**
     * @return array<string, string>
     */
    public function colorSchemeOptions(): array
    {
        return ValueFormatter::colorSchemes();
    }

    /**
     * @return array<string, string>
     */
    public function currencyOptions(): array
    {
        return ValueFormatter::currencies();
    }

    protected function normalizedNumberFormat(): string
    {
        return ValueFormatter::formatKey($this->editNumberFormat);
    }

    protected function normalizedCurrency(): string
    {
        return ValueFormatter::normalizeCurrency($this->editCurrency);
    }

    public function kpiIconOptions(): array
    {
        return KpiCardCatalog::iconOptions();
    }

    /**
     * @return array<string, string>
     */
    public function kpiPeriodOptions(): array
    {
        return KpiPeriod::options();
    }

    /**
     * @return array<string, string>
     */
    public function kpiValueSizeOptions(): array
    {
        return KpiPeriod::valueSizeOptions();
    }

    /**
     * @return array<string, string>
     */
    public function kpiValueWeightOptions(): array
    {
        return KpiPeriod::valueWeightOptions();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function loadKpiSettingsFromConfig(array $config): void
    {
        $settings = KpiCardCatalog::normalizeSettings(is_array($config['visual'] ?? null) ? $config['visual'] : []);
        $comparison = $settings['comparison'];

        $this->editKpiIcon = (string) ($settings['icon'] ?? '');
        $this->editKpiMetricLabel = (string) ($settings['metric_label'] ?? '');
        $this->editKpiPeriod = (string) ($settings['period'] ?? 'monthly');
        $this->editKpiCustomDays = (string) ($settings['custom_days'] ?? 30);
        $this->editKpiValueSize = (string) ($settings['value_size'] ?? 'md');
        $this->editKpiValueWeight = (string) ($settings['value_weight'] ?? 'bold');
        $this->editKpiComparisonEnabled = (bool) ($comparison['enabled'] ?? false);
        $this->editKpiComparisonMode = (string) ($comparison['mode'] ?? 'manual');
        $this->editKpiComparisonDelta = $comparison['delta'] !== null ? (string) $comparison['delta'] : '';
        $this->editKpiComparisonUp = (bool) ($comparison['up'] ?? true);
        $this->editKpiHigherIsBetter = (bool) ($comparison['higher_is_better'] ?? true);
        $this->editKpiComparisonLabel = (string) ($comparison['label'] ?? 'than last period');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildKpiVisualConfig(): array
    {
        if ($this->selectedVisual()?->type !== 'stat_card') {
            return [];
        }

        return [
            'icon' => $this->editKpiIcon,
            'metric_label' => $this->editKpiMetricLabel,
            'period' => $this->editKpiPeriod,
            'custom_days' => max(1, (int) $this->editKpiCustomDays),
            'value_size' => $this->editKpiValueSize,
            'value_weight' => $this->editKpiValueWeight,
            'comparison' => [
                'enabled' => $this->editKpiComparisonEnabled,
                'mode' => $this->editKpiComparisonMode,
                'delta' => filled($this->editKpiComparisonDelta) ? (float) $this->editKpiComparisonDelta : null,
                'up' => $this->editKpiComparisonUp,
                'higher_is_better' => $this->editKpiHigherIsBetter,
                'label' => $this->editKpiComparisonLabel,
            ],
        ];
    }

    public function updatedEditFieldFormats(): void
    {
        $this->refreshPreviewCache();
    }

    public function updatedEditSourceField(): void
    {
        $this->refreshPreviewCache();
    }

    public function updatedEditGroupBy(): void
    {
        if ($this->selectedDimensionIsTemporal() && $this->editTimeGrain === '') {
            $this->editTimeGrain = 'day';
        }

        $this->refreshPreviewCache();
    }

    public function updatedEditTimeGrain(): void
    {
        $this->refreshPreviewCache();
    }

    public function updatedEditBreakdown(): void
    {
        $this->refreshPreviewCache();
    }

    public function updatedEditAggregation(): void
    {
        $this->refreshPreviewCache();
    }

    public function updatedEditPeriodGrains(): void
    {
        $this->refreshPreviewCache();
    }

    public function updatedEditHeading(): void
    {
        // Title/subheading morph in the preview header; chart host is wire:ignore.
    }

    public function updatedEditSubheading(): void
    {
        //
    }

    public function updatedEditColorScheme(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditBorder(): void
    {
        // Card border applies on save / canvas refresh; skip preview data reload.
    }

    public function updatedEditTitleFont(): void
    {
        //
    }

    public function updatedEditTitleSize(): void
    {
        //
    }

    public function updatedEditTitleColor(): void
    {
        //
    }

    public function updatedEditLegendShow(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditLegendPosition(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditDataLabels(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditShowXAxisTitle(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function updatedEditShowYAxisTitle(): void
    {
        $this->dispatchPreviewStyleSync();
    }

    public function refreshPreviewCache(): void
    {
        $this->cachedTransformPreviewRowCount = $this->computeTransformPreviewRowCount();
        $this->cachedPreviewPayload = $this->computeLivePreviewPayload();
        $this->dispatchPreviewStyleSync(false);
    }

    /**
     * Push the current preview chart payload to the wire:ignore Apex host without
     * requiring a full Livewire DOM remount of the chart canvas.
     */
    protected function dispatchPreviewStyleSync(bool $styleOnly = true): void
    {
        $visual = $this->selectedVisual();

        if (! $visual) {
            return;
        }

        $payload = $this->cachedPreviewPayload;

        if (! is_array($payload) || isset($payload['error'])) {
            return;
        }

        $type = $visual->type;
        $title = filled($this->editHeading)
            ? $this->editHeading
            : (string) (VisualTypes::labels()[$type] ?? 'Visual');

        $chartPayload = [
            'id' => 'panel-preview',
            'type' => $type,
            'chart' => $payload['chart'] ?? [],
            'visual' => array_merge(
                $this->buildVisualPanelConfig(),
                [
                    'color' => ValueFormatter::colorForScheme($this->editColorScheme),
                    'title' => [
                        'font' => $this->editTitleFont,
                        'size' => $this->editTitleSize,
                        'color' => $this->editTitleColor,
                        'text' => $title,
                        'subheading' => $this->editSubheading,
                    ],
                ]
            ),
            'title' => $title,
            'subheading' => $this->editSubheading,
        ];

        $this->dispatch('vd-panel-preview-chart',
            key: 'panel-'.$visual->getKey(),
            dataSignature: md5(json_encode([
                $type,
                $payload['chart'] ?? [],
                $payload['rows'] ?? null,
                $payload['value'] ?? null,
            ])),
            styleOnly: $styleOnly,
            payload: $chartPayload,
        );
    }

    protected function computeTransformPreviewRowCount(): ?int
    {
        if ($this->editSourceKey === '') {
            return null;
        }

        try {
            $visual = $this->selectedVisual();

            if (! $visual) {
                return null;
            }

            $config = $this->buildTransformPreviewConfig($visual->normalizedConfig());

            return app(DataEngine::class)->filteredRowCountFromConfig($config, 5000);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function relatedSourceOptions(): array
    {
        if ($this->editSourceKey === '') {
            return [];
        }

        return app(RelatedSourceDiscoverer::class)->discoverForSourceKey($this->editSourceKey);
    }

    /**
     * @return array<string, string>
     */
    public function allFieldOptions(): array
    {
        $fields = $this->sourceFieldOptions();

        foreach ($this->relatedSourceOptions() as $relation) {
            if (! in_array($relation['name'], $this->editRelatedJoins, true)) {
                continue;
            }

            foreach ($relation['fields'] ?? [] as $key => $label) {
                $fields[$relation['name'].'.'.$key] = $relation['related_label'].' → '.$label;
            }
        }

        return $fields;
    }

    public function hasValidDataSourceSelection(): bool
    {
        if ($this->editSourceGroup === '' || $this->editSourceKey === '') {
            return false;
        }

        $visualType = $this->selectedVisual()?->type ?? 'bar';

        if ($visualType === 'table') {
            return true;
        }

        $blueprint = VisualTypes::fieldBlueprint($visualType);

        foreach ($blueprint['slots'] as $slot) {
            if (($slot['required'] ?? false) && blank($this->{$slot['wire']} ?? '')) {
                return false;
            }
        }

        foreach ($blueprint['slots'] as $slot) {
            if (($slot['role'] ?? '') !== 'measure') {
                continue;
            }

            if (blank($this->{$slot['wire']} ?? '')) {
                return false;
            }
        }

        if (
            in_array($visualType, ['bar', 'column', 'pie', 'donut', 'funnel'], true)
            && blank($this->editGroupBy)
        ) {
            return false;
        }

        if (
            in_array($visualType, ['line', 'stacked_column', 'area'], true)
            && $this->editPeriodGrains === []
            && blank($this->editGroupBy)
        ) {
            $needsDimension = collect($blueprint['slots'])->contains(
                fn (array $slot): bool => ($slot['role'] ?? '') === 'dimension' && ! ($slot['optional'] ?? false)
            );

            if ($needsDimension) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function fieldOptionsForSlot(array $slot): array
    {
        $role = (string) ($slot['role'] ?? 'measure');
        $labeled = $this->labeledFieldOptions();

        if ($role === 'measure') {
            $measures = [];

            foreach (VirtualMeasures::options() as $key => $label) {
                $measures[$key] = $label;
            }

            foreach ($labeled as $key => $label) {
                if ($this->isMeasureField($key) && ! VirtualMeasures::isVirtual($key)) {
                    $measures[$key] = $label;
                }
            }

            foreach ($labeled as $key => $label) {
                $measures[$key] ??= $label;
            }

            return $measures;
        }

        return $labeled !== [] ? $labeled : $this->allFieldOptions();
    }

    /**
     * Schema fields with a type hint from the source, never dummy placeholders.
     *
     * @return array<string, string>
     */
    public function labeledFieldOptions(): array
    {
        $options = [];

        foreach ($this->allFieldOptions() as $key => $label) {
            $hint = $this->fieldTypeHint($key);
            $options[$key] = $hint !== '' ? $label.' ('.$hint.')' : $label;
        }

        return $options;
    }

    protected function fieldTypeHint(string $key): string
    {
        if (VirtualMeasures::isVirtual($key)) {
            return '';
        }

        if (GroupByDimension::isTemporal($key)) {
            return 'datetime';
        }

        if (GroupByDimension::isForeignKey($key)) {
            return 'id';
        }

        if ($this->isMeasureField($key)) {
            return 'number';
        }

        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function livePreviewPayload(): ?array
    {
        return $this->cachedPreviewPayload;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function computeLivePreviewPayload(): ?array
    {
        $visual = $this->selectedVisual();

        if (! $visual) {
            return null;
        }

        try {
            $config = $this->editSourceKey !== ''
                ? $this->buildTransformPreviewConfig($visual->normalizedConfig())
                : $visual->normalizedConfig();

            if ($this->editSourceKey === '' && ! $this->isPersistedDataSourceReady($config)) {
                return null;
            }

            if ($this->editSourceKey !== '') {
                if (! $this->hasValidDataSourceSelection()) {
                    return null;
                }

                $filteredCount = $this->cachedTransformPreviewRowCount ?? $this->computeTransformPreviewRowCount();

                if ($visual->type !== 'table' && ($filteredCount === null || $filteredCount < 1)) {
                    return null;
                }
            }

            $config['heading'] = $this->editHeading;
            $config['title'] = $this->editHeading;
            $config['subheading'] = $this->editSubheading;
            $config['visual'] = array_replace(
                is_array($config['visual'] ?? null) ? $config['visual'] : [],
                $this->buildVisualPanelConfig()
            );

            if (blank($config['data_source'] ?? '') && blank($config['table_name'] ?? '')) {
                return null;
            }

            $payload = app(DataEngine::class)->previewPayloadFromForm([
                ...$config,
                'type' => $visual->type,
            ]);

            $payload['type'] = $visual->type;

            return $payload;
        } catch (Throwable) {
            return [
                'type' => $visual->type,
                'error' => 'Unable to load preview for the current selection.',
            ];
        }
    }

    /**
     * Human label for the selected chart type (used by live preview chrome).
     */
    public function selectedVisualTypeLabel(): string
    {
        $type = $this->selectedVisual()?->type ?? '';

        return (string) (VisualTypes::labels()[$type] ?? str_replace('_', ' ', $type));
    }

    public function render()
    {
        return view('visual-dashboards::livewire.visual-dashboard-canvas', [
            'dashboard' => $this->dashboard(),
            'items' => $this->gridItems(),
            'columns' => (int) config('visual-dashboards.grid.columns', 12),
            'cellHeight' => (int) config('visual-dashboards.grid.cell_height', 80),
            'saveDebounceMs' => (int) config('visual-dashboards.grid.save_debounce_ms', 450),
            'gallery' => VisualTypes::gallery(),
            'selectedVisual' => $this->selectedVisual(),
            'previewPayload' => $this->livePreviewPayload(),
            'cachedTransformPreviewRowCount' => $this->cachedTransformPreviewRowCount,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function gridItems(): array
    {
        return app(LayoutManager::class)
            ->gridItems($this->dashboard(), $this->grains)
            ->values()
            ->all();
    }

    protected function selectedVisual(): ?Dashlet
    {
        if ($this->selectedVisualId === null) {
            return null;
        }

        return $this->dashboard()->dashlets()->whereKey($this->selectedVisualId)->first();
    }

    protected function visualBelongsToDashboard(int $visualId): bool
    {
        return $this->dashboard()->dashlets()->whereKey($visualId)->exists();
    }

    protected function dashboard(): CustomDashboard
    {
        return CustomDashboard::query()->findOrFail($this->dashboardId);
    }

    /**
     * Build config for transform-step preview (filters applied, not yet persisted).
     *
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    protected function buildTransformPreviewConfig(array $base = []): array
    {
        $config = $this->buildDataSourceConfig($base);
        $config['query']['applied'] = true;

        return $config;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    protected function buildDataSourceConfig(array $base = []): array
    {
        $registry = app(VisualDashboardDataSourceRegistry::class);
        $descriptor = $registry->find($this->editSourceKey);

        $config = array_replace_recursive(
            $base,
            $registry->configForKey($this->editSourceKey, $this->editSourceField ?: null),
        );

        $joins = collect($this->relatedSourceOptions())
            ->filter(fn (array $relation): bool => in_array($relation['name'], $this->editRelatedJoins, true))
            ->map(function (array $relation): array {
                $relationName = (string) $relation['name'];
                $selectedFields = collect([$this->editSourceField, $this->editGroupBy, $this->editBreakdown])
                    ->merge($this->fieldsReferencedInTransforms())
                    ->filter(fn (string $field): bool => str_starts_with($field, $relationName.'.'))
                    ->map(fn (string $field): string => substr($field, strlen($relationName) + 1))
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'relation' => $relationName,
                    'alias' => $relationName,
                    'type' => $relation['type'],
                    'related_model' => $relation['related_model'],
                    'related_table' => $relation['related_table'],
                    'foreign_key' => $relation['foreign_key'],
                    'local_key' => $relation['local_key'] ?? 'id',
                    'fields' => $selectedFields,
                ];
            })
            ->values()
            ->all();

        $query = [
            'primary_source' => $this->editSourceKey,
            'transforms' => $this->buildTransformsPayload(),
            'joins' => $joins,
            'related_joins' => $joins,
            'selected_fields' => $this->editSourceField !== '' ? [$this->editSourceField] : [],
            'remove_duplicates' => $this->editRemoveDuplicates,
            'applied' => $this->editQueryApplied,
        ];

        $config = VisualQueryBuilder::mergeIntoConfig($config, $query);

        $visualType = $this->selectedVisual()?->type ?? 'bar';

        if ($visualType === 'table') {
            $config['columns'] = array_values(array_filter($this->editTableColumns));
            $config['aggregations'] = [];
            $config['group_by'] = [];
            $config['mapping'] = [];
            $config['visual'] = array_replace(
                is_array($config['visual'] ?? null) ? $config['visual'] : [],
                [
                    'period_grains' => array_values($this->editPeriodGrains),
                    'period_filter' => $this->editPeriodGrains !== [],
                    'default_grain' => $this->editTimeGrain,
                ]
            );

            return $config;
        }

        $mappedFields = array_values(array_unique(array_filter([
            $this->editGroupBy,
            $this->editBreakdown,
            ($this->editSourceField !== '' && ! VirtualMeasures::isVirtual($this->editSourceField))
                ? $this->editSourceField
                : null,
        ])));

        $config['columns'] = $mappedFields;

        $aggregationField = VirtualMeasures::aggregationField(
            VirtualMeasures::normalizeField($this->editSourceField),
            $this->editAggregation
        );

        $config['aggregations'] = [[
            'fn' => $this->editAggregation,
            'field' => $aggregationField !== '' ? $aggregationField : '*',
            'alias' => 'value',
        ]];

        if ($visualType === 'radialbar') {
            $config['group_by'] = [];
            $config['mapping'] = ['y' => 'value'];
        } else {
            $groupBy = $this->editGroupBy;

            if ($groupBy === '' && $this->editPeriodGrains !== [] && in_array($visualType, ['line', 'stacked_column', 'area'], true)) {
                $groupBy = $this->defaultDateDimensionField();
            }

            if ($groupBy !== '') {
                $config['group_by'] = [$groupBy];
            } else {
                $config['group_by'] = [];
            }

            $mapping = ['y' => 'value'];

            if ($groupBy !== '') {
                $mapping['x'] = $groupBy;
            }

            if ($this->editBreakdown !== '') {
                $mapping['series'] = $this->editBreakdown;
            }

            $config['mapping'] = $mapping;
        }

        $config['visual'] = array_replace(
            is_array($config['visual'] ?? null) ? $config['visual'] : [],
            [
                'period_grains' => array_values($this->editPeriodGrains),
                'period_filter' => $this->editPeriodGrains !== [],
                'default_grain' => $this->editTimeGrain,
            ]
        );

        if ($this->supportsTimeBucket()) {
            $config['filters']['grain'] = $this->editTimeGrain;
        } elseif ($this->editPeriodGrains !== []) {
            $config['filters']['grain'] = $config['filters']['grain']
                ?? $this->editPeriodGrains[0]
                ?? 'month';
        }

        return $config;
    }

    protected function defaultDateDimensionField(): string
    {
        $dimensions = array_keys($this->dimensionFieldOptions());

        foreach (['created_at', 'occurred_at', 'updated_at', 'submitted_at'] as $candidate) {
            if (in_array($candidate, $dimensions, true)) {
                return $candidate;
            }
        }

        foreach ($dimensions as $field) {
            if (preg_match('/(_at|_date)$/i', $field) === 1) {
                return $field;
            }
        }

        return 'created_at';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function resolveSourceKey(array $config): string
    {
        $dataSource = (string) ($config['data_source'] ?? '');

        if (str_starts_with($dataSource, 'model:')) {
            return $dataSource;
        }

        if (str_starts_with($dataSource, 'filament:')) {
            return $dataSource;
        }

        if (($config['source_kind'] ?? '') === 'form' && filled($config['filters']['form_id'] ?? null)) {
            return 'form:'.(int) $config['filters']['form_id'];
        }

        if (($config['source_kind'] ?? '') === 'table' && filled($config['table_name'] ?? null)) {
            return 'table:'.$config['table_name'];
        }

        if (str_starts_with($dataSource, 'table:')) {
            return $dataSource;
        }

        if ($dataSource !== '') {
            return 'registered:'.$dataSource;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function resolveSourceGroup(array $config): string
    {
        $key = $this->resolveSourceKey($config);

        if ($key === '') {
            return '';
        }

        return app(VisualDashboardDataSourceRegistry::class)->groupForKey($key) ?? '';
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function loadTransformStateFromQuery(array $query): void
    {
        $filters = [];
        $renames = [];
        $formats = [];
        $dedupeColumns = [];
        $removeDuplicates = (bool) ($query['remove_duplicates'] ?? false);

        foreach ($query['transforms'] as $transform) {
            if (! is_array($transform)) {
                continue;
            }

            $type = (string) ($transform['type'] ?? 'filter');

            if ($type === 'filter') {
                $column = trim((string) ($transform['column'] ?? $transform['field'] ?? ''));

                $filters[] = [
                    'type' => 'filter',
                    'field' => $column,
                    'operator' => (string) ($transform['operator'] ?? 'equals'),
                    'value' => $transform['value'] ?? '',
                ];
            }

            if ($type === 'rename') {
                $renames[] = [
                    'from' => (string) ($transform['from'] ?? ''),
                    'to' => (string) ($transform['to'] ?? ''),
                ];
            }

            if ($type === 'format') {
                $field = (string) ($transform['field'] ?? $transform['column'] ?? '');
                $format = (string) ($transform['format'] ?? '');
                $presets = array_keys(FieldValueFormatter::dateFormatPresets());

                if ($format !== '' && ! in_array($format, $presets, true)) {
                    $formats[] = [
                        'field' => $field,
                        'function' => (string) ($transform['function'] ?? ''),
                        'format' => 'custom',
                        'custom_format' => $format,
                    ];

                    continue;
                }

                $formats[] = [
                    'field' => $field,
                    'function' => (string) ($transform['function'] ?? ''),
                    'format' => $format,
                    'custom_format' => '',
                ];
            }

            if ($type === 'dedupe') {
                $removeDuplicates = true;
                $columns = $transform['columns'] ?? [];

                if (is_array($columns)) {
                    $dedupeColumns = array_values(array_filter($columns, fn (mixed $col): bool => filled($col)));
                }
            }
        }

        $this->editTransforms = $filters !== []
            ? $filters
            : [['type' => 'filter', 'field' => '', 'operator' => 'equals', 'value' => '']];
        $this->editRenames = $renames !== [] ? $renames : [];
        $this->editFieldFormats = $formats !== [] ? $formats : [];
        $this->editRemoveDuplicates = $removeDuplicates;
        $this->editDedupeColumns = $dedupeColumns;
    }

    protected function resetTransformState(): void
    {
        $this->editTransforms = [['type' => 'filter', 'field' => '', 'operator' => 'equals', 'value' => '']];
        $this->editRenames = [];
        $this->editFieldFormats = [];
        $this->editRemoveDuplicates = false;
        $this->editDedupeColumns = [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function captureTransformState(): array
    {
        return [
            'editTransforms' => $this->editTransforms,
            'editRenames' => $this->editRenames,
            'editFieldFormats' => $this->editFieldFormats,
            'editRemoveDuplicates' => $this->editRemoveDuplicates,
            'editDedupeColumns' => $this->editDedupeColumns,
            'editQueryApplied' => $this->editQueryApplied,
        ];
    }

    protected function restoreTransformSnapshot(): void
    {
        $this->editTransforms = $this->transformSnapshot['editTransforms'] ?? [['type' => 'filter', 'field' => '', 'operator' => 'equals', 'value' => '']];
        $this->editRenames = $this->transformSnapshot['editRenames'] ?? [];
        $this->editFieldFormats = $this->transformSnapshot['editFieldFormats'] ?? [];
        $this->editRemoveDuplicates = (bool) ($this->transformSnapshot['editRemoveDuplicates'] ?? false);
        $this->editDedupeColumns = $this->transformSnapshot['editDedupeColumns'] ?? [];
        $this->editQueryApplied = (bool) ($this->transformSnapshot['editQueryApplied'] ?? false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildTransformsPayload(): array
    {
        $transforms = [];

        foreach ($this->editTransforms as $filter) {
            $column = trim((string) ($filter['field'] ?? ''));

            if ($column === '') {
                continue;
            }

            $operator = (string) ($filter['operator'] ?? 'equals');
            $value = $filter['value'] ?? null;
            $needsValue = ! in_array($operator, ['is_empty', 'is_not_empty', 'is_null', 'is_not_null'], true);

            // Incomplete include-row rules must not keep filtering after the value is cleared.
            if ($needsValue && (is_null($value) || (is_string($value) && trim($value) === ''))) {
                continue;
            }

            $transforms[] = [
                'type' => 'filter',
                'column' => $column,
                'operator' => $operator,
                'value' => is_string($value) ? trim($value) : $value,
            ];
        }

        if ($this->editRemoveDuplicates) {
            $transforms[] = [
                'type' => 'dedupe',
                'columns' => array_values($this->editDedupeColumns),
            ];
        }

        foreach ($this->editRenames as $rename) {
            $from = trim((string) ($rename['from'] ?? ''));
            $to = trim((string) ($rename['to'] ?? ''));

            if ($from === '' || $to === '') {
                continue;
            }

            $transforms[] = [
                'type' => 'rename',
                'from' => $from,
                'to' => $to,
            ];
        }

        foreach ($this->editFieldFormats as $formatRow) {
            $field = trim((string) ($formatRow['field'] ?? ''));
            $function = trim((string) ($formatRow['function'] ?? ''));

            if ($field === '' || $function === '') {
                continue;
            }

            $format = trim((string) ($formatRow['format'] ?? ''));

            if ($format === 'custom') {
                $format = trim((string) ($formatRow['custom_format'] ?? ''));
            }

            $transforms[] = [
                'type' => 'format',
                'field' => $field,
                'function' => $function,
                'format' => $format,
            ];
        }

        return $transforms;
    }

    protected function persistDataSource(): void
    {
        $visual = $this->selectedVisual();

        if (! $visual) {
            return;
        }

        $config = $this->buildDataSourceConfig($visual->normalizedConfig());

        $visual->update(['config' => $config]);
        $this->refreshLayout();
        $this->dispatch('visual-dashboard-refresh');
    }

    /**
     * @return array<int, string>
     */
    protected function fieldsReferencedInTransforms(): array
    {
        $fields = collect([$this->editSourceField, $this->editGroupBy, $this->editBreakdown]);

        foreach ($this->editTransforms as $filter) {
            $field = trim((string) ($filter['field'] ?? ''));

            if ($field !== '') {
                $fields->push($field);
            }
        }

        foreach ($this->editDedupeColumns as $column) {
            $fields->push((string) $column);
        }

        return $fields
            ->filter(fn (string $field): bool => $field !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function isPersistedDataSourceReady(array $config): bool
    {
        $visual = $this->selectedVisual();

        return VisualTypes::isPersistedConfigReady(
            $visual?->type ?? 'bar',
            $config,
            $visual?->config ?? [],
        );
    }

    /**
     * @return array<string, string>|null
     */
    protected function filterValueOptionsForDescriptor(DataSourceDescriptor $descriptor, string $column): ?array
    {
        if ($descriptor->kind === 'form') {
            return $this->filterValueOptionsForFormSubmission(
                (int) ($descriptor->meta['form_id'] ?? 0),
                $column,
            );
        }

        if (($descriptor->meta['registry_key'] ?? '') === 'form_submissions') {
            return $this->filterValueOptionsForFormSubmission(0, $column);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function filterValueOptionsForFormSubmission(int $formId, string $column): array
    {
        $table = (string) config('form-builder.tables.submissions', 'form_builder_submissions');

        if (! Schema::hasTable($table)) {
            return [];
        }

        $scope = $formId > 0
            ? fn ($query) => $query->where('form_id', $formId)
            : null;

        if ($column === 'status') {
            $configured = config('form-builder.submissions.statuses', []);
            $configured = is_array($configured) ? $configured : [];

            $dbValues = $this->distinctColumnValues($table, 'status', 50, $scope);

            $options = [];

            foreach ($configured as $key => $label) {
                $options[(string) $key] = (string) $label;
            }

            foreach ($dbValues as $key => $label) {
                $options[$key] ??= $label;
            }

            return $options;
        }

        if (str_starts_with($column, 'data.')) {
            return $this->distinctJsonPayloadValues($table, substr($column, 5), $scope);
        }

        if (! Schema::hasColumn($table, $column)) {
            return [];
        }

        return $this->distinctColumnValues($table, $column, 50, $scope);
    }

    /**
     * @param  (callable(\Illuminate\Database\Query\Builder): void)|null  $scope
     * @return array<string, string>
     */
    protected function distinctColumnValues(
        string $table,
        string $column,
        int $limit = 50,
        ?callable $scope = null,
    ): array {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $table)
            || ! preg_match('/^[A-Za-z0-9_]+$/', $column)
            || ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
        ) {
            return [];
        }

        $query = DB::table($table);

        if ($scope !== null) {
            $scope($query);
        }

        return $this->distinctColumnValuesFromQuery($query, $column, $limit);
    }

    /**
     * @param  (callable(\Illuminate\Database\Query\Builder): void)|null  $scope
     * @return array<string, string>
     */
    protected function distinctJsonPayloadValues(string $table, string $field, ?callable $scope = null, int $limit = 50): array
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $table)
            || ! preg_match('/^[A-Za-z0-9_]+$/', $field)
            || ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'data')
        ) {
            return [];
        }

        $query = DB::table($table);

        if ($scope !== null) {
            $scope($query);
        }

        return $query
            ->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(`data`, ?)) as val', ['$.'.$field])
            ->whereNotNull('data')
            ->distinct()
            ->orderBy('val')
            ->limit($limit)
            ->pluck('val')
            ->filter(fn (mixed $value): bool => filled($value))
            ->mapWithKeys(fn (mixed $value): array => [(string) $value => (string) $value])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function distinctColumnValuesFromQuery(\Illuminate\Database\Query\Builder $query, string $column, int $limit = 50): array
    {
        return $query
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->limit($limit)
            ->pluck($column)
            ->mapWithKeys(fn (mixed $value): array => [(string) $value => (string) $value])
            ->all();
    }
}
