<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_reviews', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('location');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->boolean('is_claimed')->default(false)->after('lng');
            $table->boolean('is_public_on_map')->default(true)->after('is_claimed');
            $table->json('ai_features')->nullable()->after('ai_kpis');
            $table->json('ai_ideal_steps')->nullable()->after('ai_features');
            $table->text('ai_full_summary_ar')->nullable()->after('ai_summary_ar');
        });
    }

    public function down(): void
    {
        Schema::table('project_reviews', function (Blueprint $table) {
            $table->dropColumn([
                'lat',
                'lng',
                'is_claimed',
                'is_public_on_map',
                'ai_features',
                'ai_ideal_steps',
                'ai_full_summary_ar',
            ]);
        });
    }
};
