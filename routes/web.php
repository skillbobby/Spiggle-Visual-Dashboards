<?php

use Illuminate\Support\Facades\Route;
use Spiggle\VisualDashboards\Http\Controllers\PublicDashboardController;

$path = trim((string) config('visual-dashboards.public.path', 'visual-dashboards'), '/');

Route::get($path.'/{dashboard}/{slug?}', PublicDashboardController::class)
    ->whereNumber('dashboard')
    ->name('visual-dashboards.public.show');
