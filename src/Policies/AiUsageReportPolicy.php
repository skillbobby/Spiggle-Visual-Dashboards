<?php

declare(strict_types=1);

namespace Spiggle\VisualDashboards\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Spiggle\VisualDashboards\Models\AiUsageReport;
use Spiggle\VisualDashboards\Support\PanelPermission;

class AiUsageReportPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'ViewAny:AiUsageReport');
    }

    public function view(AuthUser $authUser, AiUsageReport $aiUsageReport): bool
    {
        return PanelPermission::allows($authUser, 'View:AiUsageReport');
    }

    public function create(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'Create:AiUsageReport');
    }

    public function update(AuthUser $authUser, AiUsageReport $aiUsageReport): bool
    {
        return PanelPermission::allows($authUser, 'Update:AiUsageReport');
    }

    public function delete(AuthUser $authUser, AiUsageReport $aiUsageReport): bool
    {
        return PanelPermission::allows($authUser, 'Delete:AiUsageReport');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'DeleteAny:AiUsageReport');
    }

    public function restore(AuthUser $authUser, AiUsageReport $aiUsageReport): bool
    {
        return PanelPermission::allows($authUser, 'Restore:AiUsageReport');
    }

    public function forceDelete(AuthUser $authUser, AiUsageReport $aiUsageReport): bool
    {
        return PanelPermission::allows($authUser, 'ForceDelete:AiUsageReport');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'ForceDeleteAny:AiUsageReport');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'RestoreAny:AiUsageReport');
    }

    public function replicate(AuthUser $authUser, AiUsageReport $aiUsageReport): bool
    {
        return PanelPermission::allows($authUser, 'Replicate:AiUsageReport');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'Reorder:AiUsageReport');
    }
}
