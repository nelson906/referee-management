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
        // 1. Rinomina la tabella clubs in circles
        Schema::rename('clubs', 'circles');

        // 2. Aggiungi nuove colonne alla tabella circles
        Schema::table('circles', function (Blueprint $table) {
            // Aggiungi colonne mancanti dal nuovo schema ER
            if (!Schema::hasColumn('circles', 'code')) {
                $table->string('code', 50)->after('short_name')->unique()->nullable();
            }

            if (!Schema::hasColumn('circles', 'contact_person')) {
                $table->string('contact_person')->after('address')->nullable();
            }

            // Rinomina short_name in code se necessario
            if (Schema::hasColumn('circles', 'short_name') && !Schema::hasColumn('circles', 'code')) {
                $table->renameColumn('short_name', 'code');
            }
        });

        // 3. Genera codici per i circoli esistenti
        DB::table('circles')->whereNull('code')->orWhere('code', '')->each(function ($circle) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $circle->name), 0, 6));
            $suffix = '';
            $counter = 1;

            // Assicura unicità del codice
            while (DB::table('circles')->where('code', $code . $suffix)->where('id', '!=', $circle->id)->exists()) {
                $suffix = $counter++;
            }

            DB::table('circles')
                ->where('id', $circle->id)
                ->update(['code' => $code . $suffix]);
        });

        // 4. Aggiorna le foreign key nelle altre tabelle
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->renameColumn('club_id', 'circle_id');
            $table->foreign('circle_id')
                  ->references('id')
                  ->on('circles')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ripristina tournaments
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['circle_id']);
            $table->renameColumn('circle_id', 'club_id');
            $table->foreign('club_id')
                  ->references('id')
                  ->on('circles')
                  ->onDelete('cascade');
        });

        // Rimuovi colonne aggiunte
        Schema::table('circles', function (Blueprint $table) {
            if (Schema::hasColumn('circles', 'contact_person')) {
                $table->dropColumn('contact_person');
            }

            // Se abbiamo rinominato short_name in code, ripristina
            if (!Schema::hasColumn('circles', 'short_name') && Schema::hasColumn('circles', 'code')) {
                $table->renameColumn('code', 'short_name');
            }
        });

        // Rinomina la tabella
        Schema::rename('circles', 'clubs');
    }
};
