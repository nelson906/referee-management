<?php

/**
 * ========================================
 * ImportOldDataCommand.php - VERSIONE 42 CORRETTA
 * ========================================
 * Fix per errore "Undefined property: stdClass::$address"
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Zone;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\InstitutionalEmail;
use App\Models\Letterhead;
use App\Models\Referee;

class ImportOldDataCommand extends Command
{
    protected $signature = 'golf:import-old-data {--old-db=gestione_arbitri} {--dry-run}';
    protected $description = 'Importa i dati dal vecchio sistema gestione_arbitri';

    private $oldDatabase;
    private $dryRun;

    public function handle()
    {
        $this->oldDatabase = $this->option('old-db');
        $this->dryRun = $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('🔍 MODALITÀ DRY-RUN: Nessuna modifica sarà effettuata');
        }

        $this->info("Inizio importazione dati dal database: {$this->oldDatabase}");

        // Configura connessione al database vecchio
        $this->setupOldDatabaseConnection();

        // Verifica che il database vecchio esista
        if (!$this->checkOldDatabase()) {
            $this->error("Database {$this->oldDatabase} non trovato!");
            return 1;
        }

        try {
            // Ordine di importazione ottimizzato
            $this->ensureBaseZones();
            $this->importZones();
            $this->importTournamentTypes();
            $this->importUsers(); // ✅ Logica unificata users+referees
            $this->importClubs();
            $this->importTournaments();
            $this->importAvailabilities();
            $this->importAssignments();
            $this->importSupportData();

            $this->info('✅ Importazione completata con successo!');
        } catch (\Exception $e) {
            $this->error('❌ Errore durante importazione: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function setupOldDatabaseConnection()
    {
        $this->info("Configurazione connessione al database: {$this->oldDatabase}");

        config(['database.connections.old_db' => [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => $this->oldDatabase,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        // ✅ Test della connessione con info dettagliate
        try {
            $pdo = DB::connection('old_db')->getPdo();
            $this->info('✅ Connessione al database vecchio riuscita');

            // Test query per verificare l'accesso
            $result = DB::connection('old_db')->select('SELECT DATABASE() as current_db');
            $this->info('Database attualmente connesso: ' . $result[0]->current_db);

        } catch (\Exception $e) {
            $this->error('❌ Errore connessione database: ' . $e->getMessage());
            throw $e;
        }
    }

    private function checkOldDatabase(): bool
    {
        try {
            DB::connection('old_db')->getPdo();
            return true;
        } catch (\Exception $e) {
            $this->error('Database check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Verifica se una tabella esiste nel database vecchio
     */
    private function tableExists(string $tableName): bool
    {
        try {
            $exists = DB::connection('old_db')->getSchemaBuilder()->hasTable($tableName);
            $this->info("Tabella '{$tableName}': " . ($exists ? 'TROVATA' : 'NON TROVATA'));
            return $exists;
        } catch (\Exception $e) {
            $this->error("Errore controllo tabella '{$tableName}': " . $e->getMessage());
            return false;
        }
    }

    private function ensureBaseZones()
    {
        if ($this->dryRun) {
            $this->info('[DRY-RUN] Verificherei zone base...');
            return;
        }

        $this->info('Verifica zone base...');

        // Se non ci sono zone, crea quelle di base
        if (Zone::count() === 0) {
            $this->info('Creazione zone base...');

            $zones = [
                ['name' => 'Zona 1', 'description' => 'Zona 1', 'is_national' => false],
                ['name' => 'Zona 2', 'description' => 'Zona 2', 'is_national' => false],
                ['name' => 'Zona 3', 'description' => 'Zona 3', 'is_national' => false],
                ['name' => 'Zona 4', 'description' => 'Zona 4', 'is_national' => false],
                ['name' => 'Zona 5', 'description' => 'Zona 5', 'is_national' => false],
                ['name' => 'Zona 6', 'description' => 'Zona 6', 'is_national' => false],
                ['name' => 'Zona 7', 'description' => 'Zona 7', 'is_national' => false],
                ['name' => 'Comitato Regionale Calabria', 'description' => 'CRC', 'is_national' => true],
            ];

            foreach ($zones as $zone) {
                Zone::create($zone);
            }

            $this->info('Zone base create: ' . Zone::count());
        }

        // Se non ci sono tournament types, crea quelli di base
        if (TournamentType::count() === 0) {
            $this->info('Creazione tipi torneo base...');

            $types = [
                [
                    'name' => 'Gara Sociale',
                    'code' => 'SOC',
                    'description' => 'Gara sociale del circolo',
                    'is_national' => false,
                    'level' => 'zonale',
                    'required_level' => 'primo_livello',
                    'min_referees' => 1,
                    'max_referees' => 2,
                    'sort_order' => 10,
                    'settings' => json_encode([
                        'required_referee_level' => 'primo_livello',
                        'min_referees' => 1,
                        'max_referees' => 2,
                        'visibility_zones' => 'own'
                    ])
                ],
                [
                    'name' => 'Coppa Italia',
                    'code' => 'CI',
                    'description' => 'Coppa Italia zonale',
                    'is_national' => false,
                    'level' => 'zonale',
                    'required_level' => 'regionale',
                    'min_referees' => 2,
                    'max_referees' => 3,
                    'sort_order' => 20,
                    'settings' => json_encode([
                        'required_referee_level' => 'regionale',
                        'min_referees' => 2,
                        'max_referees' => 3,
                        'visibility_zones' => 'own'
                    ])
                ],
                [
                    'name' => 'Campionato Nazionale',
                    'code' => 'CN',
                    'description' => 'Campionato nazionale',
                    'is_national' => true,
                    'level' => 'nazionale',
                    'required_level' => 'nazionale',
                    'min_referees' => 3,
                    'max_referees' => 5,
                    'sort_order' => 30,
                    'settings' => json_encode([
                        'required_referee_level' => 'nazionale',
                        'min_referees' => 3,
                        'max_referees' => 5,
                        'visibility_zones' => 'all'
                    ])
                ]
            ];

            foreach ($types as $type) {
                TournamentType::create($type);
            }

            $this->info('Tipi torneo base creati: ' . TournamentType::count());
        }
    }

    private function importZones()
    {
        $this->info('Importazione zone...');

        try {
            // ✅ Test esplicito della connessione e della tabella
            if (!$this->tableExists('zones')) {
                $this->warn('Tabella zones non trovata nel vecchio database, uso zone di default');
                return;
            }

            $oldZones = DB::connection('old_db')->table('zones')->get();
            $this->info('Trovate ' . count($oldZones) . ' zone nel database vecchio');

            foreach ($oldZones as $oldZone) {
                if ($this->dryRun) {
                    $this->info("[DRY-RUN] Importerei zona: {$oldZone->name}");
                    continue;
                }

                Zone::updateOrCreate(
                    ['id' => $oldZone->id],
                    [
                        'name' => $oldZone->name,
                        'description' => $oldZone->description ?? null,
                        'is_national' => $oldZone->is_national ?? false,
                        'header_document_path' => $oldZone->header_document_path ?? null,
                        'header_updated_at' => $oldZone->header_updated_at ?? null,
                        'header_updated_by' => $oldZone->header_updated_by ?? null,
                        'created_at' => $oldZone->created_at,
                        'updated_at' => $oldZone->updated_at,
                    ]
                );
            }

            $this->info('Zone importate: ' . count($oldZones));
        } catch (\Exception $e) {
            $this->error('ERRORE durante importazione zone: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ' Linea: ' . $e->getLine());
            throw $e; // Re-throw per fermare l'esecuzione
        }
    }

    private function importTournamentTypes()
    {
        $this->info('Importazione tipi torneo...');

        try {
            // ✅ Test esplicito della connessione e della tabella
            if (!$this->tableExists('tournament_types')) {
                $this->warn('Tabella tournament_types non trovata, uso tipi di default');
                return;
            }

            $oldTypes = DB::connection('old_db')->table('tournament_types')->get();
            $this->info('Trovati ' . count($oldTypes) . ' tipi torneo nel database vecchio');

            foreach ($oldTypes as $oldType) {
                if ($this->dryRun) {
                    $this->info("[DRY-RUN] Importerei tipo torneo: {$oldType->name}");
                    continue;
                }

                $requiredLevel = $this->mapRequiredLevel($oldType->required_level ?? 'primo_livello');
                $minReferees = $oldType->min_referees ?? 1;
                $maxReferees = $oldType->max_referees ?? ($oldType->referees_needed ?? $minReferees);

                $settings = [
                    'required_referee_level' => $requiredLevel,
                    'min_referees' => $minReferees,
                    'max_referees' => $maxReferees,
                    'visibility_zones' => ($oldType->is_national ?? false) ? 'all' : 'own',
                    'special_requirements' => $oldType->special_requirements ?? null,
                ];

                TournamentType::updateOrCreate(
                    ['id' => $oldType->id],
                    [
                        'name' => $oldType->name,
                        'code' => $oldType->code ?? ($oldType->short_name ?? ('TT' . $oldType->id)),
                        'description' => $oldType->description ?? null,
                        'is_national' => $oldType->is_national ?? false,
                        'level' => ($oldType->is_national ?? false) ? 'nazionale' : 'zonale',
                        'required_level' => $requiredLevel,
                        'min_referees' => $minReferees,
                        'max_referees' => $maxReferees,
                        'sort_order' => $oldType->sort_order ?? ($oldType->id * 10),
                        'is_active' => $oldType->is_active ?? true,
                        'settings' => json_encode($settings),
                        'created_at' => $oldType->created_at,
                        'updated_at' => $oldType->updated_at,
                    ]
                );
            }

            $this->info('Tipi torneo importati: ' . count($oldTypes));
        } catch (\Exception $e) {
            $this->error('ERRORE durante importazione tipi torneo: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ' Linea: ' . $e->getLine());
            throw $e; // Re-throw per fermare l'esecuzione
        }
    }

    /**
     * ✅ UPDATED: Importazione users con logica unificata e debug migliorato
     */
    private function importUsers()
    {
        $this->info('Importazione utenti con logica unificata...');

        try {
            // 1. Importa tutti gli users di base
            $oldUsers = DB::connection('old_db')->table('users')->get();
            $this->info('Trovati ' . count($oldUsers) . ' utenti nel database vecchio');

            $oldReferees = collect();
            $oldRoleUsers = collect();

            // 2. Carica referees se esistono
            try {
                if ($this->tableExists('referees')) {
                    $oldReferees = DB::connection('old_db')->table('referees')->get()->keyBy('user_id');
                    $this->info('Trovati ' . $oldReferees->count() . ' referees nel database vecchio');
                }
            } catch (\Exception $e) {
                $this->warn('Errore caricamento tabella referees: ' . $e->getMessage());
            }

            // 3. Carica role_user se esistono
            try {
                if ($this->tableExists('role_user')) {
                    $oldRoleUsers = DB::connection('old_db')->table('role_user')->get()->groupBy('user_id');
                    $this->info('Trovati ruoli per ' . $oldRoleUsers->count() . ' utenti');

                    if ($this->dryRun) {
                        // ✅ AGGIUNTO: Debug per vedere tutti i role_id presenti
                        $allRoles = DB::connection('old_db')->table('role_user')->select('role_id')->distinct()->get();
                        $roleIds = $allRoles->pluck('role_id')->toArray();
                        $this->info("[DRY-RUN] Role IDs presenti nel database: " . implode(', ', $roleIds));

                        // ✅ AGGIUNTO: Mostra alcuni esempi di role_user
                        $sampleRoles = DB::connection('old_db')->table('role_user')->limit(10)->get();
                        $this->info("[DRY-RUN] Esempi di role_user:");
                        foreach ($sampleRoles as $role) {
                            $this->info("   - User {$role->user_id} -> Role {$role->role_id}");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->warn('Errore caricamento tabella role_user: ' . $e->getMessage());
            }

            // 4. Processa ogni utente
            $processedUsers = 0;
            $refereeUsers = 0;
            $adminUsers = 0;

            foreach ($oldUsers as $oldUser) {
                try {
                    // ✅ Determina il tipo di utente
                    $userType = $this->determineUserType($oldUser, $oldReferees, $oldRoleUsers);
                    $referee = $oldReferees->get($oldUser->id);

                    // ✅ Determina zone_id dalla tabella referees se esiste
                    $zoneId = 1; // Default
                    if ($referee && isset($referee->zone_id)) {
                        $zoneId = $referee->zone_id;
                    }

                    // ✅ Dati base per tutti gli utenti (SENZA campi referee)
                    $userData = [
                        'name' => $oldUser->name,
                        'email' => $oldUser->email,
                        'email_verified_at' => $oldUser->email_verified_at,
                        'password' => $oldUser->password,
                        'remember_token' => $oldUser->remember_token,
                        'user_type' => $userType,
                        'zone_id' => $zoneId,
                        'phone' => isset($oldUser->phone) ? $oldUser->phone : null,
                        'city' => null, // Non presente nel vecchio DB
                        'is_active' => isset($oldUser->is_active) ? $oldUser->is_active : true,
                        'created_at' => $oldUser->created_at,
                        'updated_at' => $oldUser->updated_at,
                    ];

                    // ✅ FIXED: Campi referee SOLO per gli arbitri
                    if ($userType === 'referee') {
                        if ($referee) {
                            // Dati reali dell'arbitro dalla tabella referees
                            $userData['referee_code'] = isset($referee->referee_code) ? $referee->referee_code : $this->generateRefereeCode();
                            $userData['level'] = isset($referee->qualification) ? $this->mapQualification($referee->qualification) : 'aspirante';
                            $userData['category'] = isset($referee->category) ? $referee->category : 'misto';
                            $userData['certified_date'] = isset($referee->certified_date) ? $referee->certified_date : now()->subYears(2);
                        } else {
                            // ✅ FIXED: Prova a prendere i dati dalla tabella users se non c'è tabella referees
                            $userData['referee_code'] = isset($oldUser->referee_code) ? $oldUser->referee_code : $this->generateRefereeCode();
                            $userData['level'] = isset($oldUser->level) ? $this->mapQualification($oldUser->level) : 'aspirante';
                            $userData['category'] = isset($oldUser->category) ? $oldUser->category : 'misto';
                            $userData['certified_date'] = isset($oldUser->certified_date) ? $oldUser->certified_date : now()->subYears(2);
                        }
                        $refereeUsers++;
                    } else {
                        // ✅ FIXED: Per utenti non-referee, usa valori di default NON NULL
                        $userData['referee_code'] = 'N/A'; // Non NULL per evitare constraint violation
                        $userData['level'] = 'aspirante';
                        $userData['category'] = 'misto';
                        $userData['certified_date'] = now()->subYears(10); // Data di default se la colonna è NOT NULL
                        $adminUsers++;
                    }

                    // ✅ DRY-RUN: Mostra dettagli completi
                    if ($this->dryRun) {
                        $this->info("[DRY-RUN] User: {$oldUser->name} -> Tipo: {$userType}");
                        if ($oldReferees->count() > 0) {
                            $hasRefereeData = $oldReferees->has($oldUser->id) ? 'SI' : 'NO';
                            $this->info("   - Ha dati referee: {$hasRefereeData}");
                        }
                        continue;
                    }

                    // ✅ IMPORTAZIONE REALE - Usa esplicitamente la connessione di default
                    User::updateOrCreate(
                        ['id' => $oldUser->id],
                        $userData
                    );

                    // ✅ Se è un referee, crea sempre il record nella tabella referees
                    if ($userType === 'referee') {
                        $this->createRefereeRecord($oldUser->id, $referee, $userData);
                    }

                    $processedUsers++;

                    // Progress ogni 50 utenti
                    if ($processedUsers % 50 === 0) {
                        $this->info("Processati {$processedUsers} utenti...");
                    }

                } catch (\Exception $e) {
                    $this->error("Errore processando utente {$oldUser->id} ({$oldUser->name}): " . $e->getMessage());
                    throw $e; // Re-throw per fermare l'esecuzione
                }
            }

            $this->info("Utenti processati: {$processedUsers}");
            $this->info("Arbitri: {$refereeUsers}");
            $this->info("Admin: {$adminUsers}");

            // ✅ Verifica finale
            if (!$this->dryRun) {
                $totalUsers = User::count();
                $totalReferees = User::where('user_type', 'referee')->count();
                $totalAdmins = User::whereIn('user_type', ['admin', 'national_admin', 'super_admin'])->count();

                $this->info('=== VERIFICA FINALE ===');
                $this->info("Utenti totali nel DB: {$totalUsers}");
                $this->info("Arbitri nel DB: {$totalReferees}");
                $this->info("Admin nel DB: {$totalAdmins}");
            }

        } catch (\Exception $e) {
            $this->error('ERRORE durante importazione utenti: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ' Linea: ' . $e->getLine());
            throw $e;
        }
    }

    /**
     * ✅ FIXED: Crea record nella tabella referees per arbitri con gestione robusta e debug
     */
    private function createRefereeRecord($userId, $oldReferee, $userData)
    {
        try {
            // ✅ Dati base sempre presenti
            $refereeData = [
                'user_id' => $userId,
                'zone_id' => $userData['zone_id'],
                'referee_code' => $userData['referee_code'],
                'level' => $userData['level'],
                'category' => $userData['category'],
                'certified_date' => $userData['certified_date'],
                'created_at' => $userData['created_at'],
                'updated_at' => $userData['updated_at'],
            ];

            // ✅ FIXED: Gestione sicura delle proprietà aggiuntive - VERSIONE MINIMALE
            if ($oldReferee && is_object($oldReferee)) {
                // Oggetto reale dal database - usa isset() per ogni proprietà
                $refereeData = array_merge($refereeData, [
                    'address' => isset($oldReferee->address) ? $oldReferee->address : null,
                    'postal_code' => isset($oldReferee->postal_code) ? $oldReferee->postal_code : null,
                    'tax_code' => isset($oldReferee->tax_code) ? $oldReferee->tax_code : null,
                    'profile_completed_at' => isset($oldReferee->profile_completed_at) ? $oldReferee->profile_completed_at : now(),
                    'badge_number' => isset($oldReferee->badge_number) ? $oldReferee->badge_number : null,
                    'first_certification_date' => isset($oldReferee->first_certification_date) ? $oldReferee->first_certification_date : $userData['certified_date'],
                    'last_renewal_date' => isset($oldReferee->last_renewal_date) ? $oldReferee->last_renewal_date : null,
                    'expiry_date' => isset($oldReferee->expiry_date) ? $oldReferee->expiry_date : null,
                    'bio' => isset($oldReferee->bio) ? $oldReferee->bio : 'Arbitro di golf certificato',
                    'experience_years' => isset($oldReferee->experience_years) ? $oldReferee->experience_years : 0,
                    'total_tournaments' => isset($oldReferee->total_tournaments) ? $oldReferee->total_tournaments : 0,
                    'tournaments_current_year' => isset($oldReferee->tournaments_current_year) ? $oldReferee->tournaments_current_year : 0,
                    'available_for_international' => isset($oldReferee->available_for_international) ? $oldReferee->available_for_international : false,
                ]);
            } else {
                // ✅ Nessun oggetto referee o oggetto artificiale - usa valori di default MINIMALI
                $refereeData = array_merge($refereeData, [
                    'address' => null,
                    'postal_code' => null,
                    'tax_code' => null,
                    'profile_completed_at' => now(),
                    'badge_number' => null,
                    'first_certification_date' => $userData['certified_date'],
                    'last_renewal_date' => null,
                    'expiry_date' => null,
                    'bio' => 'Arbitro di golf certificato',
                    'experience_years' => 0,
                    'total_tournaments' => 0,
                    'tournaments_current_year' => 0,
                    'available_for_international' => false,
                ]);
            }

            if ($this->dryRun) {
                // ✅ FIXED: Mostra cosa sarebbe stato creato nella tabella referees
                $this->info("[DRY-RUN] Creerei referee record per user {$userId}:");
                $this->info("   - Referee Code: {$refereeData['referee_code']}");
                $this->info("   - Level: {$refereeData['level']}");
                $this->info("   - Zone ID: {$refereeData['zone_id']}");
                $this->info("   - Bio: {$refereeData['bio']}");
                $this->info("   - Specializations: {$refereeData['specializations']}");
                return;
            }

            // ✅ Usa il Model Referee invece del Query Builder
            Referee::updateOrCreate(
                ['user_id' => $userId],
                $refereeData
            );

        } catch (\Exception $e) {
            $this->error("Errore creazione referee record per user {$userId}: " . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ' Linea: ' . $e->getLine());
            throw $e;
        }
    }

    /**
     * ✅ Determina il tipo di utente dal vecchio database
     */
    private function determineUserType($oldUser, $oldReferees, $oldRoleUsers): string
    {
        // 1. Controlla se ha ruoli nella tabella role_user
        if ($oldRoleUsers->count() > 0 && $oldRoleUsers->has($oldUser->id)) {
            $roles = $oldRoleUsers->get($oldUser->id);

            // ✅ FIXED: Controlla TUTTI i ruoli per gestire ruoli multipli
            $foundRoles = [];
            foreach ($roles as $role) {
                switch ($role->role_id) {
                    case 1:
                        $foundRoles[] = 'super_admin';
                        break;
                    case 2:
                        $foundRoles[] = 'national_admin';
                        break;
                    case 3:
                        $foundRoles[] = 'admin';
                        break;
                    case 4:
                        $foundRoles[] = 'referee';
                        break;
                    case 5:
                    case 6:
                        $foundRoles[] = 'referee';
                        break;
                }
            }

            // ✅ Priorità: admin > referee (se ha entrambi, è admin)
            if (in_array('super_admin', $foundRoles)) {
                return 'super_admin';
            }
            if (in_array('national_admin', $foundRoles)) {
                return 'national_admin';
            }
            if (in_array('admin', $foundRoles)) {
                return 'admin';
            }
            if (in_array('referee', $foundRoles)) {
                return 'referee';
            }
        }

        // 2. ✅ Se non ha ruoli specifici, controlla se ha dati referee
        if ($oldReferees->count() > 0 && $oldReferees->has($oldUser->id)) {
            return 'referee';
        }

        // 3. Controlla campi legacy nell'utente stesso
        if (isset($oldUser->is_super_admin) && $oldUser->is_super_admin) {
            return 'super_admin';
        }

        if (isset($oldUser->is_admin) && $oldUser->is_admin) {
            return 'admin';
        }

        // 4. Controlla se ha caratteristiche da arbitro nell'user stesso
        if (isset($oldUser->referee_code) && $oldUser->referee_code) {
            return 'referee';
        }

        if (isset($oldUser->level) && $oldUser->level) {
            return 'referee';
        }

        // 5. Default conservativo
        return 'referee';
    }

    /**
     * ✅ Genera codice arbitro univoco
     */
    private function generateRefereeCode(): string
    {
        do {
            $code = 'ARB' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::where('referee_code', $code)->exists());

        return $code;
    }

    private function importClubs()
    {
        $this->info('Importazione circoli...');

        $oldClubs = DB::connection('old_db')->table('clubs')->get();

        foreach ($oldClubs as $oldClub) {
            if ($this->dryRun) {
                $this->info("[DRY-RUN] Importerei circolo: {$oldClub->name}");
                continue;
            }

            // Gestione contact_info JSON se presente
            $contactInfo = null;
            if (isset($oldClub->contact_info)) {
                $contactInfo = json_decode($oldClub->contact_info, true);
            }

            $email = null;
            $phone = null;

            if ($contactInfo) {
                $email = isset($contactInfo['email']) ? $contactInfo['email'] : null;
                $phone = isset($contactInfo['phone']) ? $contactInfo['phone'] : null;
            }

            if (!$email) {
                $email = $oldClub->email ?? null;
            }

            if (!$phone) {
                $phone = $oldClub->phone ?? null;
            }

            Club::updateOrCreate(
                ['id' => $oldClub->id],
                [
                    'name' => $oldClub->name,
                    'code' => $oldClub->code ?? ($oldClub->short_name ?? ('CLUB' . $oldClub->id)),
                    'city' => $oldClub->city ?? 'N/A',
                    'province' => $oldClub->province ?? 'XX',
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $oldClub->address ?? null,
                    'contact_person' => $oldClub->contact_person ?? null,
                    'zone_id' => $oldClub->zone_id ?? 1,
                    'notes' => $oldClub->notes ?? null,
                    'is_active' => $oldClub->is_active ?? true,
                    'created_at' => $oldClub->created_at,
                    'updated_at' => $oldClub->updated_at,
                ]
            );
        }

        $this->info('Circoli importati: ' . Club::count());
    }

    private function importTournaments()
    {
        $this->info('Importazione tornei...');

        $oldTournaments = DB::connection('old_db')->table('tournaments')->get();

        // ✅ Pre-carica tutti i club una volta sola
        $clubs = Club::pluck('zone_id', 'id')->toArray();

        foreach ($oldTournaments as $oldTournament) {
            if ($this->dryRun) {
                $this->info("[DRY-RUN] Importerei torneo: {$oldTournament->name}");
                continue;
            }

            // ✅ Ricava zone_id dal club
            $zoneId = isset($clubs[$oldTournament->club_id]) ? $clubs[$oldTournament->club_id] : 1;

            $availabilityDeadline = $oldTournament->availability_deadline ?? date('Y-m-d', strtotime($oldTournament->start_date . ' -7 days'));
            $endDate = $oldTournament->end_date ?? $oldTournament->start_date;
            $tournamentTypeId = $oldTournament->tournament_type_id ?? ($oldTournament->type_id ?? 1);
            $notes = $oldTournament->notes ?? null;
            $status = $this->mapTournamentStatus($oldTournament->status ?? 'draft');

            Tournament::updateOrCreate(
                ['id' => $oldTournament->id],
                [
                    'name' => $oldTournament->name,
                    'start_date' => $oldTournament->start_date,
                    'end_date' => $endDate,
                    'availability_deadline' => $availabilityDeadline,
                    'club_id' => $oldTournament->club_id,
                    'tournament_type_id' => $tournamentTypeId,
                    'zone_id' => $zoneId,
                    'notes' => $notes,
                    'status' => $status,
                    'created_at' => $oldTournament->created_at,
                    'updated_at' => $oldTournament->updated_at,
                ]
            );
        }

        $this->info('Tornei importati: ' . Tournament::count());
    }

    private function importAvailabilities()
    {
        $this->info('Importazione disponibilità...');

        try {
            $oldAvailabilities = DB::connection('old_db')->table('availabilities')->get();

            foreach ($oldAvailabilities as $availability) {
                if ($this->dryRun) {
                    $userId = $availability->referee_id ?? $availability->user_id;
                    $this->info("[DRY-RUN] Importerei disponibilità per user {$userId}");
                    continue;
                }

                $userId = $availability->referee_id ?? $availability->user_id;
                $status = $availability->status ?? 'available';
                $notes = $availability->notes ?? null;
                $submittedAt = $availability->submitted_at ?? $availability->created_at;

                \App\Models\Availability::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'tournament_id' => $availability->tournament_id
                    ],
                    [
                        'status' => $status,
                        'notes' => $notes,
                        'submitted_at' => $submittedAt,
                        'created_at' => $availability->created_at,
                        'updated_at' => $availability->updated_at,
                    ]
                );
            }

            $this->info('Disponibilità importate: ' . count($oldAvailabilities));
        } catch (\Exception $e) {
            $this->warn('Tabella availabilities non trovata: ' . $e->getMessage());
        }
    }

    private function importAssignments()
    {
        $this->info('Importazione assegnazioni...');

        try {
            $oldAssignments = DB::connection('old_db')->table('assignments')->get();

            foreach ($oldAssignments as $assignment) {
                if ($this->dryRun) {
                    $userId = $assignment->referee_id ?? $assignment->user_id;
                    $this->info("[DRY-RUN] Importerei assegnazione per user {$userId}");
                    continue;
                }

                $userId = $assignment->referee_id ?? $assignment->user_id;
                $assignedById = $assignment->assigned_by_id ?? ($assignment->assigned_by ?? 1);
                $role = $assignment->role ?? 'Arbitro';
                $notes = $assignment->notes ?? null;
                $isConfirmed = $assignment->is_confirmed ?? false;
                $assignedAt = $assignment->assigned_at ?? $assignment->created_at;

                \App\Models\Assignment::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'tournament_id' => $assignment->tournament_id
                    ],
                    [
                        'assigned_by_id' => $assignedById,
                        'role' => $role,
                        'notes' => $notes,
                        'is_confirmed' => $isConfirmed,
                        'assigned_at' => $assignedAt,
                        'created_at' => $assignment->created_at,
                        'updated_at' => $assignment->updated_at,
                    ]
                );
            }

            $this->info('Assegnazioni importate: ' . count($oldAssignments));
        } catch (\Exception $e) {
            $this->warn('Tabella assignments non trovata: ' . $e->getMessage());
        }
    }

    private function importSupportData()
    {
        $this->info('Importazione dati di supporto...');

        // Import institutional emails
        try {
            if (DB::connection('old_db')->getSchemaBuilder()->hasTable('institutional_emails')) {
                $oldEmails = DB::connection('old_db')->table('institutional_emails')->get();

                foreach ($oldEmails as $email) {
                    if ($this->dryRun) {
                        $this->info("[DRY-RUN] Importerei email istituzionale: {$email->email}");
                        continue;
                    }

                    $description = $email->description ?? null;
                    $isActive = $email->is_active ?? true;
                    $zoneId = $email->zone_id ?? null;
                    $category = $email->category ?? 'altro';
                    $receiveAll = $email->receive_all_notifications ?? false;
                    $notificationTypes = $email->notification_types ?? json_encode([]);
                    $createdAt = $email->created_at ?? now();
                    $updatedAt = $email->updated_at ?? now();

                    InstitutionalEmail::updateOrCreate(
                        ['email' => $email->email],
                        [
                            'name' => $email->name,
                            'email' => $email->email,
                            'description' => $description,
                            'is_active' => $isActive,
                            'zone_id' => $zoneId,
                            'category' => $category,
                            'receive_all_notifications' => $receiveAll,
                            'notification_types' => $notificationTypes,
                            'created_at' => $createdAt,
                            'updated_at' => $updatedAt,
                        ]
                    );
                }

                $this->info('Email istituzionali importate: ' . count($oldEmails));
            }
        } catch (\Exception $e) {
            $this->warn('Tabella institutional_emails non trovata: ' . $e->getMessage());
        }

        // Import letterheads
        try {
            if (DB::connection('old_db')->getSchemaBuilder()->hasTable('letterheads')) {
                $oldLetterheads = DB::connection('old_db')->table('letterheads')->get();

                foreach ($oldLetterheads as $letterhead) {
                    if ($this->dryRun) {
                        $title = $letterhead->title ?? $letterhead->id;
                        $this->info("[DRY-RUN] Importerei intestazione: {$title}");
                        continue;
                    }

                    $title = $letterhead->title ?? 'Intestazione';
                    $headerContent = $letterhead->header_content ?? null;
                    $footerContent = $letterhead->footer_content ?? null;
                    $logoPath = $letterhead->logo_path ?? null;
                    $zoneId = $letterhead->zone_id ?? null;
                    $isActive = $letterhead->is_active ?? true;
                    $isDefault = $letterhead->is_default ?? false;
                    $settings = $letterhead->settings ?? json_encode([]);
                    $createdAt = $letterhead->created_at ?? now();
                    $updatedAt = $letterhead->updated_at ?? now();

                    Letterhead::updateOrCreate(
                        ['id' => $letterhead->id],
                        [
                            'title' => $title,
                            'header_content' => $headerContent,
                            'footer_content' => $footerContent,
                            'logo_path' => $logoPath,
                            'zone_id' => $zoneId,
                            'is_active' => $isActive,
                            'is_default' => $isDefault,
                            'settings' => $settings,
                            'created_at' => $createdAt,
                            'updated_at' => $updatedAt,
                        ]
                    );
                }

                $this->info('Intestazioni importate: ' . count($oldLetterheads));
            }
        } catch (\Exception $e) {
            $this->warn('Tabella letterheads non trovata: ' . $e->getMessage());
        }
    }

    // Helper methods

    /**
     * ✅ Mappa qualifiche vecchie a nuove
     */
    private function mapQualification($oldQualification): string
    {
        $qualification = strtolower(trim($oldQualification));

        switch ($qualification) {
            case 'primo livello':
            case '1° livello':
            case '1_livello':
            case '1 livello':
                return '1_livello';
            case 'regionale':
                return 'regionale';
            case 'nazionale/internazionale':
            case 'nazionale':
            case 'internazionale':
                return 'nazionale';
            case 'archivio':
                return 'archivio';
            case 'aspirante':
                return 'aspirante';
            default:
                return 'aspirante';
        }
    }

    private function mapRequiredLevel($oldLevel): string
    {
        return $this->mapQualification($oldLevel);
    }

    private function mapTournamentStatus($oldStatus): string
    {
        $status = strtolower(trim($oldStatus));

        switch ($status) {
            case 'bozza':
            case 'draft':
                return 'draft';
            case 'aperto':
            case 'open':
                return 'open';
            case 'chiuso':
            case 'closed':
                return 'closed';
            case 'assegnato':
            case 'assigned':
                return 'assigned';
            case 'completato':
            case 'completed':
                return 'completed';
            default:
                return 'draft';
        }
    }
}
