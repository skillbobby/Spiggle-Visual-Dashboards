<?php

namespace Spiggle\VisualDashboards\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Spiggle\VisualDashboards\Database\Seeders\SampleDashboardsSeeder;
use Spiggle\VisualDashboards\Models\CustomDashboard;

class SeedChartGalleryCommand extends Command
{
    protected $signature = 'visual-dashboards:seed-gallery {--user= : User ID (defaults to the admin user)}';

    protected $aliases = ['dashlet:seed-gallery'];

    protected $description = 'Create the Chart gallery dashboard (one dashlet per chart type on Contact Us form data)';

    public function handle(): int
    {
        $seeder = new SampleDashboardsSeeder;
        $user = $this->resolveUser($seeder);
        $seeder->removeLegacyDashboards($user);
        $dashboard = $seeder->seedChartGalleryDashboard($user);

        if ($dashboard === null) {
            $this->error('Chart gallery was not created. Form submissions must be available and at least one form must exist.');

            return self::FAILURE;
        }

        $this->reportDashboard($user, $dashboard);

        return self::SUCCESS;
    }

    protected function resolveUser(SampleDashboardsSeeder $seeder): Model
    {
        $userId = $this->option('user');

        if ($userId === null || $userId === '') {
            return $seeder->resolveUser();
        }

        $modelClass = config('visual-dashboards.user_model', 'App\\Models\\User');

        if (! class_exists($modelClass)) {
            throw new RuntimeException("Dashlet user model [{$modelClass}] is not available.");
        }

        $user = $modelClass::query()->find((int) $userId);

        if (! $user instanceof Model) {
            throw new RuntimeException("User #{$userId} was not found.");
        }

        return $user;
    }

    protected function reportDashboard(Model $user, CustomDashboard $dashboard): void
    {
        $label = $user->name ?? $user->email ?? 'user';
        $visuals = $dashboard->dashlets()->count();

        $this->info("Chart gallery ready for user #{$user->getKey()} ({$label}).");
        $this->line("  • {$visuals} chart type(s) on dashboard #{$dashboard->id}");
        $this->line('  • Open Visual Dashboards → '.SampleDashboardsSeeder::CHART_GALLERY_NAME);
    }
}
