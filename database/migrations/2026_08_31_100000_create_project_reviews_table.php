<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('project_name');
            $table->string('stage')->default('idea');
            $table->string('location')->nullable();
            $table->unsignedInteger('team_size')->nullable();
            $table->decimal('annual_budget', 12, 2)->nullable();
            $table->text('mission');
            $table->text('problem');
            $table->text('beneficiaries');
            $table->text('activities');
            $table->text('impact')->nullable();
            $table->text('sustainability')->nullable();
            $table->unsignedTinyInteger('ai_score')->nullable();
            $table->string('ai_level')->nullable();
            $table->text('ai_summary_ar')->nullable();
            $table->json('ai_strengths')->nullable();
            $table->json('ai_risks')->nullable();
            $table->json('ai_recommendations')->nullable();
            $table->json('ai_kpis')->nullable();
            $table->timestamp('ai_generated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_reviews');
    }
};