<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_reviews', function (Blueprint $table) {
            $table->boolean('ai_is_fallback')->default(false)->after('ai_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('project_reviews', function (Blueprint $table) {
            $table->dropColumn('ai_is_fallback');
        });
    }
};
