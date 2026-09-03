<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_pillar_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('pillar_id')->constrained('pillars')->cascadeOnDelete();
            $table->decimal('raw_score', 5, 2);
            $table->decimal('max_score', 5, 2)->default(15.00);
            $table->decimal('percentage', 5, 2);
            $table->boolean('is_weak')->default(false);
            $table->timestamps();
            $table->unique(['assessment_id', 'pillar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_pillar_results');
    }
};
