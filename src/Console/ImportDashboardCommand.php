<?php

namespace Spiggle\VisualDashboards\Console;

use Illuminate\Console\Command;
use Spiggle\VisualDashboards\Services\DashboardImporter;

class ImportDashboardCommand extends Command
{
    protected $signature = 'visual-dashboards:import {path : Path to dashboard JSON} {--user= : Owner user ID} {--replace : Replace an existing dashboard with the same name} {--name= : Override imported dashboard name}';

    protected $aliases = ['dashlet:import'];

    protected $description = 'Import a dashboard and dashlets from JSON';

    public function handle(DashboardImporter $importer): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $userId = (int) ($this->option('user') ?: 1);
        $payload = $importer->decodeJson((string) file_get_contents($path));
        $dashboard = $importer->import(
            $payload,
            $userId,
            (bool) $this->option('replace'),
            $this->option('name') ? (string) $this->option('name') : null,
        );

        $this->info("Imported [{$dashboard->name}] with {$dashboard->dashlets()->count()} dashlet(s).");

        return self::SUCCESS;
    }
}
