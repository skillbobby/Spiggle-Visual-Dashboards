<?php

declare(strict_types=1);

namespace Spiggle\VisualDashboards\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Support\DashboardAccess;
use Spiggle\VisualDashboards\Support\PanelPermission;

class CustomDashboardPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'ViewAny:CustomDashboard');
    }

    public function view(AuthUser $authUser, CustomDashboard $customDashboard): bool
    {
        return DashboardAccess::canViewInApp($authUser, $customDashboard);
    }

    public function create(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'Create:CustomDashboard');
    }

    public function update(AuthUser $authUser, CustomDashboard $customDashboard): bool
    {
        return PanelPermission::allows($authUser, 'Update:CustomDashboard');
    }

    public function delete(AuthUser $authUser, CustomDashboard $customDashboard): bool
    {
        return PanelPermission::allows($authUser, 'Delete:CustomDashboard');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'DeleteAny:CustomDashboard');
    }

    public function restore(AuthUser $authUser, CustomDashboard $customDashboard): bool
    {
        return PanelPermission::allows($authUser, 'Restore:CustomDashboard');
    }

    public function forceDelete(AuthUser $authUser, CustomDashboard $customDashboard): bool
    {
        return PanelPermission::allows($authUser, 'ForceDelete:CustomDashboard');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'ForceDeleteAny:CustomDashboard');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'RestoreAny:CustomDashboard');
    }

    public function replicate(AuthUser $authUser, CustomDashboard $customDashboard): bool
    {
        return PanelPermission::allows($authUser, 'Replicate:CustomDashboard');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'Reorder:CustomDashboard');
    }
}
