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
            if (! Schema::hasColumn($tableName, 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }

            if (! Schema::hasColumn($tableName, 'heading')) {
                $table->string('heading')->nullable()->after('slug');
            }

            if (! Schema::hasColumn($tableName, 'description')) {
                $table->text('description')->nullable()->after('heading');
            }

            if (! Schema::hasColumn($tableName, 'is_published')) {
                $table->boolean('is_published')->default(false)->after('is_default');
            }

            if (! Schema::hasColumn($tableName, 'layout_json')) {
                $table->json('layout_json')->nullable()->after('is_published');
            }
        });

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (Schema::hasColumn($tableName, 'slug')) {
                $table->unique(['user_id', 'slug'], 'custom_dashboards_user_slug_unique');
            }
        });
    }

    public function down(): void
    {
        $tableName = config('visual-dashboards.tables.dashboards', 'custom_dashboards');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropUnique('custom_dashboards_user_slug_unique');
            $table->dropColumn(['slug', 'heading', 'description', 'is_published', 'layout_json']);
        });
    }
};
