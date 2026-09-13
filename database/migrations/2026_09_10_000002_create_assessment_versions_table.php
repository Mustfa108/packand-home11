<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version_number');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('notes_ar')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique('version_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_versions');
    }
};
