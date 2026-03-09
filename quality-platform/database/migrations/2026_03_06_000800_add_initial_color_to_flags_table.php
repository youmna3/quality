<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flags')) {
            return;
        }

        Schema::table('flags', function (Blueprint $table) {
            if (! Schema::hasColumn('flags', 'initial_color')) {
                $table->enum('initial_color', ['yellow', 'red', 'both'])->nullable()->after('color');
            }
        });

        DB::table('flags')
            ->whereNull('initial_color')
            ->update(['initial_color' => DB::raw('color')]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('flags')) {
            return;
        }

        Schema::table('flags', function (Blueprint $table) {
            if (Schema::hasColumn('flags', 'initial_color')) {
                $table->dropColumn('initial_color');
            }
        });
    }
};

