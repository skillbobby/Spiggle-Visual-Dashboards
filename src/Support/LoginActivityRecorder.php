<?php

namespace Spiggle\VisualDashboards\Support;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoginActivityRecorder
{
    public function handle(Login $event): void
    {
        $table = (string) config('visual-dashboards.tables.login_events', 'dashlet_login_events');

        if (! Schema::hasTable($table)) {
            return;
        }

        $user = $event->user;
        $role = 'User';

        if (is_object($user) && method_exists($user, 'getRoleNames')) {
            $role = (string) ($user->getRoleNames()->first() ?: 'User');
        }

        DB::table($table)->insert([
            'user_id' => is_object($user) ? $user->getAuthIdentifier() : null,
            'role_name' => $role,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
