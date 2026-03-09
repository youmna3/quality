<?php

use Illuminate\Database\Migrations\Migration;
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

        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'reviewer', 'tutor') NOT NULL DEFAULT 'tutor'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'tutor') NOT NULL DEFAULT 'tutor'");
    }
};

