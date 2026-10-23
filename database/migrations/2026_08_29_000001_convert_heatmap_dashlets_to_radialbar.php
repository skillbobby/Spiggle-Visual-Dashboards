<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashlets')) {
            return;
        }

        DB::table('dashlets')
            ->where('type', 'heatmap')
            ->update(['type' => 'radialbar']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashlets')) {
            return;
        }

        DB::table('dashlets')
            ->where('type', 'radialbar')
            ->update(['type' => 'heatmap']);
    }
};
