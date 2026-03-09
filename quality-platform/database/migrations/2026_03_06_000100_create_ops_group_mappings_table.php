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
        if (Schema::hasTable('ops_group_mappings')) {
            return;
        }

        Schema::create('ops_group_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('tutor_code');
            $table->string('slot');
            $table->date('session_date')->nullable();
            $table->string('group_code');
            $table->timestamps();

            $table->index(['tutor_code', 'slot']);
            $table->unique(['tutor_code', 'slot', 'session_date'], 'ops_group_unique_row');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ops_group_mappings');
    }
};
