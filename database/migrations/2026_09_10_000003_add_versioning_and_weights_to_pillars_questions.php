<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pillars', function (Blueprint $table) {
            $table->foreignId('assessment_version_id')
                ->nullable()
                ->after('id')
                ->constrained('assessment_versions')
                ->cascadeOnDelete();
            $table->decimal('weight', 5, 2)->default(1.00)->after('display_order');
            $table->boolean('is_active')->default(true)->after('weight');

            $table->index(['assessment_version_id', 'is_active']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('assessment_version_id')
                ->nullable()
                ->after('id')
                ->constrained('assessment_versions')
                ->cascadeOnDelete();
            $table->decimal('weight', 5, 2)->default(1.00)->after('display_order');
            $table->boolean('used_in_assessments')->default(false)->after('is_active');

            $table->index(['assessment_version_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('pillars', function (Blueprint $table) {
            $table->dropForeign(['assessment_version_id']);
            $table->dropColumn(['assessment_version_id', 'weight', 'is_active']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['assessment_version_id']);
            $table->dropColumn(['assessment_version_id', 'weight', 'used_in_assessments']);
        });
    }
};
