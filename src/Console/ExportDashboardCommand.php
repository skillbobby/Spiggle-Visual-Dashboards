<?php

namespace Spiggle\VisualDashboards\Console;

use Illuminate\Console\Command;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Services\DashboardExporter;

class ExportDashboardCommand extends Command
{
    protected $signature = 'visual-dashboards:export {dashboard : Dashboard ID or name} {--path= : Write JSON to this file path}';

    protected $aliases = ['dashlet:export'];

    protected $description = 'Export a dashboard and its dashlets as JSON';

    public function handle(DashboardExporter $exporter): int
    {
        $dashboard = $this->resolveDashboard((string) $this->argument('dashboard'));

        if (! $dashboard) {
            $this->error('Dashboard not found.');

            return self::FAILURE;
        }

        $json = $exporter->toJson($dashboard);
        $path = (string) ($this->option('path') ?: storage_path('app/dashlet-export-'.$dashboard->id.'.json'));

        file_put_contents($path, $json);

        $this->info("Exported [{$dashboard->name}] to {$path}");

        return self::SUCCESS;
    }

    protected function resolveDashboard(string $reference): ?CustomDashboard
    {
        if (is_numeric($reference)) {
            return CustomDashboard::query()->find((int) $reference);
        }

        return CustomDashboard::query()->where('name', $reference)->first();
    }
}
