<?php

namespace Spiggle\VisualDashboards\Http\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Support\DashboardAccess;

class PublicDashboardController
{
    public function __invoke(int $dashboard, ?string $slug = null): View|RedirectResponse
    {
        $record = CustomDashboard::query()->whereKey($dashboard)->firstOrFail();

        if (blank($record->slug)) {
            abort(404);
        }

        $user = auth()->user();

        if (! DashboardAccess::isPubliclyViewable($record)) {
            if ($user === null) {
                throw new AuthenticationException(
                    'Unauthenticated.',
                    [],
                    route('filament.admin.auth.login'),
                );
            }

            if (! DashboardAccess::canViewInApp($user, $record)) {
                abort(403);
            }
        }

        $shareUrl = $record->shareUrl();

        if ($shareUrl && $record->slug !== $slug) {
            return redirect()->to($shareUrl, 301);
        }

        return view('visual-dashboards::public.show', [
            'dashboard' => $record,
        ]);
    }
}
