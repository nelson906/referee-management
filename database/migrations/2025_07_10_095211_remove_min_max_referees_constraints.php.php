<?php
// database/migrations/YYYY_MM_DD_HHMMSS_remove_min_max_referees_constraints.php

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
        // Rimuovi colonne min_referees e max_referees da tournament_categories
        Schema::table('tournament_categories', function (Blueprint $table) {
            $table->dropColumn(['min_referees', 'max_referees']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_categories', function (Blueprint $table) {
            $table->integer('min_referees')->default(1)->after('settings');
            $table->integer('max_referees')->default(1)->after('min_referees');
        });
    }
};
