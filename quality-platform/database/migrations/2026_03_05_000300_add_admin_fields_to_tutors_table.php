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
        if (! Schema::hasTable('tutors')) {
            return;
        }

        Schema::table('tutors', function (Blueprint $table) {
            if (! Schema::hasColumn('tutors', 'grade')) {
                $table->string('grade', 50)->nullable()->after('mentor_name');
            }

            if (! Schema::hasColumn('tutors', 'zoom_email')) {
                $table->string('zoom_email')->nullable()->after('grade');
            }

            if (! Schema::hasColumn('tutors', 'zoom_password')) {
                $table->string('zoom_password')->nullable()->after('zoom_email');
            }

            if (! Schema::hasColumn('tutors', 'dashboard_email')) {
                $table->string('dashboard_email')->nullable()->after('zoom_password');
            }

            if (! Schema::hasColumn('tutors', 'dashboard_password')) {
                $table->string('dashboard_password')->nullable()->after('dashboard_email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tutors')) {
            return;
        }

        Schema::table('tutors', function (Blueprint $table) {
            $columns = [
                'grade',
                'zoom_email',
                'zoom_password',
                'dashboard_email',
                'dashboard_password',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('tutors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

