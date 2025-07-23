<?php

/**
 * ========================================
 * DATAMIGRATIONSEEDER - VERSIONE FINALE
 * ========================================
 * File: database/seeders/DataMigrationSeeder.php
 * Sostituisci COMPLETAMENTE il contenuto del file con questo codice
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
        $this->migrateUsers(); // ✅ Con RefereeLevelsHelper
        $this->createReferees(); // ✅ Extension table
        $this->migrateClubs();
        $this->migrateTournaments();
        $this->migrateAvailabilities();
        $this->migrateAssignments();
        $this->createSupportData();

        $this->command->info('✅ Migrazione dati completata!');

        // 5. Chiudi connessione
        $this->closeOldDatabaseConnection();
    }

    /**
     * ✅ MIGRAZIONE USERS con RefereeLevelsHelper
     */
    private function migrateUsers()
    {
        $this->command->info('👥 Migrazione users (con unificazione referees)...');

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

        foreach ($oldUsers as $user) {
            try {
                // Determina il tipo di utente
                $userType = $this->determineUserType($user, $oldReferees, $oldRoleUsers);
                $referee = $oldReferees->get($user->id);

                // ✅ FIX: Gestione zone_id sicura
                $zoneId = $this->getValidZoneId($user, $userType);

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
                    $userData['level'] = $this->mapQualificationWithHelper($referee->qualification ?? 'aspirante');
                    $userData['category'] = $referee->category ?? 'misto';
                    $userData['certified_date'] = $referee->certified_date ?? now()->subYears(2);

                    $this->command->info("🔄 Migrating referee: {$user->name} (code: {$userData['referee_code']}, level: {$userData['level']})");
                } else {
                    // ✅ FIX: Valori di default per utenti non-arbitri usando Helper
                    $userData['referee_code'] = null; // NULL per non-arbitri
                    $userData['level'] = RefereeLevelsHelper::normalize('aspirante');   // Usa helper
                    $userData['category'] = 'misto';    // Categoria di default
                    $userData['certified_date'] = null;

                    $this->command->info("🔄 Migrating user: {$user->name} ({$userType}, level: {$userData['level']})");
                }

                // Insert/Update user
                DB::table('users')->updateOrInsert(
                    ['id' => $user->id],
                    $userData
                );

            } catch (\Exception $e) {
                $this->command->error("❌ Errore migrazione user {$user->id} ({$user->name}): " . $e->getMessage());

                // Debug dettagliato per questo user
                $this->debugUserMigration($user, $oldReferees, $oldRoleUsers);

                // Continua con il prossimo user
                continue;
            }
        }

        $this->command->info("✅ Migrati " . $oldUsers->count() . " users (con unificazione referees)");
        $this->command->info('Arbitri: ' . DB::table('users')->where('user_type', 'referee')->count());
        $this->command->info('Admin: ' . DB::table('users')->whereIn('user_type', ['admin', 'national_admin', 'super_admin'])->count());
    }

    /**
     * ✅ NUOVO: Mappa qualifiche usando RefereeLevelsHelper
     */
    private function mapQualificationWithHelper(?string $oldQualification): string
    {
        if (empty($oldQualification)) {
            return RefereeLevelsHelper::normalize('aspirante');
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
     * ✅ NUOVO: Gestione sicura zone_id
     */
    private function getValidZoneId($user, string $userType): int
    {
        // 1. Se user ha zone_id valida, usala
        if (isset($user->zone_id) && $user->zone_id > 0) {
            $zoneExists = DB::table('zones')->where('id', $user->zone_id)->exists();
            if ($zoneExists) {
                return $user->zone_id;
            } else {
                $this->command->warn("   ⚠️  Zone {$user->zone_id} non esiste per user {$user->name}");
            }
        }

        // 2. Per Super Admin e National Admin, usa zona nazionale (se esiste)
        if (in_array($userType, ['super_admin', 'national_admin'])) {
            $nationalZone = DB::table('zones')->where('is_national', true)->first();
            if ($nationalZone) {
                $this->command->info("   🏛️  Assegnata zona nazionale ({$nationalZone->id}) per {$userType}");
                return $nationalZone->id;
            }
        }

        // 3. Fallback: prima zona disponibile
        $firstZone = DB::table('zones')->orderBy('id')->first();
        if ($firstZone) {
            $this->command->warn("   📍 Fallback zona {$firstZone->id} per user {$user->name}");
            return $firstZone->id;
        }

        // 4. Ultimo fallback: zona 1 (dovrebbe essere creata dalla migrazione zones)
        $this->command->error("   ❌ Nessuna zona trovata! Fallback zona 1");
        return 1;
    }

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
            $marker = $zone->is_national ? '🏛️' : '🏢';
            $this->command->info("   {$marker} Zone {$zone->id}: {$zone->name}");
        }
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
     * 🔍 DEBUG: Migrazione user specifica
     */
    private function debugUserMigration($user, $oldReferees, $oldRoleUsers)
    {
        $this->command->info("🔍 DEBUG User {$user->id}:");
        $this->command->info("   Name: {$user->name}");
        $this->command->info("   Email: {$user->email}");
        $this->command->info("   Zone ID: " . ($user->zone_id ?? 'NULL'));

        // Check referee
        $referee = $oldReferees->get($user->id);
        if ($referee) {
            $this->command->info("   Referee qualification: " . ($referee->qualification ?? 'NULL'));
            $normalized = $this->mapQualificationWithHelper($referee->qualification ?? 'aspirante');
            $this->command->info("   Normalized level: {$normalized}");
        }

        // Check roles
        if (isset($oldRoleUsers[$user->id])) {
            $roles = $oldRoleUsers[$user->id];
            $roleIds = $roles->pluck('role_id')->toArray();
            $this->command->info("   Role IDs: " . implode(', ', $roleIds));
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
    // SETUP E METODI HELPER
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
    // METODI DI MIGRAZIONE
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
                    'description' => $zone->description ?? null,
                    'is_national' => $zone->is_national ?? false,
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

        try {
            $oldTypes = DB::connection('old')->table('tournament_types')->get();

            foreach ($oldTypes as $type) {
                $levelMapping = [
                    '1_livello' => '1_livello',
                    'regionale' => 'Regionale',
                    'nazionale' => 'Nazionale',
                    'internazionale' => 'Internazionale',
                ];

                $requiredLevel = $levelMapping[$type->required_level] ?? '1_livello';
                $minReferees = $type->min_referees ?? 1;
                $maxReferees = $type->max_referees ?? $type->referees_needed ?? $minReferees;

                $settings = [
                    'required_referee_level' => $requiredLevel,
                    'min_referees' => $minReferees,
                    'max_referees' => $maxReferees,
                    'visibility_zones' => $type->is_national ? 'all' : 'own',
                    'special_requirements' => $type->special_requirements ?? null,
                    'notification_templates' => $type->notification_templates ?? [],
                ];

                DB::table('tournament_types')->updateOrInsert(
                    ['id' => $type->id],
                    [
                        'name' => $type->name,
                        'code' => $type->code ?? $type->short_name ?? 'TT' . $type->id,
                        'description' => $type->description ?? null,
                        'is_national' => $type->is_national ?? false,
                        'level' => $type->is_national ? 'nazionale' : 'zonale',
                        'required_level' => $requiredLevel,
                        'sort_order' => $type->sort_order ?? ($type->id * 10),
                        'is_active' => $type->is_active ?? true,
                        'min_referees' => $minReferees,
                        'max_referees' => $maxReferees,
                        'settings' => json_encode($settings),
                        'created_at' => $type->created_at ?? now(),
                        'updated_at' => $type->updated_at ?? now(),
                    ]
                );
            }

            $this->command->info("✅ Migrati " . $oldTypes->count() . " tournament_types");
        } catch (\Exception $e) {
            $this->command->warn("⚠️ Tabella tournament_types non trovata o errore: " . $e->getMessage());
        }
    }

    /**
     * ✅ Crea referees (solo dati estesi) per utenti con user_type='referee'
     */
    private function createReferees()
    {
        $this->command->info('🏌️ Creazione referees (solo dati estesi)...');

        $refereeUsers = DB::table('users')->where('user_type', 'referee')->get();
        $oldReferees = DB::connection('old')->table('referees')->get()->keyBy('user_id');

        foreach ($refereeUsers as $user) {
            $oldReferee = $oldReferees->get($user->id);

            DB::table('referees')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    // ✅ RIMOSSE zone_id, referee_code, level, category, certified_date
                    // Questi campi sono già nella tabella users unificata
                    'address' => $oldReferee->address ?? null,
                    'postal_code' => $oldReferee->postal_code ?? null,
                    'tax_code' => $oldReferee->tax_code ?? null,
                    'profile_completed_at' => $oldReferee->profile_completed_at ?? now(),
                    // Campi estesi con defaults
                    'badge_number' => $oldReferee->badge_number ?? null,
                    'first_certification_date' => $oldReferee->first_certification_date ?? $user->certified_date,
                    'last_renewal_date' => $oldReferee->last_renewal_date ?? null,
                    'expiry_date' => $oldReferee->expiry_date ?? null,
                    'qualifications' => $oldReferee->qualifications ?? json_encode([]),
                    'languages' => $oldReferee->languages ?? json_encode(['italiano']),
                    'notes' => $oldReferee->notes ?? null,
                    'created_at' => $oldReferee->created_at ?? now(),
                    'updated_at' => $oldReferee->updated_at ?? now(),
                ]
            );
        }

        $this->command->info("✅ Creati " . $refereeUsers->count() . " referees estesi");
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
                        'code' => $club->code ?? null,
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
                DB::table('tournaments')->updateOrInsert(
                    ['id' => $tournament->id],
                    [
                        'name' => $tournament->name,
                        'tournament_type_id' => $tournament->tournament_type_id ?? 1,
                        'club_id' => $tournament->club_id ?? null,
                        'zone_id' => $tournament->zone_id ?? 1,
                        'start_date' => $tournament->start_date,
                        'end_date' => $tournament->end_date,
                        'status' => $tournament->status ?? 'draft',
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
                // Verifica che user_id esista nella tabella users
                $userExists = DB::table('users')->where('id', $availability->user_id)->exists();
                if (!$userExists) {
                    continue;
                }

                DB::table('availabilities')->updateOrInsert(
                    [
                        'user_id' => $availability->user_id,
                        'tournament_id' => $availability->tournament_id
                    ],
                    [
                        'availability_type' => $availability->availability_type ?? 'available',
                        'notes' => $availability->notes ?? null,
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
                // Verifica che user_id esista
                $userExists = DB::table('users')->where('id', $assignment->user_id)->exists();
                if (!$userExists) {
                    continue;
                }

                DB::table('assignments')->updateOrInsert(
                    [
                        'user_id' => $assignment->user_id,
                        'tournament_id' => $assignment->tournament_id
                    ],
                    [
                        'role' => $assignment->role ?? 'referee',
                        'status' => $assignment->status ?? 'assigned',
                        'notes' => $assignment->notes ?? null,
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
