<?php

namespace Spiggle\VisualDashboards\Livewire;

use Illuminate\Auth\AuthenticationException;
use Livewire\Component;
use Spiggle\VisualDashboards\Livewire\Concerns\InteractsWithDashboardFilters;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Services\LayoutManager;
use Spiggle\VisualDashboards\Support\DashboardAccess;

class PublicDashboardView extends Component
{
    use InteractsWithDashboardFilters;

    public int $dashboardId;

    /**
     * @var array<int, string>
     */
    public array $grains = [];

    public function mount(int $dashboardId): void
    {
        $this->dashboardId = $dashboardId;
        $this->assertShareAccessible();
        $this->initializeDashboardFilters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function setGrain(int $visualId, string $grain): array
    {
        $this->assertShareAccessible();

        if (! in_array($grain, ['day', 'week', 'month', 'year'], true)) {
            return $this->gridItems();
        }

        $this->grains[$visualId] = $grain;

        return $this->gridItems();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function refreshLayout(): array
    {
        $this->assertShareAccessible();

        return $this->gridItems();
    }

    /**
     * @param  array<int, array<string, int>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function updateLayout(array $items, bool $silent = true): array
    {
        $dashboard = $this->assertShareAccessible();
        abort_unless(auth()->id() !== null && (int) auth()->id() === (int) $dashboard->user_id, 403);

        $manager = app(LayoutManager::class);
        $manager->sync($dashboard, $manager->resolve($items));

        return $this->gridItems();
    }

    public function render()
    {
        $dashboard = $this->assertShareAccessible();

        return view('visual-dashboards::livewire.dashboard-grid', array_merge([
            'dashboard' => $dashboard,
            'items' => $this->gridItems(),
            'columns' => (int) config('visual-dashboards.grid.columns', 12),
            'cellHeight' => (int) config('visual-dashboards.grid.cell_height', 80),
            'saveDebounceMs' => (int) config('visual-dashboards.grid.save_debounce_ms', 450),
            'presentationMode' => true,
            'canArrange' => auth()->id() !== null && (int) auth()->id() === (int) $dashboard->user_id,
        ], $this->dashboardFilterViewData(canEditFilters: false)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function gridItems(): array
    {
        $dashboard = $this->assertShareAccessible();

        return app(LayoutManager::class)
            ->gridItems($dashboard, $this->grains, activeFilters: $this->filterValues)
            ->values()
            ->all();
    }

    protected function assertShareAccessible(): CustomDashboard
    {
        $dashboard = CustomDashboard::query()->findOrFail($this->dashboardId);

        if (blank($dashboard->slug)) {
            abort(404);
        }

        if (DashboardAccess::isPubliclyViewable($dashboard)) {
            return $dashboard;
        }

        $user = auth()->user();

        if ($user === null) {
            throw new AuthenticationException(
                'Unauthenticated.',
                [],
                route('filament.admin.auth.login'),
            );
        }

        if (! DashboardAccess::canViewInApp($user, $dashboard)) {
            abort(403);
        }

        return $dashboard;
    }
}
