<?php

namespace Spiggle\VisualDashboards\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Models\Dashlet;

/**
 * @extends Factory<Dashlet>
 */
class DashletFactory extends Factory
{
    protected $model = Dashlet::class;

    public function definition(): array
    {
        return [
            'custom_dashboard_id' => CustomDashboard::factory(),
            'type' => 'bar',
            'grid_x' => 0,
            'grid_y' => 0,
            'grid_w' => 4,
            'grid_h' => 4,
            'grid_z' => 0,
            'config' => [
                'title' => fake()->words(2, true),
                'data_source' => 'sample_metrics',
                'filters' => [],
                'visual' => [
                    'color' => '#f59e0b',
                    'border' => true,
                ],
            ],
        ];
    }

    public function statCard(): static
    {
        return $this->state([
            'type' => 'stat_card',
            'grid_w' => 3,
            'grid_h' => 2,
        ]);
    }
}
