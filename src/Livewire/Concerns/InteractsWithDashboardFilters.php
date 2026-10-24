<?php

namespace Spiggle\VisualDashboards\Livewire\Concerns;

use Illuminate\Support\Str;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Models\Dashlet;
use Spiggle\VisualDashboards\Support\DashboardFilters;

trait InteractsWithDashboardFilters
{
    /**
     * @var array<string, string>
     */
    public array $filterValues = [];

    public bool $filterEditorOpen = false;

    public string $newFilterLabel = '';

    public string $newFilterControl = 'select';

    public string $newFilterField = '';

    public function initializeDashboardFilters(): void
    {
        foreach ($this->dashboardFilterDefinitions() as $filter) {
            $this->filterValues[$filter['id']] ??= '';
        }
    }

    public function updatedFilterValues(): void
    {
        if (method_exists($this, 'refreshLayout')) {
            $this->refreshLayout();
        }
    }

    public function addDashboardFilter(): void
    {
        $field = DashboardFilters::fieldKey($this->newFilterField);

        if ($field === '' || ! array_key_exists($field, $this->availableFilterFields())) {
            return;
        }

        $filters = $this->dashboardFilterDefinitions();
        $filters[] = [
            'id' => (string) Str::uuid(),
            'label' => trim($this->newFilterLabel) !== '' ? trim($this->newFilterLabel) : Str::headline($field),
            'control' => $this->newFilterControl === 'date' ? 'date' : 'select',
            'fields' => [$field],
        ];

        $this->newFilterLabel = '';
        $this->newFilterField = '';
        $this->newFilterControl = 'select';
        $this->filterEditorOpen = false;
        $this->persistDashboardFilters($filters);
    }

    public function removeDashboardFilter(string $id): void
    {
        $filters = array_values(array_filter(
            $this->dashboardFilterDefinitions(),
            static fn (array $filter): bool => $filter['id'] !== $id
        ));

        unset($this->filterValues[$id]);
        $this->persistDashboardFilters($filters);
    }

    /**
     * @return list<array{id: string, label: string, control: string, fields: list<string>}>
     */
    public function dashboardFilterDefinitions(): array
    {
        return DashboardFilters::definitions($this->dashboardForFilters());
    }

    /**
     * @return array<string, list<string>>
     */
    public function dashboardFilterOptionMap(): array
    {
        return DashboardFilters::options($this->dashboardFilterDefinitions(), $this->dashboardFilterTables());
    }

    /**
     * @return array<string, string>
     */
    public function availableFilterFields(): array
    {
        return DashboardFilters::fieldsForTables($this->dashboardFilterTables());
    }

    /**
     * @return array<string, mixed>
     */
    protected function dashboardFilterViewData(bool $canEditFilters): array
    {
        return [
            'canEditFilters' => $canEditFilters,
            'dashboardFilters' => $this->dashboardFilterDefinitions(),
            'dashboardFilterOptions' => $this->dashboardFilterOptionMap(),
            'dashboardFilterFields' => $canEditFilters ? $this->availableFilterFields() : [],
        ];
    }

    /**
     * @param  list<array{id: string, label: string, control: string, fields: list<string>}>  $filters
     */
    protected function persistDashboardFilters(array $filters): void
    {
        $dashboard = $this->dashboardForFilters();
        $layout = is_array($dashboard->layout_json) ? $dashboard->layout_json : [];
        $layout['filters'] = $filters;
        $dashboard->update(['layout_json' => $layout]);
        $this->initializeDashboardFilters();

        if (method_exists($this, 'refreshLayout')) {
            $this->refreshLayout();
        }
    }

    /**
     * @return list<string>
     */
    protected function dashboardFilterTables(): array
    {
        return $this->dashboardForFilters()
            ->dashlets()
            ->get()
            ->map(fn (Dashlet $dashlet): ?string => DashboardFilters::tableName($dashlet->normalizedConfig()))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function dashboardForFilters(): CustomDashboard
    {
        if (method_exists($this, 'dashboard')) {
            return $this->dashboard();
        }

        return $this->assertShareAccessible();
    }
}
