<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('week_cycles')) {
            return;
        }

        Schema::table('week_cycles', function (Blueprint $table) {
            if (! Schema::hasColumn('week_cycles', 'reports_published_at')) {
                $table->timestamp('reports_published_at')->nullable()->after('deadline_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('week_cycles') || ! Schema::hasColumn('week_cycles', 'reports_published_at')) {
            return;
        }

        Schema::table('week_cycles', function (Blueprint $table) {
            $table->dropColumn('reports_published_at');
        });
    }
};
