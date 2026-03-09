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
        if (Schema::hasTable('ops_issue_entries')) {
            return;
        }

        Schema::create('ops_issue_entries', function (Blueprint $table) {
            $table->id();
            $table->string('tutor_code');
            $table->date('session_date')->nullable();
            $table->string('slot')->nullable();
            $table->text('issue_text');
            $table->timestamps();

            $table->index('tutor_code');
            $table->index(['tutor_code', 'session_date', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ops_issue_entries');
    }
};
