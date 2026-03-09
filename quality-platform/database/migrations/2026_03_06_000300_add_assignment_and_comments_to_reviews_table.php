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
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'reviewer_assignment_id')) {
                $table->foreignId('reviewer_assignment_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('reviewer_assignments')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('reviews', 'positive_comments_json')) {
                $table->json('positive_comments_json')->nullable()->after('negative_note');
            }

            if (! Schema::hasColumn('reviews', 'negative_comments_json')) {
                $table->json('negative_comments_json')->nullable()->after('positive_comments_json');
            }

            if (! Schema::hasColumn('reviews', 'score_breakdown_json')) {
                $table->json('score_breakdown_json')->nullable()->after('negative_comments_json');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'reviewer_assignment_id')) {
                $table->dropConstrainedForeignId('reviewer_assignment_id');
            }

            $dropColumns = [];
            foreach (['positive_comments_json', 'negative_comments_json', 'score_breakdown_json'] as $column) {
                if (Schema::hasColumn('reviews', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
