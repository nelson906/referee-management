<?php
// app/Console/Commands/FinalDirectMigrationCommand.php
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
use App\Models\Referee; // Aggiunto import per Referee

class FinalDirectMigrationCommand extends Command
{
    protected $signature = 'golf:final-migration {old_db_name} {--clean : Pulisce i dati esistenti prima di importare}';
    protected $description = 'Migrazione definitiva con approccio USER-CENTRIC per risolvere i problemi di duplicazione';

    private $oldDb;
    private $mappingLog = [];

    public function handle()
    {
        $this->oldDb = $this->argument('old_db_name');

        if ($this->option('clean')) {
            $this->cleanExistingData();
        }

        $this->info("🚀 MIGRAZIONE DEFINITIVA USER-CENTRIC");
        $this->info("Database origine: {$this->oldDb}");

        try {
            // 1. SETUP: Verifica e preparazione
            $this->setupConnection();
            $this->createBaseData();

            // 2. MIGRAZIONE CORE: User-centric approach
            $this->migrateUsersUserCentric();

            // 3. MIGRAZIONE SUPPORTO
            $this->migrateClubs();
            $this->migrateTournaments();
            $this->migrateAvailabilities();
            $this->migrateAssignments();

            // 4. CLEANUP E VERIFICA
            $this->cleanupOrphanedData();
            $this->validateMigration();
            $this->printMigrationReport();

            $this->info("✅ MIGRAZIONE COMPLETATA CON SUCCESSO!");

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

        // Configurazione connessione al vecchio DB
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

        // Test connessione
        try {
            DB::connection('old_db')->getPdo();
            $this->info("✅ Connessione al database {$this->oldDb} stabilita");
        } catch (\Exception $e) {
            throw new \Exception("Impossibile connettersi al database {$this->oldDb}: " . $e->getMessage());
        }
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
            ['id' => 1, 'name' => 'Gara Zonale', 'code' => 'ZON', 'is_national' => false, 'required_level' => 'primo_livello', 'min_referees' => 1, 'max_referees' => 2],
            ['id' => 2, 'name' => 'Coppa Italia', 'code' => 'CI', 'is_national' => true, 'required_level' => 'regionale', 'min_referees' => 2, 'max_referees' => 3],
            ['id' => 3, 'name' => 'Campionato Nazionale', 'code' => 'CN', 'is_national' => true, 'required_level' => 'nazionale', 'min_referees' => 2, 'max_referees' => 4],
        ];

        foreach ($types as $type) {
            TournamentType::updateOrCreate(['id' => $type['id']], $type);
        }

        $this->info("✅ Dati base creati");
    }

