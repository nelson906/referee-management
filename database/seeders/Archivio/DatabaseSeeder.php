<?php

/**
 * ========================================
 * DatabaseSeeder.php - VERSIONE CORRETTA
 * ========================================
 * Risolve errori di sintassi e logica nel metodo shouldRunMigration()
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * STRATEGY:
     * - If old database exists → DataMigrationSeeder (production)
     * - If no old database → Development seeders (fresh install)
     */
    public function run(): void
    {
        $this->command->info('🔍 Checking database strategy...');

        // Check if we should run migration from old database
        if ($this->shouldRunMigration()) {
            $this->command->info('📊 Running PRODUCTION migration from existing database...');
            $this->call([
                DataMigrationSeeder::class,
            ]);
        } else {
            $this->command->info('🚀 Running DEVELOPMENT seeding for fresh installation...');
            $this->call([
                // Core system settings
                SettingsSeeder::class,

                // Zones and administrative structure
                ZonesSeeder::class,

                // ✅ FIXED: tournament_types (NOT tournament_categories)
                TournamentTypesSeeder::class,

                // Users (Super Admin, Zone Admins, etc.)
                UsersSeeder::class,

                // Golf clubs
                ClubsSeeder::class,

                // Sample tournaments (for development/testing)
                TournamentsSeeder::class,

                // Sample assignments (for development/testing)
                AssignmentsSeeder::class,

                // Support data
                SupportDataSeeder::class,
            ]);
        }

        $this->command->info('✅ Database seeding completed!');
    }

    /**
     * ✅ FIXED: Check if we should run migration from old database
     */
    private function shouldRunMigration(): bool
    {
        try {
            // ✅ CONFIGURAZIONE CORRETTA: gestione_arbitri
            $this->setupOldDatabaseConnection();

            // Test connection and check for expected tables
            DB::connection('old')->getPdo();
            $this->command->info('✅ Connessione a gestione_arbitri stabilita');

            $tables = DB::connection('old')->select('SHOW TABLES');

            // ✅ FIXED: Logica corretta per verifica tabelle
            $expectedTables = ['users', 'tournaments', 'zones', 'tournament_types'];
            $existingTables = array_map(function($table) {
                return array_values((array) $table)[0];
            }, $tables);

            // Verifica che almeno le tabelle principali esistano
            $foundExpectedTables = 0;
            foreach ($expectedTables as $expectedTable) {
                if (in_array($expectedTable, $existingTables)) {
                    $foundExpectedTables++;
                }
            }

            // Se trova almeno 3 tabelle su 4, considera che sia il database giusto
            $hasValidStructure = $foundExpectedTables >= 3;

            if ($hasValidStructure) {
                $this->command->info("✅ Trovate {$foundExpectedTables}/{" . count($expectedTables) . "} tabelle principali");

                // Verifica che ci siano dati
                $userCount = DB::connection('old')->table('users')->count();
                if ($userCount > 0) {
                    $this->command->info("✅ Trovati {$userCount} users nel database gestione_arbitri");
                    return true;
                }
            }

            $this->command->info("⚠️ Database gestione_arbitri non ha struttura valida o è vuoto");
            return false;

        } catch (\Exception $e) {
            $this->command->info("⚠️ Database gestione_arbitri non disponibile: " . $e->getMessage());
            return false;
        } finally {
            // Pulisci connessione se è stata creata
            try {
                DB::disconnect('old');
            } catch (\Exception $e) {
                // Ignora errori di disconnessione
            }
        }
    }

    /**
     * ✅ Setup connessione database di origine
     */
    private function setupOldDatabaseConnection()
    {
        config(['database.connections.old' => [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => 'gestione_arbitri', // ✅ FISSO: database di origine
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
        ]]);
    }
}
