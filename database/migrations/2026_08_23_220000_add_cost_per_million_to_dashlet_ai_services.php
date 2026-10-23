<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashlet_ai_services') || Schema::hasColumn('dashlet_ai_services', 'cost_per_million')) {
            return;
        }

        Schema::table('dashlet_ai_services', function (Blueprint $table): void {
            $table->decimal('cost_per_million', 12, 2)->nullable()->after('color');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashlet_ai_services') || ! Schema::hasColumn('dashlet_ai_services', 'cost_per_million')) {
            return;
        }

        Schema::table('dashlet_ai_services', function (Blueprint $table): void {
            $table->dropColumn('cost_per_million');
        });
    }
};
