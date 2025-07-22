<?php

/**
 * ========================================
 * DataMigrationSeeder.php - VERSIONE CORRETTA
 * ========================================
 * Combina la logica di ImportOldDataCommand con DataMigrationSeeder
 * per gestire correttamente il database gestione_arbitri originale
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DataMigrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * ✅ FIXED: Gestisce correttamente il database gestione_arbitri originale
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
        $this->migrateUsers(); // ✅ PRIMA users (con logica unificata)
        $this->createReferees(); // ✅ POI referees (extension)
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
     * ✅ Setup della connessione al database di origine (gestione_arbitri)
     */
    private function setupOldDatabaseConnection()
    {
        $oldDbConfig = [
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
        ];

        config(['database.connections.old' => $oldDbConfig]);

        $this->command->info("🔗 Configurazione connessione database di origine:");
        $this->command->info("   Host: {$oldDbConfig['host']}:{$oldDbConfig['port']}");
        $this->command->info("   Database: {$oldDbConfig['database']}");
        $this->command->info("   Username: {$oldDbConfig['username']}");
    }

    /**
     * 🔍 DEBUG: Verifica stato database
     */
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

    /**
     * ✅ Verifica dettagliata della connessione al database gestione_arbitri
     */
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

            // Verifica tabelle principali esistenti
            $requiredTables = ['users', 'referees', 'zones', 'clubs', 'tournament_types', 'tournaments'];
            $missingTables = [];

            foreach ($requiredTables as $table) {
                if (!$this->tableExists('old', $table)) {
                    $missingTables[] = $table;
                }
            }

            if (!empty($missingTables)) {
                $this->command->error('❌ Tabelle mancanti nel database gestione_arbitri: ' . implode(', ', $missingTables));
                return false;
            }
            $this->command->info('✅ Tutte le tabelle principali trovate');

            // Verifica dati di base
            $userCount = DB::connection('old')->table('users')->count();
            $refereeCount = DB::connection('old')->table('referees')->count();
            $this->command->info("📊 Trovati {$userCount} users e {$refereeCount} referees nel database gestione_arbitri");

            return true;
        } catch (\PDOException $e) {
            $this->command->error('❌ Errore PDO: ' . $e->getMessage());
            $this->command->error('   Codice errore: ' . $e->getCode());
            return false;
        } catch (\Exception $e) {
            $this->command->error('❌ Errore generico connessione database gestione_arbitri: ' . $e->getMessage());
            $this->command->error('   Tipo errore: ' . get_class($e));
            return false;
        }
    }

    /**
     * ✅ Verifica se una tabella esiste nel database specificato
     */
    private function tableExists(string $connection, string $table): bool
    {
        try {
            $tables = DB::connection($connection)->select('SHOW TABLES');

            foreach ($tables as $tableObj) {
                $tableName = array_values((array) $tableObj)[0];
                if ($tableName === $table) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            $this->command->warn("⚠️ Errore verifica tabella {$table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Disconnette la connessione al database gestione_arbitri
     */
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
     * ✅ FIXED: Migrazione users con logica corretta per database originale
     * Combina users e referees dal database originale nella tabella users unificata
     */
    private function migrateUsers()
    {
        $this->command->info('👥 Migrazione users (con unificazione referees)...');

        // 1. PRIMA importa tutti gli users di base
        $oldUsers = DB::connection('old')->table('users')->get();
        $oldReferees = DB::connection('old')->table('referees')->get()->keyBy('user_id');
        $oldRoleUsers = [];

        // 2. Cerca role_user per determinare admin (se esiste)
        try {
            if ($this->tableExists('old', 'role_user')) {
                $oldRoleUsers = DB::connection('old')->table('role_user')->get()->groupBy('user_id');
            }
        } catch (\Exception $e) {
            $this->command->warn('Tabella role_user non trovata, uso solo referees');
        }

        foreach ($oldUsers as $user) {
            // ✅ Determina il tipo di utente
            $userType = $this->determineUserType($user, $oldReferees, $oldRoleUsers);
            $referee = $oldReferees->get($user->id);

            // ✅ Dati base per tutti gli utenti
            $userData = [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'remember_token' => $user->remember_token,
                'user_type' => $userType,
                'zone_id' => $user->zone_id ?? 1,
                'phone' => $user->phone ?? null,
                'city' => null, // Non presente nel vecchio DB
                'is_active' => $user->is_active ?? true,
                'created_at' => $user->created_at ?? now(),
                'updated_at' => $user->updated_at ?? now(),
            ];

            // ✅ Campi referee: sempre inclusi ma con valori appropriati
            if ($userType === 'referee' && $referee) {
                // Dati reali dell'arbitro
                $userData['referee_code'] = $referee->referee_code ?? $this->generateRefereeCode();
                $userData['level'] = $this->mapQualification($referee->qualification ?? 'aspirante');
                $userData['category'] = $referee->category ?? 'misto';
                $userData['certified_date'] = $referee->certified_date ?? now()->subYears(2);

                $this->command->info("🔄 Migrating referee: {$user->name} (code: {$userData['referee_code']})");
            } else {
                // ✅ Valori di default per utenti non-arbitri
                $userData['referee_code'] = null; // NULL per non-arbitri
                $userData['level'] = 'aspirante';   // Livello di default
                $userData['category'] = 'misto';    // Categoria di default
                $userData['certified_date'] = null;

                $this->command->info("🔄 Migrating user: {$user->name} ({$userType})");
            }

            DB::table('users')->updateOrInsert(
                ['id' => $user->id],
                $userData
            );
        }

        $this->command->info("✅ Migrati " . $oldUsers->count() . " users (con unificazione referees)");
        $this->command->info('Arbitri: ' . DB::table('users')->where('user_type', 'referee')->count());
        $this->command->info('Admin: ' . DB::table('users')->whereIn('user_type', ['admin', 'national_admin', 'super_admin'])->count());
    }

    /**
     * ✅ Determina il tipo di utente dal vecchio database
     */
    private function determineUserType($oldUser, $oldReferees, $oldRoleUsers): string
    {
        // 1. Controlla se ha ruoli admin
        if (isset($oldRoleUsers[$oldUser->id])) {
            $roles = $oldRoleUsers[$oldUser->id];
            foreach ($roles as $role) {
                switch ($role->role_id) {
                    case 1:
                        return 'super_admin';
                    case 2:
                        return 'national_admin';
                    case 3:
                        return 'admin';
                }
            }
        }

        // 2. Controlla se è un arbitro
        if ($oldReferees->has($oldUser->id)) {
            return 'referee';
        }

        // 3. Controlla campi legacy
        if (isset($oldUser->is_super_admin) && $oldUser->is_super_admin) {
            return 'super_admin';
        }

        if (isset($oldUser->is_admin) && $oldUser->is_admin) {
            return 'admin';
        }

        // 4. Default
        return 'referee';
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

    /**
     * ✅ Mappa qualifiche vecchie a nuove
     */
    private function mapQualification($oldQualification): string
    {
        return match (strtolower(trim($oldQualification))) {
            'primo livello', '1° livello', '1_livello', '1 livello' => 'primo_livello',
            'regionale' => 'regionale',
            'nazionale/internazionale', 'nazionale', 'internazionale' => 'nazionale',
            'archivio' => 'aspirante', // Mappa archivio ad aspirante
            'aspirante' => 'aspirante',
            default => 'aspirante'
        };
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
                    'zone_id' => $user->zone_id,
                    'referee_code' => $user->referee_code,
                    'level' => $user->level,
                    'category' => $user->category,
                    'certified_date' => $user->certified_date,
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
                    'languages' => $oldReferee->languages ?? json_encode(['it']),
                    'available_for_international' => $oldReferee->available_for_international ?? false,
                    'specializations' => $oldReferee->specializations ?? 'Golf tradizionale',
                    'total_tournaments' => $oldReferee->total_tournaments ?? 0,
                    'tournaments_current_year' => $oldReferee->tournaments_current_year ?? 0,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            );
        }

        $this->command->info("✅ Creati " . $refereeUsers->count() . " referees (extension only)");
    }

    /**
     * ✅ Migrazione clubs
     */
    private function migrateClubs()
    {
        $this->command->info('🏌️ Migrazione clubs...');

        $oldClubs = DB::connection('old')->table('clubs')->get();

        foreach ($oldClubs as $club) {
            // Gestione contact_info JSON se presente
            $contactInfo = null;
            if (isset($club->contact_info)) {
                $contactInfo = json_decode($club->contact_info, true);
            }

            DB::table('clubs')->updateOrInsert(
                ['id' => $club->id],
                [
                    'name' => $club->name,
                    'code' => $club->code ?? $club->short_name ?? 'CLUB' . $club->id,
                    'city' => $club->city ?? 'N/A',
                    'province' => $club->province ?? 'XX',
                    'email' => $contactInfo['email'] ?? $club->email ?? null,
                    'phone' => $contactInfo['phone'] ?? $club->phone ?? null,
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
    }

    /**
     * ✅ Migrazione tournament_types
     */
    private function migrateTournamentTypes()
    {
        $this->command->info('🏆 Migrazione tournament_types...');

        $oldTypes = DB::connection('old')->table('tournament_types')->get();

        foreach ($oldTypes as $type) {
            $levelMapping = [
                '1_livello' => 'primo_livello',
                'regionale' => 'regionale',
                'nazionale' => 'nazionale',
                'internazionale' => 'internazionale',
            ];

            $requiredLevel = $levelMapping[$type->required_level] ?? 'primo_livello';
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
    }

    /**
     * ✅ Migrazione tournaments
     */
    private function migrateTournaments()
    {
        $this->command->info('🏆 Migrazione tournaments...');

        $oldTournaments = DB::connection('old')->table('tournaments')->get();

        // Pre-carica i club per ricavare zone_id
        $clubs = DB::table('clubs')->pluck('zone_id', 'id')->toArray();

        foreach ($oldTournaments as $tournament) {
            // Ricava zone_id dal club
            $zoneId = $clubs[$tournament->club_id] ?? 1;

            DB::table('tournaments')->updateOrInsert(
                ['id' => $tournament->id],
                [
                    'name' => $tournament->name,
                    'start_date' => $tournament->start_date,
                    'end_date' => $tournament->end_date ?? $tournament->start_date,
                    'availability_deadline' => $tournament->availability_deadline ?? date('Y-m-d', strtotime($tournament->start_date . ' -7 days')),
                    'club_id' => $tournament->club_id,
                    'tournament_type_id' => $tournament->tournament_type_id ?? $tournament->type_id ?? 1,
                    'zone_id' => $zoneId,
                    'notes' => $tournament->notes ?? null,
                    'status' => $this->mapTournamentStatus($tournament->status ?? 'draft'),
                    'convocation_letter' => $tournament->convocation_letter ?? null,
                    'club_letter' => $tournament->club_letter ?? null,
                    'letters_generated_at' => $tournament->letters_generated_at ?? null,
                    'convocation_file_path' => $tournament->convocation_file_path ?? null,
                    'convocation_file_name' => $tournament->convocation_file_name ?? null,
                    'convocation_generated_at' => $tournament->convocation_generated_at ?? null,
                    'club_letter_file_path' => $tournament->club_letter_file_path ?? null,
                    'club_letter_file_name' => $tournament->club_letter_file_name ?? null,
                    'club_letter_generated_at' => $tournament->club_letter_generated_at ?? null,
                    'documents_last_updated_by' => $tournament->documents_last_updated_by ?? null,
                    'document_version' => $tournament->document_version ?? 1,
                    'created_at' => $tournament->created_at ?? now(),
                    'updated_at' => $tournament->updated_at ?? now(),
                ]
            );
        }

        $this->command->info("✅ Migrati " . $oldTournaments->count() . " tournaments");
    }

    /**
     * ✅ Mappa stati torneo
     */
    private function mapTournamentStatus($oldStatus): string
    {
        return match (strtolower(trim($oldStatus))) {
            'bozza', 'draft' => 'draft',
            'aperto', 'open' => 'open',
            'chiuso', 'closed' => 'closed',
            'assegnato', 'assigned' => 'assigned',
            'completato', 'completed' => 'completed',
            default => 'draft'
        };
    }

    /**
     * ✅ Migrazione availabilities
     */
    private function migrateAvailabilities()
    {
        $this->command->info('📅 Migrazione availabilities...');

        $oldAvailabilities = DB::connection('old')->table('availabilities')->get();

        foreach ($oldAvailabilities as $availability) {
            DB::table('availabilities')->updateOrInsert(
                [
                    'user_id' => $availability->referee_id ?? $availability->user_id,
                    'tournament_id' => $availability->tournament_id
                ],
                [
                    'status' => $availability->status ?? 'available',
                    'notes' => $availability->notes ?? null,
                    'submitted_at' => $availability->submitted_at ?? $availability->created_at ?? now(),
                    'created_at' => $availability->created_at ?? now(),
                    'updated_at' => $availability->updated_at ?? now(),
                ]
            );
        }

        $this->command->info("✅ Migrati " . $oldAvailabilities->count() . " availabilities");
    }

    /**
     * ✅ Migrazione assignments
     */
    private function migrateAssignments()
    {
        $this->command->info('📝 Migrazione assignments...');

        $oldAssignments = DB::connection('old')->table('assignments')->get();

        foreach ($oldAssignments as $assignment) {
            DB::table('assignments')->updateOrInsert(
                [
                    'user_id' => $assignment->referee_id ?? $assignment->user_id,
                    'tournament_id' => $assignment->tournament_id
                ],
                [
                    'assigned_by_id' => $assignment->assigned_by_id ?? $assignment->assigned_by ?? 1,
                    'role' => $assignment->role ?? 'Arbitro',
                    'notes' => $assignment->notes ?? null,
                    'is_confirmed' => $assignment->is_confirmed ?? true,
                    'assigned_at' => $assignment->assigned_at ?? $assignment->created_at ?? now(),
                    'created_at' => $assignment->created_at ?? now(),
                    'updated_at' => $assignment->updated_at ?? now(),
                ]
            );
        }

        $this->command->info("✅ Migrati " . $oldAssignments->count() . " assignments");
    }

    /**
     * ✅ Creazione dati di supporto
     */
    private function createSupportData()
    {
        $this->command->info('🔧 Creazione dati di supporto...');

        // Migrate institutional emails
        if ($this->tableExists('old', 'institutional_emails')) {
            $oldEmails = DB::connection('old')->table('institutional_emails')->get();

            foreach ($oldEmails as $email) {
                DB::table('institutional_emails')->updateOrCreate(
                    ['email' => $email->email],
                    [
                        'name' => $email->name,
                        'email' => $email->email,
                        'description' => $email->description ?? null,
                        'is_active' => $email->is_active ?? true,
                        'zone_id' => $email->zone_id ?? null,
                        'category' => $email->category ?? 'altro',
                        'receive_all_notifications' => $email->receive_all_notifications ?? false,
                        'notification_types' => $email->notification_types ?? json_encode([]),
                        'created_at' => $email->created_at ?? now(),
                        'updated_at' => $email->updated_at ?? now(),
                    ]
                );
            }
        }

        // Migrate letter templates
        if ($this->tableExists('old', 'letter_templates')) {
            $oldTemplates = DB::connection('old')->table('letter_templates')->get();

            foreach ($oldTemplates as $template) {
                DB::table('letter_templates')->updateOrCreate(
                    ['name' => $template->name],
                    [
                        'name' => $template->name,
                        'type' => $this->mapTemplateType($template->type ?? 'assignment'),
                        'subject' => $template->subject ?? 'Comunicazione',
                        'body' => $template->body,
                        'zone_id' => $template->zone_id ?? null,
                        'tournament_type_id' => $template->tournament_type_id ?? $template->tournament_category_id ?? null,
                        'is_active' => $template->is_active ?? true,
                        'variables' => $template->variables ?? json_encode([]),
                        'description' => $template->description ?? null,
                        'settings' => $template->settings ?? json_encode([]),
                        'created_at' => $template->created_at ?? now(),
                        'updated_at' => $template->updated_at ?? now(),
                    ]
                );
            }
        }

        // Migrate letterheads
        if ($this->tableExists('old', 'letterheads')) {
            $oldLetterheads = DB::connection('old')->table('letterheads')->get();

            foreach ($oldLetterheads as $letterhead) {
                DB::table('letterheads')->updateOrCreate(
                    ['id' => $letterhead->id],
                    [
                        'title' => $letterhead->title ?? 'Intestazione',
                        'header_content' => $letterhead->header_content ?? null,
                        'footer_content' => $letterhead->footer_content ?? null,
                        'logo_path' => $letterhead->logo_path ?? null,
                        'zone_id' => $letterhead->zone_id ?? null,
                        'is_active' => $letterhead->is_active ?? true,
                        'is_default' => $letterhead->is_default ?? false,
                        'settings' => $letterhead->settings ?? json_encode([]),
                        'created_at' => $letterhead->created_at ?? now(),
                        'updated_at' => $letterhead->updated_at ?? now(),
                    ]
                );
            }
        }

        $this->command->info("✅ Creati dati di supporto");
    }

    /**
     * ✅ Mappa tipi template
     */
    private function mapTemplateType(string $type): string
    {
        $mapping = [
            'convocation' => 'convocation',
            'assignment' => 'assignment',
            'club' => 'club',
            'notification' => 'assignment',
        ];

        return $mapping[$type] ?? 'assignment';
    }
}
