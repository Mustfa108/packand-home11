<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('assessment_version_id')
                ->nullable()
                ->after('user_id')
                ->constrained('assessment_versions')
                ->nullOnDelete();
            $table->enum('org_type', ['civil_society', 'volunteer_team', 'startup', 'other'])
                ->nullable()
                ->after('readiness_level');
            $table->enum('org_size', ['small', 'medium', 'large'])->nullable()->after('org_type');
            $table->unsignedInteger('team_member_count')->nullable()->after('org_size');
            $table->timestamp('completed_at')->nullable()->after('team_member_count');
        });

        Schema::table('assessment_pillar_results', function (Blueprint $table) {
            $table->string('pillar_name_ar')->nullable()->after('pillar_id');
            $table->string('pillar_name_en')->nullable()->after('pillar_name_ar');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropForeign(['assessment_version_id']);
            $table->dropColumn([
                'assessment_version_id',
                'org_type',
                'org_size',
                'team_member_count',
                'completed_at',
            ]);
        });

        Schema::table('assessment_pillar_results', function (Blueprint $table) {
            $table->dropColumn(['pillar_name_ar', 'pillar_name_en']);
        });
    }
};
