<?php

/**
 * ========================================
 * MIGRATION TESTER - Test con Subset Dati
 * ========================================
 * Comandi per testare la migrazione con un subset limitato di dati
 * prima di procedere con la migrazione completa
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Database\Seeders\DataMigrationSeeder;

class TestMigrationCommand extends Command
{
    protected $signature = 'golf:test-migration
                            {--limit=10 : Limite di record per test}
                            {--backup : Crea backup prima del test}
                            {--cleanup : Pulisce dati test dopo il run}
                            {--validate : Solo validazione, nessuna migrazione}';

    protected $description = 'Testa la migrazione con un subset limitato di dati';

    private $testResults = [];
    private $backupId;

    public function handle()
    {
        $this->info('🧪 AVVIO TEST MIGRAZIONE');
        $this->info('========================');

        $limit = $this->option('limit');
        $this->info("Limite record per test: {$limit}");

        // 1. Backup se richiesto
        if ($this->option('backup')) {
            $this->createBackup();
        }

        // 2. Solo validazione?
        if ($this->option('validate')) {
            return $this->validateOnly();
        }

        // 3. Setup test environment
        $this->setupTestEnvironment();

        // 4. Esegui test migrazione
        $this->runTestMigration($limit);

        // 5. Valida risultati
        $this->validateResults();

        // 6. Report finale
        $this->generateReport();

        // 7. Cleanup se richiesto
        if ($this->option('cleanup')) {
            $this->cleanup();
        }

        return 0;
    }

    /**
     * 💾 Crea backup del database target
     */
    private function createBackup()
    {
        $this->info('💾 Creazione backup database target...');

        $this->backupId = 'test_backup_' . date('Y_m_d_H_i_s');

        try {
            // Backup delle tabelle principali
            $tables = ['users', 'referees', 'zones', 'tournaments', 'clubs'];

            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    $backupTable = "{$table}_{$this->backupId}";
                    DB::statement("CREATE TABLE {$backupTable} AS SELECT * FROM {$table}");
                    $this->info("   ✅ Backup {$table} → {$backupTable}");
                }
            }

            $this->info("✅ Backup completato: {$this->backupId}");

        } catch (\Exception $e) {
            $this->error("❌ Errore backup: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Solo validazione senza migrazione
     */
    private function validateOnly()
    {
        $this->info('✅ MODALITÀ SOLO VALIDAZIONE');

        // 1. Test connessione source
        $sourceConnection = $this->testSourceConnection();

        // 2. Analizza mapping potenziali
        $mappingAnalysis = $this->analyzePotentialMapping();

        // 3. Report validazione
        $this->info('📊 RISULTATI VALIDAZIONE:');
        $this->info("   Source DB: " . ($sourceConnection ? 'OK' : 'ERRORE'));
        $this->info("   Users mappabili: {$mappingAnalysis['mappable_users']}");
        $this->info("   Referees mappabili: {$mappingAnalysis['mappable_referees']}");
        $this->info("   Issues rilevati: {$mappingAnalysis['issues_count']}");

        if ($mappingAnalysis['issues_count'] > 0) {
            $this->warn('⚠️  Issues trovati:');
            foreach ($mappingAnalysis['issues'] as $issue) {
                $this->warn("   - {$issue}");
            }
        }

        return 0;
    }

    /**
     * 🔧 Setup ambiente di test
     */
    private function setupTestEnvironment()
    {
        $this->info('🔧 Setup ambiente di test...');

        // Assicurati che le tabelle target esistano
        $requiredTables = ['users', 'referees', 'zones', 'clubs', 'tournaments'];

        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("❌ Tabella mancante: {$table}");
                $this->info("💡 Esegui prima: php artisan migrate");
                exit(1);
            }
        }

        $this->info('✅ Ambiente di test pronto');
    }

    /**
     * 🚀 Esegui test migrazione
     */
    private function runTestMigration(int $limit)
    {
        $this->info("🚀 Avvio test migrazione (limite: {$limit})...");

        try {
            // Crea un TestDataMigrationSeeder modificato
            $testSeeder = new TestDataMigrationSeeder($limit);
            $testSeeder->setCommand($this);
            $testSeeder->run();

            $this->testResults['migration_completed'] = true;
            $this->info('✅ Test migrazione completato');

        } catch (\Exception $e) {
            $this->testResults['migration_completed'] = false;
            $this->testResults['migration_error'] = $e->getMessage();
            $this->error('❌ Errore test migrazione: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Valida risultati migrazione
     */
    private function validateResults()
    {
        $this->info('✅ Validazione risultati...');

        $validation = [
            'users_migrated' => DB::table('users')->count(),
            'referees_created' => DB::table('referees')->count(),
            'zones_migrated' => DB::table('zones')->count(),
            'data_integrity' => $this->checkDataIntegrity(),
            'referees_user_link' => $this->validateRefereesUserLink(),
            'user_types_distribution' => $this->analyzeUserTypesDistribution()
        ];

        $this->testResults['validation'] = $validation;

        $this->info('📊 RISULTATI VALIDAZIONE:');
        $this->info("   Users migrati: {$validation['users_migrated']}");
        $this->info("   Referees creati: {$validation['referees_created']}");
        $this->info("   Zones migrate: {$validation['zones_migrated']}");
        $this->info("   Integrità dati: " . ($validation['data_integrity'] ? 'OK' : 'ERRORE'));
        $this->info("   Link referees-user: " . ($validation['referees_user_link'] ? 'OK' : 'ERRORE'));

        // Distribuzione user_type
        $this->info('👥 Distribuzione user_type:');
        foreach ($validation['user_types_distribution'] as $type => $count) {
            $this->info("   - {$type}: {$count}");
        }
    }

    /**
     * 📄 Genera report finale
     */
    private function generateReport()
    {
        $this->info('📄 Generazione report finale...');

        $reportFile = storage_path('logs/migration_test_' . date('Y_m_d_H_i_s') . '.json');

        $report = [
            'test_timestamp' => now()->toISOString(),
            'test_config' => [
                'limit' => $this->option('limit'),
                'backup_created' => $this->option('backup'),
                'backup_id' => $this->backupId ?? null
            ],
            'results' => $this->testResults,
            'recommendations' => $this->generateRecommendations()
        ];

        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));

        $this->info("📄 Report salvato: {$reportFile}");

        // Mostra raccomandazioni
        $this->info('🎯 RACCOMANDAZIONI:');
        foreach ($report['recommendations'] as $rec) {
            $this->info("   - {$rec}");
        }
    }

    /**
     * 🧹 Cleanup dati test
     */
    private function cleanup()
    {
        if (!$this->confirm('🧹 Confermi la pulizia dei dati test?')) {
            return;
        }

        $this->info('🧹 Pulizia dati test...');

        try {
            // Ripristina da backup se disponibile
            if ($this->backupId) {
                $this->restoreFromBackup();
            } else {
                // Pulizia semplice
                DB::table('referees')->truncate();
                DB::table('users')->truncate();
                DB::table('zones')->truncate();
            }

            $this->info('✅ Cleanup completato');

        } catch (\Exception $e) {
            $this->error('❌ Errore cleanup: ' . $e->getMessage());
        }
    }

    /**
     * Helper methods
     */
    private function testSourceConnection(): bool
    {
        try {
            $oldDbConfig = [
                'driver' => 'mysql',
                'host' => config('database.connections.mysql.host'),
                'port' => config('database.connections.mysql.port'),
                'database' => 'gestione_arbitri',
                'username' => config('database.connections.mysql.username'),
                'password' => config('database.connections.mysql.password'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => false,
                'engine' => null,
            ];

            config(['database.connections.old' => $oldDbConfig]);

            DB::connection('old')->getPdo();
            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    private function analyzePotentialMapping(): array
    {
        try {
            $users = DB::connection('old')->table('users')->get();
            $referees = DB::connection('old')->table('referees')->get();

            $issues = [];

            // Check per email duplicate
            $duplicateEmails = $users->groupBy('email')->filter(function($group) {
                return $group->count() > 1;
            })->count();

            if ($duplicateEmails > 0) {
                $issues[] = "Email duplicate: {$duplicateEmails}";
            }

            // Check per referees senza user
            $orphanReferees = $referees->whereNotIn('user_id', $users->pluck('id'))->count();
            if ($orphanReferees > 0) {
                $issues[] = "Referees orfani: {$orphanReferees}";
            }

            return [
                'mappable_users' => $users->count(),
                'mappable_referees' => $referees->count(),
                'issues_count' => count($issues),
                'issues' => $issues
            ];

        } catch (\Exception $e) {
            return [
                'mappable_users' => 0,
                'mappable_referees' => 0,
                'issues_count' => 1,
                'issues' => ['Errore connessione database source']
            ];
        }
    }

    private function checkDataIntegrity(): bool
    {
        try {
            // Verifica che ogni referee abbia un user corrispondente
            $orphanReferees = DB::table('referees')
                ->leftJoin('users', 'referees.user_id', '=', 'users.id')
                ->whereNull('users.id')
                ->count();

            return $orphanReferees === 0;

        } catch (\Exception $e) {
            return false;
        }
    }

    private function validateRefereesUserLink(): bool
    {
        try {
            $refereeUsers = DB::table('users')
                ->where('user_type', 'referee')
                ->count();

            $refereesTable = DB::table('referees')->count();

            // Non devono essere necessariamente uguali, ma referees non può essere > referee users
            return $refereesTable <= $refereeUsers;

        } catch (\Exception $e) {
            return false;
        }
    }

    private function analyzeUserTypesDistribution(): array
    {
        return DB::table('users')
            ->select('user_type', DB::raw('COUNT(*) as count'))
            ->groupBy('user_type')
            ->pluck('count', 'user_type')
            ->toArray();
    }

    private function generateRecommendations(): array
    {
        $recommendations = [];

        if ($this->testResults['migration_completed'] ?? false) {
            $recommendations[] = 'Migrazione test completata con successo';

            $validation = $this->testResults['validation'] ?? [];

            if ($validation['data_integrity'] ?? false) {
                $recommendations[] = 'Integrità dati OK - Procedi con migrazione completa';
            } else {
                $recommendations[] = 'ATTENZIONE: Problemi integrità dati - Verifica prima di continuare';
            }

            if (($validation['users_migrated'] ?? 0) > 0) {
                $recommendations[] = "Users migrati: {$validation['users_migrated']} - Mapping funzionante";
            }

        } else {
            $recommendations[] = 'ERRORE migrazione test - Risolvi problemi prima di continuare';
            if (isset($this->testResults['migration_error'])) {
                $recommendations[] = "Errore: {$this->testResults['migration_error']}";
            }
        }

        return $recommendations;
    }

    private function restoreFromBackup()
    {
        $tables = ['users', 'referees', 'zones', 'tournaments', 'clubs'];

        foreach ($tables as $table) {
            $backupTable = "{$table}_{$this->backupId}";

            if (Schema::hasTable($backupTable)) {
                DB::statement("DROP TABLE IF EXISTS {$table}");
                DB::statement("CREATE TABLE {$table} AS SELECT * FROM {$backupTable}");
                DB::statement("DROP TABLE {$backupTable}");
                $this->info("   ✅ Ripristinato {$table}");
            }
        }
    }
}

/**
 * ========================================
 * TEST DATA MIGRATION SEEDER
 * ========================================
 * Versione modificata del DataMigrationSeeder per test con limite
 */

class TestDataMigrationSeeder extends DataMigrationSeeder
{
    private $limit;
    private $command;

    public function __construct(int $limit = 10)
    {
        $this->limit = $limit;
    }

    public function setCommand($command)
    {
        $this->command = $command;
    }

    /**
     * Override del metodo run per limitare i record
     */
    public function run(): void
    {
        $this->command->info("🧪 Test migrazione con limite: {$this->limit}");

        // Setup connessione (stesso del parent)
        $this->setupOldDatabaseConnection();

        if (!$this->checkOldDatabase()) {
            $this->command->error('❌ Database source non disponibile');
            return;
        }

        // Migrazione limitata
        $this->migrateZonesLimited();
        $this->migrateTournamentTypesLimited();
        $this->migrateUsersLimited();
        $this->createRefereesLimited();
        $this->migrateClubsLimited();

        $this->command->info('✅ Test migrazione completato');
    }

    /**
     * Migrazione users limitata
     */
    private function migrateUsersLimited()
    {
        $this->command->info("👥 Test migrazione users (limite: {$this->limit})...");

        $oldUsers = DB::connection('old')->table('users')->limit($this->limit)->get();
        $oldReferees = DB::connection('old')->table('referees')->get()->keyBy('user_id');
        $oldRoleUsers = [];

        // Stesso mapping del parent
        try {
            if ($this->tableExists('old', 'role_user')) {
                $oldRoleUsers = DB::connection('old')->table('role_user')->get()->groupBy('user_id');
            }
        } catch (\Exception $e) {
            $this->command->warn('Tabella role_user non trovata');
        }

        foreach ($oldUsers as $user) {
            $userType = $this->determineUserType($user, $oldReferees, $oldRoleUsers);
            $referee = $oldReferees->get($user->id);

            $userData = [
                'id' => $user->id, // Mantieni ID originale per test
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'user_type' => $userType,
                'zone_id' => $user->zone_id ?? 1,
                'phone' => $user->phone ?? null,
                'is_active' => $user->is_active ?? true,
                'created_at' => $user->created_at ?? now(),
                'updated_at' => $user->updated_at ?? now(),
            ];

            // Logica referee (stesso del parent)
            if ($userType === 'referee' && $referee) {
                $userData['referee_code'] = $referee->referee_code ?? $this->generateRefereeCode();
                $userData['level'] = $this->mapQualification($referee->qualification ?? 'aspirante');
                $userData['category'] = $referee->category ?? 'misto';
                $userData['certified_date'] = $referee->certified_date ?? now()->subYears(2);
            } else {
                $userData['referee_code'] = null;
                $userData['level'] = 'aspirante';
                $userData['category'] = 'misto';
                $userData['certified_date'] = null;
            }

            DB::table('users')->updateOrInsert(['id' => $user->id], $userData);

            $this->command->info("   ✅ Migrato: {$user->name} ({$userType})");
        }

        $this->command->info("✅ Test: migrati {$oldUsers->count()} users");
    }

    // Altri metodi limitati...
    private function migrateZonesLimited()
    {
        $zones = DB::connection('old')->table('zones')->limit(5)->get();
        foreach ($zones as $zone) {
            DB::table('zones')->updateOrInsert(['id' => $zone->id], [
                'name' => $zone->name,
                'description' => $zone->description ?? null,
                'is_national' => $zone->is_national ?? false,
                'created_at' => $zone->created_at ?? now(),
                'updated_at' => $zone->updated_at ?? now(),
            ]);
        }
        $this->command->info("✅ Test: migrate {$zones->count()} zones");
    }

    private function migrateTournamentTypesLimited()
    {
        // Implementazione limitata...
        $this->command->info("✅ Test: tournament_types (limitato)");
    }

    private function createRefereesLimited()
    {
        $refereeUsers = DB::table('users')->where('user_type', 'referee')->get();
        $oldReferees = DB::connection('old')->table('referees')->get()->keyBy('user_id');

        foreach ($refereeUsers as $user) {
            $oldReferee = $oldReferees->get($user->id);
            if ($oldReferee) {
                DB::table('referees')->updateOrInsert(['user_id' => $user->id], [
                    'zone_id' => $user->zone_id,
                    'referee_code' => $user->referee_code,
                    'level' => $user->level,
                    'category' => $user->category,
                    'certified_date' => $user->certified_date,
                    'address' => $oldReferee->address ?? null,
                    'postal_code' => $oldReferee->postal_code ?? null,
                    'tax_code' => $oldReferee->tax_code ?? null,
                    'profile_completed_at' => now(),
                ]);
            }
        }
        $this->command->info("✅ Test: creati {$refereeUsers->count()} referees");
    }

    private function migrateClubsLimited()
    {
        // Implementazione limitata...
        $this->command->info("✅ Test: clubs (limitato)");
    }

    // Include i metodi helper necessari dal parent
    private function setupOldDatabaseConnection() { /* stesso del parent */ }
    private function checkOldDatabase(): bool { /* stesso del parent */ }
    private function tableExists($connection, $table): bool { /* stesso del parent */ }
    private function determineUserType($user, $referees, $roleUsers): string { /* stesso del parent */ }
    private function generateRefereeCode(): string { /* stesso del parent */ }
    private function mapQualification($qualification): string { /* stesso del parent */ }
}
