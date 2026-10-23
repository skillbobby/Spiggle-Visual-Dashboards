<?php

namespace Spiggle\VisualDashboards\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Models\Dashlet;
use Spiggle\VisualDashboards\Services\LayoutManager;
use Spiggle\VisualDashboards\Support\FeatureCatalog;

class DashboardImporter
{
    public function __construct(
        protected DashboardExporter $exporter,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function import(array $payload, int $userId, bool $replace = false, ?string $nameOverride = null): CustomDashboard
    {
        $validated = $this->validate($payload);

        $dashboardName = $nameOverride ?: (string) ($validated['dashboard']['name'] ?? 'Imported dashboard');
        $isDefault = (bool) ($validated['dashboard']['is_default'] ?? false);
        $isPublished = (bool) ($validated['dashboard']['is_published'] ?? false);
        $viewerRoles = array_values(array_filter(array_map(
            static fn (mixed $role): string => trim((string) $role),
            is_array($validated['dashboard']['viewer_roles'] ?? null)
                ? $validated['dashboard']['viewer_roles']
                : []
        )));

        $dashboard = CustomDashboard::query()
            ->where('user_id', $userId)
            ->where('name', $dashboardName)
            ->first();

        if ($dashboard && ! $replace) {
            $dashboardName = $this->uniqueName($userId, $dashboardName);
            $dashboard = null;
        }

        if ($dashboard && $replace) {
            $dashboard->dashlets()->delete();
            $dashboard->update([
                'is_default' => $isDefault,
                'is_published' => $isPublished,
                'viewer_roles' => $viewerRoles,
            ]);
        }

        if (! $dashboard) {
            $dashboard = CustomDashboard::query()->create([
                'user_id' => $userId,
                'name' => $dashboardName,
                'is_default' => $isDefault,
                'is_published' => $isPublished,
                'viewer_roles' => $viewerRoles,
            ]);
        }

        foreach ($validated['dashlets'] as $definition) {
            $type = (string) ($definition['type'] ?? 'stat_card');
            FeatureCatalog::assertAllowed($type);

            Dashlet::query()->create([
                'custom_dashboard_id' => $dashboard->id,
                'type' => $type,
                'grid_x' => (int) ($definition['grid_x'] ?? 0),
                'grid_y' => (int) ($definition['grid_y'] ?? 0),
                'grid_w' => (int) ($definition['grid_w'] ?? 4),
                'grid_h' => (int) ($definition['grid_h'] ?? 4),
                'config' => is_array($definition['config'] ?? null) ? $definition['config'] : [],
            ]);
        }

        $items = $dashboard->dashlets()
            ->get()
            ->map(fn (Dashlet $dashlet): array => [
                'id' => $dashlet->id,
                'x' => $dashlet->grid_x,
                'y' => $dashlet->grid_y,
                'w' => $dashlet->grid_w,
                'h' => $dashlet->grid_h,
            ])
            ->all();

        app(LayoutManager::class)->sync($dashboard, $items);

        return $dashboard->fresh('dashlets');
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeJson(string $json): array
    {
        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'json' => 'Import file must contain valid JSON.',
            ]);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function validate(array $payload): array
    {
        return Validator::make($payload, [
            'version' => ['nullable', 'string'],
            'dashboard.name' => ['required', 'string', 'max:120'],
            'dashboard.is_default' => ['nullable', 'boolean'],
            'dashboard.is_published' => ['nullable', 'boolean'],
            'dashboard.viewer_roles' => ['nullable', 'array'],
            'dashboard.viewer_roles.*' => ['string', 'max:120'],
            'dashlets' => ['required', 'array'],
            'dashlets.*.type' => ['required', 'string'],
            'dashlets.*.grid_x' => ['nullable', 'integer', 'min:0'],
            'dashlets.*.grid_y' => ['nullable', 'integer', 'min:0'],
            'dashlets.*.grid_w' => ['nullable', 'integer', 'min:1'],
            'dashlets.*.grid_h' => ['nullable', 'integer', 'min:1'],
            'dashlets.*.config' => ['nullable', 'array'],
        ])->validate();
    }

    protected function uniqueName(int $userId, string $name): string
    {
        $base = Str::limit($name, 110, '');
        $candidate = $base.' (import)';

        while (CustomDashboard::query()->where('user_id', $userId)->where('name', $candidate)->exists()) {
            $candidate = $base.' (import '.Str::lower(Str::random(4)).')';
        }

        return $candidate;
    }
}
