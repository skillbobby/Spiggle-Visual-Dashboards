<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $dashboard->displayHeading() }}</title>
    @livewireStyles
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1"></script>
    @php
        $cssRoot = dirname((new ReflectionClass(\Spiggle\VisualDashboards\VisualDashboardsServiceProvider::class))->getFileName(), 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'css';
    @endphp
    @if (is_file($cssRoot.DIRECTORY_SEPARATOR.'dashlet-grid.css'))
        <style>{!! file_get_contents($cssRoot.DIRECTORY_SEPARATOR.'dashlet-grid.css') !!}</style>
    @endif
    @if (is_file($cssRoot.DIRECTORY_SEPARATOR.'visual-dashboard.css'))
        <style>{!! file_get_contents($cssRoot.DIRECTORY_SEPARATOR.'visual-dashboard.css') !!}</style>
    @endif
</head>
<body class="vd-public-page">
    <header class="vd-public-header">
        <div>
            <h1>{{ $dashboard->displayHeading() }}</h1>
            @if (filled($dashboard->description))
                <p>{{ $dashboard->description }}</p>
            @endif
        </div>
    </header>
    <main class="vd-public-body">
        @livewire('visual-dashboards.public-dashboard', ['dashboardId' => $dashboard->getKey()], key('public-dashboard-'.$dashboard->getKey()))
    </main>
    @livewireScripts
</body>
</html>
