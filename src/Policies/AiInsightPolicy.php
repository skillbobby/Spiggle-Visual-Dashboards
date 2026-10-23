<?php

declare(strict_types=1);

namespace Spiggle\VisualDashboards\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Spiggle\VisualDashboards\Models\AiInsight;
use Spiggle\VisualDashboards\Support\PanelPermission;

class AiInsightPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'ViewAny:AiInsight');
    }

    public function view(AuthUser $authUser, AiInsight $aiInsight): bool
    {
        return PanelPermission::allows($authUser, 'View:AiInsight');
    }

    public function create(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'Create:AiInsight');
    }

    public function update(AuthUser $authUser, AiInsight $aiInsight): bool
    {
        return PanelPermission::allows($authUser, 'Update:AiInsight');
    }

    public function delete(AuthUser $authUser, AiInsight $aiInsight): bool
    {
        return PanelPermission::allows($authUser, 'Delete:AiInsight');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'DeleteAny:AiInsight');
    }

    public function restore(AuthUser $authUser, AiInsight $aiInsight): bool
    {
        return PanelPermission::allows($authUser, 'Restore:AiInsight');
    }

    public function forceDelete(AuthUser $authUser, AiInsight $aiInsight): bool
    {
        return PanelPermission::allows($authUser, 'ForceDelete:AiInsight');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'ForceDeleteAny:AiInsight');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'RestoreAny:AiInsight');
    }

    public function replicate(AuthUser $authUser, AiInsight $aiInsight): bool
    {
        return PanelPermission::allows($authUser, 'Replicate:AiInsight');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'Reorder:AiInsight');
    }
}
