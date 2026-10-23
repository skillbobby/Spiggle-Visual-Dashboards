<?php

namespace Spiggle\VisualDashboards\Support;

use BezhanSalleh\FilamentShield\FilamentShield;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\PermissionRegistrar;

class PanelPermission
{
    /**
     * Shield and Spatie permissions apply when those packages are installed.
     * A panel user is allowed when neither package is present, so the canvas
     * works on a fresh Filament app.
     */
    public static function allows(?Authenticatable $user, string $permission): bool
    {
        if ($user === null || ! method_exists($user, 'can')) {
            return false;
        }

        if (! self::enforced()) {
            return true;
        }

        return $user->can($permission);
    }

    public static function enforced(): bool
    {
        return class_exists(PermissionRegistrar::class)
            || class_exists(FilamentShield::class);
    }
}
