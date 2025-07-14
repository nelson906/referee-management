<?php
/**
 * FASE 2: CONSOLIDAMENTO TOURNAMENT TYPES
 *
 * File: database/migrations/2025_07_12_000000_consolidate_tournament_types.php
 *
 * Standardizza su tournament_types table come da handoff document
 */

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
        // 1. Rinomina tournament_categories → tournament_types
        if (Schema::hasTable('tournament_categories') && !Schema::hasTable('tournament_types')) {
            Schema::rename('tournament_categories', 'tournament_types');
        }

        // 2. Aggiorna foreign key in tournaments table
        if (Schema::hasTable('tournaments')) {
            Schema::table('tournaments', function (Blueprint $table) {
                // Drop existing foreign key
                $table->dropForeign(['tournament_category_id']);

                // Rename column
                $table->renameColumn('tournament_category_id', 'tournament_type_id');
            });

            // Recreate foreign key with new reference
            Schema::table('tournaments', function (Blueprint $table) {
                $table->foreign('tournament_type_id')
                      ->references('id')
                      ->on('tournament_types')
                      ->onDelete('restrict');
            });
        }

        // 3. Aggiorna altri riferimenti se esistono
        $this->updateOtherReferences();

        echo "✅ CONSOLIDAMENTO COMPLETATO: tournament_categories → tournament_types\n";
        echo "✅ FOREIGN KEY AGGIORNATA: tournament_category_id → tournament_type_id\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Processo inverso per rollback
        if (Schema::hasTable('tournaments')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->dropForeign(['tournament_type_id']);
                $table->renameColumn('tournament_type_id', 'tournament_category_id');
            });

            Schema::table('tournaments', function (Blueprint $table) {
                $table->foreign('tournament_category_id')
                      ->references('id')
                      ->on('tournament_categories')
                      ->onDelete('restrict');
            });
        }

        if (Schema::hasTable('tournament_types')) {
            Schema::rename('tournament_types', 'tournament_categories');
        }
    }

    /**
     * Update other table references if they exist
     */
    private function updateOtherReferences(): void
    {
        // Letter templates table (se esiste)
        if (Schema::hasTable('letter_templates') && Schema::hasColumn('letter_templates', 'tournament_category_id')) {
            Schema::table('letter_templates', function (Blueprint $table) {
                $table->dropForeign(['tournament_category_id']);
                $table->renameColumn('tournament_category_id', 'tournament_type_id');
                $table->foreign('tournament_type_id')->references('id')->on('tournament_types');
            });
        }

        // Institutional emails table (se esiste)
        if (Schema::hasTable('institutional_emails') && Schema::hasColumn('institutional_emails', 'tournament_category_id')) {
            Schema::table('institutional_emails', function (Blueprint $table) {
                $table->dropForeign(['tournament_category_id']);
                $table->renameColumn('tournament_category_id', 'tournament_type_id');
                $table->foreign('tournament_type_id')->references('id')->on('tournament_types');
            });
        }

        // Altri riferimenti si possono aggiungere qui
    }
};

