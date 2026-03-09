<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('week_cycles')) {
            return;
        }

        Schema::create('week_cycles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('week_number')->unique();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamps();

            $table->index('deadline_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('week_cycles');
    }
};
