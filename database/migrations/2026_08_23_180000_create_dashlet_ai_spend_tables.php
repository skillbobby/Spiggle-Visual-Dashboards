<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashlet_ai_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('vendor_key', 40);
            $table->string('color', 16)->default('#111827');
            $table->string('initials', 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('dashlet_ai_usage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('dashlet_ai_services')->cascadeOnDelete();
            $table->date('reported_on');
            $table->unsignedBigInteger('tokens')->default(0);
            $table->decimal('spend_usd', 12, 2)->default(0);
            $table->unsignedBigInteger('form_submission_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['service_id', 'reported_on']);
        });

        Schema::create('dashlet_ai_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('dashlet_ai_services')->cascadeOnDelete();
            $table->date('detected_on');
            $table->decimal('amount_usd', 12, 2)->default(0);
            $table->string('title');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('form_submission_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('dashlet_ai_insights', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32)->default('switch');
            $table->string('title');
            $table->text('body');
            $table->string('action_label')->nullable();
            $table->string('savings_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('form_submission_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashlet_ai_insights');
        Schema::dropIfExists('dashlet_ai_anomalies');
        Schema::dropIfExists('dashlet_ai_usage_reports');
        Schema::dropIfExists('dashlet_ai_services');
    }
};
