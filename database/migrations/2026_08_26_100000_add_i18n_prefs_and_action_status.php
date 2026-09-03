<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pillars', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name_ar');
            $table->text('description_en')->nullable()->after('description_ar');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->text('text_en')->nullable()->after('text_ar');
        });

        Schema::table('action_plan_items', function (Blueprint $table) {
            $table->string('phase_label_en')->nullable()->after('phase_label_ar');
            $table->text('action_en')->nullable()->after('action_ar');
            $table->text('kpi_en')->nullable()->after('kpi_ar');
            $table->text('ai_rephrased_en')->nullable()->after('ai_rephrased_ar');
            $table->string('status', 32)->default('not_started')->after('kpi_en');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('ar')->after('organization_name');
            $table->string('theme', 16)->default('system')->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('pillars', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'description_en']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('text_en');
        });

        Schema::table('action_plan_items', function (Blueprint $table) {
            $table->dropColumn([
                'phase_label_en',
                'action_en',
                'kpi_en',
                'ai_rephrased_en',
                'status',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locale', 'theme']);
        });
    }
};
