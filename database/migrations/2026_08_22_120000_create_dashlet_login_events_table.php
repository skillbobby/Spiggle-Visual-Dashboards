<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('visual-dashboards.tables.login_events', 'dashlet_login_events');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $blueprint->string('role_name', 80)->default('User');
            $blueprint->timestamp('occurred_at')->index();
            $blueprint->timestamps();

            $blueprint->index(['role_name', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('visual-dashboards.tables.login_events', 'dashlet_login_events'));
    }
};
