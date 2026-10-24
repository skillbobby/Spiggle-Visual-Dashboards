<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('visual-dashboards.tables.dashboards', 'custom_dashboards');

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'viewer_roles')) {
                $table->json('viewer_roles')->nullable()->after('is_published');
            }
        });
    }

    public function down(): void
    {
        $tableName = config('visual-dashboards.tables.dashboards', 'custom_dashboards');

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (Schema::hasColumn($tableName, 'viewer_roles')) {
                $table->dropColumn('viewer_roles');
            }
        });
    }
};
