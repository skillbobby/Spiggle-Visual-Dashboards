<?php

declare(strict_types=1);

namespace Spiggle\VisualDashboards\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Spiggle\VisualDashboards\Models\AiAnomaly;
use Spiggle\VisualDashboards\Support\PanelPermission;

class AiAnomalyPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'ViewAny:AiAnomaly');
    }

    public function view(AuthUser $authUser, AiAnomaly $aiAnomaly): bool
    {
        return PanelPermission::allows($authUser, 'View:AiAnomaly');
    }

    public function create(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'Create:AiAnomaly');
    }

    public function update(AuthUser $authUser, AiAnomaly $aiAnomaly): bool
    {
        return PanelPermission::allows($authUser, 'Update:AiAnomaly');
    }

    public function delete(AuthUser $authUser, AiAnomaly $aiAnomaly): bool
    {
        return PanelPermission::allows($authUser, 'Delete:AiAnomaly');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'DeleteAny:AiAnomaly');
    }

    public function restore(AuthUser $authUser, AiAnomaly $aiAnomaly): bool
    {
        return PanelPermission::allows($authUser, 'Restore:AiAnomaly');
    }

    public function forceDelete(AuthUser $authUser, AiAnomaly $aiAnomaly): bool
    {
        return PanelPermission::allows($authUser, 'ForceDelete:AiAnomaly');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'ForceDeleteAny:AiAnomaly');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'RestoreAny:AiAnomaly');
    }

    public function replicate(AuthUser $authUser, AiAnomaly $aiAnomaly): bool
    {
        return PanelPermission::allows($authUser, 'Replicate:AiAnomaly');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'Reorder:AiAnomaly');
    }
}
