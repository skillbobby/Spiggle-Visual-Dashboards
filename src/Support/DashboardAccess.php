<?php

namespace Spiggle\VisualDashboards\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Spiggle\VisualDashboards\Models\CustomDashboard;

class DashboardAccess
{
    public static function isPubliclyViewable(CustomDashboard $dashboard): bool
    {
        return (bool) $dashboard->is_published && filled($dashboard->slug);
    }

    /**
     * Whether the share URL may be opened for this viewer.
     *
     * - Public link on → anyone (including guests)
     * - Public link off → authenticated users who pass canViewInApp()
     */
    public static function canAccessShareUrl(?Authenticatable $user, CustomDashboard $dashboard): bool
    {
        if (! filled($dashboard->slug)) {
            return false;
        }

        if (self::isPubliclyViewable($dashboard)) {
            return true;
        }

        return self::canViewInApp($user, $dashboard);
    }

    /**
     * Users who can manage (edit) the dashboard: owners, or anyone with Update permission.
     */
    public static function canManage(?Authenticatable $user, CustomDashboard $dashboard): bool
    {
        if ($user === null) {
            return false;
        }

        if ((int) $dashboard->user_id === (int) $user->getAuthIdentifier()) {
            return PanelPermission::allows($user, 'Update:CustomDashboard')
                || PanelPermission::allows($user, 'View:CustomDashboard');
        }

        return PanelPermission::allows($user, 'Update:CustomDashboard');
    }

    public static function canViewInApp(?Authenticatable $user, CustomDashboard $dashboard): bool
    {
        if ($user === null) {
            return false;
        }

        $canUpdate = PanelPermission::allows($user, 'Update:CustomDashboard');
        $canView = PanelPermission::allows($user, 'View:CustomDashboard');

        if (! $canUpdate && ! $canView) {
            return false;
        }

        // Anyone who can manage dashboards bypasses the role allowlist.
        if ($canUpdate) {
            return true;
        }

        // Owners with view access always keep in-app access to their own dashboards.
        if ((int) $dashboard->user_id === (int) $user->getAuthIdentifier()) {
            return true;
        }

        $allowedRoles = $dashboard->viewerRoles();

        if ($allowedRoles === []) {
            return true;
        }

        return method_exists($user, 'hasAnyRole') && $user->hasAnyRole($allowedRoles);
    }

    /**
     * Scope list queries for authenticated panel users.
     *
     * Users with Update see all. Others see their own, unrestricted dashboards,
     * and those whose viewer_roles include at least one of their roles.
     *
     * @param  Builder<CustomDashboard>  $query
     * @return Builder<CustomDashboard>
     */
    public static function scopeVisibleTo(Builder $query, ?Authenticatable $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('0 = 1');
        }

        if (PanelPermission::allows($user, 'Update:CustomDashboard')) {
            return $query;
        }

        $roleNames = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->filter()->values()->all()
            : [];

        return $query->where(function (Builder $builder) use ($user, $roleNames): void {
            $builder
                ->where('user_id', $user->getAuthIdentifier())
                ->orWhereNull('viewer_roles')
                ->orWhere('viewer_roles', '[]')
                ->orWhere('viewer_roles', 'null');

            foreach ($roleNames as $roleName) {
                $builder->orWhereJsonContains('viewer_roles', (string) $roleName);
            }
        });
    }
}
