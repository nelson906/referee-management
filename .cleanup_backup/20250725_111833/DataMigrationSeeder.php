<?php

/**
 * ========================================
 * DATAMIGRATIONSEEDER - VERSIONE CORRETTA
 * ========================================
 * File: database/seeders/DataMigrationSeeder.php
 *
 * ✅ USER CENTRIC approach implementato
 * ✅ RefereeLevelsHelper utilizzato sempre
 * ✅ Zone mapping corretto (geografico + CRC funzionale)
 * ✅ Logging dettagliato per validazione
 * ✅ Solo extension data in referees table
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Helpers\RefereeLevelsHelper;
use Carbon\Carbon;

class DataMigrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Inizio migrazione dati dal database gestione_arbitri...');

        // 1. Setup connessione
        $this->setupOldDatabaseConnection();

        // 2. DEBUG configurazione
        $this->debugDatabaseConnection();

        // 3. Verifica database
        if (!$this->checkOldDatabase()) {
            $this->command->error('❌ Impossibile connettersi al database di origine');
            $this->command->info('💡 Suggerimenti:');
            $this->command->info('   1. Verifica che il database "gestione_arbitri" esista');
            $this->command->info('   2. Pulisci cache Laravel: php artisan config:clear');
            $this->command->info('   3. Verifica credenziali database in .env');
            return;
        }

        // 4. Procedi con migrazione nel giusto ordine...
        $this->command->info('✅ Database verificato, procedo con migrazione...');

        // ✅ ORDINE CORRETTO per evitare constraint violations
        $this->migrateZones();
        $this->migrateTournamentTypes();
        $this->migrateUsers(); // ✅ Con RefereeLevelsHelper e zone mapping corretto
        $this->createReferees(); // ✅ Solo extension data
        $this->migrateClubs();
        $this->migrateTournaments();
        $this->migrateAvailabilities();
        $this->migrateAssignments();
        $this->createSupportData();

        $this->command->info('✅ Migrazione dati completata!');

        // 5. Statistiche finali
        $this->showMigrationStats();

        // 6. Chiudi connessione
        $this->closeOldDatabaseConnection();
    }

    /**
     * ✅ MIGRAZIONE USERS con RefereeLevelsHelper e zone mapping corretto
     */
    private function migrateUsers()
    {
        $this->command->info('👥 Migrazione users (approccio USER CENTRIC)...');

        // 1. Importa tutti gli users di base
        $oldUsers = DB::connection('old')->table('users')->get();
        $oldReferees = DB::connection('old')->table('referees')->get()->keyBy('user_id');
        $oldRoleUsers = [];

        // 2. Carica role_user assignments
        try {
            if ($this->tableExists('old', 'role_user')) {
                $oldRoleUsers = DB::connection('old')->table('role_user')
                    ->select('user_id', 'role_id')
                    ->get()
                    ->groupBy('user_id');

                $this->command->info("🔗 Trovati role assignments: " . count($oldRoleUsers) . " users con ruoli");
            } else {
                $this->command->warn('⚠️  Tabella role_user non trovata, uso solo referees table');
            }
        } catch (\Exception $e) {
            $this->command->warn('⚠️  Errore lettura role_user: ' . $e->getMessage());
        }

        // 3. Debug struttura roles
        $this->debugRolesStructure();

        // 4. Verifica zone esistenti prima della migrazione
        $this->verifyZones();

        $migrationStats = [
            'total_users' => 0,
            'referees' => 0,
            'admins' => 0,
            'zone_mappings' => [],
            'level_mappings' => [],
            'errors' => []
        ];

        foreach ($oldUsers as $user) {
            try {
                $migrationStats['total_users']++;

                // Determina il tipo di utente
                $userType = $this->determineUserType($user, $oldReferees, $oldRoleUsers);
                $referee = $oldReferees->get($user->id);

                // ✅ FIX: Gestione zone_id corretta (da referees.zone_id)
                $zoneId = $this->getValidZoneId($user, $referee, $userType);

                // Dati base per tutti gli utenti
                $userData = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'password' => $user->password,
                    'remember_token' => $user->remember_token,
                    'user_type' => $userType,
                    'zone_id' => $zoneId,
                    'phone' => $user->phone ?? null,
                    'city' => null, // Non presente nel vecchio DB
                    'is_active' => $user->is_active ?? true,
                    'created_at' => $user->created_at ?? now(),
                    'updated_at' => $user->updated_at ?? now(),
                ];

                // ✅ FIX: Campi referee con RefereeLevelsHelper
                if ($userType === 'referee' && $referee) {
                    // Dati reali dell'arbitro
                    $userData['referee_code'] = $referee->referee_code ?? $this->generateRefereeCode();
                    $userData['level'] = $this->mapQualificationWithHelper($referee->qualification ?? 'Aspirante');
                    $userData['category'] = $referee->category ?? 'misto';
                    $userData['certified_date'] = $referee->certified_date ?? now()->subYears(2);

                    // Statistiche
                    $migrationStats['referees']++;
                    $this->trackLevelMapping($migrationStats, $referee->qualification ?? 'Aspirante', $userData['level']);

                    $this->command->info("🔄 Migrating referee: {$user->name} (code: {$userData['referee_code']}, level: {$userData['level']}, zone: {$zoneId})");
                } else {
                    // ✅ FIX: Valori corretti per utenti non-arbitri
                    $userData['referee_code'] = null; // NULL per non-arbitri
                    $userData['level'] = RefereeLevelsHelper::normalize('Aspirante');   // Usa helper con default
                    $userData['category'] = 'misto';    // Categoria di default
                    $userData['certified_date'] = null;

                    // Statistiche
                    $migrationStats['admins']++;

                    $this->command->info("🔄 Migrating user: {$user->name} ({$userType}, level: {$userData['level']}, zone: " . ($zoneId ? $zoneId : 'NULL') . ")");
                }

                // Traccia mapping zone
                $this->trackZoneMapping($migrationStats, $referee, $zoneId);

                // Insert/Update user
                DB::table('users')->updateOrInsert(
                    ['id' => $user->id],
                    $userData
                );

            } catch (\Exception $e) {
                $migrationStats['errors'][] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'error' => $e->getMessage()
                ];

                $this->command->error("❌ Errore migrazione user {$user->id} ({$user->name}): " . $e->getMessage());

                // Debug dettagliato per questo user
                $this->debugUserMigration($user, $oldReferees, $oldRoleUsers);

                // Continua con il prossimo user
                continue;
            }
        }

        // Report finale migrazione users
        $this->showUserMigrationReport($migrationStats);
    }

    /**
     * ✅ NUOVO: Mappa qualifiche usando RefereeLevelsHelper
     */
    private function mapQualificationWithHelper(?string $oldQualification): string
    {
        if (empty($oldQualification)) {
            return RefereeLevelsHelper::normalize('Aspirante');
        }

        // Usa il helper per normalizzare
        $normalized = RefereeLevelsHelper::normalize($oldQualification);

        // Se il helper non riconosce il valore, usa mapping manuale di fallback
        if (!$normalized || !RefereeLevelsHelper::isValid($normalized)) {
            $normalized = $this->mapQualificationFallback($oldQualification);
        }

        // Log per debug
        $this->command->info("   📝 Qualification mapping: '{$oldQualification}' → '{$normalized}'");

        return $normalized;
    }

    /**
     * ✅ FALLBACK: Mapping manuale per casi non gestiti dall'helper
     */
    private function mapQualificationFallback(string $oldQualification): string
    {
        $mapping = [
            'primo livello' => '1_livello',
            '1° livello' => '1_livello',
            '1_livello' => '1_livello',
            '1 livello' => '1_livello',
            'regionale' => 'Regionale',
            'nazionale/internazionale' => 'Nazionale',
            'nazionale' => 'Nazionale',
            'internazionale' => 'Internazionale',
            'archivio' => 'Archivio',
            'aspirante' => 'Aspirante'
        ];

        $key = strtolower(trim($oldQualification));
        $result = $mapping[$key] ?? 'Aspirante';

        $this->command->warn("   ⚠️  Fallback mapping: '{$oldQualification}' → '{$result}'");

        return $result;
    }

    /**
     * ✅ NUOVO: Gestione corretta zone_id (da referees.zone_id)
     */
    private function getValidZoneId($user, $referee, string $userType): ?int
    {
        // 1. Per Admin (tutti i tipi), zona = NULL
        if (in_array($userType, ['super_admin', 'national_admin', 'admin'])) {
            $this->command->info("   🏛️  Admin user ({$userType}): zone_id = NULL");
            return null;
        }

        // 2. Per Referee: leggi zona da referees.zone_id
        if ($userType === 'referee' && $referee && isset($referee->zone_id)) {
            if ($referee->zone_id > 0) {
                // Mappa zona per ID (1=SZR1, 2=SZR2, ..., 8=CRC)
                $zoneName = $this->mapZoneIdToName($referee->zone_id);
                $targetZone = DB::table('zones')->where('name', $zoneName)->first();

                if ($targetZone) {
                    $this->command->info("   📍 Referee zone mapping: DB zone_id {$referee->zone_id} → {$zoneName} → target zone {$targetZone->id}");
                    return $targetZone->id;
                } else {
                    $this->command->error("   ❌ Zona target '{$zoneName}' non trovata per referee {$user->name}");
                }
            } else {
                $this->command->warn("   ⚠️  Referee {$user->name} ha zone_id = 0 o NULL nel DB origine");
            }
        }

        // 3. ⚠️ ANOMALIA: Referee senza zona (NON dovrebbe succedere)
        if ($userType === 'referee') {
            $this->command->error("   🚨 ANOMALIA: Referee {$user->name} senza zona valida! Assegno SZR1 come fallback");

            $fallbackZone = DB::table('zones')->where('name', 'SZR1')->first();
            return $fallbackZone ? $fallbackZone->id : 1;
        }

        // 4. Fallback finale
        return null;
    }

    /**
     * ✅ NUOVO: Mappa zone_id del DB originale a nome zona
     */
    private function mapZoneIdToName(int $zoneId): string
    {
        $mapping = [
            1 => 'SZR1',
            2 => 'SZR2',
            3 => 'SZR3',
            4 => 'SZR4',
            5 => 'SZR5',
            6 => 'SZR6',
            7 => 'SZR7',
            8 => 'CRC'
        ];

        return $mapping[$zoneId] ?? 'SZR1'; // Fallback a SZR1
    }

    /**
     * ✅ Determina il tipo di utente dal vecchio database
     */
    private function determineUserType($oldUser, $oldReferees, $oldRoleUsers): string
    {
        // 1. Controlla se ha ruoli admin tramite role_user
        if (isset($oldRoleUsers[$oldUser->id])) {
            $roles = $oldRoleUsers[$oldUser->id];

            foreach ($roles as $roleAssignment) {
                $roleId = $roleAssignment->role_id;

                switch ($roleId) {
                    case 1:
                        $this->command->info("   🔑 Super Admin role trovato per: {$oldUser->name}");
                        return 'super_admin';
                    case 2:
                        $this->command->info("   🔑 National Admin role trovato per: {$oldUser->name}");
                        return 'national_admin';
                    case 3:
                        $this->command->info("   🔑 Zone Admin role trovato per: {$oldUser->name}");
                        return 'admin';
                    case 4:
                        // Role 4 = Referee, continua a controllare altri ruoli
                        break;
                    default:
                        $this->command->warn("   ⚠️  Role ID non riconosciuto: {$roleId} per user: {$oldUser->name}");
                        break;
                }
            }
        }

        // 2. Se non ha ruoli admin, è un referee
        if ($oldReferees->has($oldUser->id)) {
            return 'referee';
        }

        // 3. Fallback su campi legacy
        if (isset($oldUser->is_super_admin) && $oldUser->is_super_admin) {
            $this->command->info("   🔄 Legacy super_admin flag per: {$oldUser->name}");
            return 'super_admin';
        }

        if (isset($oldUser->is_admin) && $oldUser->is_admin) {
            $this->command->info("   🔄 Legacy admin flag per: {$oldUser->name}");
            return 'admin';
        }

        // 4. Default: referee
        $this->command->info("   📝 Default referee assignment per: {$oldUser->name}");
        return 'referee';
    }

    /**
     * ✅ NUOVO: Crea referees (SOLO dati estesi) per approccio USER CENTRIC
     */
    private function createReferees()
    {
        $this->command->info('🏌️ Creazione referees (solo dati estesi - USER CENTRIC)...');

        $refereeUsers = DB::table('users')->where('user_type', 'referee')->get();
        $oldReferees = DB::connection('old')->table('referees')->get()->keyBy('user_id');

        $extensionStats = [
            'total_referees' => 0,
            'with_address' => 0,
            'with_badge' => 0,
            'with_bio' => 0,
            'missing_data' => []
        ];

        foreach ($refereeUsers as $user) {
            $oldReferee = $oldReferees->get($user->id);
            $extensionStats['total_referees']++;

            // ✅ USER CENTRIC: Solo dati ESTESI, NO duplicazione
            $refereeExtensionData = [
                // ✅ EXTENDED ADDRESS INFO (da DB originale)
                'address' => $oldReferee->address ?? null,
                'postal_code' => $oldReferee->postal_code ?? null,
                'tax_code' => $oldReferee->tax_code ?? null,

                // ✅ CERTIFICATION DETAILS (da DB originale)
                'badge_number' => $oldReferee->badge_number ?? null,
                'first_certification_date' => $oldReferee->certified_date ?? $oldReferee->first_certification_date ?? $user->certified_date,
                'last_renewal_date' => $oldReferee->last_renewal_date ?? null,
                'expiry_date' => $oldReferee->expiry_date ?? null,

                // ✅ REFEREE PROFILE DATA (campi nuovi - NULL se mancanti)
                'bio' => $oldReferee->bio ?? null,
                'experience_years' => $oldReferee->experience_years ?? 0,
                'qualifications' => $oldReferee->qualifications ?? json_encode([]),
                'languages' => $oldReferee->languages ?? json_encode(['italiano']),
                'specializations' => $oldReferee->specializations ?? json_encode([]),

                // ✅ AVAILABILITY & PREFERENCES (campi nuovi)
                'available_for_international' => $oldReferee->available_for_international ?? false,
                'preferences' => $oldReferee->preferences ?? json_encode([]),

                // ✅ STATISTICS (campi nuovi)
                'total_tournaments' => $oldReferee->total_tournaments ?? 0,
                'tournaments_current_year' => $oldReferee->tournaments_current_year ?? 0,

                // ✅ PROFILE STATUS
                'profile_completed_at' => $oldReferee->profile_completed_at ?? now(),

                'created_at' => $oldReferee->created_at ?? now(),
                'updated_at' => $oldReferee->updated_at ?? now(),
            ];

            // Statistiche sui dati disponibili
            if ($refereeExtensionData['address']) $extensionStats['with_address']++;
            if ($refereeExtensionData['badge_number']) $extensionStats['with_badge']++;
            if ($refereeExtensionData['bio']) $extensionStats['with_bio']++;

            // Traccia dati mancanti
            if (!$oldReferee) {
                $extensionStats['missing_data'][] = $user->name;
            }

            DB::table('referees')->updateOrInsert(
                ['user_id' => $user->id],
                $refereeExtensionData
            );

            $this->command->info("   ✅ Extended data per referee: {$user->name}");
        }

        $this->showRefereeExtensionReport($extensionStats);
    }

    // ========================================
    // STATISTICHE E REPORT
    // ========================================

    /**
     * ✅ NUOVO: Report dettagliato migrazione users
     */
    private function showUserMigrationReport(array $stats)
    {
        $this->command->info("📊 REPORT MIGRAZIONE USERS:");
        $this->command->info("   Total users: {$stats['total_users']}");
        $this->command->info("   Referees: {$stats['referees']}");
        $this->command->info("   Admins: {$stats['admins']}");

        if (!empty($stats['zone_mappings'])) {
            $this->command->info("   Zone mappings:");
            foreach ($stats['zone_mappings'] as $mapping => $count) {
                $this->command->info("     {$mapping}: {$count} users");
            }
        }

        if (!empty($stats['level_mappings'])) {
            $this->command->info("   Level mappings:");
            foreach ($stats['level_mappings'] as $mapping => $count) {
                $this->command->info("     {$mapping}: {$count} mappings");
            }
        }

        if (!empty($stats['errors'])) {
            $this->command->error("   Errori: " . count($stats['errors']));
            foreach ($stats['errors'] as $error) {
                $this->command->error("     User {$error['user_id']} ({$error['user_name']}): {$error['error']}");
            }
        }
    }

    /**
     * ✅ NUOVO: Report creazione referees extension
     */
    private function showRefereeExtensionReport(array $stats)
    {
        $this->command->info("📊 REPORT REFEREES EXTENSION:");
        $this->command->info("   Total referees: {$stats['total_referees']}");
        $this->command->info("   With address: {$stats['with_address']}");
        $this->command->info("   With badge number: {$stats['with_badge']}");
        $this->command->info("   With bio: {$stats['with_bio']}");

        if (!empty($stats['missing_data'])) {
            $this->command->warn("   Missing original referee data: " . count($stats['missing_data']));
            foreach ($stats['missing_data'] as $name) {
                $this->command->warn("     {$name}");
            }
        }
    }

    /**
     * ✅ NUOVO: Traccia mapping zone per statistiche
     */
    private function trackZoneMapping(array &$stats, $referee, ?int $targetZoneId)
    {
        $sourceZone = $referee ? ($referee->zone_id ?? 'NULL') : 'NULL';
        $targetZone = $targetZoneId ?? 'NULL';
        $mapping = "Source {$sourceZone} → Target {$targetZone}";

        if (!isset($stats['zone_mappings'][$mapping])) {
            $stats['zone_mappings'][$mapping] = 0;
        }
        $stats['zone_mappings'][$mapping]++;
    }

    /**
     * ✅ NUOVO: Traccia mapping livelli per statistiche
     */
    private function trackLevelMapping(array &$stats, ?string $sourceLevel, string $targetLevel)
    {
        $mapping = "'{$sourceLevel}' → '{$targetLevel}'";

        if (!isset($stats['level_mappings'][$mapping])) {
            $stats['level_mappings'][$mapping] = 0;
        }
        $stats['level_mappings'][$mapping]++;
    }

    /**
     * ✅ NUOVO: Statistiche finali migrazione
     */
    private function showMigrationStats()
    {
        $this->command->info('📊 STATISTICHE FINALI MIGRAZIONE:');

        $stats = [
            'zones' => DB::table('zones')->count(),
            'users_total' => DB::table('users')->count(),
            'users_referees' => DB::table('users')->where('user_type', 'referee')->count(),
            'users_admins' => DB::table('users')->whereIn('user_type', ['admin', 'national_admin', 'super_admin'])->count(),
            'referees_extension' => DB::table('referees')->count(),
            'clubs' => DB::table('clubs')->count(),
            'tournaments' => DB::table('tournaments')->count(),
            'availabilities' => DB::table('availabilities')->count(),
            'assignments' => DB::table('assignments')->count(),
        ];

        foreach ($stats as $table => $count) {
            $this->command->info("   {$table}: {$count}");
        }

        // Verifica coerenza USER CENTRIC
        if ($stats['users_referees'] === $stats['referees_extension']) {
            $this->command->info('✅ USER CENTRIC: Coerenza referees verificata');
        } else {
            $this->command->error('❌ USER CENTRIC: Incoerenza referees! Users: ' . $stats['users_referees'] . ', Extensions: ' . $stats['referees_extension']);
        }
    }

    // ========================================
    // METODI DEBUG E VERIFICA
    // ========================================

    /**
     * ✅ NUOVO: Verifica zone esistenti
     */
    private function verifyZones()
    {
        $zoneCount = DB::table('zones')->count();
        $this->command->info("🔍 Zone disponibili nel target DB: {$zoneCount}");

        if ($zoneCount === 0) {
            $this->command->error("❌ ERRORE: Nessuna zona presente! Esegui prima migrateZones()");
            throw new \Exception("Nessuna zona presente nel database target");
        }

        // Mostra zone disponibili
        $zones = DB::table('zones')->select('id', 'name', 'is_national')->get();
        foreach ($zones as $zone) {
            $marker = $zone->is_national ? '🏛️' : '📍';
            $this->command->info("   {$marker} Zone {$zone->id}: {$zone->name}");
        }
    }

    /**
     * 🔍 DEBUG: Migrazione user specifica
     */
    private function debugUserMigration($user, $oldReferees, $oldRoleUsers)
    {
        $this->command->info("🔍 DEBUG User {$user->id}:");
        $this->command->info("   Name: {$user->name}");
        $this->command->info("   Email: {$user->email}");

        // Check referee
        $referee = $oldReferees->get($user->id);
        if ($referee) {
            $this->command->info("   Referee qualification: " . ($referee->qualification ?? 'NULL'));
            $this->command->info("   Referee zone_id: " . ($referee->zone_id ?? 'NULL'));
            $normalized = $this->mapQualificationWithHelper($referee->qualification ?? 'Aspirante');
            $this->command->info("   Normalized level: {$normalized}");
        } else {
            $this->command->info("   No referee record found");
        }

        // Check roles
        if (isset($oldRoleUsers[$user->id])) {
            $roles = $oldRoleUsers[$user->id];
            $roleIds = $roles->pluck('role_id')->toArray();
            $this->command->info("   Role IDs: " . implode(', ', $roleIds));
        } else {
            $this->command->info("   No roles found");
        }
    }

    /**
     * 🔍 DEBUG: Verifica struttura roles nel database source
     */
    private function debugRolesStructure()
    {
        try {
            if ($this->tableExists('old', 'roles')) {
                $roles = DB::connection('old')->table('roles')
                    ->select('id', 'name', 'guard_name')
                    ->orderBy('id')
                    ->get();

                $this->command->info('🔍 DEBUG: Struttura roles nel database source:');
                foreach ($roles as $role) {
                    $this->command->info("   ID: {$role->id} | Name: '{$role->name}' | Guard: {$role->guard_name}");
                }

                // Conta assignments per role
                if ($this->tableExists('old', 'role_user')) {
                    $assignments = DB::connection('old')->table('role_user')
                        ->select('role_id', DB::raw('COUNT(*) as user_count'))
                        ->groupBy('role_id')
                        ->get();

                    $this->command->info('👥 Distribuzione role assignments:');
                    foreach ($assignments as $assignment) {
                        $roleName = $roles->firstWhere('id', $assignment->role_id)->name ?? 'Unknown';
                        $this->command->info("   Role {$assignment->role_id} ('{$roleName}'): {$assignment->user_count} users");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->command->warn("⚠️  Errore debug roles: " . $e->getMessage());
        }
    }

    /**
     * ✅ Controlla se una tabella esiste nel database
     */
    private function tableExists($connection, $table): bool
    {
        try {
            DB::connection($connection)->table($table)->limit(1)->get();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * ✅ Genera codice arbitro univoco
     */
    private function generateRefereeCode(): string
    {
        do {
            $code = 'ARB' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (DB::table('users')->where('referee_code', $code)->exists());

        return $code;
    }

    // ========================================
    // SETUP E METODI HELPER ORIGINALI
    // ========================================

    /**
     * ✅ Setup della connessione al database di origine
     */
    private function setupOldDatabaseConnection()
    {
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

        $this->command->info("🔗 Configurazione connessione database di origine:");
        $this->command->info("   Host: {$oldDbConfig['host']}:{$oldDbConfig['port']}");
        $this->command->info("   Database: {$oldDbConfig['database']}");
        $this->command->info("   Username: {$oldDbConfig['username']}");
    }

    private function checkOldDatabase(): bool
    {
        try {
            $this->command->info('🔍 Verifica connessione al database gestione_arbitri...');

            // Test connessione PDO
            $pdo = DB::connection('old')->getPdo();
            $this->command->info('✅ Connessione PDO stabilita');

            // Verifica database exists
            $dbName = 'gestione_arbitri';
            $result = DB::connection('old')->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);

            if (empty($result)) {
                $this->command->error("❌ Database '{$dbName}' non trovato");
                return false;
            }

            $this->command->info("✅ Database '{$dbName}' trovato");

            // Test query semplice
            $userCount = DB::connection('old')->table('users')->count();
            $this->command->info("✅ Test query: {$userCount} users nel database source");

            return true;

        } catch (\Exception $e) {
            $this->command->error('❌ Errore connessione: ' . $e->getMessage());
            return false;
        }
    }

    private function debugDatabaseConnection()
    {
        $this->command->info('🔍 DEBUG: Verifica configurazione database...');

        // Verifica configurazione attuale
        $config = config('database.connections.old');
        $this->command->info('Database configurato: ' . ($config['database'] ?? 'NON CONFIGURATO'));

        // Verifica database esistenti
        try {
            $databases = DB::select('SHOW DATABASES');
            $this->command->info('📊 Database disponibili:');
            foreach ($databases as $db) {
                $dbName = $db->Database ?? $db->{'Database'};
                $marker = ($dbName === 'gestione_arbitri') ? ' ✅' : '';
                $this->command->info("   - {$dbName}{$marker}");
            }
        } catch (\Exception $e) {
            $this->command->error('Errore lista database: ' . $e->getMessage());
        }
    }

    private function closeOldDatabaseConnection()
    {
        try {
            DB::disconnect('old');
            $this->command->info('🔌 Connessione database gestione_arbitri chiusa');
        } catch (\Exception $e) {
            $this->command->warn('⚠️ Errore chiusura connessione: ' . $e->getMessage());
        }
    }

    // ========================================
    // METODI DI MIGRAZIONE ORIGINALI
    // ========================================

    /**
     * ✅ Migrazione zones
     */
    private function migrateZones()
    {
        $this->command->info('📍 Migrazione zones...');

        $oldZones = DB::connection('old')->table('zones')->get();

        foreach ($oldZones as $zone) {
            DB::table('zones')->updateOrInsert(
                ['id' => $zone->id],
                [
                    'name' => $zone->name,
                    'code' => $zone->name,
                    'description' => $zone->description ?? null,
                    'is_national' => $zone->is_national ?? false,
                    'is_active' => $zone->is_active ?? true,
                    'header_document_path' => $zone->header_document_path ?? null,
                    'header_updated_at' => $zone->header_updated_at ?? null,
                    'header_updated_by' => $zone->header_updated_by ?? null,
                    'created_at' => $zone->created_at ?? now(),
                    'updated_at' => $zone->updated_at ?? now(),
                ]
            );
        }

        $this->command->info("✅ Migrati " . $oldZones->count() . " zones");
    }

    /**
     * ✅ Migrazione tournament_types
     */
    private function migrateTournamentTypes()
    {
        $this->command->info('🏆 Migrazione tournament_types...');

        // ✅ VERIFICA STRUTTURA TABELLA TARGET
        try {
            $columns = DB::select("DESCRIBE tournament_types");
            $this->command->info('🔍 Struttura tabella tournament_types target:');
            foreach ($columns as $col) {
                $this->command->info("   - {$col->Field} ({$col->Type})");
            }
        } catch (\Exception $e) {
            $this->command->error("❌ Errore lettura struttura tournament_types: " . $e->getMessage());
            return;
        }

        try {
            $oldTypes = DB::connection('old')->table('tournament_types')->get();

            $this->command->info("🔍 Trovati " . $oldTypes->count() . " tournament_types nel DB originale");

            foreach ($oldTypes as $type) {
                // ✅ FIX: Usa solo campi che esistono realmente nel DB originale
                // Campi reali: id, code, name, description, active, is_national, created_at, updated_at

                $settings = [
                    'required_referee_level' => 'primo_livello', // ✅ ENUM corretto
                    'min_referees' => 1, // Default
                    'max_referees' => 3, // Default
                    'visibility_zones' => ($type->is_national ?? false) ? 'all' : 'own',
                    'special_requirements' => null,
                    'notification_templates' => [],
                ];

                DB::table('tournament_types')->updateOrInsert(
                    ['id' => $type->id],
                    [
                        'name' => $type->name,
                        'short_name' => $type->code ?? $type->name ?? 'TT' . $type->id, // ✅ CORRETTO: short_name
                        'description' => $type->description ?? null,
                        'is_national' => $type->is_national ?? false,
                        'level' => ($type->is_national ?? false) ? 'nazionale' : 'zonale',
                        'required_level' => 'primo_livello', // ✅ ENUM corretto del target DB
                        'sort_order' => $type->id * 10, // Usa ID per ordinamento
                        'is_active' => $type->active ?? true, // ✅ Campo reale: 'active'
                        'min_referees' => 1, // Default
                        'max_referees' => 3, // Default
                        'settings' => json_encode($settings),
                        'created_at' => $type->created_at ?? now(),
                        'updated_at' => $type->updated_at ?? now(),
                    ]
                );

                $this->command->info("   ✅ Migrato tournament_type ID: {$type->id} - {$type->name}");
            }

            $this->command->info("✅ Migrati " . $oldTypes->count() . " tournament_types");
        } catch (\Exception $e) {
            $this->command->warn("⚠️ Tabella tournament_types non trovata o errore: " . $e->getMessage());
        }
    }

    /**
     * ✅ Migrazione clubs
     */
    private function migrateClubs()
    {
        $this->command->info('🏌️ Migrazione clubs...');

        try {
            $oldClubs = DB::connection('old')->table('clubs')->get();

            foreach ($oldClubs as $club) {
                DB::table('clubs')->updateOrInsert(
                    ['id' => $club->id],
                    [
                        'name' => $club->name,
                        'code' => $club->short_name ?? null,
                        'email' => $club->email ?? null,
                        'phone' => $club->phone ?? null,
                        'address' => $club->address ?? null,
                        'contact_person' => $club->contact_person ?? null,
                        'zone_id' => $club->zone_id ?? 1,
                        'notes' => $club->notes ?? null,
                        'is_active' => $club->is_active ?? true,
                        'created_at' => $club->created_at ?? now(),
                        'updated_at' => $club->updated_at ?? now(),
                    ]
                );
            }

            $this->command->info("✅ Migrati " . $oldClubs->count() . " clubs");
        } catch (\Exception $e) {
            $this->command->warn("⚠️ Errore migrazione clubs: " . $e->getMessage());
        }
    }

    /**
     * ✅ Migrazione tournaments
     */
    private function migrateTournaments()
    {
        $this->command->info('🏆 Migrazione tournaments...');

        try {
            $oldTournaments = DB::connection('old')->table('tournaments')->get();

            foreach ($oldTournaments as $tournament) {
                // ✅ Verifica che tournament_type_id esista
                $typeExists = DB::table('tournament_types')->where('id', $tournament->type_id)->exists();
                if (!$typeExists) {
                    $this->command->warn("   ⚠️ Tournament type ID {$tournament->type_id} non esiste per tournament: {$tournament->name} - uso fallback ID 1");
                    $tournamentTypeId = 1; // Fallback al primo type
                } else {
                    $tournamentTypeId = $tournament->type_id;
                }

                DB::table('tournaments')->updateOrInsert(
                    ['id' => $tournament->id],
                    [
                        'name' => $tournament->name,
                        'tournament_type_id' => $tournamentTypeId,
                        'club_id' => $tournament->club_id ?? null,
                        'zone_id' => $tournament->zone_id ?? 1,
                        'start_date' => $tournament->start_date,
                        'end_date' => $tournament->end_date,
                        'availability_deadline' => $tournament->availability_deadline ?? $tournament->start_date,
                        'status' => (!in_array($tournament->status, ['draft', 'open', 'closed', 'assigned', 'completed'])) ? 'draft' : $tournament->status,
                        'description' => $tournament->description ?? null,
                        'notes' => $tournament->notes ?? null,
                        'created_at' => $tournament->created_at ?? now(),
                        'updated_at' => $tournament->updated_at ?? now(),
                    ]
                );
            }

            $this->command->info("✅ Migrati " . $oldTournaments->count() . " tournaments");
        } catch (\Exception $e) {
            $this->command->warn("⚠️ Errore migrazione tournaments: " . $e->getMessage());
        }
    }

    /**
     * ✅ Migrazione availabilities
     */
    private function migrateAvailabilities()
    {
        $this->command->info('📅 Migrazione availabilities...');

        try {
            $oldAvailabilities = DB::connection('old')->table('availabilities')->get();

            foreach ($oldAvailabilities as $availability) {
                // ✅ Verifica che referee_id esista nella tabella users
                $userExists = DB::table('users')->where('id', $availability->referee_id)->exists();
                if (!$userExists) {
                    $this->command->warn("   ⚠️ User ID {$availability->referee_id} non esiste per availability tournament {$availability->tournament_id}");
                    continue;
                }

                // ✅ Verifica che tournament_id esista nella tabella tournaments
                $tournamentExists = DB::table('tournaments')->where('id', $availability->tournament_id)->exists();
                if (!$tournamentExists) {
                    $this->command->warn("   ⚠️ Tournament ID {$availability->tournament_id} non esiste per availability user {$availability->referee_id}");
                    continue;
                }

                DB::table('availabilities')->updateOrInsert(
                    [
                        'user_id' => $availability->referee_id,
                        'tournament_id' => $availability->tournament_id
                    ],
                    [
                        'notes' => $availability->notes ?? null,
                        'submitted_at' => $availability->submitted_at ?? now(),
                        'created_at' => $availability->created_at ?? now(),
                        'updated_at' => $availability->updated_at ?? now(),
                    ]
                );
            }

            $this->command->info("✅ Migrate " . $oldAvailabilities->count() . " availabilities");
        } catch (\Exception $e) {
            $this->command->warn("⚠️ Errore migrazione availabilities: " . $e->getMessage());
        }
    }

    /**
     * ✅ Migrazione assignments
     */
    private function migrateAssignments()
    {
        $this->command->info('📋 Migrazione assignments...');

        try {
            $oldAssignments = DB::connection('old')->table('assignments')->get();

            foreach ($oldAssignments as $assignment) {
                // ✅ Verifica che referee_id esista
                $userExists = DB::table('users')->where('id', $assignment->referee_id)->exists();
                if (!$userExists) {
                    $this->command->warn("   ⚠️ User ID {$assignment->referee_id} non esiste per assignment tournament {$assignment->tournament_id}");
                    continue;
                }

                // ✅ Verifica che tournament_id esista nella tabella tournaments
                $tournamentExists = DB::table('tournaments')->where('id', $assignment->tournament_id)->exists();
                if (!$tournamentExists) {
                    $this->command->warn("   ⚠️ Tournament ID {$assignment->tournament_id} non esiste per assignment user {$assignment->referee_id}");
                    continue;
                }

                DB::table('assignments')->updateOrInsert(
                    [
                        'user_id' => $assignment->referee_id,
                        'tournament_id' => $assignment->tournament_id
                    ],
                    [
                        'assigned_by_id' => $assignment->assigned_by ?? 1,
                        'role' => $assignment->role ?? 'Arbitro',
                        'notes' => $assignment->notes ?? null,
                        'is_confirmed' => $assignment->is_confirmed ?? false,
                        'assigned_at' => $assignment->assigned_at ?? now(),
                        'created_at' => $assignment->created_at ?? now(),
                        'updated_at' => $assignment->updated_at ?? now(),
                    ]
                );
            }

            $this->command->info("✅ Migrati " . $oldAssignments->count() . " assignments");
        } catch (\Exception $e) {
            $this->command->warn("⚠️ Errore migrazione assignments: " . $e->getMessage());
        }
    }

    /**
     * ✅ Crea dati di supporto
     */
    private function createSupportData()
    {
        $this->command->info('🔧 Creazione dati di supporto...');

        // Settings di default
        $defaultSettings = [
            ['key' => 'system.name', 'value' => 'Sistema Gestione Arbitri Golf'],
            ['key' => 'system.email', 'value' => 'admin@federgolf.it'],
            ['key' => 'notifications.enabled', 'value' => '1'],
        ];

        foreach ($defaultSettings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info("✅ Creati dati di supporto");
    }
}