    private function migrateUsersUserCentric()
    {
        $this->info("👥 MIGRAZIONE USER-CENTRIC (risolve duplicazione User/Referee)...");

        // Tabelle da verificare nel vecchio DB
        $oldTables = DB::connection('old_db')->select("SHOW TABLES");
        $tableNames = array_map(function($table) {
            return array_values((array)$table)[0];
        }, $oldTables);

        $this->info("Tabelle trovate nel vecchio DB: " . implode(', ', $tableNames));

        // STRATEGIA USER-CENTRIC: User diventa la fonte di verità unica
        $usersCreated = 0;
        $refereesConverted = 0;
        $refereesTableCreated = 0; // Aggiunto contatore per tabella referees
        $adminsCreated = 0;

        // 1. IMPORTA UTENTI BASE
        if (in_array('users', $tableNames)) {
            $this->info("📥 Importazione users...");
            $oldUsers = DB::connection('old_db')->table('users')->get();

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
                    // CAMPI USER-CENTRIC: inizializzazione
                    'user_type' => 'referee', // Default, sarà aggiornato
                    'referee_code' => null,
                    'level' => null,
                    'category' => 'misto',
                    'zone_id' => 1,
                    'certified_date' => null,
                    'phone' => null,
                    'city' => null,
                    'is_active' => true,
                    'last_login_at' => null,
                ];

                User::updateOrCreate(['id' => $oldUser->id], $userData);
                $usersCreated++;
            }
            $this->info("✅ Users base importati: {$usersCreated}");
        }

        // 2. AGGIORNA CON DATI REFEREE (User-centric) + CREA RECORD REFEREES
        if (in_array('referees', $tableNames)) {
            $this->info("⚖️ Consolidamento dati referee in User + Creazione tabella referees...");
            $oldReferees = DB::connection('old_db')->table('referees')->get();

            foreach ($oldReferees as $oldReferee) {
                $user = User::find($oldReferee->user_id);
                if (!$user) {
                    $this->warn("User {$oldReferee->user_id} non trovato per referee, skip");
                    continue;
                }

                // CONSOLIDAMENTO USER-CENTRIC: tutti i dati referee vanno in User
                $user->update([
                    'user_type' => 'referee',
                    'referee_code' => $this->generateRefereeCode($oldReferee),
                    'level' => $this->mapLevel($oldReferee->qualification ?? '1_livello'),
                    'category' => $this->mapCategory($oldReferee->category ?? 'misto'),
                    'zone_id' => $oldReferee->zone_id ?? 1,
                    'certified_date' => $oldReferee->certified_date ?? now(),
                    'phone' => $oldReferee->phone ?? null,
                    'city' => $oldReferee->city ?? null,
                    'is_active' => $oldReferee->active ?? true,
                ]);

                // CREA RECORD NELLA TABELLA REFEREES
                $this->createRefereeRecord($user, $oldReferee);
                $refereesTableCreated++;
                $refereesConverted++;
            }
            $this->info("✅ Dati referee consolidati in User: {$refereesConverted}");
            $this->info("✅ Record referees creati: {$refereesTableCreated}");
        }

        // 3. GESTIONE ADMIN/ROLES
        if (in_array('role_user', $tableNames)) {
            $this->info("👑 Gestione ruoli admin...");
            $roleUsers = DB::connection('old_db')->table('role_user')->get();

            foreach ($roleUsers as $roleUser) {
                $user = User::find($roleUser->user_id);
                if (!$user) continue;

                $userType = match((int)$roleUser->role_id) {
                    1 => 'super_admin',
                    2 => 'national_admin',
                    3 => 'admin',
                    default => 'referee'
                };

                $user->update(['user_type' => $userType]);
                if ($userType !== 'referee') $adminsCreated++;
            }
            $this->info("✅ Admin configurati: {$adminsCreated}");
        }

        // 4. GESTIONE DIRETTA ARBITRI (se presenti nella tabella arbitri)
        if (in_array('arbitri', $tableNames)) {
            $this->info("⚖️ Importazione diretta da tabella arbitri...");
            $arbitri = DB::connection('old_db')->table('arbitri')->get();

            foreach ($arbitri as $arbitro) {
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
                    'is_active' => !empty($arbitro->Livello_2025) && $arbitro->Livello_2025 !== 'ARCH',
                    'email_verified_at' => now(),
                ];

                $user = User::updateOrCreate(
                    ['email' => $arbitro->Email],
                    $userData
                );

                // CREA RECORD NELLA TABELLA REFEREES PER ARBITRI
                $this->createRefereeRecordFromArbitro($user, $arbitro);
                $refereesTableCreated++;
                $refereesConverted++;
            }
            $this->info("✅ Arbitri importati: {$refereesConverted}");
            $this->info("✅ Record referees da arbitri: {$refereesTableCreated}");
        }

        $this->mappingLog['users'] = [
            'total_created' => $usersCreated,
            'referees_converted' => $refereesConverted,
            'referees_table_created' => $refereesTableCreated,
            'admins_created' => $adminsCreated,
        ];
    }

    // NUOVO METODO: Crea record nella tabella referees da dati referee esistenti
    private function createRefereeRecord(User $user, $oldReferee)
    {
        $refereeData = [
            'user_id' => $user->id,
            'address' => $oldReferee->address ?? null,
            'postal_code' => $oldReferee->postal_code ?? null,
            'tax_code' => $oldReferee->tax_code ?? null,
            'badge_number' => $oldReferee->badge_number ?? null,
            'first_certification_date' => $this->parseDate($oldReferee->certified_date),
            'last_renewal_date' => $this->parseDate($oldReferee->last_renewal_date ?? $oldReferee->certified_date),
            'expiry_date' => $this->calculateExpiryDate($oldReferee->last_renewal_date ?? $oldReferee->certified_date),
            'bio' => $oldReferee->bio ?? null,
            'experience_years' => $oldReferee->experience_years ?? 0,
            'qualifications' => $this->parseJsonField($oldReferee->qualifications),
            'languages' => $this->parseJsonField($oldReferee->languages),
            'specializations' => $this->parseJsonField($oldReferee->specializations),
            'available_for_international' => $oldReferee->available_for_international ?? false,
            'preferences' => $this->parseJsonField($oldReferee->preferences),
            'total_tournaments' => $oldReferee->total_tournaments ?? 0,
            'tournaments_current_year' => $oldReferee->tournaments_current_year ?? 0,
            'profile_completed_at' => $oldReferee->profile_completed_at ?? now(),
            'created_at' => $oldReferee->created_at ?? now(),
            'updated_at' => $oldReferee->updated_at ?? now(),
        ];

        Referee::updateOrCreate(['user_id' => $user->id], $refereeData);
    }

    // NUOVO METODO: Crea record nella tabella referees da dati arbitri
    private function createRefereeRecordFromArbitro(User $user, $arbitro)
    {
        $refereeData = [
            'user_id' => $user->id,
            // Mapping esatto da database_map.txt
            'address' => $arbitro->Pr_abit ?? null, // arbitri.Pr_abit --> referees.address
            'postal_code' => null, // Non presente in arbitri
            'tax_code' => null, // Non presente in arbitri
            'badge_number' => $this->generateRefereeCodeFromArbitro($arbitro),
            'first_certification_date' => $this->parseDate($arbitro->Prima_Nomina), // arbitri.Prima_Nomina --> referees.first_certification_date
            'last_renewal_date' => $this->parseDate($arbitro->Ultimo_Esame), // arbitri.Ultimo_Esame --> referees.last_renewal_date
            'expiry_date' => $this->calculateExpiryDate($arbitro->Ultimo_Esame),
            'bio' => null,
            'experience_years' => $this->calculateExperienceYears($arbitro->Prima_Nomina),
            'qualifications' => null,
            'languages' => null,
            'specializations' => null,
            'available_for_international' => $this->isInternationalLevel($arbitro->Livello_2025 ?? ''),
            'preferences' => null,
            'total_tournaments' => 0, // Da calcolare se necessario
            'tournaments_current_year' => 0, // Da calcolare se necessario
            'profile_completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        Referee::updateOrCreate(['user_id' => $user->id], $refereeData);
    }

    private function migrateClubs()
    {
        $this->info("🏌️ Migrazione circoli - MAPPING ESATTO da 'circoli'");

        $tablesAvailable = $this->getAvailableTables();

        if (in_array('circoli', $tablesAvailable)) {
            $this->info("📥 Importazione da tabella 'circoli' con mapping esatto...");
            $circoli = DB::connection('old_db')->table('circoli')->get();

            $clubsCreated = 0;
            foreach ($circoli as $circolo) {
                // ✅ MAPPING ESATTO DA database_map.txt
                $clubData = [
                    'id' => $circolo->Id, // circoli.Id --> clubs.id
                    'code' => $circolo->Circolo_Id ?? 'CLUB' . $circolo->Id, // circoli.Circolo_Id --> clubs.code
                    'name' => $circolo->Circolo_Nome ?? 'Club', // circoli.Circolo_Nome --> clubs.name
                    'address' => $circolo->Indirizzo ?? null, // circoli.Indirizzo --> clubs.address
                    'postal_code' => $circolo->CAP ?? null, // circoli.CAP --> clubs.postal_code
                    'city' => $circolo->Città ?? null, // circoli.Città --> clubs.city (nullable)
                    'province' => $circolo->Provincia ?? null, // circoli.Provincia --> clubs.province
                    'region' => $circolo->Regione ?? null, // circoli.Regione --> clubs.region
                    'email' => $circolo->Email ?? null, // circoli.Email --> clubs.email
                    'phone' => $circolo->Telefono ?? null, // circoli.Telefono --> clubs.phone
                    'website' => $circolo->Web ?? null, // circoli.Web --> clubs.website
                    'is_active' => $this->mapSedeGara($circolo->SedeGara ?? 'Y'), // circoli.SedeGara --> clubs.is_active
                    'zone_id' => $this->extractZoneIdFromCircoli($circolo->Zona ?? 'SZR1'), // circoli.Zona --> clubs.zone_id
                    'contact_person' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                Club::updateOrCreate(['id' => $circolo->Id], $clubData);
                $clubsCreated++;
            }

            $this->info("✅ Circoli migrati da 'circoli': {$clubsCreated}");
            $this->mappingLog['clubs'] = $clubsCreated;
        } else {
            $this->warn("Tabella 'circoli' non trovata");
        }
    }

    private function migrateTournaments()
    {
        $this->info("🏆 Migrazione tornei - MAPPING ESATTO da 'gare_2025'");

        $tablesAvailable = $this->getAvailableTables();

        if (in_array('gare_2025', $tablesAvailable)) {
            $this->info("📥 Importazione da tabella 'gare_2025' con mapping esatto...");
            $gare = DB::connection('old_db')->table('gare_2025')->get();

            $tournamentsCreated = 0;
            foreach ($gare as $gara) {
                // Trova club_id dal nome del circolo
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

                // ✅ MAPPING ESATTO DA database_map.txt
                $tournamentData = [
                    'id' => $gara->id, // gare_2025.id --> tournaments.id
                    'name' => $gara->Nome_gare ?? 'Torneo', // gare_2025.Nome_gare --> tournaments.name
                    'tournament_type_id' => $this->mapTournamentTypeFromTipo($gara->Tipo ?? 'ZON'), // gare_2025.Tipo --> tournaments.tournament_type_id
                    'club_id' => $clubId, // gare_2025.Circolo --> tournaments.club_id (via lookup)
                    'start_date' => $this->parseDate($gara->StartTime) ?? now(), // gare_2025.StartTime --> tournaments.start_date
                    'end_date' => $this->parseDate($gara->EndTime) ?? now(), // gare_2025.EndTime --> tournaments.end_date
                    'zone_id' => $this->extractZoneIdFromGare($gara->Zona ?? null) ?? $club->zone_id, // gare_2025.Zona --> tournaments.zone_id
                    'availability_deadline' => $this->calculateDeadline($gara->StartTime),
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                Tournament::updateOrCreate(['id' => $gara->id], $tournamentData);
                $tournamentsCreated++;

                // Store assignment data per dopo (TD, Arbitri, Osservatori, Comitato)
                $this->storeAssignmentDataFromGare($gara);
            }

            $this->info("✅ Tornei migrati da 'gare_2025': {$tournamentsCreated}");
            $this->mappingLog['tournaments'] = $tournamentsCreated;
        } else {
            $this->warn("Tabella 'gare_2025' non trovata");
        }
    }

    private function storeAssignmentDataFromGare($gara)
    {
        // Store per processare in migrateAssignments
        if (!isset($this->assignmentData)) {
            $this->assignmentData = [];
        }

        $this->assignmentData[] = [
            'tournament_id' => $gara->id,
            'td' => $gara->TD ?? null, // gare_2025.TD --> assignments.role (Direttore di Torneo)
            'arbitri' => $gara->Arbitri ?? null, // gare_2025.Arbitri --> assignments.role (Arbitro)
            'osservatori' => $gara->Osservatori ?? null, // gare_2025.Osservatori --> assignments.role (Osservatore)
            'comitato' => $gara->Comitato ?? null, // gare_2025.Comitato --> assignments (nomi separati da virgole)
        ];
    }

    private function migrateAvailabilities()
    {
        $this->info("📋 Migrazione disponibilità - MAPPING ESATTO da 'gare_2025.Disponibili'");

        $tablesAvailable = $this->getAvailableTables();
        $availabilitiesCreated = 0;

        // Prima controlla se esiste tabella availabilities standard
        if (in_array('availabilities', $tablesAvailable)) {
            $this->info("📥 Importazione da tabella 'availabilities' standard...");
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

        // ✅ MAPPING ESATTO: gare_2025.Disponibili --> availabilities (nomi separati da virgole)
        if (in_array('gare_2025', $tablesAvailable)) {
            $this->info("📥 Processamento campo 'Disponibili' da 'gare_2025'...");
            $gareWithDisponibili = DB::connection('old_db')
                ->table('gare_2025')
                ->whereNotNull('Disponibili')
                ->where('Disponibili', '!=', '')
                ->get();

            foreach ($gareWithDisponibili as $gara) {
                $tournament = Tournament::find($gara->id);
                if (!$tournament) {
                    $this->warn("Tournament {$gara->id} non trovato per disponibilità");
                    continue;
                }

                // Parse nomi separati da virgole
                $disponibili = array_filter(array_map('trim', explode(',', $gara->Disponibili)));

                foreach ($disponibili as $refereeName) {
                    $user = $this->findUserByName($refereeName);
                    if (!$user) {
                        $this->warn("Utente non trovato per disponibilità: '{$refereeName}'");
                        continue;
                    }

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
        $this->info("📝 Migrazione assegnazioni - MAPPING ESATTO da 'gare_2025' (TD, Arbitri, Osservatori, Comitato)");

        $tablesAvailable = $this->getAvailableTables();
        $assignmentsCreated = 0;

        // Prima controlla se esiste tabella assignments standard
        if (in_array('assignments', $tablesAvailable)) {
            $this->info("📥 Importazione da tabella 'assignments' standard...");
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

        // ✅ MAPPING ESATTO: processo i dati da gare_2025 (TD, Arbitri, Osservatori, Comitato)
        if (isset($this->assignmentData)) {
            $this->info("📥 Processamento assegnazioni da 'gare_2025' (TD, Arbitri, Osservatori, Comitato)...");

            foreach ($this->assignmentData as $data) {
                $tournament = Tournament::find($data['tournament_id']);
                if (!$tournament) continue;

                // gare_2025.TD --> assignments.role (Direttore di Torneo)
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

                // gare_2025.Arbitri --> assignments.role (Arbitro)
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

                // gare_2025.Osservatori --> assignments.role (Osservatore)
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

                // gare_2025.Comitato --> assignments (nomi separati da virgole, ruolo da determinare)
                if (!empty($data['comitato'])) {
                    $comitatoNames = array_filter(array_map('trim', explode(',', $data['comitato'])));
                    foreach ($comitatoNames as $comitatoNome) {
                        $user = $this->findUserByName($comitatoNome);
                        if ($user) {
                            // Se non è già assegnato in un altro ruolo, assegna come Arbitro
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
                'is_confirmed' => true, // Assumo confermato se importato
                'assigned_at' => now(),
                'assigned_by_id' => 1, // Default system user
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function cleanupOrphanedData()
    {
        $this->info("🧹 Pulizia dati orfani...");

        // Rimuovi availability senza user o tournament validi
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

        // Analogo per assignments
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
            'referees_records' => Referee::count(), // Aggiunto conteggio referees
            'clubs' => Club::count(),
            'tournaments' => Tournament::count(),
            'availabilities' => Availability::count(),
            'assignments' => Assignment::count(),
        ];

        // Controlli di integrità
        $issues = [];

        // Users senza referee_code che sono referee
        $refereesWithoutCode = User::where('user_type', 'referee')
            ->whereNull('referee_code')
            ->count();
        if ($refereesWithoutCode > 0) {
            $issues[] = "{$refereesWithoutCode} arbitri senza codice arbitro";
        }

        // Users referee senza record nella tabella referees
        $refereesWithoutRecord = User::where('user_type', 'referee')
            ->whereDoesntHave('referee')
            ->count();
        if ($refereesWithoutRecord > 0) {
            $issues[] = "{$refereesWithoutRecord} arbitri senza record nella tabella referees";
        }

        // Tournaments senza club
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

        $this->info("✅ Migrazione USER-CENTRIC completata!");
        $this->info("📝 Problema duplicazione User/Referee RISOLTO");
        $this->info("🎯 User è ora la fonte di verità unica per i dati referee");
        $this->info("📋 Tabella referees popolata con dati aggiuntivi");
    }

    // HELPER METHODS - MAPPING ESATTO DA database_map.txt
    private function getAvailableTables(): array
    {
        $tables = DB::connection('old_db')->select("SHOW TABLES");
        return array_map(function($table) {
            return array_values((array)$table)[0];
        }, $tables);
    }

    // NUOVI HELPER METHODS PER REFEREES
    private function parseJsonField($field)
    {
        if (empty($field)) return null;
        if (is_string($field)) {
            $decoded = json_decode($field, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }
        return $field;
    }

    private function calculateExpiryDate($lastRenewalDate)
    {
        if (empty($lastRenewalDate)) return null;

        $renewalDate = $this->parseDate($lastRenewalDate);
        if (!$renewalDate) return null;

        // Aggiungi 2 anni alla data di ultimo rinnovo
        return $renewalDate->addYears(2);
    }

    private function calculateExperienceYears($firstCertificationDate)
    {
        if (empty($firstCertificationDate)) return 0;

        $certDate = $this->parseDate($firstCertificationDate);
        if (!$certDate) return 0;

        return now()->diffInYears($certDate);
    }

    private function isInternationalLevel($level)
    {
        return in_array(strtoupper(trim($level)), ['NAZ', 'INT', 'NAZ/INT']);
    }

    // ARBITRI MAPPING HELPERS
    private function mapRole($role): string
    {
        return match (strtolower(trim($role ?? ''))) {
            'super_admin', 'superadmin', '1' => 'super_admin',
            'national_admin', 'nationaladmin', 'crc', '2' => 'national_admin',
            'admin', 'zone_admin', 'zoneadmin', '3' => 'admin',
            'referee', 'arbitro', '' => 'referee',
            default => 'referee'
        };
    }

    private function mapArbitroStatus($arbitro): bool
    {
        // arbitri.Arbitro --> users.is_active (A=active)
        return strtoupper(trim($arbitro ?? '')) === 'A';
    }

    private function mapCategoryFromCircolo($circolo): string
    {
        // arbitri.Circolo --> users.category
        return 'misto'; // Default, si può affinare se necessario
    }

    private function extractZoneIdFromArbitri($zona): int
    {
        // arbitri.Zona --> users.zone_id
        if (preg_match('/(\d+)/', $zona ?? '', $matches)) {
            return min((int)$matches[1], 8); // Max 8 zone (incluso CRC)
        }
        return 1;
    }

    // CIRCOLI MAPPING HELPERS
    private function mapSedeGara($sedeGara): bool
    {
        // circoli.SedeGara --> clubs.is_active
        return strtoupper(trim($sedeGara ?? 'Y')) === 'Y';
    }

    private function extractZoneIdFromCircoli($zona): int
    {
        // circoli.Zona --> clubs.zone_id
        if (preg_match('/(\d+)/', $zona ?? '', $matches)) {
            return min((int)$matches[1], 8);
        }
        return 1;
    }

    // GARE_2025 MAPPING HELPERS
    private function mapTournamentTypeFromTipo($tipo): int
    {
        // gare_2025.Tipo --> tournaments.tournament_type_id
        return match (strtoupper(trim($tipo ?? ''))) {
            'CN', 'NAZ', 'NAZIONALE' => 3, // Nazionale
            'CI', 'REG', 'REGIONALE' => 2, // Regionale/Coppa Italia
            'ZON', 'SOC', 'ZONALE' => 1, // Zonale
            default => 1
        };
    }

    private function extractZoneIdFromGare($zona): ?int
    {
        // gare_2025.Zona --> tournaments.zone_id
        if (empty($zona)) return null;

        if (preg_match('/(\d+)/', $zona, $matches)) {
            return min((int)$matches[1], 8);
        }
        return null;
    }

    private function findClubIdByNameExact($clubName): ?int
    {
        // gare_2025.Circolo --> tournaments.club_id (via lookup)
        if (empty($clubName)) return null;

        return Club::where('name', 'LIKE', "%{$clubName}%")
            ->orWhere('code', 'LIKE', "%{$clubName}%")
            ->value('id');
    }

    private function calculateDeadline($startTime): string
    {
        $startDate = $this->parseDate($startTime);
        if (!$startDate) return now()->format('Y-m-d');

        return $startDate->subDays(7)->format('Y-m-d');
    }

    // COMMON HELPERS
    private function parseDate($dateString): ?\Carbon\Carbon
    {
        if (empty($dateString)) return null;

        try {
            return \Carbon\Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function findUserByName($name): ?User
    {
        if (empty($name)) return null;

        // Cerca per nome esatto
        $user = User::where('name', $name)->first();
        if ($user) return $user;

        // Cerca per nome parziale
        $user = User::where('name', 'LIKE', "%{$name}%")->first();
        if ($user) return $user;

        // Cerca per singole parole del nome
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

    private function generateRefereeCode($referee): string
    {
        return $referee->referee_code ?? 'ARB' . str_pad($referee->user_id, 4, '0', STR_PAD_LEFT);
    }

    private function generateRefereeCodeFromArbitro($arbitro): string
    {
        return 'ARB' . str_pad($arbitro->id, 4, '0', STR_PAD_LEFT);
    }

    private function mapLevel($qualification): string
    {
        return match (strtolower(trim($qualification))) {
            'aspirante' => 'Aspirante',
            'primo livello', '1° livello', '1_livello' => '1_livello',
            'regionale' => 'Regionale',
            'nazionale', 'internazionale', 'nazionale/internazionale' => 'Nazionale',
            'archivio' => 'Archivio',
            default => '1_livello'
        };
    }

    private function mapLevelFromArbitro($livello): string
    {
        return match (strtoupper(trim($livello))) {
            'ASP' => 'Aspirante',
            '1°' => '1_livello',
            'REG' => 'Regionale',
            'NAZ', 'NAZ/INT' => 'Nazionale',
            'INT' => 'Internazionale',
            'ARCH' => 'Archivio',
            default => '1_livello'
        };
    }

    private function mapCategory($category): string
    {
        return match (strtolower(trim($category))) {
            'maschile', 'm' => 'maschile',
            'femminile', 'f' => 'femminile',
            'misto', 'mixed' => 'misto',
            default => 'misto'
        };
    }

    private function extractZoneId($zona): int
    {
        if (preg_match('/(\d+)/', $zona, $matches)) {
            return min((int)$matches[1], 7); // Max 7 zone
        }
        return 1;
    }

    private function findClubIdByName($clubName): ?int
    {
        return Club::where('name', 'LIKE', "%{$clubName}%")
            ->orWhere('code', 'LIKE', "%{$clubName}%")
            ->value('id');
    }

    private function mapTournamentType($tipo): int
    {
        return match (strtoupper(trim($tipo))) {
            'CN', 'NAZ' => 3, // Nazionale
            'CI', 'REG' => 2, // Regionale/Coppa Italia
            'ZON', 'SOC' => 1, // Zonale
            default => 1
        };
    }
}
