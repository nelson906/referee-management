<?php
// app/Console/Commands/FixedEnhancedMigrationCommand.php
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
use Carbon\Carbon;

class FixedEnhancedMigrationCommand extends Command
{
    protected $signature = 'golf:enhanced-migration {old_db_name} {--clean : Pulisce i dati esistenti} {--debug : Modalità debug verbose}';
    protected $description = 'Migrazione migliorata che gestisce tabella referees vuota ricostruendo da arbitri';

    private $oldDb;
    private $mappingLog = [];
    private $debug = false;
    private $strategy = '';
    private $assignmentData = [];

    public function handle()
    {
        $this->oldDb = $this->argument('old_db_name');
        $this->debug = $this->option('debug');

        if ($this->option('clean')) {
            $this->cleanExistingData();
        }

        $this->info("🚀 MIGRAZIONE ENHANCED - GESTIONE REFEREES VUOTI");
        $this->info("Database origine: {$this->oldDb}");

        try {
            // 1. SETUP E ANALISI
            $this->setupConnection();
            $this->analyzeSourceData();
            $this->createBaseData();

            // 2. MIGRAZIONE ADATTIVA
            $this->migrateUsersAdaptive();
            $this->ensureRefereesPopulated();

            // 3. MIGRAZIONE SUPPORTO
            $this->migrateClubs();
            $this->migrateTournaments();
            $this->migrateAvailabilities();
            $this->migrateAssignments();

            // 4. VALIDAZIONE E CLEANUP
            $this->cleanupOrphanedData();
            $this->validateMigration();
            $this->printMigrationReport();

            $this->info("✅ MIGRAZIONE ENHANCED COMPLETATA!");

        } catch (\Exception $e) {
            $this->error("❌ ERRORE: " . $e->getMessage());
            $this->error("Stack: " . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function setupConnection()
    {
        $this->info("🔧 Setup connessione database...");

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

        try {
            DB::connection('old_db')->getPdo();
            $this->info("✅ Connessione al database {$this->oldDb} stabilita");
        } catch (\Exception $e) {
            throw new \Exception("Impossibile connettersi al database {$this->oldDb}: " . $e->getMessage());
        }
    }

    private function analyzeSourceData()
    {
        $this->info("🔍 Analisi dati sorgente...");

        $tables = $this->getAvailableTables();

        $counts = [
            'arbitri' => 0,
            'users' => 0,
            'referees' => 0,
            'circoli' => 0,
            'gare_2025' => 0
        ];

        foreach ($counts as $table => $count) {
            if (in_array($table, $tables)) {
                $counts[$table] = DB::connection('old_db')->table($table)->count();
            }
        }

        $this->info("📊 Conteggi tabelle:");
        foreach ($counts as $table => $count) {
            $status = $count > 0 ? "✅" : "❌";
            $this->info("  {$status} {$table}: {$count}");
        }

        // Identifica strategia migrazione
        $this->identifyMigrationStrategy($counts);
    }

    private function identifyMigrationStrategy($counts)
    {
        $this->info("\n🎯 Strategia di migrazione identificata:");

        if ($counts['referees'] == 0 && $counts['arbitri'] > 0) {
            $this->strategy = 'REBUILD_FROM_ARBITRI';
            $this->info("📋 REBUILD_FROM_ARBITRI: Ricostruzione completa da tabella arbitri");

        } elseif ($counts['users'] > 0 && $counts['referees'] > 0) {
            $this->strategy = 'CONSOLIDATE_EXISTING';
            $this->info("📋 CONSOLIDATE_EXISTING: Consolidamento dati esistenti");

        } elseif ($counts['arbitri'] > 0) {
            $this->strategy = 'HYBRID_APPROACH';
            $this->info("📋 HYBRID_APPROACH: Approccio ibrido arbitri + users/referees");

        } else {
            throw new \Exception("❌ Nessuna strategia valida trovata - dati insufficienti");
        }

        $this->mappingLog['strategy'] = $this->strategy;
    }

    private function cleanExistingData()
    {
        $this->warn("🧹 Pulizia dati esistenti...");

        if (!$this->confirm('Sei sicuro di voler cancellare tutti i dati esistenti?')) {
            $this->info("Migrazione annullata.");
            exit(0);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        $tables = ['assignments', 'availabilities', 'tournaments', 'clubs', 'users', 'referees'];
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

        // Zone obbligatorie
        $zones = [
            ['id' => 1, 'name' => 'Zona 1', 'description' => 'SZR1', 'is_national' => false],
            ['id' => 2, 'name' => 'Zona 2', 'description' => 'SZR2', 'is_national' => false],
            ['id' => 3, 'name' => 'Zona 3', 'description' => 'SZR3', 'is_national' => false],
            ['id' => 4, 'name' => 'Zona 4', 'description' => 'SZR4', 'is_national' => false],
            ['id' => 5, 'name' => 'Zona 5', 'description' => 'SZR5', 'is_national' => false],
            ['id' => 6, 'name' => 'Zona 6', 'description' => 'SZR6', 'is_national' => false],
            ['id' => 7, 'name' => 'Zona 7', 'description' => 'SZR7', 'is_national' => false],
            ['id' => 8, 'name' => 'CRC', 'description' => 'Comitato Regionale Calabria', 'is_national' => true],
        ];

        foreach ($zones as $zone) {
            Zone::updateOrCreate(['id' => $zone['id']], $zone);
        }

        // Tournament Types base
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

    private function migrateUsersAdaptive()
    {
        $this->info("👥 MIGRAZIONE ADATTIVA USERS...");

        if ($this->strategy === 'REBUILD_FROM_ARBITRI') {
            $this->migrateFromArbitriOnly();
        } elseif ($this->strategy === 'CONSOLIDATE_EXISTING') {
            $this->migrateConsolidateExisting();
        } elseif ($this->strategy === 'HYBRID_APPROACH') {
            $this->migrateHybridApproach();
        }
    }

    private function migrateFromArbitriOnly()
    {
        $this->info("🔄 Strategia: Ricostruzione completa da arbitri");

        $arbitri = DB::connection('old_db')->table('arbitri')->get();
        $usersCreated = 0;

        foreach ($arbitri as $arbitro) {
            if (empty($arbitro->Email)) {
                $this->debugLog("Skip arbitro senza email: ID {$arbitro->id}");
                continue;
            }

            $userData = [
                'name' => trim(($arbitro->Nome ?? '') . ' ' . ($arbitro->Cognome ?? '')),
                'email' => $arbitro->Email,
                'password' => Hash::make($arbitro->Password ?? 'password123'),
                'user_type' => 'referee',
                'referee_code' => $this->generateRefereeCodeFromArbitro($arbitro),
                'level' => $this->mapLevelFromArbitro($arbitro->Livello_2025 ?? 'ASP'),
                'category' => 'misto',
                'zone_id' => $this->extractZoneId($arbitro->Zona ?? 'SZR1'),
                'certified_date' => $this->parseDate($arbitro->Prima_Nomina) ?? now(),
                'phone' => $arbitro->Cellulare ?? null,
                'city' => $arbitro->Citta ?? null,
                'is_active' => $this->isActiveArbitro($arbitro->Livello_2025 ?? ''),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $user = User::updateOrCreate(
                ['email' => $arbitro->Email],
                $userData
            );

            $usersCreated++;
            $this->debugLog("User creato: {$user->id} - {$user->name}");
        }

        $this->info("✅ Users creati da arbitri: {$usersCreated}");
        $this->mappingLog['users'] = ['created_from_arbitri' => $usersCreated];
    }

    private function migrateConsolidateExisting()
    {
        $this->info("🔄 Strategia: Consolidamento dati esistenti");

        // 1. Importa users esistenti
        $oldUsers = DB::connection('old_db')->table('users')->get();
        $usersImported = 0;

        foreach ($oldUsers as $oldUser) {
            $userData = [
                'id' => $oldUser->id,
                'name' => $oldUser->name,
                'email' => $oldUser->email,
                'password' => $oldUser->password,
                'email_verified_at' => $oldUser->email_verified_at,
                'remember_token' => $oldUser->remember_token,
                'created_at' => $oldUser->created_at,
                'updated_at' => $oldUser->updated_at,
                'user_type' => $oldUser->user_type ?? 'referee',
                'referee_code' => $oldUser->referee_code ?? 'ARB' . str_pad($oldUser->id, 4, '0', STR_PAD_LEFT),
                'level' => $oldUser->level ?? '1_livello',
                'category' => $oldUser->category ?? 'misto',
                'zone_id' => $oldUser->zone_id ?? 1,
                'certified_date' => $oldUser->certified_date ?? now(),
                'phone' => $oldUser->phone ?? null,
                'city' => $oldUser->city ?? null,
                'is_active' => $oldUser->is_active ?? true,
                'last_login_at' => $oldUser->last_login_at ?? null,
            ];

            User::updateOrCreate(['id' => $oldUser->id], $userData);
            $usersImported++;
        }

        $this->info("✅ Users esistenti importati: {$usersImported}");
        $this->mappingLog['users'] = ['imported_existing' => $usersImported];
    }

    private function migrateHybridApproach()
    {
        // Prima importa users esistenti, poi integra con arbitri
        $this->migrateConsolidateExisting();

        $this->info("🔄 Integrazione con dati arbitri...");

        $arbitri = DB::connection('old_db')->table('arbitri')->get();
        $integrated = 0;

        foreach ($arbitri as $arbitro) {
            if (empty($arbitro->Email)) continue;

            $existingUser = User::where('email', $arbitro->Email)->first();

            if (!$existingUser) {
                // Crea nuovo user da arbitro
                $userData = [
                    'name' => trim(($arbitro->Nome ?? '') . ' ' . ($arbitro->Cognome ?? '')),
                    'email' => $arbitro->Email,
                    'password' => Hash::make($arbitro->Password ?? 'password123'),
                    'user_type' => 'referee',
                    'referee_code' => $this->generateRefereeCodeFromArbitro($arbitro),
                    'level' => $this->mapLevelFromArbitro($arbitro->Livello_2025 ?? 'ASP'),
                    'category' => 'misto',
                    'zone_id' => $this->extractZoneId($arbitro->Zona ?? 'SZR1'),
                    'certified_date' => $this->parseDate($arbitro->Prima_Nomina) ?? now(),
                    'phone' => $arbitro->Cellulare ?? null,
                    'city' => $arbitro->Citta ?? null,
                    'is_active' => $this->isActiveArbitro($arbitro->Livello_2025 ?? ''),
                    'email_verified_at' => now(),
                ];

                User::create($userData);
                $integrated++;
            }
        }

        $this->info("✅ Users integrati da arbitri: {$integrated}");
        $this->mappingLog['users']['integrated_from_arbitri'] = $integrated;
    }

    private function ensureRefereesPopulated()
    {
        $this->info("⚖️ Assicurazione popolazione tabella referees...");

        $usersReferee = User::where('user_type', 'referee')->get();
        $refereesCreated = 0;

        foreach ($usersReferee as $user) {
            $existingReferee = Referee::where('user_id', $user->id)->first();

            if (!$existingReferee) {
                $this->createRefereeFromUser($user);
                $refereesCreated++;
            }
        }

        $this->info("✅ Record referees creati: {$refereesCreated}");
        $this->mappingLog['referees'] = ['created' => $refereesCreated];
    }

    private function createRefereeFromUser(User $user)
    {
        // Cerca dati arbitro corrispondente se disponibile
        $arbitro = null;
        try {
            $arbitro = DB::connection('old_db')
                ->table('arbitri')
                ->where('Email', $user->email)
                ->first();
        } catch (\Exception $e) {
            // Tabella arbitri non disponibile
        }

        $refereeData = [
            'user_id' => $user->id,
            'address' => $arbitro->Pr_abit ?? null,
            'postal_code' => null,
            'tax_code' => null,
            'badge_number' => $user->referee_code,
            'first_certification_date' => $this->parseDate($arbitro->Prima_Nomina ?? $user->certified_date),
            'last_renewal_date' => $this->parseDate($arbitro->Ultimo_Esame ?? $user->certified_date),
            'expiry_date' => $this->calculateExpiryDate($arbitro->Ultimo_Esame ?? $user->certified_date),
            'bio' => null,
            'experience_years' => $this->calculateExperienceYears($arbitro->Prima_Nomina ?? $user->certified_date),
            'qualifications' => null,
            'languages' => null,
            'specializations' => null,
            'available_for_international' => $this->isInternationalLevel($user->level),
            'preferences' => null,
            'total_tournaments' => 0,
            'tournaments_current_year' => 0,
            'profile_completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        Referee::create($refereeData);
        $this->debugLog("Referee creato per user: {$user->id} - {$user->name}");
    }

    private function migrateClubs()
    {
        $this->info("🏌️ Migrazione circoli...");

        $tablesAvailable = $this->getAvailableTables();

        if (in_array('circoli', $tablesAvailable)) {
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

            $this->info("✅ Circoli migrati: {$clubsCreated}");
            $this->mappingLog['clubs'] = $clubsCreated;
        }
    }

    private function migrateTournaments()
    {
        $this->info("🏆 Migrazione tornei...");

        $tablesAvailable = $this->getAvailableTables();

        if (in_array('gare_2025', $tablesAvailable)) {
            $gare = DB::connection('old_db')->table('gare_2025')->get();
            $tournamentsCreated = 0;

            foreach ($gare as $gara) {
                $clubId = $this->findClubIdByNameExact($gara->Circolo ?? '');
                if (!$clubId) {
                    $this->warn("Club non trovato per: " . ($gara->Circolo ?? 'N/A') . " - Torneo: " . ($gara->Nome_gare ?? 'N/A'));
                    continue;
                }

                $club = Club::find($clubId);
                if (!$club) {
                    $this->warn("Club ID {$clubId} non esiste");
                    continue;
                }

                $tournamentData = [
                    'id' => $gara->id,
                    'name' => $gara->Nome_gare ?? 'Torneo',
                    'tournament_type_id' => $this->mapTournamentTypeFromTipo($gara->Tipo ?? 'ZON'),
                    'club_id' => $clubId,
                    'start_date' => $this->parseDate($gara->StartTime) ?? now(),
                    'end_date' => $this->parseDate($gara->EndTime) ?? now(),
                    'zone_id' => $this->extractZoneIdFromGare($gara->Zona ?? null) ?? $club->zone_id,
                    'availability_deadline' => $this->calculateDeadline($gara->StartTime),
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                Tournament::updateOrCreate(['id' => $gara->id], $tournamentData);
                $tournamentsCreated++;

                $this->storeAssignmentDataFromGare($gara);
            }

            $this->info("✅ Tornei migrati: {$tournamentsCreated}");
            $this->mappingLog['tournaments'] = $tournamentsCreated;
        }
    }

    private function migrateAvailabilities()
    {
        $this->info("📋 Migrazione disponibilità...");

        $tablesAvailable = $this->getAvailableTables();
        $availabilitiesCreated = 0;

        // Standard availabilities table
        if (in_array('availabilities', $tablesAvailable)) {
            $oldAvailabilities = DB::connection('old_db')->table('availabilities')->get();

            foreach ($oldAvailabilities as $availability) {
                $userId = User::where('id', $availability->referee_id)->value('id');
                $tournamentId = Tournament::where('id', $availability->tournament_id)->value('id');

                if (!$userId || !$tournamentId) continue;

                Availability::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'tournament_id' => $tournamentId
                    ],
                    [
                        'notes' => $availability->notes ?? null,
                        'submitted_at' => $availability->submitted_at ?? $availability->created_at,
                        'created_at' => $availability->created_at,
                        'updated_at' => $availability->updated_at,
                    ]
                );
                $availabilitiesCreated++;
            }
        }

        // From gare_2025.Disponibili
        if (in_array('gare_2025', $tablesAvailable)) {
            $gareWithDisponibili = DB::connection('old_db')
                ->table('gare_2025')
                ->whereNotNull('Disponibili')
                ->where('Disponibili', '!=', '')
                ->get();

            foreach ($gareWithDisponibili as $gara) {
                $tournament = Tournament::find($gara->id);
                if (!$tournament) continue;

                $disponibili = array_filter(array_map('trim', explode(',', $gara->Disponibili)));

                foreach ($disponibili as $refereeName) {
                    $user = $this->findUserByName($refereeName);
                    if (!$user) continue;

                    Availability::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'tournament_id' => $tournament->id
                        ],
                        [
                            'notes' => 'Importato da gare_2025.Disponibili',
                            'submitted_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                    $availabilitiesCreated++;
                }
            }
        }

        $this->info("✅ Disponibilità migrate: {$availabilitiesCreated}");
        $this->mappingLog['availabilities'] = $availabilitiesCreated;
    }

    private function migrateAssignments()
    {
        $this->info("📝 Migrazione assegnazioni...");

        $tablesAvailable = $this->getAvailableTables();
        $assignmentsCreated = 0;

        // Standard assignments table
        if (in_array('assignments', $tablesAvailable)) {
            $oldAssignments = DB::connection('old_db')->table('assignments')->get();

            foreach ($oldAssignments as $assignment) {
                $userId = User::where('id', $assignment->referee_id)->value('id');
                $tournamentId = Tournament::where('id', $assignment->tournament_id)->value('id');

                if (!$userId || !$tournamentId) continue;

                Assignment::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'tournament_id' => $tournamentId
                    ],
                    [
                        'role' => $assignment->role ?? 'Arbitro',
                        'is_confirmed' => $assignment->is_confirmed ?? false,
                        'assigned_at' => $assignment->assigned_at ?? now(),
                        'assigned_by_id' => $assignment->assigned_by ?? 1,
                        'notes' => $assignment->notes ?? null,
                        'created_at' => $assignment->created_at,
                        'updated_at' => $assignment->updated_at,
                    ]
                );
                $assignmentsCreated++;
            }
        }

        // From stored assignment data
        if (isset($this->assignmentData)) {
            foreach ($this->assignmentData as $data) {
                $tournament = Tournament::find($data['tournament_id']);
                if (!$tournament) continue;

                // TD assignments
                if (!empty($data['td'])) {
                    $tdNames = array_filter(array_map('trim', explode(',', $data['td'])));
                    foreach ($tdNames as $tdName) {
                        $user = $this->findUserByName($tdName);
                        if ($user) {
                            $this->createAssignment($user->id, $tournament->id, 'Direttore di Torneo', 'TD da gare_2025');
                            $assignmentsCreated++;
                        }
                    }
                }

                // Arbitri assignments
                if (!empty($data['arbitri'])) {
                    $arbitriNames = array_filter(array_map('trim', explode(',', $data['arbitri'])));
                    foreach ($arbitriNames as $arbitroName) {
                        $user = $this->findUserByName($arbitroName);
                        if ($user) {
                            $this->createAssignment($user->id, $tournament->id, 'Arbitro', 'Arbitro da gare_2025');
                            $assignmentsCreated++;
                        }
                    }
                }

                // Osservatori assignments
                if (!empty($data['osservatori'])) {
                    $osservatoriNames = array_filter(array_map('trim', explode(',', $data['osservatori'])));
                    foreach ($osservatoriNames as $osservatoreNome) {
                        $user = $this->findUserByName($osservatoreNome);
                        if ($user) {
                            $this->createAssignment($user->id, $tournament->id, 'Osservatore', 'Osservatore da gare_2025');
                            $assignmentsCreated++;
                        }
                    }
                }

                // Comitato assignments
                if (!empty($data['comitato'])) {
                    $comitatoNames = array_filter(array_map('trim', explode(',', $data['comitato'])));
                    foreach ($comitatoNames as $comitatoNome) {
                        $user = $this->findUserByName($comitatoNome);
                        if ($user) {
                            $exists = Assignment::where('user_id', $user->id)
                                ->where('tournament_id', $tournament->id)
                                ->exists();

                            if (!$exists) {
                                $this->createAssignment($user->id, $tournament->id, 'Arbitro', 'Comitato da gare_2025');
                                $assignmentsCreated++;
                            }
                        }
                    }
                }
            }
        }

        $this->info("✅ Assegnazioni migrate: {$assignmentsCreated}");
        $this->mappingLog['assignments'] = $assignmentsCreated;
    }

    private function createAssignment($userId, $tournamentId, $role, $notes)
    {
        Assignment::updateOrCreate(
            [
                'user_id' => $userId,
                'tournament_id' => $tournamentId
            ],
            [
                'role' => $role,
                'is_confirmed' => true,
                'assigned_at' => now(),
                'assigned_by_id' => 1,
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function cleanupOrphanedData()
    {
        $this->info("🧹 Pulizia dati orfani...");

        // Cleanup orphaned availabilities
        $orphanedAvailabilities = DB::table('availabilities')
            ->leftJoin('users', 'availabilities.user_id', '=', 'users.id')
            ->leftJoin('tournaments', 'availabilities.tournament_id', '=', 'tournaments.id')
            ->whereNull('users.id')
            ->orWhereNull('tournaments.id')
            ->count('availabilities.id');

        if ($orphanedAvailabilities > 0) {
            DB::table('availabilities')
                ->leftJoin('users', 'availabilities.user_id', '=', 'users.id')
                ->leftJoin('tournaments', 'availabilities.tournament_id', '=', 'tournaments.id')
                ->where(function($q) {
                    $q->whereNull('users.id')->orWhereNull('tournaments.id');
                })
                ->delete();

            $this->info("🗑️ Rimosse {$orphanedAvailabilities} disponibilità orfane");
        }

        // Cleanup orphaned assignments
        $orphanedAssignments = DB::table('assignments')
            ->leftJoin('users', 'assignments.user_id', '=', 'users.id')
            ->leftJoin('tournaments', 'assignments.tournament_id', '=', 'tournaments.id')
            ->whereNull('users.id')
            ->orWhereNull('tournaments.id')
            ->count('assignments.id');

        if ($orphanedAssignments > 0) {
            DB::table('assignments')
                ->leftJoin('users', 'assignments.user_id', '=', 'users.id')
                ->leftJoin('tournaments', 'assignments.tournament_id', '=', 'tournaments.id')
                ->where(function($q) {
                    $q->whereNull('users.id')->orWhereNull('tournaments.id');
                })
                ->delete();

            $this->info("🗑️ Rimosse {$orphanedAssignments} assegnazioni orfane");
        }
    }

    private function validateMigration()
    {
        $this->info("✅ Validazione migrazione...");

        $stats = [
            'users_total' => User::count(),
            'users_referees' => User::where('user_type', 'referee')->count(),
            'users_admins' => User::whereIn('user_type', ['admin', 'national_admin', 'super_admin'])->count(),
            'referees_records' => Referee::count(),
            'clubs' => Club::count(),
            'tournaments' => Tournament::count(),
            'availabilities' => Availability::count(),
            'assignments' => Assignment::count(),
        ];

        $issues = [];

        // Check users without referee_code
        $refereesWithoutCode = User::where('user_type', 'referee')
            ->whereNull('referee_code')
            ->count();
        if ($refereesWithoutCode > 0) {
            $issues[] = "{$refereesWithoutCode} arbitri senza codice arbitro";
        }

        // Check referee users without referee record
        $refereesWithoutRecord = User::where('user_type', 'referee')
            ->whereDoesntHave('referee')
            ->count();
        if ($refereesWithoutRecord > 0) {
            $issues[] = "{$refereesWithoutRecord} arbitri senza record nella tabella referees";
        }

        // Check tournaments without club
        $tournamentsWithoutClub = Tournament::whereNull('club_id')->count();
        if ($tournamentsWithoutClub > 0) {
            $issues[] = "{$tournamentsWithoutClub} tornei senza circolo";
        }

        $this->mappingLog['validation'] = [
            'stats' => $stats,
            'issues' => $issues,
        ];

        if (!empty($issues)) {
            $this->warn("⚠️ Problemi rilevati:");
            foreach ($issues as $issue) {
                $this->warn("  - {$issue}");
            }
        } else {
            $this->info("✅ Validazione superata senza problemi");
        }
    }

    private function printMigrationReport()
    {
        $this->info("\n" . str_repeat("=", 60));
        $this->info("📊 REPORT MIGRAZIONE FINALE");
        $this->info(str_repeat("=", 60));

        foreach ($this->mappingLog as $section => $data) {
            $this->info("🔸 " . strtoupper($section) . ":");
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (is_array($value)) {
                        $this->info("  {$key}:");
                        foreach ($value as $subKey => $subValue) {
                            $this->info("    {$subKey}: {$subValue}");
                        }
                    } else {
                        $this->info("  {$key}: {$value}");
                    }
                }
            } else {
                $this->info("  Totale: {$data}");
            }
            $this->info("");
        }

        $this->info("✅ Migrazione ENHANCED completata!");
        $this->info("📝 Problema duplicazione User/Referee RISOLTO");
        $this->info("🎯 User è ora la fonte di verità unica per i dati referee");
        $this->info("📋 Tabella referees popolata con dati aggiuntivi");
    }

    // HELPER METHODS
    private function debugLog($message)
    {
        if ($this->debug) {
            $this->line("🐛 {$message}");
        }
    }

    private function getAvailableTables()
    {
        $tables = DB::connection('old_db')->select("SHOW TABLES");
        return array_map(function($table) {
            return array_values((array)$table)[0];
        }, $tables);
    }

    private function isActiveArbitro($livello)
    {
        return !empty($livello) && strtoupper($livello) !== 'ARCH';
    }

    private function isInternationalLevel($level)
    {
        return in_array($level, ['Nazionale', 'Internazionale']);
    }

    private function generateRefereeCodeFromArbitro($arbitro)
    {
        return 'ARB' . str_pad($arbitro->id, 4, '0', STR_PAD_LEFT);
    }

    private function mapLevelFromArbitro($livello)
    {
        $livello = strtoupper(trim($livello));
        if ($livello === 'ASP') return 'Aspirante';
        if ($livello === '1°') return '1_livello';
        if ($livello === 'REG') return 'Regionale';
        if (in_array($livello, ['NAZ', 'NAZ/INT'])) return 'Nazionale';
        if ($livello === 'INT') return 'Internazionale';
        if ($livello === 'ARCH') return 'Archivio';
        return '1_livello';
    }

    private function extractZoneId($zona)
    {
        if (preg_match('/(\d+)/', $zona, $matches)) {
            return min((int)$matches[1], 8);
        }
        return 1;
    }

    private function parseDate($dateString)
    {
        if (empty($dateString)) return null;

        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function calculateExpiryDate($lastRenewalDate)
    {
        if (empty($lastRenewalDate)) return null;

        $renewalDate = $this->parseDate($lastRenewalDate);
        if (!$renewalDate) return null;

        return $renewalDate->addYears(2);
    }

    private function calculateExperienceYears($firstCertificationDate)
    {
        if (empty($firstCertificationDate)) return 0;

        $certDate = $this->parseDate($firstCertificationDate);
        if (!$certDate) return 0;

        return now()->diffInYears($certDate);
    }

    private function mapSedeGara($sedeGara)
    {
        return strtoupper(trim($sedeGara ?? 'Y')) === 'Y';
    }

    private function extractZoneIdFromCircoli($zona)
    {
        if (preg_match('/(\d+)/', $zona ?? '', $matches)) {
            return min((int)$matches[1], 8);
        }
        return 1;
    }

    private function mapTournamentTypeFromTipo($tipo)
    {
        $tipo = strtoupper(trim($tipo ?? ''));
        if (in_array($tipo, ['CN', 'NAZ', 'NAZIONALE'])) return 3;
        if (in_array($tipo, ['CI', 'REG', 'REGIONALE'])) return 2;
        if (in_array($tipo, ['ZON', 'SOC', 'ZONALE'])) return 1;
        return 1;
    }

    private function extractZoneIdFromGare($zona)
    {
        if (empty($zona)) return null;
        if (preg_match('/(\d+)/', $zona, $matches)) {
            return min((int)$matches[1], 8);
        }
        return null;
    }

    private function findClubIdByNameExact($clubName)
    {
        if (empty($clubName)) return null;
        return Club::where('name', 'LIKE', "%{$clubName}%")
            ->orWhere('code', 'LIKE', "%{$clubName}%")
            ->value('id');
    }

    private function calculateDeadline($startTime)
    {
        $startDate = $this->parseDate($startTime);
        if (!$startDate) return now()->format('Y-m-d');
        return $startDate->subDays(7)->format('Y-m-d');
    }

    private function storeAssignmentDataFromGare($gara)
    {
        if (!isset($this->assignmentData)) {
            $this->assignmentData = [];
        }

        $this->assignmentData[] = [
            'tournament_id' => $gara->id,
            'td' => $gara->TD ?? null,
            'arbitri' => $gara->Arbitri ?? null,
            'osservatori' => $gara->Osservatori ?? null,
            'comitato' => $gara->Comitato ?? null,
        ];
    }

    private function findUserByName($name)
    {
        if (empty($name)) return null;

        // Exact match
        $user = User::where('name', $name)->first();
        if ($user) return $user;

        // Partial match
        $user = User::where('name', 'LIKE', "%{$name}%")->first();
        if ($user) return $user;

        // Word parts
        $nameParts = explode(' ', trim($name));
        if (count($nameParts) >= 2) {
            foreach ($nameParts as $part) {
                if (strlen($part) >= 3) {
                    $user = User::where('name', 'LIKE', "%{$part}%")->first();
                    if ($user) return $user;
                }
            }
        }

        return null;
    }
}
