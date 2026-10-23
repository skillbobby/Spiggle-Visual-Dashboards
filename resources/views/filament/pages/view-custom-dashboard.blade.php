<x-filament-panels::page>
    @push('styles')
        @if (class_exists(\Filament\Support\Facades\FilamentAsset::class))
            <link rel="stylesheet" href="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('dashlet-grid') }}" data-navigate-track />
            <link rel="stylesheet" href="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('visual-dashboard') }}" data-navigate-track />
        @endif
    @endpush

    @livewire('visual-dashboards.visual-dashboard-canvas', ['dashboardId' => $this->getRecord()->getKey()], key('visual-dashboard-'.$this->getRecord()->getKey()))
</x-filament-panels::page>
