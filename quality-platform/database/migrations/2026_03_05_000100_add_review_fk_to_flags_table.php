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
        if (! Schema::hasTable('flags') || ! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('flags', function (Blueprint $table) {
            $table->foreign('review_id')->references('id')->on('reviews')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('flags')) {
            return;
        }

        Schema::table('flags', function (Blueprint $table) {
            $table->dropForeign(['review_id']);
        });
    }
};

