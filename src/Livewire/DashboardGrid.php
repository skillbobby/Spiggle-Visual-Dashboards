<?php

namespace Spiggle\VisualDashboards\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Livewire\Component;
use Spiggle\VisualDashboards\Filament\Support\DashletFormSchema;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Models\Dashlet;
use Spiggle\VisualDashboards\Services\LayoutManager;
use Spiggle\VisualDashboards\Support\DashboardAccess;

class DashboardGrid extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public int $dashboardId;

    public bool $presentationMode = false;

    /**
     * @var array<int, string>
     */
    public array $grains = [];

    /**
     * @var array<int, array<string, int>>
     */
    public array $layout = [];

    public function mount(int $dashboardId, bool $presentationMode = false): void
    {
        $dashboard = CustomDashboard::query()->findOrFail($dashboardId);

        abort_unless(
            DashboardAccess::canViewInApp(auth()->user(), $dashboard),
            403
        );

        $this->dashboardId = $dashboardId;
        $this->presentationMode = $presentationMode;
        $this->refreshLayout();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function setGrain(int $dashletId, string $grain): array
    {
        if (! in_array($grain, ['day', 'week', 'month', 'year'], true) || ! $this->dashletBelongsToDashboard($dashletId)) {
            return $this->gridItems();
        }

        $this->grains[$dashletId] = $grain;

        return $this->refreshLayout();
    }

    public function editDashletAction(): Action
    {
        return Action::make('editDashlet')
            ->label('Edit dashlet')
            ->modalHeading('Edit dashlet')
            ->modalDescription('Source → filter → aggregate → visual → preview.')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Save dashlet')
            ->closeModalByClickingAway(false)
            ->steps(DashletFormSchema::steps())
            ->fillForm(function (array $arguments): array {
                $dashlet = $this->dashletFromArguments($arguments);

                return $dashlet ? DashletFormSchema::formStateFromDashlet($dashlet) : [];
            })
            ->action(function (array $data, array $arguments): void {
                $dashlet = $this->dashletFromArguments($arguments);

                if (! $dashlet) {
                    return;
                }

                DashletFormSchema::assertAllowedType((string) $data['type']);

                $dashlet->update([
                    'type' => (string) $data['type'],
                    'config' => DashletFormSchema::configFromFormData($data),
                ]);

                Notification::make()
                    ->title('Dashlet updated')
                    ->success()
                    ->send();

                $this->refreshLayout();
                $this->dispatch('dashlet-grid-refresh');
            });
    }

    public function deleteDashletAction(): Action
    {
        return Action::make('deleteDashlet')
            ->label('Remove')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete this dashlet?')
            ->modalDescription('This removes the dashlet from the dashboard. This cannot be undone.')
            ->modalSubmitActionLabel('Delete')
            ->action(function (array $arguments): void {
                $dashlet = $this->dashletFromArguments($arguments);

                if (! $dashlet) {
                    return;
                }

                $dashlet->delete();

                Notification::make()
                    ->title('Dashlet removed')
                    ->success()
                    ->send();

                $this->refreshLayout();
                $this->dispatch('dashlet-grid-refresh');
            });
    }

    public function openEditDashlet(int $dashletId): void
    {
        if (! $this->dashletBelongsToDashboard($dashletId)) {
            return;
        }

        $this->mountAction('editDashlet', ['dashletId' => $dashletId]);
    }

    public function openDeleteDashlet(int $dashletId): void
    {
        if (! $this->dashletBelongsToDashboard($dashletId)) {
            return;
        }

        $this->mountAction('deleteDashlet', ['dashletId' => $dashletId]);
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
    public function updateLayout(array $items, bool $silent = false): array
    {
        $manager = app(LayoutManager::class);
        $resolved = $manager->resolve($items);
        $manager->sync($this->dashboard(), $resolved);

        $fresh = $this->refreshLayout();

        if (! $silent) {
            Notification::make()
                ->title('Layout saved')
                ->success()
                ->duration(2500)
                ->send();
        }

        return $fresh;
    }

    public function render()
    {
        return view('visual-dashboards::livewire.dashboard-grid', [
            'dashboard' => $this->dashboard(),
            'items' => $this->gridItems(),
            'columns' => (int) config('visual-dashboards.grid.columns', 12),
            'cellHeight' => (int) config('visual-dashboards.grid.cell_height', 80),
            'saveDebounceMs' => (int) config('visual-dashboards.grid.save_debounce_ms', 450),
            'presentationMode' => $this->presentationMode,
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

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function dashletFromArguments(array $arguments): ?Dashlet
    {
        $dashletId = (int) ($arguments['dashletId'] ?? 0);

        if ($dashletId < 1) {
            return null;
        }

        return $this->dashboard()->dashlets()->whereKey($dashletId)->first();
    }

    protected function dashletBelongsToDashboard(int $dashletId): bool
    {
        return $this->dashboard()->dashlets()->whereKey($dashletId)->exists();
    }

    protected function dashboard(): CustomDashboard
    {
        return CustomDashboard::query()->findOrFail($this->dashboardId);
    }
}
