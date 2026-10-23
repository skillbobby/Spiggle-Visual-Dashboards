<?php

namespace Spiggle\VisualDashboards;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spiggle\FormBuilder\Events\FormSubmitted;
use Spiggle\VisualDashboards\Console\ExportDashboardCommand;
use Spiggle\VisualDashboards\Console\ImportDashboardCommand;
use Spiggle\VisualDashboards\Console\SeedChartGalleryCommand;
use Spiggle\VisualDashboards\Console\SeedSampleDashboardsCommand;
use Spiggle\VisualDashboards\Console\VerifyDashletCommand;
use Spiggle\VisualDashboards\Livewire\DashboardGrid;
use Spiggle\VisualDashboards\Livewire\PublicDashboardView;
use Spiggle\VisualDashboards\Livewire\VisualDashboardCanvas;
use Spiggle\VisualDashboards\Models\AiAnomaly;
use Spiggle\VisualDashboards\Models\AiInsight;
use Spiggle\VisualDashboards\Models\AiService;
use Spiggle\VisualDashboards\Models\AiUsageReport;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Policies\AiAnomalyPolicy;
use Spiggle\VisualDashboards\Policies\AiInsightPolicy;
use Spiggle\VisualDashboards\Policies\AiServicePolicy;
use Spiggle\VisualDashboards\Policies\AiUsageReportPolicy;
use Spiggle\VisualDashboards\Policies\CustomDashboardPolicy;
use Spiggle\VisualDashboards\Services\DashboardExporter;
use Spiggle\VisualDashboards\Services\DashboardImporter;
use Spiggle\VisualDashboards\Services\DashletRegistry;
use Spiggle\VisualDashboards\Services\DataEngine;
use Spiggle\VisualDashboards\Services\LayoutManager;
use Spiggle\VisualDashboards\Services\RelatedSourceDiscoverer;
use Spiggle\VisualDashboards\Services\VisualDashboardDataSourceRegistry;
use Spiggle\VisualDashboards\Support\AiSpendAggregator;
use Spiggle\VisualDashboards\Support\AiSpendFormSync;
use Spiggle\VisualDashboards\Support\LoginActivityRecorder;

class VisualDashboardsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/visual-dashboards.php', 'visual-dashboards');

        $this->app->singleton(DashletRegistry::class);
        $this->app->singleton(DataEngine::class);
        $this->app->singleton(LayoutManager::class);
        $this->app->singleton(DashboardExporter::class);
        $this->app->singleton(DashboardImporter::class);
        $this->app->singleton(AiSpendAggregator::class);
        $this->app->singleton(AiSpendFormSync::class);
        $this->app->singleton(VisualDashboardDataSourceRegistry::class);
        $this->app->singleton(RelatedSourceDiscoverer::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/visual-dashboards.php' => config_path('visual-dashboards.php'),
        ], 'visual-dashboards-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'visual-dashboards-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/visual-dashboards'),
        ], 'visual-dashboards-views');

        $this->publishes([
            __DIR__.'/../resources/css' => public_path('vendor/spiggle-visual-dashboards/css'),
        ], 'visual-dashboards-assets');

        Gate::policy(CustomDashboard::class, CustomDashboardPolicy::class);
        Gate::policy(AiService::class, AiServicePolicy::class);
        Gate::policy(AiUsageReport::class, AiUsageReportPolicy::class);
        Gate::policy(AiAnomaly::class, AiAnomalyPolicy::class);
        Gate::policy(AiInsight::class, AiInsightPolicy::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'visual-dashboards');

        Route::middleware('web')->group(__DIR__.'/../routes/web.php');

        if (class_exists(FilamentAsset::class)) {
            FilamentAsset::register([
                Css::make('dashlet-grid', __DIR__.'/../resources/css/dashlet-grid.css'),
                Css::make('visual-dashboard', __DIR__.'/../resources/css/visual-dashboard.css'),
                Css::make('dashlet-ai-spend', __DIR__.'/../resources/css/ai-spend.css'),
            ]);
        }

        if (class_exists(Livewire::class)) {
            Livewire::component('visual-dashboards.dashboard-grid', DashboardGrid::class);
            Livewire::component('visual-dashboards.visual-dashboard-canvas', VisualDashboardCanvas::class);
            Livewire::component('visual-dashboards.public-dashboard', PublicDashboardView::class);
        }

        Event::listen(Login::class, LoginActivityRecorder::class);

        if (class_exists(FormSubmitted::class)) {
            Event::listen(
                FormSubmitted::class,
                function (FormSubmitted $event): void {
                    app(AiSpendFormSync::class)->handleSubmitted($event);
                }
            );
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedSampleDashboardsCommand::class,
                SeedChartGalleryCommand::class,
                VerifyDashletCommand::class,
                ExportDashboardCommand::class,
                ImportDashboardCommand::class,
            ]);
        }
    }
}
