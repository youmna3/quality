<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('complaints')) {
            return;
        }

        if (! Schema::hasColumn('complaints', 'issue_type')) {
            Schema::table('complaints', function (Blueprint $table) {
                $table->enum('issue_type', ['session', 'student'])->default('session')->after('group_code');
            });
        } else {
            DB::statement("ALTER TABLE complaints MODIFY issue_type ENUM('session', 'student') NOT NULL DEFAULT 'session'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('complaints') || ! Schema::hasColumn('complaints', 'issue_type')) {
            return;
        }

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('issue_type');
        });
    }
};
