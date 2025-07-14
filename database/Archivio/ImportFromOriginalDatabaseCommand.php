<?php

/**
 * ========================================
 * ImportFromOriginalDatabaseCommand.php
 * ========================================
 * Comando per importazione dal database originale SQL1466239_4
 * Torno alla versione che funzionava + fix ENUM
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Zone;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Availability;
use App\Models\Assignment;
use App\Models\Referee;
use Carbon\Carbon;

class ImportFromOriginalDatabaseCommand extends Command
{
    protected $signature = 'golf:import-original {--database=SQL1466239_4} {--dry-run} {--limit=0}';
    protected $description = 'Importa i dati dal database originale SQL1466239_4';

    private $originalDatabase;
    private $dryRun;
    private $limit;
    private $clubMapping = [];

    public function handle()
    {
        $this->originalDatabase = $this->option('database');
        $this->dryRun = $this->option('dry-run');
        $this->limit = (int) $this->option('limit');

        if ($this->dryRun) {
            $this->warn('🔍 MODALITÀ DRY-RUN: Nessuna modifica sarà effettuata');
        }

        $this->info("🚀 Inizio importazione dal database originale: {$this->originalDatabase}");

        // Configura connessione al database originale
        $this->setupOriginalDatabaseConnection();

        // Verifica connessione
        if (!$this->checkOriginalDatabase()) {
            $this->error("❌ Database {$this->originalDatabase} non raggiungibile!");
            return 1;
        }

        try {
            // Analizza struttura database originale
            $this->analyzeOriginalDatabase();

            // Importazione nell'ordine corretto
            $this->createBaseData();
            $this->importArbitri();
            $this->importCircoli();
            $this->importGare();
            $this->createRefereeLevelHistory();

            $this->info('✅ Importazione completata con successo!');
        } catch (\Exception $e) {
            $this->error('❌ Errore durante importazione: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function setupOriginalDatabaseConnection()
    {
        config(['database.connections.original' => [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => $this->originalDatabase,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);
    }

    private function checkOriginalDatabase()
    {
        try {
            DB::connection('original')->getPdo();
            return true;
        } catch (\Exception $e) {
            $this->error("Errore connessione: " . $e->getMessage());
            return false;
        }
    }

    private function analyzeOriginalDatabase()
    {
        $this->info('🔍 Analisi struttura database originale...');

        $tables = ['arbitri', 'gare_2025', 'circoli'];

        foreach ($tables as $table) {
            try {
                $count = DB::connection('original')->table($table)->count();
                $this->info("📊 Tabella '{$table}': {$count} records");

                if ($this->dryRun) {
                    $sample = DB::connection('original')->table($table)->limit(3)->get();
                    $this->info("   Campione dati:");
                    foreach ($sample as $record) {
                        $recordId = property_exists($record, 'id') ? $record->id : 'N/A';
                        $this->info("   - ID {$recordId}: " . json_encode(array_slice((array)$record, 0, 3), JSON_UNESCAPED_UNICODE));
                    }
                }
            } catch (\Exception $e) {
                $this->warn("⚠️ Tabella '{$table}' non trovata o errore: " . $e->getMessage());
            }
        }
    }

    private function createBaseData()
    {
        if ($this->dryRun) {
            $this->info('[DRY-RUN] Creerei dati di base (zone, tournament types)...');
            return;
        }

        $this->info('🏗️ Creazione dati di base...');

        if (Zone::count() === 0) {
            $zones = [
                ['name' => 'SZR1', 'description' => 'Piemonte e Liguria', 'is_national' => false],
                ['name' => 'SZR2', 'description' => 'Lombardia', 'is_national' => false],
                ['name' => 'SZR3', 'description' => 'Veneto e Friuli Venezia Giulia', 'is_national' => false],
                ['name' => 'SZR4', 'description' => 'Emilia e Romagna', 'is_national' => false],
                ['name' => 'SZR5', 'description' => 'Toscana e Marche', 'is_national' => false],
                ['name' => 'SZR6', 'description' => 'Lazio, Abruzzo, Molise e Sardegna', 'is_national' => false],
                ['name' => 'SZR7', 'description' => 'Sud Italia', 'is_national' => false],
                ['name' => 'CRC', 'description' => 'Comitato Regole Campionati', 'is_national' => false],
            ];

            foreach ($zones as $zone) {
                Zone::create($zone);
            }
            $this->info('✅ Create ' . count($zones) . ' zone');
        }

        if (TournamentType::count() === 0) {
            $types = [
                [
                    'name' => 'Gara Sociale',
                    'code' => 'SOC',
                    'description' => 'Gara sociale del circolo',
                    'is_national' => false,
                    'level' => 'zonale',
                    'required_level' => 'primo_livello', // ✅ CORRETTO per ENUM
                    'min_referees' => 1,
                    'max_referees' => 2,
                    'sort_order' => 10,
                    'settings' => json_encode(['required_referee_level' => 'primo_livello', 'min_referees' => 1, 'max_referees' => 2])
                ],
                [
                    'name' => 'Coppa Italia',
                    'code' => 'CI',
                    'description' => 'Coppa Italia',
                    'is_national' => false,
                    'level' => 'zonale',
                    'required_level' => 'regionale', // ✅ CORRETTO per ENUM
                    'min_referees' => 2,
                    'max_referees' => 3,
                    'sort_order' => 20,
                    'settings' => json_encode(['required_referee_level' => 'regionale', 'min_referees' => 2, 'max_referees' => 3])
                ],
                [
                    'name' => 'Campionato Nazionale',
                    'code' => 'CN',
                    'description' => 'Campionato nazionale',
                    'is_national' => true,
                    'level' => 'nazionale',
                    'required_level' => 'nazionale', // ✅ CORRETTO per ENUM
                    'min_referees' => 3,
                    'max_referees' => 5,
                    'sort_order' => 30,
                    'settings' => json_encode(['required_referee_level' => 'nazionale', 'min_referees' => 3, 'max_referees' => 5])
                ]
            ];

            foreach ($types as $type) {
                TournamentType::create($type);
            }
            $this->info('✅ Creati ' . count($types) . ' tipi torneo');
        }
    }

    private function importArbitri()
    {
        $this->info('👥 Importazione arbitri...');

        try {
            $query = DB::connection('original')->table('arbitri');

            if ($this->limit > 0) {
                $query->limit($this->limit);
            }

            $arbitri = $query->get();
            $processedCount = 0;

            foreach ($arbitri as $arbitro) {
                $processedCount++;

                if ($this->dryRun) {
                    if ($processedCount <= 5) {
                        $nome = property_exists($arbitro, 'Nome') ? $arbitro->Nome : '';
                        $cognome = property_exists($arbitro, 'Cognome') ? $arbitro->Cognome : '';
                        $this->info("[DRY-RUN] Arbitro #{$processedCount}: {$nome} {$cognome}");

                        $email = property_exists($arbitro, 'Email') ? $arbitro->Email : 'N/A';
                        $this->info("   - Email/Level: {$email}");

                        $livello2025 = property_exists($arbitro, 'Livello_2025') ? $arbitro->Livello_2025 : 'N/A';
                        $this->info("   - Livello 2025: {$livello2025}");

                        $role = property_exists($arbitro, 'Role') ? $arbitro->Role : 'N/A';
                        $this->info("   - Role: {$role}");

                        $zona = property_exists($arbitro, 'Zona') ? $arbitro->Zona : 'N/A';
                        $this->info("   - Zona: {$zona}");
                    }
                    continue;
                }

                // Mappatura corretta
                $nome = property_exists($arbitro, 'Nome') ? trim($arbitro->Nome) : '';
                $cognome = property_exists($arbitro, 'Cognome') ? trim($arbitro->Cognome) : '';
                $fullName = trim($nome . ' ' . $cognome);

                $emailReale = property_exists($arbitro, 'Email') ? $arbitro->Email : null;
                $email = $emailReale ? $emailReale : ('arbitro' . $arbitro->id . '@temp.com');

                $password = property_exists($arbitro, 'Password') ? $arbitro->Password : 'password123';
                $role = property_exists($arbitro, 'Role') ? $arbitro->Role : '';
                $phone = property_exists($arbitro, 'Cellulare') ? $arbitro->Cellulare : null;
                $city = property_exists($arbitro, 'Qualifica') ? $arbitro->Qualifica : null;
                $arbitroStatus = property_exists($arbitro, 'Arbitro') ? $arbitro->Arbitro : '';
                $livello2025 = property_exists($arbitro, 'Livello_2025') ? $arbitro->Livello_2025: 'Aspirante';
                $zona = property_exists($arbitro, 'Zona') ? $arbitro->Zona : '';
                $circolo = property_exists($arbitro, 'Circolo') ? $arbitro->Circolo : 'misto';
                $prCircolo = property_exists($arbitro, 'Pr_circolo') ? $arbitro->Pr_circolo : null;
                $primaNomina = property_exists($arbitro, 'Prima_Nomina') ? $arbitro->Prima_Nomina : null;

                $userData = [
                    'name' => $fullName,
                    'email' => $email,
                    'password' => bcrypt($password),
                    'user_type' => $this->mapRole($role),
                    'phone' => $phone,
                    'city' => $city,
                    'is_active' => $this->mapActiveStatus($arbitroStatus),
                    'level' => $this->normalizeLevel($livello2025),
                    'zone_id' => $this->mapZoneId($zona),
                    'category' => $circolo,
                    'certified_date' => $prCircolo, // ✅ NON parsifica come data
                    'email_verified_at' => $this->parseDate($primaNomina),
                    'referee_code' => $this->generateRefereeCode($password),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $user = User::updateOrCreate(
                    ['id' => $arbitro->id],
                    $userData
                );

                if ($userData['user_type'] === 'referee') {
                    $this->createRefereeRecord($user->id, $arbitro, $userData);
                }
            }

            $this->info("✅ Processati {$processedCount} arbitri");

        } catch (\Exception $e) {
            $this->error("❌ Errore importazione arbitri: " . $e->getMessage());
        }
    }

    private function createRefereeRecord($userId, $arbitro, $userData)
    {
        if ($this->dryRun) return;

        $prAbit = property_exists($arbitro, 'Pr_abit') ? $arbitro->Pr_abit : null;
        $casa = property_exists($arbitro, 'Casa') ? $arbitro->Casa : null;
        $ufficio = property_exists($arbitro, 'Ufficio') ? $arbitro->Ufficio : null;
        $password = property_exists($arbitro, 'Password') ? $arbitro->Password : '';
        $livello2025 = property_exists($arbitro, 'Livello_2025') ? $arbitro->Livello_2025 : null;

        $refereeData = [
            'user_id' => $userId,
            'address' => $prAbit,
            'badge_number' => $this->extractBadgeNumber($password),
            'first_certification_date' => $this->parseDate($casa),
            'last_renewal_date' => $this->parseDate($ufficio),
            'bio' => 'Arbitro di golf certificato',
            'experience_years' => $this->calculateExperienceYears($casa),
            'qualifications' => json_encode([]),
            'languages' => json_encode(['it']),
            'specializations' => json_encode(['Golf tradizionale']),
            'available_for_international' => false,
            'preferences' => json_encode(['livello_2025' => $livello2025]),
            'total_tournaments' => 0,
            'tournaments_current_year' => 0,
            'profile_completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        Referee::updateOrCreate(
            ['user_id' => $userId],
            $refereeData
        );
    }

    private function importCircoli()
    {
        $this->info('🏌️ Importazione circoli...');

        try {
            $query = DB::connection('original')->table('circoli');

            if ($this->limit > 0) {
                $query->limit($this->limit);
            }

            $circoli = $query->get();
            $processedCount = 0;

            foreach ($circoli as $circolo) {
                $processedCount++;

                if ($this->dryRun) {
                    if ($processedCount <= 5) {
                        $circoloNome = property_exists($circolo, 'Circolo_Nome') ? $circolo->Circolo_Nome : 'N/A';
                        $this->info("[DRY-RUN] Circolo #{$processedCount}: {$circoloNome}");

                        $circoloId = property_exists($circolo, 'Circolo_Id') ? $circolo->Circolo_Id : 'N/A';
                        $this->info("   - Codice: {$circoloId}");

                        $citta = property_exists($circolo, 'Città') ? $circolo->Città : 'N/A';
                        $this->info("   - Città: {$citta}");

                        $zona = property_exists($circolo, 'Zona') ? $circolo->Zona : 'N/A';
                        $this->info("   - Zona: {$zona}");
                    }
                    continue;
                }

                $circoloNome = property_exists($circolo, 'Circolo_Nome') ? $circolo->Circolo_Nome : 'Circolo Sconosciuto';
                $circoloId = property_exists($circolo, 'Circolo_Id') ? $circolo->Circolo_Id : ('CLUB' . $circolo->Id);
                $originalCode = $circoloId;
                $clubCode = $this->ensureUniqueClubCode($originalCode, $circolo->Id);

                $indirizzo = property_exists($circolo, 'Indirizzo') ? $circolo->Indirizzo : null;
                $cap = property_exists($circolo, 'CAP') ? $circolo->CAP : null;
                $citta = property_exists($circolo, 'Città') ? $circolo->Città : null;
                $provincia = property_exists($circolo, 'Provincia') ? $circolo->Provincia : null;
                $regione = property_exists($circolo, 'Regione') ? $circolo->Regione : null;
                $email = property_exists($circolo, 'Email') ? $circolo->Email : null;
                $telefono = property_exists($circolo, 'Telefono') ? $circolo->Telefono : null;
                $web = property_exists($circolo, 'Web') ? $circolo->Web : null;
                $sedeGara = property_exists($circolo, 'SedeGara') ? $circolo->SedeGara : 'A';
                $zona = property_exists($circolo, 'Zona') ? $circolo->Zona : 'SZR1';

                $clubData = [
                    'name' => $circoloNome,
                    'code' => $clubCode,
                    'address' => $indirizzo,
                    'postal_code' => $cap,
                    'city' => $citta ?: 'N/A', // ✅ RICHIESTO per clubs table
                    'province' => $provincia,
                    'region' => $regione,
                    'email' => $email,
                    'phone' => $telefono,
                    'website' => $web,
                    'is_active' => $this->mapActiveStatus($sedeGara),
                    'zone_id' => $this->mapZoneId($zona),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                Club::updateOrCreate(
                    ['id' => $circolo->Id],
                    $clubData
                );
            }

            $this->info("✅ Processati {$processedCount} circoli");

            // ✅ Crea mappatura DOPO aver importato i circoli
            if (!$this->dryRun) {
                $this->createClubMapping();
            }

        } catch (\Exception $e) {
            $this->error("❌ Errore importazione circoli: " . $e->getMessage());
        }
    }

    private function createClubMapping()
    {
        $this->info('🔗 Creazione mappatura club...');

        $clubs = Club::all(['id', 'name', 'code']);

        foreach ($clubs as $club) {
            $this->clubMapping[strtoupper($club->name)] = $club->id;
            $this->clubMapping[strtoupper($club->code)] = $club->id;
            $this->clubMapping[strtoupper(str_replace(' ', '', $club->name))] = $club->id;
        }

        $this->info("✅ Creata mappatura per " . count($clubs) . " club");
    }

    private function importGare()
    {
        $this->info('🏆 Importazione gare/tornei...');

        try {
            $query = DB::connection('original')->table('gare_2025');

            if ($this->limit > 0) {
                $query->limit($this->limit);
            }

            $gare = $query->get();
            $processedCount = 0;

            foreach ($gare as $gara) {
                $processedCount++;

                if ($this->dryRun) {
                    if ($processedCount <= 5) {
                        $nomeGara = property_exists($gara, 'Nome_gara') ? $gara->Nome_gara : 'N/A';
                        $this->info("[DRY-RUN] Gara #{$processedCount}: {$nomeGara}");

                        $tipo = property_exists($gara, 'Tipo') ? $gara->Tipo : 'N/A';
                        $this->info("   - Tipo: {$tipo}");

                        $startTime = property_exists($gara, 'StartTime') ? $gara->StartTime : 'N/A';
                        $this->info("   - Start: {$startTime}");

                        $circolo = property_exists($gara, 'Circolo') ? $gara->Circolo : 'N/A';
                        $this->info("   - Circolo: {$circolo}");
                    }
                    continue;
                }

                $circoloName = property_exists($gara, 'Circolo') ? $gara->Circolo : '';
                $clubId = $this->mapClubId($circoloName);
                if (!$clubId) {
                    $this->warn("⚠️ Club non trovato per gara {$gara->id}: '{$circoloName}'");
                    continue;
                }

                $nomeGara = property_exists($gara, 'Nome_gara') ? $gara->Nome_gara : 'Gara Sconosciuta';
                $startTime = property_exists($gara, 'StartTime') ? $gara->StartTime : null;
                $endTime = property_exists($gara, 'EndTime') ? $gara->EndTime : null;
                $tipo = property_exists($gara, 'Tipo') ? $gara->Tipo : '';
                $zona = property_exists($gara, 'Zona') ? $gara->Zona : 'SZR1';

                $endDate = $endTime ? $this->parseDate($endTime) : $this->parseDate($startTime);

                $tournamentData = [
                    'name' => $nomeGara,
                    'start_date' => $this->parseDate($startTime),
                    'end_date' => $endDate,
                    'club_id' => $clubId,
                    'tournament_type_id' => $this->mapTournamentType($tipo),
                    'zone_id' => $this->mapZoneId($zona),
                    'status' => 'draft',
                    'availability_deadline' => $this->calculateDeadline($startTime),
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $tournament = Tournament::updateOrCreate(
                    ['id' => $gara->id],
                    $tournamentData
                );

                if (!$this->dryRun) {
                    $this->processAvailabilities($tournament, $gara);
                    $this->processAssignments($tournament, $gara);
                }
            }

            $this->info("✅ Processate {$processedCount} gare");

        } catch (\Exception $e) {
            $this->error("❌ Errore importazione gare: " . $e->getMessage());
        }
    }

    private function processAvailabilities($tournament, $gara)
    {
        $disponibili = property_exists($gara, 'Disponibili') ? $gara->Disponibili : '';
        if (empty($disponibili)) return;

        $disponibiliArray = array_map('trim', explode(',', $disponibili));

        foreach ($disponibiliArray as $nome) {
            if (empty($nome)) continue;

            $user = User::where('name', 'LIKE', "%{$nome}%")
                       ->where('user_type', 'referee')
                       ->first();

            if ($user) {
                Availability::updateOrCreate([
                    'user_id' => $user->id,
                    'tournament_id' => $tournament->id,
                ], [
                    'notes' => null,
                    'submitted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function processAssignments($tournament, $gara)
    {
        $td = property_exists($gara, 'TD') ? $gara->TD : '';
        $arbitri = property_exists($gara, 'Arbitri') ? $gara->Arbitri : '';
        $osservatori = property_exists($gara, 'Osservatori') ? $gara->Osservatori : '';
        $comitato = property_exists($gara, 'Comitato') ? $gara->Comitato : '';

        $assignments = [
            'Direttore di Torneo' => $td,
            'Arbitro' => $arbitri,
            'Osservatore' => $osservatori,
        ];

        foreach ($assignments as $role => $names) {
            if (empty($names)) continue;

            $nameList = array_map('trim', explode(',', $names));

            foreach ($nameList as $nome) {
                if (empty($nome)) continue;

                $user = User::where('name', 'LIKE', "%{$nome}%")->first();

                if ($user) {
                    Assignment::updateOrCreate([
                        'user_id' => $user->id,
                        'tournament_id' => $tournament->id,
                    ], [
                        'role' => $role,
                        'assigned_by_id' => 1,
                        'assigned_at' => now(),
                        'is_confirmed' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function createRefereeLevelHistory()
    {
        $this->info('📊 Creazione storica livelli arbitri...');

        if ($this->dryRun) {
            $this->info('[DRY-RUN] Creerei storico livelli per tutti gli arbitri (2015-2025)');
            return;
        }

        if (!DB::getSchemaBuilder()->hasTable('referee_level_history')) {
            $this->error('❌ Tabella referee_level_history non trovata! Esegui prima: php artisan migrate');
            return;
        }

        $referees = User::where('user_type', 'referee')->get();

        foreach ($referees as $referee) {
            $currentLevel = $referee->level ? $referee->level : 'Aspirante';

            // Livello corrente (2025)
            DB::table('referee_level_history')->updateOrInsert([
                'user_id' => $referee->id,
                'year' => 2025,
            ], [
                'level' => $currentLevel,
                'effective_date' => now()->startOfYear(),
                'notes' => 'Livello corrente importato',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            for ($year = 2015; $year < 2025; $year++) {
                DB::table('referee_level_history')->updateOrInsert([
                    'user_id' => $referee->id,
                    'year' => $year,
                ], [
                    'level' => 'Aspirante',
                    'effective_date' => Carbon::create($year, 1, 1),
                    'notes' => 'Livello storico (default)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->info("✅ Creato storico per {$referees->count()} arbitri (2015-2025)");
    }

    // HELPER METHODS
    private function normalizeLevel($level)
    {
        $level = strtolower(trim($level ? $level : ''));

        $mapping = [
            'aspirante' => 'Aspirante',
            'naz' => 'Nazionale',
            'reg' => 'Regionale',
            '1°' => '1_livello',
            '1_livello', 'primo livello', 'primo_livello' => '1_livello',
            'regionale' => 'Regionale',
            'nazionale' => 'Nazionale',
            'internazionale' => 'Internazionale',
            'archivio' => 'Archivio',
            'int' => 'Internazionale',
            'arch' => 'Archivio',
        ];

        return isset($mapping[$level]) ? $mapping[$level] : 'Aspirante';
    }

    private function mapRole($role)
    {
        $role = strtolower(trim($role ? $role : ''));

        if ($role === 'admin' || $role === 'administrator') {
            return 'admin';
        } elseif ($role === 'super_admin' || $role === 'superadmin') {
            return 'super_admin';
        } elseif ($role === 'national_admin') {
            return 'national_admin';
        } else {
            return 'referee';
        }
    }

    private function mapActiveStatus($status)
    {
        return strtoupper(trim($status ? $status : '')) === 'A';
    }

    private function mapZoneId($zona)
    {
        $zona = trim($zona ? $zona : '');

        $mapping = [
            'SZR1' => 1, 'SZR2' => 2, 'SZR3' => 3, 'SZR4' => 4,
            'SZR5' => 5, 'SZR6' => 6, 'SZR7' => 7, 'CRC' => 8,
            '1' => 1, '2' => 2, '3' => 3, '4' => 4,
            '5' => 5, '6' => 6, '7' => 7,
        ];

        return isset($mapping[$zona]) ? $mapping[$zona] : 1;
    }

    private function mapTournamentType($tipo)
    {
        $tipo = strtolower(trim($tipo ? $tipo : ''));

        if ($tipo === 'sociale' || $tipo === 'gara sociale') {
            return 1;
        } elseif ($tipo === 'coppa italia' || $tipo === 'ci') {
            return 2;
        } elseif ($tipo === 'nazionale' || $tipo === 'campionato nazionale') {
            return 3;
        } else {
            return 1;
        }
    }

    private function mapClubId($clubName)
    {
        if (empty($clubName)) return null;

        $clubKey = strtoupper(trim($clubName));

        if (isset($this->clubMapping[$clubKey])) {
            return $this->clubMapping[$clubKey];
        }

        $clubKeyNoSpaces = str_replace(' ', '', $clubKey);
        if (isset($this->clubMapping[$clubKeyNoSpaces])) {
            return $this->clubMapping[$clubKeyNoSpaces];
        }

        $club = Club::where('name', 'LIKE', "%{$clubName}%")->first();
        if ($club) {
            $this->clubMapping[$clubKey] = $club->id;
            return $club->id;
        }

        return null;
    }

    private function ensureUniqueClubCode($originalCode, $clubId)
    {
        $code = $originalCode;
        $counter = 1;

        while (Club::where('code', $code)->where('id', '!=', $clubId)->exists()) {
            $code = $originalCode . '_' . $counter;
            $counter++;
        }

        return $code;
    }

    private function generateRefereeCode($password)
    {
        $password = $password ? $password : '';
        if (preg_match('/ARB(\d+)/', $password, $matches)) {
            return 'ARB' . str_pad($matches[1], 4, '0', STR_PAD_LEFT);
        }

        do {
            $code = 'ARB' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::where('referee_code', $code)->exists());

        return $code;
    }

    private function extractBadgeNumber($password)
    {
        $password = $password ? $password : '';
        if (preg_match('/(\d{3,6})/', $password, $matches)) {
            return $matches[1];
        }
        return null;
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

    private function calculateDeadline($startDate)
    {
        $start = $this->parseDate($startDate);
        return $start ? $start->copy()->subDays(7) : null;
    }

    private function calculateExperienceYears($firstCertification)
    {
        $firstDate = $this->parseDate($firstCertification);
        return $firstDate ? now()->diffInYears($firstDate) : 0;
    }
}
