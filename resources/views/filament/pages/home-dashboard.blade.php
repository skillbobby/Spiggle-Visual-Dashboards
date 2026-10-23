<x-filament-panels::page>
    <div
        class="dashlet-overview-page"
        x-data
        x-on:open-connect-service.window="$wire.mountAction('connectService')"
    >
        @if ($dashboardId)
            @livewire('visual-dashboards.dashboard-grid', [
                'dashboardId' => $dashboardId,
                'presentationMode' => true,
            ], key('home-dashlet-grid-'.$dashboardId))
        @endif
    </div>
</x-filament-panels::page>
