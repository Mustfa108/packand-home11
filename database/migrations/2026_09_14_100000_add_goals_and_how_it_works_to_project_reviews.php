<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_reviews', function (Blueprint $table) {
            $table->json('ai_goals')->nullable()->after('ai_features');
            $table->json('ai_how_it_works')->nullable()->after('ai_goals');
        });
    }

    public function down(): void
    {
        Schema::table('project_reviews', function (Blueprint $table) {
            $table->dropColumn(['ai_goals', 'ai_how_it_works']);
        });
    }
};
