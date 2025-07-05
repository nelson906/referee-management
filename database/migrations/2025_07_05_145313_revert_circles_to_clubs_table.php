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
        // 1. Prima aggiorna le foreign key nelle altre tabelle
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->renameColumn('club_id', 'club_id');
            $table->foreign('club_id')
                  ->references('id')
                  ->on('clubs')  // Ancora punta a 'clubs' temporaneamente
                  ->onDelete('cascade');
        });

        // 2. Rinomina la tabella clubs in clubs
        Schema::rename('clubs', 'clubs');

        // 3. Aggiorna la foreign key per puntare alla tabella rinominata
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->foreign('club_id')
                  ->references('id')
                  ->on('clubs')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ripristina il processo inverso
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->foreign('club_id')
                  ->references('id')
                  ->on('clubs')
                  ->onDelete('cascade');
        });

        Schema::rename('clubs', 'clubs');

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->renameColumn('club_id', 'club_id');
            $table->foreign('club_id')
                  ->references('id')
                  ->on('clubs')
                  ->onDelete('cascade');
        });
    }
};
