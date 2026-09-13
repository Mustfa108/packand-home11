<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('org_type', ['civil_society', 'volunteer_team', 'startup', 'other'])
                ->nullable()
                ->after('organization_name');
            $table->enum('org_size', ['small', 'medium', 'large'])
                ->nullable()
                ->after('org_type');
            $table->unsignedInteger('team_member_count')->nullable()->after('org_size');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['org_type', 'org_size', 'team_member_count']);
        });
    }
};
