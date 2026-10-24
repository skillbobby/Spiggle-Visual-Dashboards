<?php

namespace Spiggle\VisualDashboards\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Models\Dashlet;

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
     * @return array<string, mixed>
     */
    public static function restrictedPayload(Dashlet $dashlet): array
    {
        $config = $dashlet->normalizedConfig();
        $heading = (string) ($config['heading'] ?: $config['title'] ?: 'Visual');
        $roles = self::stringList($config['access_roles'] ?? []);
        $permissions = self::stringList($config['access_permissions'] ?? []);
        $limits = [];

        if ($roles !== []) {
            $limits[] = implode(', ', $roles);
        }

        if ($permissions !== []) {
            $limits[] = implode(', ', $permissions);
        }

        $who = $limits === [] ? 'selected roles' : implode(' and ', $limits);

        return [
            'id' => $dashlet->id,
            'type' => $dashlet->type,
            'title' => $heading,
            'heading' => $heading,
            'subheading' => '',
            'restricted' => true,
            'empty_message' => 'This visual stays on the dashboard. Your role cannot see its data. It is limited to '.$who.'.',
            'visual' => [
                'border' => true,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        $configured = self::configuredMap('dashlets.access.roles');

        if (class_exists(Role::class) && self::hasTable(config('permission.table_names.roles', 'roles'))) {
            $roles = Role::query()
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

        if (class_exists(Permission::class) && self::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            $permissions = Permission::query()
                ->orderBy('name')
                ->pluck('name', 'name')
                ->all();

            return array_merge($configured, $permissions);
        }

        return $configured;
    }

    /**
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
