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
        if (! Schema::hasTable('flags')) {
            return;
        }

        Schema::table('flags', function (Blueprint $table) {
            if (! Schema::hasColumn('flags', 'screenshot_path')) {
                $table->string('screenshot_path')->nullable()->after('duration_text');
            }
        });

        DB::statement("ALTER TABLE flags MODIFY color ENUM('yellow', 'red', 'both') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('flags')) {
            return;
        }

        DB::statement("ALTER TABLE flags MODIFY color ENUM('yellow', 'red') NOT NULL");

        Schema::table('flags', function (Blueprint $table) {
            if (Schema::hasColumn('flags', 'screenshot_path')) {
                $table->dropColumn('screenshot_path');
            }
        });
    }
};
