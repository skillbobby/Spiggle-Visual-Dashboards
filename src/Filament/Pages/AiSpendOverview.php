<?php

namespace Spiggle\VisualDashboards\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Spiggle\VisualDashboards\Database\Seeders\AiSpendOverviewDashboardSeeder;
use Spiggle\VisualDashboards\Database\Seeders\AiSpendSeeder;
use Spiggle\VisualDashboards\Filament\Support\AiSpendFormSchema;
use Spiggle\VisualDashboards\Filament\VisualDashboardsCluster;
use Spiggle\VisualDashboards\Models\AiService;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Support\AiSpendDashboard;
use UnitEnum;

class AiSpendOverview extends Page
{
    protected static ?string $cluster = VisualDashboardsCluster::class;

    protected static ?string $slug = 'overview';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected string $view = 'visual-dashboards::filament.pages.home-dashboard';

    public ?int $dashboardId = null;

    public static function getNavigationLabel(): string
    {
        return 'Overview';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('visual-dashboards.navigation.group', 'Dashboards');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Overview';
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    /**
     * @return array<int, mixed>
     */
    public function getWidgets(): array
    {
        return [];
    }

    /**
     * @return array<int, mixed>
     */
    public function getHeaderWidgets(): array
    {
        return [];
    }

    public function mount(): void
    {
        $this->dashboardId = $this->resolveDashboard()->getKey();
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        // Connect lives in the hero dashlet on the presentation board.
        return [];
    }

    public function connectServiceAction(): Action
    {
        return Action::make('connectService')
            ->label('+ Connect Service')
            ->color('gray')
            ->modalHeading('Connect service')
            ->modalWidth(Width::Large)
            ->schema(AiSpendFormSchema::serviceFields())
            ->action(function (array $data): void {
                $data['slug'] = $data['slug'] ?? Str::slug((string) ($data['name'] ?? 'service'));
                $data['is_active'] = $data['is_active'] ?? true;
                AiService::query()->create($data);

                Notification::make()
                    ->title('Service connected')
                    ->success()
                    ->send();

                $this->dispatch('dashlet-grid-refresh');
            });
    }

    protected function resolveDashboard(): CustomDashboard
    {
        $user = auth()->user();

        if (AiSpendDashboard::tablesReady() && AiService::query()->doesntExist()) {
            (new AiSpendSeeder)->run();
        }

        $seeder = new AiSpendOverviewDashboardSeeder;
        $dashboard = CustomDashboard::query()
            ->where('user_id', $user?->getKey())
            ->where('name', AiSpendOverviewDashboardSeeder::DASHBOARD_NAME)
            ->first();

        if (! $dashboard) {
            $dashboard = $seeder->run($user);
        }

        $usesAiSpend = $dashboard->dashlets()
            ->where('config->data_source', 'ai_spend')
            ->exists();

        if (! $usesAiSpend) {
            $dashboard = $seeder->run($user);
        }

        return $dashboard;
    }
}
