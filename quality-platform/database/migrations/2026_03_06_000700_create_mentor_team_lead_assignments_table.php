<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mentor_team_lead_assignments')) {
            return;
        }

        Schema::create('mentor_team_lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('mentor_name');
            $table->foreignId('team_lead_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['mentor_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_team_lead_assignments');
    }
};

