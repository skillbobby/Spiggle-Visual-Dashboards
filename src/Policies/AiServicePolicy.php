<?php

declare(strict_types=1);

namespace Spiggle\VisualDashboards\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Spiggle\VisualDashboards\Models\AiService;
use Spiggle\VisualDashboards\Support\PanelPermission;

class AiServicePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'ViewAny:AiService');
    }

    public function view(AuthUser $authUser, AiService $aiService): bool
    {
        return PanelPermission::allows($authUser, 'View:AiService');
    }

    public function create(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'Create:AiService');
    }

    public function update(AuthUser $authUser, AiService $aiService): bool
    {
        return PanelPermission::allows($authUser, 'Update:AiService');
    }

    public function delete(AuthUser $authUser, AiService $aiService): bool
    {
        return PanelPermission::allows($authUser, 'Delete:AiService');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'DeleteAny:AiService');
    }

    public function restore(AuthUser $authUser, AiService $aiService): bool
    {
        return PanelPermission::allows($authUser, 'Restore:AiService');
    }

    public function forceDelete(AuthUser $authUser, AiService $aiService): bool
    {
        return PanelPermission::allows($authUser, 'ForceDelete:AiService');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'ForceDeleteAny:AiService');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'RestoreAny:AiService');
    }

    public function replicate(AuthUser $authUser, AiService $aiService): bool
    {
        return PanelPermission::allows($authUser, 'Replicate:AiService');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return PanelPermission::allows($authUser, 'Reorder:AiService');
    }
}
