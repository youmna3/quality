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
        if (Schema::hasTable('reviews')) {
            return;
        }

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained()->cascadeOnDelete();
            $table->string('reviewer_name');
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('tutor_role', ['main', 'cover']);
            $table->date('session_date');
            $table->string('slot');
            $table->string('group_code');
            $table->string('recorded_link');
            $table->text('issue_text');
            $table->text('positive_note')->nullable();
            $table->text('negative_note')->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
