<?php

namespace Spiggle\VisualDashboards\Console;

use Illuminate\Console\Command;
use Spiggle\VisualDashboards\Database\Seeders\SampleDashboardsSeeder;
use Spiggle\VisualDashboards\Models\CustomDashboard;

class SeedSampleDashboardsCommand extends Command
{
    protected $signature = 'visual-dashboards:seed {--fresh : Delete existing demo dashboards first}';

    protected $aliases = ['dashlet:seed'];

    protected $description = 'Seed the Chart gallery dashboard (one dashlet per chart type on Contact Us form data)';

    public function handle(): int
    {
        $seeder = new SampleDashboardsSeeder;
        $user = $seeder->resolveUser();

        if ($this->option('fresh')) {
            CustomDashboard::query()
                ->where('user_id', $user->getKey())
                ->whereIn('name', array_merge(
                    SampleDashboardsSeeder::DEMO_DASHBOARD_NAMES,
                    SampleDashboardsSeeder::LEGACY_DASHBOARD_NAMES,
                    ['AI spend']
                ))
                ->each(fn (CustomDashboard $dashboard): mixed => $dashboard->delete());

            $this->warn('Removed existing demo dashboards.');
        }

        $seeder->run($user);
        $label = $user->name ?? $user->email ?? 'user';
        $dashboard = CustomDashboard::query()
            ->where('user_id', $user->getKey())
            ->where('name', SampleDashboardsSeeder::CHART_GALLERY_NAME)
            ->first();

        if ($dashboard === null) {
            $this->error('Chart gallery was not created. Form submissions must be available and at least one form must exist.');

            return self::FAILURE;
        }

        $this->info("Chart gallery seeded for user #{$user->getKey()} ({$label}).");
        $this->line('  • '.SampleDashboardsSeeder::CHART_GALLERY_NAME.' — '.$dashboard->dashlets()->count().' chart type(s)');

        return self::SUCCESS;
    }
}
