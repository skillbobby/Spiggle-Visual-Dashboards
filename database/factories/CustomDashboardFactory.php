<?php

namespace Spiggle\VisualDashboards\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spiggle\VisualDashboards\Models\CustomDashboard;

/**
 * @extends Factory<CustomDashboard>
 */
class CustomDashboardFactory extends Factory
{
    protected $model = CustomDashboard::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true).' Dashboard',
            'slug' => fake()->unique()->slug(3),
            'heading' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(8),
            'is_default' => false,
            'is_published' => false,
            'viewer_roles' => [],
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
