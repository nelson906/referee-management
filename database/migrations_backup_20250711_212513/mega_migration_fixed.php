<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MEGA CONSOLIDATION MIGRATION - VERSIONE CORRETTA
     *
     * Risolve contemporaneamente:
     * 1. User/Referee data consolidation
     * 2. Tournament Category → Tournament Type renaming
     * 3. Schema cleanup e optimization
     *
     * FIXES:
     * - Controlla esistenza indici prima di crearli
     * - Gestisce meglio i foreign key constraints
     * - Evita duplicazione di colonne/indici
     */
    public function up(): void
    {
        DB::beginTransaction();

        try {
            $this->log('🚀 INIZIO MEGA CONSOLIDAMENTO - VERSIONE CORRETTA');

            // FASE 1: USER/REFEREE CONSOLIDATION
            $this->consolidateUserReferee();

            // FASE 2: TOURNAMENT CATEGORY → TYPE RENAMING
            $this->renameTournamentCategoryToType();

            // FASE 3: SCHEMA OPTIMIZATION
            $this->optimizeSchema();

            DB::commit();
            $this->log('✅ MEGA CONSOLIDAMENTO COMPLETATO CON SUCCESSO');

        } catch (\Exception $e) {
            DB::rollback();
            $this->log('❌ ERRORE: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * FASE 1: CONSOLIDAMENTO USER/REFEREE
     */
    private function consolidateUserReferee(): void
    {
        $this->log('📋 FASE 1: Consolidamento User/Referee');

        // 1.1 Aggiungi campi mancanti alla tabella users (solo se non esistono)
        $this->log('   1.1 Aggiunta campi a users table...');
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'referee_code')) {
                $table->string('referee_code')->nullable()->unique()->after('email');
            }
            if (!Schema::hasColumn('users', 'level')) {
                $table->enum('level', ['aspirante', 'primo_livello', 'regionale', 'nazionale', 'internazionale', 'archivio'])
                      ->nullable()->after('referee_code');
            }
            if (!Schema::hasColumn('users', 'category')) {
                $table->enum('category', ['maschile', 'femminile', 'misto'])
                      ->default('misto')->after('level');
            }
            if (!Schema::hasColumn('users', 'certified_date')) {
                $table->date('certified_date')->nullable()->after('category');
            }
            if (!Schema::hasColumn('users', 'notes')) {
                $table->text('notes')->nullable()->after('phone');
            }
        });

        // 1.2 Migra dati da referees a users
        $this->log('   1.2 Migrazione dati referees → users...');
        $this->migrateRefereeDataToUsers();

        // 1.3 Rimuovi campi duplicati da referees (con gestione foreign key sicura)
        $this->log('   1.3 Rimozione campi duplicati da referees...');
        $this->removeRefereesDuplicateFields();

        // 1.4 Aggiungi SOLO nuovi indici users (controlla esistenza)
        $this->log('   1.4 Aggiunta nuovi indici users...');
        $this->addUsersIndexesSafely();

        $this->log('✅ FASE 1 COMPLETATA: User/Referee consolidato');
    }

    /**
     * Rimuove i campi duplicati dalla tabella referees in modo sicuro
     */
    private function removeRefereesDuplicateFields(): void
    {
        // Lista delle foreign key da rimuovere
        $foreignKeysToRemove = [
            'referees_zone_id_foreign',
            'zone_id', // In caso il nome sia diverso
        ];

        // Rimuovi foreign key constraints in modo sicuro
        foreach ($foreignKeysToRemove as $foreignKeyName) {
            try {
                if ($this->foreignKeyExists('referees', $foreignKeyName)) {
                    Schema::table('referees', function (Blueprint $table) use ($foreignKeyName) {
                        if ($foreignKeyName === 'zone_id') {
                            $table->dropForeign(['zone_id']);
                        } else {
                            $table->dropForeign($foreignKeyName);
                        }
                    });
                    $this->log("   ✅ Rimosso foreign key: {$foreignKeyName}");
                }
            } catch (\Exception $e) {
                $this->log("   ⚠️  Warning foreign key {$foreignKeyName}: " . $e->getMessage());
                // Continua comunque
            }
        }

        // Rimuovi colonne duplicate
        $columnsToRemove = [
            'referee_code', 'level', 'category', 'certified_date',
            'zone_id', 'phone', 'is_active'
        ];

        Schema::table('referees', function (Blueprint $table) use ($columnsToRemove) {
            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('referees', $column)) {
                    $table->dropColumn($column);
                    $this->log("   ✅ Rimossa colonna duplicata: {$column}");
                }
            }
        });
    }

    /**
     * Aggiunge indici alla tabella users controllando l'esistenza
     */
    private function addUsersIndexesSafely(): void
    {
        $indicesToCreate = [
            'users_zone_id_level_index' => ['zone_id', 'level'],
            'users_level_is_active_index' => ['level', 'is_active'],
        ];

        foreach ($indicesToCreate as $indexName => $columns) {
            if (!$this->indexExists('users', $indexName)) {
                Schema::table('users', function (Blueprint $table) use ($columns) {
                    $table->index($columns);
                });
                $this->log("   ✅ Creato indice: " . implode(', ', $columns));
            } else {
                $this->log("   ⚠️  Indice già esistente: {$indexName}");
            }
        }
    }

    /**
     * FASE 2: TOURNAMENT CATEGORY → TYPE RENAMING
     */
    private function renameTournamentCategoryToType(): void
    {
        $this->log('🏆 FASE 2: Tournament Category → Type renaming');

        // 2.1 Controlla se tournament_categories esiste
        if (!Schema::hasTable('tournament_categories')) {
            $this->log('   ⚠️  Tabella tournament_categories non trovata, saltando...');
            return;
        }

        // 2.2 Rename tournament_categories → tournament_types
        $this->log('   2.1 Rename tournament_categories → tournament_types...');
        Schema::rename('tournament_categories', 'tournament_types');

        // 2.3 Update foreign key columns in dependent tables
        $this->log('   2.2 Update foreign key columns...');
        $this->updateTournamentForeignKeys();

        $this->log('✅ FASE 2 COMPLETATA: Tournament Category → Type');
    }

    /**
     * Aggiorna le foreign key references nelle tabelle dipendenti
     */
    private function updateTournamentForeignKeys(): void
    {
        // tournaments table
        if (Schema::hasTable('tournaments') && Schema::hasColumn('tournaments', 'tournament_category_id')) {
            Schema::table('tournaments', function (Blueprint $table) {
                // Drop existing foreign key
                try {
                    $table->dropForeign(['tournament_category_id']);
                } catch (\Exception $e) {
                    $this->log("   ⚠️  Warning dropping foreign key: " . $e->getMessage());
                }

                // Rename column
                $table->renameColumn('tournament_category_id', 'tournament_type_id');
            });

            // Re-add foreign key constraint with new name
            Schema::table('tournaments', function (Blueprint $table) {
                $table->foreign('tournament_type_id')->references('id')->on('tournament_types');
            });

            $this->log('   ✅ Aggiornato tournaments.tournament_category_id → tournament_type_id');
        }

        // letter_templates table (se esiste)
        if (Schema::hasTable('letter_templates') && Schema::hasColumn('letter_templates', 'tournament_category_id')) {
            Schema::table('letter_templates', function (Blueprint $table) {
                try {
                    $table->dropForeign(['tournament_category_id']);
                } catch (\Exception $e) {
                    $this->log("   ⚠️  Warning dropping foreign key: " . $e->getMessage());
                }
                $table->renameColumn('tournament_category_id', 'tournament_type_id');
            });

            Schema::table('letter_templates', function (Blueprint $table) {
                $table->foreign('tournament_type_id')->references('id')->on('tournament_types');
            });

            $this->log('   ✅ Aggiornato letter_templates.tournament_category_id → tournament_type_id');
        }
    }

    /**
     * FASE 3: SCHEMA OPTIMIZATION
     */
    private function optimizeSchema(): void
    {
        $this->log('⚡ FASE 3: Schema optimization');

        // 3.1 Ottimizza indici tournament_types (ex tournament_categories)
        if (Schema::hasTable('tournament_types')) {
            $this->addIndexSafely('tournament_types', 'idx_types_active_sort', ['is_active', 'sort_order']);
            $this->addIndexSafely('tournament_types', 'idx_types_national_active', ['is_national', 'is_active']);
        }

        // 3.2 Ottimizza indici tournaments
        if (Schema::hasTable('tournaments')) {
            $this->addIndexSafely('tournaments', 'idx_tournaments_zone_status', ['zone_id', 'status']);
            $this->addIndexSafely('tournaments', 'idx_tournaments_type_status', ['tournament_type_id', 'status']);
            $this->addIndexSafely('tournaments', 'idx_tournaments_date_status', ['start_date', 'status']);
        }

        // 3.3 Ottimizza indici assignments
        if (Schema::hasTable('assignments')) {
            $this->addIndexSafely('assignments', 'idx_assignments_user_confirmed', ['user_id', 'is_confirmed']);
            $this->addIndexSafely('assignments', 'idx_assignments_tournament_confirmed', ['tournament_id', 'is_confirmed']);
        }

        // 3.4 Ottimizza indici availabilities
        if (Schema::hasTable('availabilities')) {
            $this->addIndexSafely('availabilities', 'idx_availabilities_user_submitted', ['user_id', 'submitted_at']);
            $this->addIndexSafely('availabilities', 'idx_availabilities_tournament_submitted', ['tournament_id', 'submitted_at']);
        }

        $this->log('✅ FASE 3 COMPLETATA: Schema optimized');
    }

    /**
     * Migra i dati dalla tabella referees alla tabella users
     */
    private function migrateRefereeDataToUsers(): void
    {
        if (!Schema::hasTable('referees')) {
            $this->log('   ⚠️  Tabella referees non trovata');
            return;
        }

        $referees = DB::table('referees')
            ->join('users', 'users.id', '=', 'referees.user_id')
            ->select('referees.*', 'users.id as user_id')
            ->get();

        $migratedCount = 0;

        foreach ($referees as $referee) {
            $updateData = [];

            // Migra solo i campi che esistono e non sono null
            if (isset($referee->referee_code) && !empty($referee->referee_code)) {
                $updateData['referee_code'] = $referee->referee_code;
            }
            if (isset($referee->level) && !empty($referee->level)) {
                $updateData['level'] = $referee->level;
            }
            if (isset($referee->category) && !empty($referee->category)) {
                $updateData['category'] = $referee->category;
            }
            if (isset($referee->certified_date)) {
                $updateData['certified_date'] = $referee->certified_date;
            }
            if (isset($referee->zone_id)) {
                $updateData['zone_id'] = $referee->zone_id;
            }
            if (isset($referee->phone) && !empty($referee->phone)) {
                $updateData['phone'] = $referee->phone;
            }
            if (isset($referee->is_active)) {
                $updateData['is_active'] = $referee->is_active;
            }

            // Assicurati che user_type sia referee
            $updateData['user_type'] = 'referee';

            if (!empty($updateData)) {
                DB::table('users')
                    ->where('id', $referee->user_id)
                    ->update($updateData);
                $migratedCount++;
            }
        }

        $this->log("   ✅ Migrati {$migratedCount} record da referees a users");
    }

    /**
     * UTILITY METHODS
     */

    /**
     * Controlla se un indice esiste
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEXES FROM {$table}");
        foreach ($indexes as $index) {
            if ($index->Key_name === $indexName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Controlla se una foreign key esiste
     */
    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        try {
            $result = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_NAME = ?
                AND TABLE_SCHEMA = DATABASE()
                AND CONSTRAINT_NAME LIKE '%{$foreignKeyName}%'
            ", [$table]);

            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Aggiunge un indice in modo sicuro
     */
    private function addIndexSafely(string $table, string $indexName, array $columns): void
    {
        if (!$this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($indexName, $columns) {
                $table->index($columns, $indexName);
            });
            $this->log("   ✅ Creato indice {$indexName} su " . implode(', ', $columns));
        } else {
            $this->log("   ⚠️  Indice {$indexName} già esistente");
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        DB::beginTransaction();

        try {
            $this->log('🔄 INIZIO ROLLBACK MEGA CONSOLIDAMENTO');

            // Rollback FASE 3: Schema optimization
            $this->rollbackSchemaOptimization();

            // Rollback FASE 2: Tournament Category → Type renaming
            $this->rollbackTournamentTypeToCategory();

            // Rollback FASE 1: User/Referee consolidation
            $this->rollbackUserRefereeConsolidation();

            DB::commit();
            $this->log('✅ ROLLBACK COMPLETATO');

        } catch (\Exception $e) {
            DB::rollback();
            $this->log('❌ ERRORE ROLLBACK: ' . $e->getMessage());
            throw $e;
        }
    }

    private function rollbackSchemaOptimization(): void
    {
        $this->log('⚡ Rollback Schema optimization');

        // Rimuovi indici ottimizzati (se esistono)
        $indicesToRemove = [
            'tournament_types' => ['idx_types_active_sort', 'idx_types_national_active'],
            'tournaments' => ['idx_tournaments_zone_status', 'idx_tournaments_type_status', 'idx_tournaments_date_status'],
            'assignments' => ['idx_assignments_user_confirmed', 'idx_assignments_tournament_confirmed'],
            'availabilities' => ['idx_availabilities_user_submitted', 'idx_availabilities_tournament_submitted'],
        ];

        foreach ($indicesToRemove as $table => $indexes) {
            if (Schema::hasTable($table)) {
                foreach ($indexes as $indexName) {
                    try {
                        if ($this->indexExists($table, $indexName)) {
                            Schema::table($table, function (Blueprint $table) use ($indexName) {
                                $table->dropIndex($indexName);
                            });
                        }
                    } catch (\Exception $e) {
                        $this->log("   ⚠️  Warning dropping index {$indexName}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    private function rollbackTournamentTypeToCategory(): void
    {
        $this->log('🏆 Rollback Tournament Type → Category');

        if (!Schema::hasTable('tournament_types')) {
            return;
        }

        // Revert foreign key columns first
        if (Schema::hasTable('tournaments') && Schema::hasColumn('tournaments', 'tournament_type_id')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->dropForeign(['tournament_type_id']);
                $table->renameColumn('tournament_type_id', 'tournament_category_id');
            });
        }

        // Rename back tournament_types → tournament_categories
        Schema::rename('tournament_types', 'tournament_categories');

        // Re-add foreign key constraint
        if (Schema::hasTable('tournaments')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->foreign('tournament_category_id')->references('id')->on('tournament_categories');
            });
        }
    }

    private function rollbackUserRefereeConsolidation(): void
    {
        $this->log('📋 Rollback User/Referee consolidation');

        // Re-add columns to referees table
        if (Schema::hasTable('referees')) {
            Schema::table('referees', function (Blueprint $table) {
                if (!Schema::hasColumn('referees', 'referee_code')) {
                    $table->string('referee_code')->nullable()->after('user_id');
                }
                if (!Schema::hasColumn('referees', 'level')) {
                    $table->enum('level', ['aspirante', 'primo_livello', 'regionale', 'nazionale', 'internazionale', 'archivio'])
                          ->nullable()->after('referee_code');
                }
                if (!Schema::hasColumn('referees', 'category')) {
                    $table->enum('category', ['maschile', 'femminile', 'misto'])
                          ->default('misto')->after('level');
                }
                if (!Schema::hasColumn('referees', 'certified_date')) {
                    $table->date('certified_date')->nullable()->after('category');
                }
                if (!Schema::hasColumn('referees', 'zone_id')) {
                    $table->foreignId('zone_id')->nullable()->constrained()->after('certified_date');
                }
                if (!Schema::hasColumn('referees', 'phone')) {
                    $table->string('phone', 20)->nullable()->after('zone_id');
                }
                if (!Schema::hasColumn('referees', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('phone');
                }
            });

            // Migrate data back to referees
            $this->migrateUsersDataBackToReferees();
        }

        // Remove columns from users table
        Schema::table('users', function (Blueprint $table) {
            $columnsToRemove = [
                'referee_code', 'level', 'category', 'certified_date', 'notes'
            ];

            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function migrateUsersDataBackToReferees(): void
    {
        $users = DB::table('users')->where('user_type', 'referee')->get();

        foreach ($users as $user) {
            $updateData = [
                'referee_code' => $user->referee_code,
                'level' => $user->level,
                'category' => $user->category,
                'certified_date' => $user->certified_date,
                'zone_id' => $user->zone_id,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
            ];

            DB::table('referees')
                ->where('user_id', $user->id)
                ->update(array_filter($updateData));
        }
    }

    /**
     * Helper method for logging
     */
    private function log(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message . "\n";
        }
    }
};
