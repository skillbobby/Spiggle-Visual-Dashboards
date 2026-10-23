<?php

namespace Spiggle\VisualDashboards\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;
use Spiggle\VisualDashboards\Models\CustomDashboard;

class DashletAccess
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function allows(?Authenticatable $user, array $config, ?CustomDashboard $dashboard = null): bool
    {
        $roles = self::stringList($config['access_roles'] ?? []);
        $permissions = self::stringList($config['access_permissions'] ?? []);

        if ($roles === [] && $permissions === []) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($dashboard && (int) $dashboard->user_id === (int) $user->getAuthIdentifier()) {
            return true;
        }

        $roleOk = $roles === [] || (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles));
        $permissionOk = $permissions === [] || (method_exists($user, 'hasAnyPermission') && $user->hasAnyPermission($permissions));

        return $roleOk && $permissionOk;
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        $configured = self::configuredMap('dashlets.access.roles');

        if (class_exists(\Spatie\Permission\Models\Role::class) && self::hasTable(config('permission.table_names.roles', 'roles'))) {
            $roles = \Spatie\Permission\Models\Role::query()
                ->orderBy('name')
                ->pluck('name', 'name')
                ->all();

            return array_merge($configured, $roles);
        }

        return $configured;
    }

    /**
     * @return array<string, string>
     */
    public static function permissionOptions(): array
    {
        $configured = self::configuredMap('dashlets.access.permissions');

        if (class_exists(\Spatie\Permission\Models\Permission::class) && self::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            $permissions = \Spatie\Permission\Models\Permission::query()
                ->orderBy('name')
                ->pluck('name', 'name')
                ->all();

            return array_merge($configured, $permissions);
        }

        return $configured;
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    protected static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $value
        )));
    }

    /**
     * @return array<string, string>
     */
    protected static function configuredMap(string $key): array
    {
        $items = config($key, []);

        if (! is_array($items)) {
            return [];
        }

        $options = [];

        foreach ($items as $name => $label) {
            $value = is_int($name) ? (string) $label : (string) $name;
            $options[$value] = is_int($name) ? (string) $label : (string) $label;
        }

        return $options;
    }

    protected static function hasTable(mixed $table): bool
    {
        return is_string($table) && $table !== '' && Schema::hasTable($table);
    }
}
