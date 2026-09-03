<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_review_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_review_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->timestamps();

            $table->index(['project_review_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_review_chat_messages');
    }
};