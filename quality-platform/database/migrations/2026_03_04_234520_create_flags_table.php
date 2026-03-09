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
        if (Schema::hasTable('flags')) {
            return;
        }

        Schema::create('flags', function (Blueprint $table) {
            $table->id();
            // Review table runs in a later migration file, so this FK is added in a follow-up migration.
            $table->foreignId('review_id');
            $table->foreignId('tutor_id')->constrained()->cascadeOnDelete();
            $table->enum('color', ['yellow', 'red']);
            $table->string('subcategory');
            $table->text('reason');
            $table->string('duration_text')->nullable();
            $table->enum('status', ['open', 'accepted', 'removed', 'partial', 'appealed', 'resolved'])->default('open');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flags');
    }
};
