<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'reviewer_type')) {
                $table->enum('reviewer_type', ['mentor', 'coordinator'])->nullable()->after('role');
            }
        });

        DB::table('users')
            ->where('role', 'reviewer')
            ->whereNull('reviewer_type')
            ->update(['reviewer_type' => 'mentor']);

        if (Schema::hasTable('mentor_coordinator_assignments')) {
            $coordinatorIds = DB::table('mentor_coordinator_assignments')
                ->whereNotNull('coordinator_user_id')
                ->pluck('coordinator_user_id')
                ->all();

            if ($coordinatorIds !== []) {
                DB::table('users')
                    ->whereIn('id', $coordinatorIds)
                    ->where('role', 'reviewer')
                    ->update(['reviewer_type' => 'coordinator']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'reviewer_type')) {
                $table->dropColumn('reviewer_type');
            }
        });
    }
};
