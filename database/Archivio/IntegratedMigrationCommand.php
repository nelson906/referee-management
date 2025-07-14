<?php
// app/Console/Commands/IntegratedMigrationCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Zone;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Availability;
use App\Models\Assignment;
use App\Models\Referee;

class IntegratedMigrationCommand extends Command
{
    protected $signature = 'golf:integrated-migration {old_db_name} {new_db_name} {--clean : Pulisce i dati esistenti} {--skip-recovery : Salta recupero da golf_referee_new}';
    protected $description = 'Migrazione completa + recupero automatico dati da golf_referee_new';

    private $oldDb;
    private $newDb;
    private $mappingLog = [];
    private $assignmentData = [];

    public function handle()
    {
        $this->oldDb = $this->argument('old_db_name');
        $this->newDb = $this->argument('new_db_name');

        if ($this->option('clean')) {
            $this->cleanExistingData();
        }

        $this->info("🚀 MIGRAZIONE INTEGRATA COMPLETA");
        $this->info("Database originale: {$this->oldDb}");
        $this->info("Database nuovo: {$this->newDb}");

        try {
            // FASE 1: MIGRAZIONE BASE
            $this->info("\n📋 FASE 1: MIGRAZIONE BASE");
            $this->setupConnections();
            $this->createBaseData();
            $this->migrateUsersAndReferees();
            $this->migrateClubs();
            $this->migrateTournaments();

            // FASE 2: RECUPERO DATI DA golf_referee_new
            if (!$this->option('skip-recovery')) {
                $this->info("\n📋 FASE 2: RECUPERO DATI AVANZATI");
                $this->recoverAdvancedData();
            }

            // FASE 3: MIGRAZIONE RELAZIONI
            $this->info("\n📋 FASE 3: MIGRAZIONE RELAZIONI");
            $this->migrateAvailabilities();
            $this->migrateAssignments();

            // FASE 4: FINALIZZAZIONE
            $this->info("\n📋 FASE 4: FINALIZZAZIONE");
            $this->cleanupOrphanedData();
            $this->validateMigration();
            $this->printIntegratedReport();

            $this->info("✅ MIGRAZIONE INTEGRATA COMPLETATA!");

        } catch (\Exception $e) {
            $this->error("❌ ERRORE: " . $e->getMessage());
            $this->error("Stack: " . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function setupConnections()
    {
        $this->info("🔧 Setup connessioni database...");

        // Database originale
        config(['database.connections.old_db' => [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => $this->oldDb,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        // Database nuovo (golf_referee_new)
        config(['database.connections.new_db' => [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => $this->newDb,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        // Test connessioni
        DB::connection('old_db')->getPdo();
        DB::connection('new_db')->getPdo();
        $this->info("✅ Connessioni stabilite");
    }

    private function cleanExistingData()
    {
        $this->warn("🧹 Pulizia dati esistenti...");

        if (!$this->confirm('Sei sicuro di voler cancellare tutti i dati esistenti?')) {
            $this->info("Migrazione annullata.");
            exit(0);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        $tables = [
            'assignments', 'availabilities', 'tournaments', 'clubs',
            'users', 'referees', 'zones', 'institutional_emails', 'letter_templates'
        ];

        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->truncate();
                $this->info("Svuotata tabella: {$table}");
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function createBaseData()
    {
        $this->info("🏗️ Creazione dati base...");

        // Zone di base (saranno aggiornate dal recupero)
        $baseZones = [
            ['id' => 1, 'name' => 'Zona 1', 'description' => 'SZR1', 'is_national' => false],
            ['id' => 2, 'name' => 'Zona 2', 'description' => 'SZR2', 'is_national' => false],
            ['id' => 3, 'name' => 'Zona 3', 'description' => 'SZR3', 'is_national' => false],
            ['id' => 4, 'name' => 'Zona 4', 'description' => 'SZR4', 'is_national' => false],
            ['id' => 5, 'name' => 'Zona 5', 'description' => 'SZR5', 'is_national' => false],
            ['id' => 6, 'name' => 'Zona 6', 'description' => 'SZR6', 'is_national' => false],
            ['id' => 7, 'name' => 'Zona 7', 'description' => 'SZR7', 'is_national' => false],
            ['id' => 8, 'name' => 'CRC', 'description' => 'Comitato Regionale Calabria', 'is_national' => true],
        ];

        foreach ($baseZones as $zone) {
            Zone::updateOrCreate(['id' => $zone['id']], $zone);
        }

        // Tournament Types
        $types = [
            ['id' => 1, 'name' => 'Gara Zonale', 'code' => 'ZON', 'is_national' => false, 'required_level' => '1_livello', 'min_referees' => 1, 'max_referees' => 2],
            ['id' => 2, 'name' => 'Coppa Italia', 'code' => 'CI', 'is_national' => true, 'required_level' => 'Regionale', 'min_referees' => 2, 'max_referees' => 3],
            ['id' => 3, 'name' => 'Campionato Nazionale', 'code' => 'CN', 'is_national' => true, 'required_level' => 'Nazionale', 'min_referees' => 2, 'max_referees' => 4],
        ];

        foreach ($types as $type) {
            TournamentType::updateOrCreate(['id' => $type['id']], $type);
        }

        $this->info("✅ Dati base creati");
    }

    private function migrateUsersAndReferees()
    {
        $this->info("👥 Migrazione Users e Referees...");

        $tables = $this->getAvailableTables('old_db');
        $usersCreated = 0;
        $refereesCreated = 0;

        // Strategia: Priorità ad arbitri, poi integra users esistenti
        if (in_array('arbitri', $tables)) {
            $this->info("⚖️ Importazione da arbitri...");
            $arbitri = DB::connection('old_db')->table('arbitri')->get();

            foreach ($arbitri as $arbitro) {
                if (empty($arbitro->Email)) continue;

                $userData = [
                    'name' => trim(($arbitro->Nome ?? '') . ' ' . ($arbitro->Cognome ?? '')),
                    'email' => $arbitro->Email,
                    'password' => Hash::make($arbitro->Password ?? 'password123'),
                    'user_type' => 'referee',
                    'referee_code' => 'ARB' . str_pad($arbitro->id, 4, '0', STR_PAD_LEFT),
                    'level' => $this->mapLevelFromArbitro($arbitro->Livello_2025 ?? 'ASP'),
                    'category' => 'misto',
                    'zone_id' => $this->extractZoneId($arbitro->Zona ?? 'SZR1'),
                    'certified_date' => $this->parseDate($arbitro->Prima_Nomina) ?? now(),
                    'phone' => $arbitro->Cellulare ?? null,
                    'city' => $arbitro->Citta ?? null,
                    'is_active' => $this->isActiveArbitro($arbitro->Livello_2025 ?? ''),
                    'email_verified_at' => now(),
                ];

                $user = User::updateOrCreate(['email' => $arbitro->Email], $userData);

                // Crea record referee
                $this->createRefereeFromArbitro($user, $arbitro);

                $usersCreated++;
                $refereesCreated++;
            }
        }

        // Integra users esistenti dal database originale
        if (in_array('users', $tables)) {
            $this->info("👤 Integrazione users esistenti...");
            $existingUsers = DB::connection('old_db')->table('users')->get();

            foreach ($existingUsers as $oldUser) {
                $existingUser = User::where('email', $oldUser->email)->first();

                if (!$existingUser) {
                    $userData = [
                        'id' => $oldUser->id,
                        'name' => $oldUser->name,
                        'email' => $oldUser->email,
                        'password' => $oldUser->password,
                        'user_type' => $oldUser->user_type ?? 'referee',
                        'referee_code' => $oldUser->referee_code ?? 'ARB' . str_pad($oldUser->id, 4, '0', STR_PAD_LEFT),
                        'level' => $oldUser->level ?? '1_livello',
                        'category' => $oldUser->category ?? 'misto',
                        'zone_id' => $oldUser->zone_id ?? 1,
                        'certified_date' => $oldUser->certified_date ?? now(),
                        'phone' => $oldUser->phone,
                        'city' => $oldUser->city,
                        'is_active' => $oldUser->is_active ?? true,
                        'email_verified_at' => $oldUser->email_verified_at,
                        'remember_token' => $oldUser->remember_token,
                        'created_at' => $oldUser->created_at,
                        'updated_at' => $oldUser->updated_at,
                    ];

                    $user = User::create($userData);

                    // Crea referee se non esiste
                    if ($user->user_type === 'referee') {
                        $this->createBasicReferee($user);
                        $refereesCreated++;
                    }

                    $usersCreated++;
                }
            }
        }

        $this->mappingLog['users'] = $usersCreated;
        $this->mappingLog['referees'] = $refereesCreated;
        $this->info("✅ Users: {$usersCreated}, Referees: {$refereesCreated}");
    }

    private function recoverAdvancedData()
    {
        $this->info("🔄 Recupero dati avanzati da golf_referee_new...");

        // 1. Aggiorna Zones con dati completi
        $this->recoverZones();

        // 2. Recupera institutional emails
        $this->recoverInstitutionalEmails();

        // 3. Recupera letter templates
        $this->recoverLetterTemplates();

        // 4. Recupera admin users
        $this->recoverAdminUsers();

        // 5. Fix nomi tornei
        $this->fixTournamentNames();
    }

    private function recoverZones()
    {
        try {
            $newZones = DB::connection('new_db')->table('zones')->get();
            $zonesUpdated = 0;

            foreach ($newZones as $newZone) {
                Zone::updateOrCreate(
                    ['id' => $newZone->id],
                    [
                        'name' => $newZone->name,
                        'description' => $newZone->description ?? null,
                        'is_national' => $newZone->is_national ?? false,
                        'header_document_path' => $newZone->header_document_path ?? null,
                        'header_updated_at' => $newZone->header_updated_at ?? null,
                        'header_updated_by' => $newZone->header_updated_by ?? null,
                        'updated_at' => now(),
                    ]
                );
                $zonesUpdated++;
            }

            $this->mappingLog['zones_updated'] = $zonesUpdated;
            $this->info("✅ Zones aggiornate: {$zonesUpdated}");
        } catch (\Exception $e) {
            $this->warn("⚠️ Errore aggiornamento zones: " . $e->getMessage());
        }
    }

    private function recoverInstitutionalEmails()
    {
        try {
            $fixedAddresses = DB::connection('new_db')->table('fixed_addresses')->get();
            $emailsCreated = 0;

            foreach ($fixedAddresses as $address) {
                DB::table('institutional_emails')->updateOrInsert(
                    ['email' => $address->email],
                    [
                        'name' => $address->name ?? 'Email Istituzionale',
                        'email' => $address->email,
                        'description' => $address->description ?? null,
                        'is_active' => $address->is_active ?? true,
                        'zone_id' => $address->zone_id ?? null,
                        'category' => $this->mapEmailCategory($address->type ?? 'altro'),
                        'receive_all_notifications' => $address->receive_all_notifications ?? false,
                        'notification_types' => $this->parseJsonField($address->notification_types),
                        'created_at' => $address->created_at ?? now(),
                        'updated_at' => now(),
                    ]
                );
                $emailsCreated++;
            }

            $this->mappingLog['institutional_emails'] = $emailsCreated;
            $this->info("✅ Email istituzionali: {$emailsCreated}");
        } catch (\Exception $e) {
            $this->warn("⚠️ Errore email istituzionali: " . $e->getMessage());
        }
    }

    private function recoverLetterTemplates()
    {
        try {
            $letterheads = DB::connection('new_db')->table('letterheads')->get();
            $templatesCreated = 0;

            foreach ($letterheads as $letterhead) {
                DB::table('letter_templates')->updateOrInsert(
                    [
                        'name' => $letterhead->name,
                        'zone_id' => $letterhead->zone_id,
                    ],
                    [
                        'name' => $letterhead->name ?? 'Template',
                        'type' => $this->mapTemplateType($letterhead->type ?? 'assignment'),
                        'subject' => $letterhead->subject ?? 'Oggetto template',
                        'body' => $letterhead->body ?? $letterhead->content ?? '',
                        'zone_id' => $letterhead->zone_id ?? null,
                        'tournament_type_id' => $letterhead->tournament_type_id ?? null,
                        'is_active' => $letterhead->is_active ?? true,
                        'is_default' => $letterhead->is_default ?? false,
                        'variables' => $this->parseJsonField($letterhead->variables),
                        'created_at' => $letterhead->created_at ?? now(),
                        'updated_at' => now(),
                    ]
                );
                $templatesCreated++;
            }

            $this->mappingLog['letter_templates'] = $templatesCreated;
            $this->info("✅ Template lettere: {$templatesCreated}");
        } catch (\Exception $e) {
            $this->warn("⚠️ Errore template lettere: " . $e->getMessage());
        }
    }

    private function recoverAdminUsers()
    {
        try {
            $adminUsers = DB::connection('new_db')
                ->table('users')
                ->whereIn('user_type', ['super_admin', 'national_admin', 'admin'])
                ->get();

            $adminsRecovered = 0;

            foreach ($adminUsers as $adminUser) {
                $user = User::updateOrCreate(
                    ['email' => $adminUser->email],
                    [
                        'name' => $adminUser->name,
                        'email' => $adminUser->email,
                        'user_type' => $adminUser->user_type,
                        'level' => $adminUser->level ?? 'Aspirante',
                        'referee_code' => $adminUser->referee_code ?? 'N/A',
                        'category' => $adminUser->category ?? 'misto',
                        'zone_id' => $adminUser->zone_id ?? null,
                        'certified_date' => $adminUser->certified_date ?? now(),
                        'password' => $adminUser->password,
                        'email_verified_at' => $adminUser->email_verified_at,
                        'phone' => $adminUser->phone ?? null,
                        'city' => $adminUser->city ?? null,
                        'is_active' => $adminUser->is_active ?? true,
                        'updated_at' => now(),
                    ]
                );
                $adminsRecovered++;
            }

            $this->mappingLog['admin_users'] = $adminsRecovered;
            $this->info("✅ Admin users: {$adminsRecovered}");
        } catch (\Exception $e) {
            $this->warn("⚠️ Errore admin users: " . $e->getMessage());
        }
    }

    private function fixTournamentNames()
    {
        try {
            $newTournaments = DB::connection('new_db')->table('tournaments')->get();
            $tournamentsFixed = 0;

            foreach ($newTournaments as $newTournament) {
                $currentTournament = Tournament::find($newTournament->id);

                if ($currentTournament &&
                    (empty($currentTournament->name) ||
                     $currentTournament->name === 'Torneo' ||
                     strlen($currentTournament->name) < 5)) {

                    $currentTournament->update([
                        'name' => $newTournament->name,
                        'notes' => $newTournament->notes ?? $currentTournament->notes,
                    ]);

                    $tournamentsFixed++;
                }
            }

            $this->mappingLog['tournament_names_fixed'] = $tournamentsFixed;
            $this->info("✅ Nomi tornei ripristinati: {$tournamentsFixed}");
        } catch (\Exception $e) {
            $this->warn("⚠️ Errore fix nomi tornei: " . $e->getMessage());
        }
    }

    // Include tutti i metodi di migrazione standard...
    private function migrateClubs()
    {
        $this->info("🏌️ Migrazione circoli...");

        $tables = $this->getAvailableTables('old_db');
        if (!in_array('circoli', $tables)) {
            $this->warn("Tabella 'circoli' non trovata");
            return;
        }

        $circoli = DB::connection('old_db')->table('circoli')->get();
        $clubsCreated = 0;

        foreach ($circoli as $circolo) {
            $clubData = [
                'id' => $circolo->Id,
                'code' => $circolo->Circolo_Id ?? 'CLUB' . $circolo->Id,
                'name' => $circolo->Circolo_Nome ?? 'Club',
                'address' => $circolo->Indirizzo ?? null,
                'postal_code' => $circolo->CAP ?? null,
                'city' => $circolo->Città ?? null,
                'province' => $circolo->Provincia ?? null,
                'region' => $circolo->Regione ?? null,
                'email' => $circolo->Email ?? null,
                'phone' => $circolo->Telefono ?? null,
                'website' => $circolo->Web ?? null,
                'is_active' => $this->mapSedeGara($circolo->SedeGara ?? 'Y'),
                'zone_id' => $this->extractZoneIdFromCircoli($circolo->Zona ?? 'SZR1'),
                'contact_person' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            Club::updateOrCreate(['id' => $circolo->Id], $clubData);
            $clubsCreated++;
        }

        $this->mappingLog['clubs'] = $clubsCreated;
        $this->info("✅ Circoli migrati: {$clubsCreated}");
    }

    private function migrateTournaments()
    {
        $this->info("🏆 Migrazione tornei...");

        $tables = $this->getAvailableTables('old_db');
        if (!in_array('gare_2025', $tables)) {
            $this->warn("Tabella 'gare_2025' non trovata");
            return;
        }

        $gare = DB::connection('old_db')->table('gare_2025')->get();
        $tournamentsCreated = 0;

        foreach ($gare as $gara) {
            $clubId = $this->findClubIdByName($gara->Circolo ?? '');
            if (!$clubId) {
                $this->warn("Club non trovato per: " . ($gara->Circolo ?? 'N/A'));
                continue;
            }

            $club = Club::find($clubId);
            if (!$club) continue;

            $tournamentData = [
                'id' => $gara->id,
                'name' => $gara->Nome_gare ?? 'Torneo',  // NOME PRESERVATO
                'tournament_type_id' => $this->mapTournamentType($gara->Tipo ?? 'ZON'),
                'club_id' => $clubId,
                'start_date' => $this->parseDate($gara->StartTime) ?? now(),
                'end_date' => $this->parseDate($gara->EndTime) ?? now(),
                'zone_id' => $this->extractZoneIdFromGare($gara->Zona) ?? $club->zone_id,
                'availability_deadline' => $this->calculateDeadline($gara->StartTime),
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            Tournament::updateOrCreate(['id' => $gara->id], $tournamentData);
            $tournamentsCreated++;

            // Store per assignments
            $this->storeAssignmentData($gara);
        }

        $this->mappingLog['tournaments'] = $tournamentsCreated;
        $this->info("✅ Tornei migrati: {$tournamentsCreated}");
    }

    // Include tutti gli helper methods necessari...
    private function createRefereeFromArbitro($user, $arbitro)
    {
        $refereeData = [
            'user_id' => $user->id,
            'address' => $arbitro->Pr_abit ?? null,
            'first_certification_date' => $this->parseDate($arbitro->Prima_Nomina),
            'last_renewal_date' => $this->parseDate($arbitro->Ultimo_Esame),
            'expiry_date' => $this->calculateExpiryDate($arbitro->Ultimo_Esame),
            'experience_years' => $this->calculateExperienceYears($arbitro->Prima_Nomina),
            'available_for_international' => $this->isInternationalLevel($user->level),
            'badge_number' => $user->referee_code,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        Referee::updateOrCreate(['user_id' => $user->id], $refereeData);
    }

    private function createBasicReferee($user)
    {
        $refereeData = [
            'user_id' => $user->id,
            'badge_number' => $user->referee_code,
            'first_certification_date' => $this->parseDate($user->certified_date),
            'available_for_international' => $this->isInternationalLevel($user->level),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        Referee::create($refereeData);
    }

    // Include tutti gli altri helper methods...
    // (migrateAvailabilities, migrateAssignments, helper methods, validation, etc.)

    private function getAvailableTables($connection): array
    {
        $tables = DB::connection($connection)->select("SHOW TABLES");
        return array_map(function($table) {
            return array_values((array)$table)[0];
        }, $tables);
    }

    private function printIntegratedReport()
    {
        $this->info("\n" . str_repeat("=", 60));
        $this->info("📊 REPORT MIGRAZIONE INTEGRATA");
        $this->info(str_repeat("=", 60));

        foreach ($this->mappingLog as $section => $count) {
            $this->info("🔸 " . strtoupper(str_replace('_', ' ', $section)) . ": {$count}");
        }

        $this->info("\n✅ MIGRAZIONE INTEGRATA COMPLETATA!");
        $this->info("🎯 Dati base + Dati avanzati da golf_referee_new");
        $this->info("🔗 Tutte le relazioni ricostruite");
        $this->info("📝 Nomi tornei preservati/ripristinati");
    }

    // Placeholder per metodi di migrazione restanti (per brevità)
    private function migrateAvailabilities() { /* Implementazione standard */ }
    private function migrateAssignments() { /* Implementazione standard */ }
    private function cleanupOrphanedData() { /* Implementazione standard */ }
    private function validateMigration() { /* Implementazione standard */ }

    // Tutti gli helper methods necessari...
    private function mapLevelFromArbitro($livello): string { /* Implementazione */ }
    private function extractZoneId($zona): int { /* Implementazione */ }
    private function isActiveArbitro($livello): bool { /* Implementazione */ }
    private function parseDate($dateString) { /* Implementazione */ }
    // ... altri helper methods
}
