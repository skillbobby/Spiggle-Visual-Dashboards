<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('visual-dashboards.tables.dashlets', 'dashlets'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_dashboard_id')
                ->constrained(config('visual-dashboards.tables.dashboards', 'custom_dashboards'))
                ->cascadeOnDelete();
            $table->string('type');
            $table->integer('grid_x')->default(0);
            $table->integer('grid_y')->default(0);
            $table->integer('grid_w')->default(4);
            $table->integer('grid_h')->default(4);
            $table->json('config');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('visual-dashboards.tables.dashlets', 'dashlets'));
    }
};
