<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('visual-dashboards.tables.dashlets', 'dashlets');

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'grid_z')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->integer('grid_z')->default(0)->after('grid_h');
        });
    }

    public function down(): void
    {
        $tableName = config('visual-dashboards.tables.dashlets', 'dashlets');

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'grid_z')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('grid_z');
        });
    }
};
