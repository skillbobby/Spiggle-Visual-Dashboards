<?php

namespace Spiggle\VisualDashboards\Services;

use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Models\Dashlet;

class DashboardExporter
{
    /**
     * @return array<string, mixed>
     */
    public function export(CustomDashboard $dashboard): array
    {
        $dashboard->loadMissing('dashlets');

        return [
            'version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'dashboard' => [
                'name' => $dashboard->name,
                'is_default' => (bool) $dashboard->is_default,
                'is_published' => (bool) $dashboard->is_published,
                'viewer_roles' => $dashboard->viewerRoles(),
            ],
            'dashlets' => $dashboard->dashlets
                ->map(fn (Dashlet $dashlet): array => [
                    'type' => $dashlet->type,
                    'grid_x' => $dashlet->grid_x,
                    'grid_y' => $dashlet->grid_y,
                    'grid_w' => $dashlet->grid_w,
                    'grid_h' => $dashlet->grid_h,
                    'grid_z' => $dashlet->grid_z,
                    'config' => $dashlet->normalizedConfig(),
                ])
                ->values()
                ->all(),
        ];
    }

    public function toJson(CustomDashboard $dashboard, int $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->export($dashboard), $options) ?: '{}';
    }
}
