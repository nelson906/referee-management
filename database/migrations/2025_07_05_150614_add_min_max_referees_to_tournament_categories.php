<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Aggiungi le colonne fisiche per compatibilità con le query SQL esistenti
        Schema::table('tournament_categories', function (Blueprint $table) {
            $table->integer('min_referees')->default(1)->after('settings');
            $table->integer('max_referees')->default(1)->after('min_referees');
        });

        // Migra i dati dal campo JSON alle colonne fisiche
        DB::table('tournament_categories')->orderBy('id')->each(function ($category) {
            $settings = json_decode($category->settings, true) ?? [];

            $minReferees = $settings['min_referees'] ?? 1;
            $maxReferees = $settings['max_referees'] ?? $minReferees;

            DB::table('tournament_categories')
                ->where('id', $category->id)
                ->update([
                    'min_referees' => $minReferees,
                    'max_referees' => $maxReferees,
                ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_categories', function (Blueprint $table) {
            $table->dropColumn(['min_referees', 'max_referees']);
        });
    }
};
