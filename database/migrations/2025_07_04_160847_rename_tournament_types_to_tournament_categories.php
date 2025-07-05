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
        // 1. Rinomina la tabella tournament_types in tournament_categories
        Schema::rename('tournament_types', 'tournament_categories');

        // 2. Modifica la struttura della tabella tournament_categories
        Schema::table('tournament_categories', function (Blueprint $table) {
            // Aggiungi nuove colonne
            $table->string('code', 50)->after('name')->unique()->nullable();
            $table->string('level', 50)->after('is_national')->default('zonale');
            $table->integer('sort_order')->after('is_active')->default(0);
            $table->json('settings')->after('sort_order')->nullable();

            // Modifica colonne esistenti
            $table->string('required_level', 50)->nullable()->change();

            // Rinomina short_name in code se esiste
            if (Schema::hasColumn('tournament_categories', 'short_name')) {
                $table->renameColumn('short_name', 'code_old');
            }
        });

        // 3. Migra i dati esistenti
        DB::table('tournament_categories')->orderBy('id')->each(function ($category) {
            $settings = [
                'required_referee_level' => $category->required_level,
                'min_referees' => $category->referees_needed ?? 1,
                'max_referees' => $category->referees_needed ?? 1,
                'visibility_zones' => $category->is_national ? 'all' : 'own',
            ];

            DB::table('tournament_categories')
                ->where('id', $category->id)
                ->update([
                    'code' => $category->code_old ?? strtoupper(substr($category->name, 0, 3)),
                    'level' => $category->is_national ? 'nazionale' : 'zonale',
                    'settings' => json_encode($settings),
                    'sort_order' => $category->id * 10,
                ]);
        });

        // 4. Rimuovi la vecchia colonna
        Schema::table('tournament_categories', function (Blueprint $table) {
            if (Schema::hasColumn('tournament_categories', 'code_old')) {
                $table->dropColumn('code_old');
            }
            if (Schema::hasColumn('tournament_categories', 'referees_needed')) {
                $table->dropColumn('referees_needed');
            }
        });

        // 5. Aggiorna la foreign key nella tabella tournaments
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['tournament_type_id']);
            $table->renameColumn('tournament_type_id', 'tournament_category_id');
            $table->foreign('tournament_category_id')
                  ->references('id')
                  ->on('tournament_categories')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ripristina tournaments foreign key
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['tournament_category_id']);
            $table->renameColumn('tournament_category_id', 'tournament_type_id');
            $table->foreign('tournament_type_id')
                  ->references('id')
                  ->on('tournament_categories')
                  ->onDelete('cascade');
        });

        // Ripristina la struttura originale
        Schema::table('tournament_categories', function (Blueprint $table) {
            $table->integer('referees_needed')->default(1);
            $table->dropColumn(['code', 'level', 'sort_order', 'settings']);
        });

        // Rinomina la tabella
        Schema::rename('tournament_categories', 'tournament_types');
    }
};
