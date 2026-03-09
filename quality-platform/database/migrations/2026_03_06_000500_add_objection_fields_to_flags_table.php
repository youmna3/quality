<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flags')) {
            return;
        }

        Schema::table('flags', function (Blueprint $table) {
            if (! Schema::hasColumn('flags', 'objection_text')) {
                $table->text('objection_text')->nullable()->after('reason');
            }
            if (! Schema::hasColumn('flags', 'objection_status')) {
                $table->enum('objection_status', ['none', 'pending', 'accepted', 'rejected'])->default('none')->after('status');
            }
            if (! Schema::hasColumn('flags', 'objection_response')) {
                $table->text('objection_response')->nullable()->after('objection_status');
            }
            if (! Schema::hasColumn('flags', 'objection_submitted_at')) {
                $table->timestamp('objection_submitted_at')->nullable()->after('objection_response');
            }
            if (! Schema::hasColumn('flags', 'objection_reviewed_by')) {
                $table->foreignId('objection_reviewed_by')->nullable()->after('objection_submitted_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('flags', 'objection_reviewed_at')) {
                $table->timestamp('objection_reviewed_at')->nullable()->after('objection_reviewed_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('flags')) {
            return;
        }

        Schema::table('flags', function (Blueprint $table) {
            if (Schema::hasColumn('flags', 'objection_reviewed_at')) {
                $table->dropColumn('objection_reviewed_at');
            }
            if (Schema::hasColumn('flags', 'objection_reviewed_by')) {
                $table->dropConstrainedForeignId('objection_reviewed_by');
            }
            if (Schema::hasColumn('flags', 'objection_submitted_at')) {
                $table->dropColumn('objection_submitted_at');
            }
            if (Schema::hasColumn('flags', 'objection_response')) {
                $table->dropColumn('objection_response');
            }
            if (Schema::hasColumn('flags', 'objection_status')) {
                $table->dropColumn('objection_status');
            }
            if (Schema::hasColumn('flags', 'objection_text')) {
                $table->dropColumn('objection_text');
            }
        });
    }
};

