<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_plan_id')->constrained('action_plans')->cascadeOnDelete();
            $table->foreignId('pillar_id')->constrained('pillars');
            $table->enum('phase', ['immediate', 'medium', 'long']);
            $table->string('phase_label_ar');
            $table->text('action_ar');
            $table->text('ai_rephrased_ar')->nullable();
            $table->text('kpi_ar')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_plan_items');
    }
};
