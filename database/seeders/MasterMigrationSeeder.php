<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Helpers\RefereeLevelsHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Models\Zone;
use Illuminate\Console\Command;
use App\Models\TournamentType;

/**
 * MasterMigrationSeeder - Migrazione Unificata
 *
 * Migra direttamente dal database reale Sql1466239_4
 * combinando le logiche di DataMigrationSeeder e DataImprovementSeeder
 */
class MasterMigrationSeeder extends Seeder
{
    private $dryRun = false;
    private $stats = [];

    public function run(): void
    {
        // Controlla se è abilitata la modalità dry-run
        $this->dryRun = env('MIGRATION_DRY_RUN', false) ||
            $this->command->confirm('Eseguire in modalità DRY-RUN (solo simulazione)?', false);

        if ($this->dryRun) {
            $this->command->info('🧪 MODALITÀ DRY-RUN ATTIVATA - Nessuna modifica al database');
        }

        $this->command->info('🚀 Inizio MasterMigrationSeeder - Migrazione Unificata...');

        // 1. Setup connessione database reale
        $this->setupRealDatabaseConnection();

        // 2. Verifica database
        if (!$this->checkRealDatabase()) {
            $this->command->error('❌ Impossibile connettersi al database Sql1466239_4');
            return;
        }

        // 3. Inizializza statistiche
        $this->initializeStats();

        // 4. Esegui migrazione nell'ordine corretto (USER CENTRIC approach)
        $this->command->info('✅ Database verificato, procedo con migrazione USER CENTRIC...');

        $this->createZones();           // Manuale: SZR1-SZR7, CRC
        $this->createTournamentTypes(); // Manuale: defaults con short_name
        $this->migrateArbitri();        // arbitri → users + referees
        $this->migrateCircoli();        // circoli → clubs
        $this->migrateGare();           // gare_2025 → tournaments
        $this->migrateDisponibilita();  // gare_2025.Disponibili → availabilities
        $this->migrateAssegnazioni();   // gare_2025.TD+Arbitri+Osservatori → assignments

        // 5. Report finale
        $this->printFinalStats();

        // 6. Chiudi connessione
        $this->closeRealDatabaseConnection();

        $this->command->info('✅ MasterMigrationSeeder completato!');
    }

    /**
     * Setup connessione al database reale Sql1466239_4
     */
    private function setupRealDatabaseConnection()
    {
        $realDbConfig = [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => 'Sql1466239_4', // Database reale
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
        ];

        config(['database.connections.real' => $realDbConfig]);

        $this->command->info("🔗 Connessione al database reale Sql1466239_4 configurata");
    }

    /**
     * Verifica database reale
     */
    private function checkRealDatabase(): bool
    {
        try {
            DB::connection('real')->getPdo();

            // Verifica tabelle principali
            $requiredTables = ['arbitri', 'circoli', 'gare_2025'];
            foreach ($requiredTables as $table) {
                if (!$this->tableExists('real', $table)) {
                    $this->command->error("❌ Tabella '{$table}' non trovata in Sql1466239_4");
                    return false;
                }
            }

            $this->command->info('✅ Database Sql1466239_4 verificato');
            return true;
        } catch (\Exception $e) {
            $this->command->error('❌ Errore connessione Sql1466239_4: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Crea zone manualmente (SZR1-SZR7, CRC)
     */
    private function createZones()
    {
        if (Zone::count() > 0) {
            $this->command->info('✅ Zone already exist (' . Zone::count() . ' found)');
            return;
        }
        $this->command->info('📍 Creazione zones...');

        $zones = [
            ['code' => 'SZR1', 'name' => 'Sezione Zonale Regole 1', 'description' => 'Piemonte-Valle d\'Aosta-Liguria', 'is_national' => false],
            ['code' => 'SZR2', 'name' => 'Sezione Zonale Regole 2', 'description' => 'Lombardia', 'is_national' => false],
            ['code' => 'SZR3', 'name' => 'Sezione Zonale Regole 3', 'description' => 'Veneto-Trentino-Friuli', 'is_national' => false],
            ['code' => 'SZR4', 'name' => 'Sezione Zonale Regole 4', 'description' => 'Emilia-Romagna', 'is_national' => false],
            ['code' => 'SZR5', 'name' => 'Sezione Zonale Regole 5', 'description' => 'Toscana-Umbria', 'is_national' => false],
            ['code' => 'SZR6', 'name' => 'Sezione Zonale Regole 6', 'description' => 'Lazio-Abruzzo-Molise', 'is_national' => false],
            ['code' => 'SZR7', 'name' => 'Sezione Zonale Regole 7', 'description' => 'Sud Italia-Sicilia-Sardegna', 'is_national' => false],
            ['code' => 'CRC', 'name' => 'Comitato Regole Campionati', 'description' => 'Comitato Regole e Campionati', 'is_national' => true],
        ];

        foreach ($zones as $zone) {
            $this->dryRunUpdateOrInsert(
                'zones',
                ['code' => $zone['code']],
                [
                    'name' => $zone['name'],
                    'code' => $zone['code'],
                    'description' => "Zona competenza territoriale {$zone['name']}",
                    'is_national' => $zone['is_national'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                "Creazione zona {$zone['code']}"
            );
        }

        $this->stats['zones'] = count($zones);
        $this->command->info("✅ Create {$this->stats['zones']} zone");
    }

    /**
     * Crea tournament types da dati reali - legge i tipi dalla colonna "tipo" in gare_2025
     */
    private function createTournamentTypes()
    {
        if (TournamentType::count() > 0) {
            $this->command->info('✅ TournamentType already exist (' . TournamentType::count() . ' found)');
            return;
        }
        $this->command->info('🏆 Creazione tournament types da dati reali (gare_2025.tipo)...');

        // SEMPRE leggi i tipi reali dal database Sql1466239_4
        try {
            $tipiReali = DB::connection('real')
                ->table('gare_2025')
                ->selectRaw('DISTINCT tipo')
                ->whereNotNull('tipo')
                ->where('tipo', '!=', '')
                ->pluck('tipo');

            $this->command->info("🔍 Trovati {$tipiReali->count()} tipi reali di torneo: " . $tipiReali->implode(', '));
        } catch (\Exception $e) {
            $this->command->error("❌ Errore lettura tipi torneo: {$e->getMessage()}");
            $this->command->info("🔄 Fallback a tipi di default...");
            $tipiReali = collect(['T18', 'GN-72', 'CI']); // Tipi di default
        }

        $createdCount = 0;

        foreach ($tipiReali as $tipo) {
            $mappedType = $this->mapTipoToTournamentType($tipo);

            $this->dryRunUpdateOrInsert(
                'tournament_types',
                ['short_name' => $mappedType['short_name']],
                $mappedType,
                "Creazione tournament type: {$mappedType['name']}"
            );

            $createdCount++;
        }

        $this->stats['tournament_types'] = $createdCount;
        $this->command->info("✅ Creati {$createdCount} tournament types da dati reali");
    }

    /**
     * Mappa tipo reale da gare_2025 a tournament_type completo
     */
    private function mapTipoToTournamentType(string $tipo): array
    {
        $tipoUpper = strtoupper(trim($tipo));

        // Mapping intelligente basato sui nomi reali
        $mappings = [
            // Tornei zonali
            'T18' => [
                'name' => 'Torneo 18 buche',
                'is_national' => false,
                'min_referees' => 1,
                'max_referees' => 2,
                'required_level' => 'primo_livello'
            ],
            'S14' => [
                'name' => 'Torneo 14 buche',
                'is_national' => false,
                'min_referees' => 1,
                'max_referees' => 2,
                'required_level' => 'primo_livello'
            ],
            'GARA GIOVANILE' => [
                'name' => 'Gara Giovanile',
                'is_national' => false,
                'min_referees' => 1,
                'max_referees' => 2,
                'required_level' => 'primo_livello'
            ],

            // Gare nazionali
            'GN-36' => [
                'name' => 'Gara Nazionale 36 buche',
                'is_national' => true,
                'min_referees' => 2,
                'max_referees' => 4,
                'required_level' => 'regionale'
            ],
            'GN-54' => [
                'name' => 'Gara Nazionale 54 buche',
                'is_national' => true,
                'min_referees' => 2,
                'max_referees' => 4,
                'required_level' => 'regionale'
            ],
            'GN-72' => [
                'name' => 'Gara Nazionale 72 buche',
                'is_national' => true,
                'min_referees' => 3,
                'max_referees' => 5,
                'required_level' => 'nazionale'
            ],
            'GN-72/54' => [
                'name' => 'Gara Nazionale 72/54 buche',
                'is_national' => true,
                'min_referees' => 3,
                'max_referees' => 5,
                'required_level' => 'nazionale'
            ],

            // Campionati (nazionali di alto livello)
            'CI' => [
                'name' => 'Campionato Italiano',
                'is_national' => true,
                'min_referees' => 3,
                'max_referees' => 6,
                'required_level' => 'nazionale'
            ],
            'CNZ' => [
                'name' => 'Campionato Nazionale',
                'is_national' => true,
                'min_referees' => 3,
                'max_referees' => 6,
                'required_level' => 'nazionale'
            ],
            'TNZ' => [
                'name' => 'Torneo Nazionale',
                'is_national' => true,
                'min_referees' => 2,
                'max_referees' => 4,
                'required_level' => 'regionale'
            ],
        ];

        // Se il tipo è mappato, usa il mapping
        if (isset($mappings[$tipoUpper])) {
            $mapped = $mappings[$tipoUpper];
        } else {
            // Mapping automatico basato su pattern nel nome
            $mapped = $this->detectTournamentTypeByPattern($tipo);
        }

        // Costruisci il record completo
        $settings = [
            'required_referee_level' => $mapped['required_level'],
            'min_referees' => $mapped['min_referees'],
            'max_referees' => $mapped['max_referees'],
            'visibility_zones' => $mapped['is_national'] ? 'all' : 'own',
            'migrated_from' => 'gare_2025',
            'original_tipo' => $tipo,
        ];

        return [
            'name' => $mapped['name'],
            'short_name' => $tipo, // Usa il codice originale come short_name
            'description' => "Tipologia torneo: {$mapped['name']} (migrato da dati reali)",
            'is_national' => $mapped['is_national'],
            'required_level' => $mapped['required_level'],
            'level' => $mapped['is_national'] ? 'nazionale' : 'zonale',
            'min_referees' => $mapped['min_referees'],
            'max_referees' => $mapped['max_referees'],
            'sort_order' => $mapped['is_national'] ? 20 : 10, // Nazionali per ultimi
            'is_active' => true,
            'settings' => json_encode($settings),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Rileva tipo di torneo da pattern nel nome (per tipi non mappati)
     */
    private function detectTournamentTypeByPattern(string $tipo): array
    {
        $tipoLower = strtolower($tipo);

        // Pattern per rilevare tornei nazionali
        $nationalPatterns = ['nazionale', 'naz', 'gn-', 'cn', 'ci', 'campionato', 'championship'];
        $isNational = false;

        foreach ($nationalPatterns as $pattern) {
            if (str_contains($tipoLower, $pattern)) {
                $isNational = true;
                break;
            }
        }

        // Pattern per rilevare livello alto (più arbitri necessari)
        $highLevelPatterns = ['72', 'campionato', 'championship', 'internazionale'];
        $isHighLevel = false;

        foreach ($highLevelPatterns as $pattern) {
            if (str_contains($tipoLower, $pattern)) {
                $isHighLevel = true;
                break;
            }
        }

        // Determina caratteristiche basate sui pattern
        if ($isNational && $isHighLevel) {
            return [
                'name' => $tipo,
                'is_national' => true,
                'min_referees' => 3,
                'max_referees' => 6,
                'required_level' => 'nazionale'
            ];
        } elseif ($isNational) {
            return [
                'name' => $tipo,
                'is_national' => true,
                'min_referees' => 2,
                'max_referees' => 4,
                'required_level' => 'regionale'
            ];
        } else {
            return [
                'name' => $tipo,
                'is_national' => false,
                'min_referees' => 1,
                'max_referees' => 2,
                'required_level' => 'primo_livello'
            ];
        }
    }

    /**
     * Migra arbitri (arbitri → users + referees)
     * Implementa logica USER CENTRIC + auto-risoluzione conflitti
     */
    private function migrateArbitri()
    {
        $this->command->info('👥 Migrazione arbitri (approccio USER CENTRIC)...');

        // SEMPRE leggi dal database reale (anche in dry-run per statistiche corrette)
        try {
            $arbitri = DB::connection('real')->table('arbitri')->get();
            $this->command->info("🔍 Trovati {$arbitri->count()} arbitri nel database reale Sql1466239_4");
        } catch (\Exception $e) {
            $this->command->error("❌ Errore lettura arbitri: {$e->getMessage()}");
            return;
        }

        $processedCount = 0;
        $conflictCount = 0;
        $giovSkipped = 0;

        foreach ($arbitri as $arbitro) {
            // Skip record GIOV - controllo su Livello_2025
            if ($this->isGiovRecord($arbitro)) {
                $giovSkipped++;
                $this->command->info("⏭️ Saltato record GIOV: {$arbitro->Nome} {$arbitro->Cognome}");
                continue;
            }

            // Auto-risoluzione conflitti email
            $originalEmail = $arbitro->Email ?? null;
            $email = $this->resolveEmailConflict($originalEmail, $arbitro->Nome, $arbitro->Cognome);

            if ($originalEmail !== $email) {
                $conflictCount++;
                $this->command->info("🔄 Conflitto email risolto: {$originalEmail} → {$email}");
            }

            // Crea record user (con dati referee integrati)
            $userData = [
                'name' => trim($arbitro->Nome . ' ' . $arbitro->Cognome),
                'email' => $email,
                'password' => bcrypt($arbitro->Password),
                'user_type' => 'referee',
                'zone_id' => $this->mapZoneFromArbitro($arbitro),
                'referee_code' => $arbitro->codice ?? $this->generateRefereeCode(),
                'level' => $this->mapQualification($arbitro->Livello_2025 ?? 'aspirante'),
                'category' => $this->mapCategory($arbitro->categoria ?? 'misto'),
                'certified_date' => $this->parseDate($arbitro->Prima_Nomina ?? null),
                'phone' => $arbitro->Cellulare ?? null,
                'city' => $arbitro->Citta ?? null,
                'is_active' => $this->mapBooleanValue($arbitro->attivo ?? 'Vero'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $userId = $this->dryRunInsertGetId(
                'users',
                $userData,
                "Creazione user: {$userData['name']}"
            );

            // Crea record referee (extension)
            $this->dryRunInsert(
                'referees',
                [
                    'user_id' => $userId,
                    'address' => $arbitro->indirizzo ?? null,
                    'postal_code' => $arbitro->cap ?? null,
                    'first_certification_date' => $this->parseDate($arbitro->Prima_Nomina ?? null),
                    'last_renewal_date' => $this->parseDate($arbitro->Ultimo_Esame ?? null),
                    'tax_code' => $arbitro->codice_fiscale ?? null,
                    'profile_completed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                "Creazione referee extension per: {$userData['name']}"
            );

            $processedCount++;
        }

        $this->stats['arbitri'] = $processedCount;
        $this->stats['conflitti_risolti'] = $conflictCount;
        $this->stats['record_giov_saltati'] = $giovSkipped;

        $this->command->info("✅ Migrati {$processedCount} arbitri (conflitti risolti: {$conflictCount}, GIOV saltati: {$giovSkipped})");
    }

    /**
     * Migra circoli (circoli → clubs)
     */
    /**
     * 🔧 FIX: Gestione constraint UNIQUE per clubs (name, code)
     */

    /**
     * Migra circoli con gestione conflict UNIQUE
     */
    /**
     * 🔧 FIX: Migra circoli SENZA usare $circolo->id (che non esiste)
     */
    private function migrateCircoli()
    {
        $this->command->info('⛳ Migrazione circoli...');

        try {
            $circoli = DB::connection('real')->table('circoli')->get();
            $this->command->info("🔍 Trovati {$circoli->count()} circoli nel database reale Sql1466239_4");
        } catch (\Exception $e) {
            $this->command->error("❌ Errore lettura circoli: {$e->getMessage()}");
            return;
        }

        $processedCount = 0;
        $skippedCount = 0;

        foreach ($circoli as $circolo) {
            // NON usare $circolo->id - potrebbe non esistere
            $originalName = $circolo->Circolo_Nome ?? "Circolo Sconosciuto";
            $originalCode = $circolo->Circolo_Id ?? $this->generateUniqueClubCode($originalName);

            // Risolvi conflitti UNIQUE
            $name = $this->resolveUniqueClubName($originalName);
            $code = $this->resolveUniqueClubCode($originalCode);

            // Controlla se esiste già un club con lo stesso nome O codice
            if (!$this->dryRun) {
                $existingClub = DB::table('clubs')
                    ->where('name', $name)
                    ->orWhere('code', $code)
                    ->first();

                if ($existingClub) {
                    $this->command->info("⏭️ Club già esistente: {$name} (Codice: {$code})");
                    $skippedCount++;
                    continue;
                }
            }

            $clubData = [
                // NON includere 'id' - lascia che Laravel auto-generi
                'name' => $name,
                'code' => $code,
                'address' => $circolo->Indirizzo ?? null,
                'city' => $circolo->Città ?? null,
                'postal_code' => $circolo->CAP ?? null,
                'province' => $circolo->Provincia ?? null,
                'region' => $circolo->Regione ?? null,
                'phone' => $circolo->Telefono ?? null,
                'email' => $circolo->Email ?? null,
                'website' => $circolo->Web ?? null,
                'zone_id' => $this->mapZoneFromCircolo($circolo),
                'is_active' => $this->mapBooleanValue($circolo->SedeGara ?? 'Vero'),
                'settings' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $success = $this->dryRunInsert(
                'clubs',
                $clubData,
                "Creazione club: {$clubData['name']}"
            );

            if ($success) {
                $processedCount++;
            } else {
                $skippedCount++;
            }
        }

        // Crea circoli TBA DOPO i circoli normali
        $this->createVirtualTBAClubs();

        $this->stats['circoli'] = $processedCount;
        $this->command->info("✅ Migrati {$processedCount} circoli (saltati: {$skippedCount}) + circoli TBA virtuali");
    }

    /**
     * Genera codice club univoco basato sul nome
     */
    private function generateUniqueClubCode(string $name): string
    {
        // Crea codice base dal nome (prime lettere)
        $words = explode(' ', strtoupper(trim($name)));
        $baseCode = '';

        foreach ($words as $word) {
            if (strlen($word) > 0) {
                $baseCode .= substr($word, 0, 1);
            }
        }

        // Se troppo corto, usa le prime lettere del nome
        if (strlen($baseCode) < 3) {
            $baseCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3));
        }

        // Se ancora troppo corto, aggiungi padding
        if (strlen($baseCode) < 3) {
            $baseCode = str_pad($baseCode, 3, 'X');
        }

        return substr($baseCode, 0, 10); // Massimo 10 caratteri
    }

    /**
     * Risolve conflitti UNIQUE per club name (versione migliorata)
     */
    private function resolveUniqueClubName(string $originalName): string
    {
        if ($this->dryRun) {
            return $originalName;
        }

        $name = trim($originalName);
        $baseName = $name;
        $counter = 1;

        // Prova nomi progressivi fino a trovarne uno libero
        while (DB::table('clubs')->where('name', $name)->exists()) {
            $name = $baseName . " ({$counter})";
            $counter++;

            // Sicurezza: evita loop infiniti
            if ($counter > 100) {
                $name = $baseName . "_" . substr(uniqid(), -6);
                break;
            }
        }

        if ($name !== $originalName) {
            $this->command->info("🔄 Nome club modificato: '{$originalName}' → '{$name}'");
        }

        return $name;
    }

    /**
     * Risolve conflitti UNIQUE per club code (versione migliorata)
     */
    private function resolveUniqueClubCode(string $originalCode): string
    {
        if ($this->dryRun) {
            return $originalCode;
        }

        $code = strtoupper(trim($originalCode));
        $baseCode = $code;
        $counter = 1;

        // Prova codici progressivi fino a trovarne uno libero
        while (DB::table('clubs')->where('code', $code)->exists()) {
            // Per codici brevi, aggiungi numero
            if (strlen($baseCode) <= 6) {
                $code = $baseCode . $counter;
            } else {
                // Per codici lunghi, sostituisci la fine
                $code = substr($baseCode, 0, 6) . $counter;
            }

            $counter++;

            // Sicurezza: evita loop infiniti
            if ($counter > 100) {
                $code = substr($baseCode, 0, 6) . substr(uniqid(), -4);
                break;
            }
        }

        if ($code !== strtoupper($originalCode)) {
            $this->command->info("🔄 Codice club modificato: '{$originalCode}' → '{$code}'");
        }

        return $code;
    }

    /**
     * DEBUG: Mostra struttura record circolo per debug
     */
    private function debugCircoloStructure($circolo)
    {
        $this->command->info("🔍 DEBUG - Struttura record circolo:");
        foreach ($circolo as $key => $value) {
            $this->command->line("  {$key}: " . ($value ?? 'NULL'));
        }
    }

    /**
     * Risolve club per torneo (versione senza dipendenza da ID specifici)
     */
    private function resolveClubForTournament($gara): int
    {
        if ($this->dryRun) {
            return 1; // Fallback per dry-run
        }

        // STEP 1: Prova match per nome/codice circolo dal campo Circolo
        if (isset($gara->Circolo) && $gara->Circolo) {
            $club = DB::table('clubs')
                ->where('code', 'LIKE', "%{$gara->Circolo}%")
                ->orWhere('name', 'LIKE', "%{$gara->Circolo}%")
                ->first();
            if ($club) {
                return $club->id;
            }
        }

        // STEP 2: Prova altri campi che potrebbero contenere info sul circolo
        $possibleClubFields = ['club_name', 'circolo_nome', 'sede', 'location'];

        foreach ($possibleClubFields as $field) {
            if (isset($gara->$field) && $gara->$field) {
                $club = DB::table('clubs')
                    ->where('name', 'LIKE', "%{$gara->$field}%")
                    ->first();
                if ($club) {
                    $this->command->info("🎯 Club trovato via {$field}: {$club->name}");
                    return $club->id;
                }
            }
        }

        // STEP 3: Fallback a TBA della zona specifica
        $zoneId = $this->resolveZoneForTournament($gara, null);
        $zone = DB::table('zones')->find($zoneId);

        if ($zone) {
            $tbaClub = DB::table('clubs')
                ->where('code', "TBA_{$zone->code}")
                ->first();

            if ($tbaClub) {
                $this->command->info("🎯 Usato TBA per zona {$zone->code}: {$tbaClub->name}");
                return $tbaClub->id;
            }
        }

        // STEP 4: Fallback finale - primo club disponibile
        $fallbackClub = DB::table('clubs')->first();

        if ($fallbackClub) {
            $this->command->warn("⚠️ Fallback al primo club disponibile: {$fallbackClub->name}");
            return $fallbackClub->id;
        }

        return 1; // Fallback assoluto
    }


    /**
     * Crea circoli TBA virtuali con gestione UNIQUE
     */
    private function createVirtualTBAClubs()
    {
        try {
            $zones = DB::table('zones')->get();
            $this->command->info("🏗️ Creazione circoli TBA per {$zones->count()} zone");
        } catch (\Exception $e) {
            $this->command->error("❌ Errore lettura zone per TBA: {$e->getMessage()}");
            return;
        }

        $createdCount = 0;

        foreach ($zones as $zone) {
            $tbaName = "TBA - {$zone->name}";
            $tbaCode = "TBA_{$zone->code}";

            // Controlla se esiste già
            if (!$this->dryRun) {
                $existingTBA = DB::table('clubs')
                    ->where('code', $tbaCode)
                    ->orWhere('name', $tbaName)
                    ->first();

                if ($existingTBA) {
                    $this->command->info("⏭️ TBA già esistente per zona {$zone->code}: {$existingTBA->name}");
                    continue;
                }
            }

            $success = $this->dryRunInsert(
                'clubs',
                [
                    'name' => $tbaName,
                    'code' => $tbaCode,
                    'address' => 'To Be Announced',
                    'city' => 'TBA',
                    'zone_id' => $zone->id,
                    'is_active' => true,
                    'settings' => json_encode(['virtual_tba' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                "Creazione club TBA per zona {$zone->code}"
            );

            if ($success) {
                $createdCount++;
            }
        }

        $this->command->info("  → Creati {$createdCount} circoli TBA virtuali");
    }


    /**
     * OPTIONAL: Cleanup clubs duplicati prima della migrazione
     */
    private function cleanupDuplicateClubs()
    {
        if ($this->dryRun) {
            $this->command->info("🧪 DRY-RUN: Cleanup clubs duplicati");
            return;
        }

        $this->command->info("🧹 Cleanup clubs duplicati...");

        // Trova duplicati per nome
        $duplicateNames = DB::table('clubs')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicateNames as $name) {
            $duplicates = DB::table('clubs')
                ->where('name', $name)
                ->orderBy('id')
                ->get();

            // Mantieni il primo, elimina gli altri
            $toKeep = $duplicates->first();
            $toDelete = $duplicates->skip(1);

            foreach ($toDelete as $duplicate) {
                $this->command->info("🗑️ Eliminato club duplicato: {$duplicate->name} (ID: {$duplicate->id})");
                DB::table('clubs')->where('id', $duplicate->id)->delete();
            }
        }

        // Trova duplicati per codice
        $duplicateCodes = DB::table('clubs')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('code');

        foreach ($duplicateCodes as $code) {
            $duplicates = DB::table('clubs')
                ->where('code', $code)
                ->orderBy('id')
                ->get();

            $toKeep = $duplicates->first();
            $toDelete = $duplicates->skip(1);

            foreach ($toDelete as $duplicate) {
                $this->command->info("🗑️ Eliminato club codice duplicato: {$duplicate->code} (ID: {$duplicate->id})");
                DB::table('clubs')->where('id', $duplicate->id)->delete();
            }
        }
    }
    /**
     * Migra tornei (gare_2025 → tournaments)
     */
    private function migrateGare()
    {
        $this->command->info('🏆 Migrazione tornei (gare_2025)...');

        // SEMPRE leggi dal database reale (anche in dry-run per statistiche corrette)
        try {
            $gare = DB::connection('real')->table('gare_2025')->get();
            $this->command->info("🔍 Trovati {$gare->count()} tornei nel database reale Sql1466239_4");
        } catch (\Exception $e) {
            $this->command->error("❌ Errore lettura tornei: {$e->getMessage()}");
            return;
        }

        $processedCount = 0;

        foreach ($gare as $gara) {
            $clubId = $this->resolveClubForTournament($gara);
            $tournamentTypeId = $this->resolveTournamentType($gara);
            $zoneId = $this->resolveZoneForTournament($gara, $clubId);

            $tournamentData = [
                'name' => $gara->Nome_gara ?? "Torneo #{$gara->id}",
                'description' => $gara->descrizione ?? null,
                'start_date' => $this->parseDate($gara->StartTime),
                'end_date' => $this->parseDate($gara->EndTime),
                'availability_deadline' => $this->calculateAvailabilityDeadline($gara->StartTime), // ✅ FIX
                'club_id' => $clubId,
                'zone_id' => $zoneId,
                'tournament_type_id' => $tournamentTypeId,
                'status' => $this->mapTournamentStatus($gara->stato ?? 'draft'),
                'notes' => $gara->note ?? null,
                'created_at' => $this->parseDate($gara->created_at ?? null) ?? now(),
                'updated_at' => now(),
            ];

            $this->dryRunUpdateOrInsert(
                'tournaments',
                ['id' => $gara->id],
                $tournamentData,
                "Creazione torneo: {$tournamentData['name']}"
            );

            $processedCount++;
        }

        $this->stats['tornei'] = $processedCount;
        $this->command->info("✅ Migrati {$processedCount} tornei");
    }

    /**
     * Migra disponibilità (parsing CSV da gare_2025.Disponibilità)
     */
    private function migrateDisponibilita()
    {
        $this->command->info('📅 Migrazione disponibilità (parsing CSV)...');

        // SEMPRE leggi dal database reale (anche in dry-run per statistiche corrette)
        try {
            $gare = DB::connection('real')
                ->table('gare_2025')
                ->whereNotNull('Disponibili')
                ->where('Disponibili', '!=', '')
                ->get();
            $this->command->info("🔍 Trovati {$gare->count()} tornei con disponibilità CSV nel database reale");
        } catch (\Exception $e) {
            $this->command->error("❌ Errore lettura disponibilità: {$e->getMessage()}");
            return;
        }

        $totalAvailabilities = 0;

        foreach ($gare as $gara) {
            $count = $this->parseDisponibilitaCSV($gara->Disponibili, $gara->id);
            $totalAvailabilities += $count;

            if ($count > 0) {
                $this->command->info("  → Torneo #{$gara->id}: {$count} disponibilità");
            }
        }

        $this->stats['disponibilita'] = $totalAvailabilities;
        $this->command->info("✅ Migrate {$totalAvailabilities} disponibilità totali");
    }

    /**
     * Migra assegnazioni (parsing CSV da TD + Arbitri + Osservatori)
     */
    private function migrateAssegnazioni()
    {
        $this->command->info('📋 Migrazione assegnazioni (parsing CSV TD + Arbitri + Osservatori)...');

        // SEMPRE leggi dal database reale (anche in dry-run per statistiche corrette)
        try {
            $gare = DB::connection('real')
                ->table('gare_2025')
                ->where(function ($query) {
                    $query->whereNotNull('TD')
                        ->orWhereNotNull('Arbitri')
                        ->orWhereNotNull('Osservatori');
                })
                ->get();
            $this->command->info("🔍 Trovati {$gare->count()} tornei con assegnazioni CSV nel database reale");
        } catch (\Exception $e) {
            $this->command->error("❌ Errore lettura assegnazioni: {$e->getMessage()}");
            return;
        }

        $totalAssignments = 0;

        foreach ($gare as $gara) {
            $count = $this->parseAssegnazioniCSV($gara);
            $totalAssignments += $count;

            if ($count > 0) {
                $this->command->info("  → Torneo #{$gara->id}: {$count} assegnazioni");
            }
        }

        $this->stats['assegnazioni'] = $totalAssignments;
        $this->command->info("✅ Migrate {$totalAssignments} assegnazioni totali");
    }

    // ========================================
    // METODI DI UTILITÀ E HELPER
    // ========================================

    /**
     * Identifica record GIOV da rimuovere (attività giovanile)
     * GIOV nella colonna Livello_2025 indica attività giovanile, non arbitro
     */
    private function isGiovRecord($arbitro): bool
    {
        $livello2025 = strtoupper(trim($arbitro->Livello_2025 ?? ''));
        return $livello2025 === 'GIOV';
    }

    /**
     * Mappa qualifiche/livelli dal database reale
     * ARCH=Archivio, REG=Regionale, NAZ=Nazionale, INT=Internazionale
     */
    private function mapQualification(?string $qualification): string
    {
        if (empty($qualification)) {
            return RefereeLevelsHelper::normalize('Aspirante');
        }

        $qual = strtoupper(trim($qualification));

        return match ($qual) {
            'ARCH', 'ARCHIVIO' => RefereeLevelsHelper::normalize('Archivio'),
            'ASP', 'ASPIRANTE' => RefereeLevelsHelper::normalize('Aspirante'),
            'PRIMO', 'PRIMO_LIVELLO', '1_LIVELLO', '1° LIVELLO' => RefereeLevelsHelper::normalize('1_livello'),
            'REG', 'REGIONALE' => RefereeLevelsHelper::normalize('Regionale'),
            'NAZ', 'NAZIONALE' => RefereeLevelsHelper::normalize('Nazionale'),
            'INT', 'INTERNAZIONALE' => RefereeLevelsHelper::normalize('Internazionale'),
            default => RefereeLevelsHelper::normalize('Aspirante')
        };
    }

    /**
     * Mappa categoria arbitro
     */
    private function mapCategory(?string $category): string
    {
        if (empty($category)) {
            return 'misto';
        }

        $cat = strtolower(trim($category));

        return match ($cat) {
            'uomini', 'maschile', 'm' => 'maschile',
            'donne', 'femminile', 'f' => 'femminile',
            'misto', 'misti', 'm/f' => 'misto',
            default => 'misto'
        };
    }

    /**
     * Mappa zona da record arbitro
     */
    private function mapZoneFromArbitro($arbitro): ?int
    {
        if (isset($arbitro->zona_id)) {
            return $arbitro->zona_id;
        }

        if (isset($arbitro->Zona) || isset($arbitro->codice_zona)) {
            $zoneCode = $arbitro->Zona ?? $arbitro->codice_zona;
            $zone = DB::table('zones')->where('code', strtoupper($zoneCode))->first();
            if ($zone) {
                return $zone->id;
            }
        }

        $defaultZone = DB::table('zones')->where('code', 'SZR1')->first();
        return $defaultZone ? $defaultZone->id : 1;
    }

    /**
     * Genera codice arbitro univoco
     */
    private function generateRefereeCode(): string
    {
        do {
            $code = 'ARB' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (DB::table('users')->where('referee_code', $code)->exists());

        return $code;
    }

    /**
     * Parse date con gestione formati multipli
     */
    private function parseDate($dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/Y H:i:s'];

            foreach ($formats as $format) {
                try {
                    return Carbon::createFromFormat($format, $dateString);
                } catch (\Exception $ignored) {
                    continue;
                }
            }

            $this->command->warn("⚠️ Impossibile parsare data: {$dateString}");
            return null;
        }
    }

    /**
     * Slugify per generazione email
     */
    private function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '', $text);

        return trim($text);
    }

    /**
     * Auto-risoluzione conflitti email
     */
    private function resolveEmailConflict(?string $email, string $nome, string $cognome): string
    {
        if (empty($email)) {
            return $this->generateUniqueEmail($nome, $cognome);
        }

        if ($this->dryRun) {
            return $email; // In dry-run non controllare conflitti
        }

        $existingUser = DB::table('users')->where('email', $email)->first();
        if (!$existingUser) {
            return $email;
        }

        return $this->generateUniqueEmail($nome, $cognome);
    }

    /**
     * Genera email univoca basata su nome/cognome
     */
    private function generateUniqueEmail(string $nome, string $cognome): string
    {
        $baseEmail = strtolower(
            $this->slugify($nome) . '.' . $this->slugify($cognome) . '@arbitri.golf'
        );

        if ($this->dryRun) {
            return $baseEmail; // In dry-run non controllare unicità
        }

        $counter = 1;
        $email = $baseEmail;

        while (DB::table('users')->where('email', $email)->exists()) {
            $email = str_replace('@arbitri.golf', $counter . '@arbitri.golf', $baseEmail);
            $counter++;
        }

        return $email;
    }

    /**
     * Mapping booleano italiano
     */
    private function mapBooleanValue($value): bool
    {
        if (is_bool($value)) return $value;

        $strValue = strtolower(trim($value));
        return in_array($strValue, ['vero', 'true', '1', 'si', 'sì', 'yes']);
    }

    /**
     * Trova user ID da nome completo (per parsing CSV)
     */
    private function findUserByFullName(string $fullName): ?int
    {
        if (empty($fullName)) {
            return null;
        }

        if ($this->dryRun) {
            return 999; // ID fittizio per dry-run
        }

        $cleanName = trim($fullName);

        $user = DB::table('users')
            ->where('name', $cleanName)
            ->where('user_type', 'referee')
            ->first();

        if ($user) {
            return $user->id;
        }

        $user = DB::table('users')
            ->where('name', 'LIKE', "%{$cleanName}%")
            ->where('user_type', 'referee')
            ->first();

        if ($user) {
            $this->command->info("🔍 Match parziale: '{$cleanName}' → '{$user->name}'");
            return $user->id;
        }

        $this->command->warn("⚠️ Utente non trovato: '{$cleanName}'");
        return null;
    }

    /**
     * Crea assegnazione da nome (per parsing CSV)
     */
    private function createAssignmentFromName(int $tournamentId, string $fullName, string $role): bool
    {
        $userId = $this->findUserByFullName($fullName);

        if (!$userId) {
            return false;
        }

        if (!$this->dryRun) {
            $existing = DB::table('assignments')
                ->where('tournament_id', $tournamentId)
                ->where('user_id', $userId)
                ->where('role', $role)
                ->first();

            if ($existing) {
                return true;
            }
        }

        $success = $this->dryRunInsert(
            'assignments',
            [
                'tournament_id' => $tournamentId,
                'user_id' => $userId,
                'assigned_by_id' => 1, // Default system user
                'role' => $role,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            "Assegnazione {$role} per torneo #{$tournamentId}: {$fullName}"
        );

        return $success;
    }

    // ========================================
    // METODI DI SUPPORTO PER MIGRAZIONE
    // ========================================

    /**
     * Risolve conflitti nome circolo
     */
    private function resolveClubNameConflict(string $originalName): string
    {
        if ($this->dryRun) {
            return $originalName;
        }

        $name = $originalName;
        $counter = 1;

        while (DB::table('clubs')->where('name', $name)->exists()) {
            $name = $originalName . " ({$counter})";
            $counter++;
        }

        return $name;
    }

    /**
     * Mappa zona da record circolo
     */
    private function mapZoneFromCircolo($circolo): int
    {
        if (isset($circolo->zona_id) && $circolo->zona_id) {
            return $circolo->zona_id;
        }

        if (isset($circolo->Zona)) {
            $zone = DB::table('zones')->where('code', strtoupper($circolo->Zona))->first();
            if ($zone) {
                return $zone->id;
            }
        }

        return DB::table('zones')->where('code', 'SZR1')->value('id') ?? 1;
    }

    /**
     * Risolve zona per torneo (migliorata per gestire più campi)
     */
    private function resolveZoneForTournament($gara, ?int $clubId): int
    {
        if ($this->dryRun) {
            return 1; // Fallback per dry-run
        }

        // STEP 1: zona_id diretta
        if (isset($gara->zona_id) && $gara->zona_id) {
            return $gara->zona_id;
        }

        // STEP 2: Campo Zona nel record
        if (isset($gara->Zona) && $gara->Zona) {
            $zone = DB::table('zones')
                ->where('code', strtoupper($gara->Zona))
                ->first();
            if ($zone) {
                return $zone->id;
            }
        }

        // STEP 3: Zona dal club (se disponibile)
        if ($clubId) {
            $club = DB::table('clubs')->find($clubId);
            if ($club && $club->zone_id) {
                return $club->zone_id;
            }
        }

        // STEP 4: Fallback - prima zona disponibile
        $defaultZone = DB::table('zones')->where('code', 'SZR1')->first();
        return $defaultZone ? $defaultZone->id : 1;
    }

    /**
     * Risolve tournament type - FIX: usa short_name
     */
    private function resolveTournamentType($gara): int
    {
        if ($this->dryRun) {
            return 1;
        }

        if (isset($gara->tipo) && $gara->tipo) {
            $type = DB::table('tournament_types')
                ->where('name', 'LIKE', "%{$gara->Tipo}%")
                ->first();
            if ($type) {
                return $type->id;
            }
        }

        return DB::table('tournament_types')
            ->where('short_name', 'GARA_ZONALE')
            ->value('id') ?? 1;
    }


    /**
     * Mappa status torneo
     */
    private function mapTournamentStatus(?string $status): string
    {
        if (empty($status)) {
            return 'draft';
        }

        $status = strtolower(trim($status));

        return match ($status) {
            'bozza', 'draft' => 'draft',
            'aperto', 'open' => 'open',
            'chiuso', 'closed' => 'closed',
            'annullato', 'cancelled' => 'cancelled',
            'completato', 'completed' => 'completed',
            default => 'draft'
        };
    }

    /**
     * Parsing CSV disponibilità (con contatore)
     */
    private function parseDisponibilitaCSV($disponibilitaString, $tournamentId): int
    {
        if (empty($disponibilitaString)) {
            return 0;
        }

        $nomi = explode(',', $disponibilitaString);
        $count = 0;

        foreach ($nomi as $nomeCompleto) {
            $userId = $this->findUserByFullName(trim($nomeCompleto));
            if ($userId) {
                $success = $this->dryRunUpdateOrInsert(
                    'availabilities',
                    [
                        'user_id' => $userId,
                        'tournament_id' => $tournamentId,
                    ],
                    [
                        'notes' => 'Migrato da CSV Disponibilità',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    "Disponibilità per torneo #{$tournamentId}: " . trim($nomeCompleto)
                );

                if ($success) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Parsing CSV assegnazioni (con inversione intelligente nomi "Cognome Nome" → "Nome Cognome")
     */
    private function parseAssegnazioniCSV($gara): int
    {
        $count = 0;

        // TD (Direttore di Torneo)
        if (!empty($gara->TD)) {
            $correctedName = $this->smartNameInversion($gara->TD);
            if ($this->createAssignmentFromName($gara->id, $correctedName, 'Direttore di Torneo')) {
                $count++;
            }
        }

        // Arbitri (lista CSV)
        if (!empty($gara->Arbitri)) {
            $arbitri = explode(',', $gara->Arbitri);
            foreach ($arbitri as $arbitro) {
                $correctedName = $this->smartNameInversion(trim($arbitro));
                if ($this->createAssignmentFromName($gara->id, $correctedName, 'Arbitro')) {
                    $count++;
                }
            }
        }

        // Osservatori (lista CSV)
        if (!empty($gara->Osservatori)) {
            $osservatori = explode(',', $gara->Osservatori);
            foreach ($osservatori as $osservatore) {
                $correctedName = $this->smartNameInversion(trim($osservatore));
                if ($this->createAssignmentFromName($gara->id, $correctedName, 'Osservatore')) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Inversione intelligente nome con verifica su tabella users
     * Gestisce nomi/cognomi multipli e verifica contro database
     */
    private function smartNameInversion(string $fullName): string
    {
        $cleanName = trim($fullName);

        if (empty($cleanName)) {
            return $cleanName;
        }

        // In dry-run, simula il processo ma non accede al database
        if ($this->dryRun) {
            $this->command->info("🧪 DRY-RUN: Inversione nome '{$cleanName}'");
            return $cleanName;
        }

        // STEP 1: Prova il nome così com'è (potrebbe essere già corretto)
        $directMatch = DB::table('users')
            ->where('name', $cleanName)
            ->where('user_type', 'referee')
            ->first();

        if ($directMatch) {
            $this->command->info("✅ Match diretto: '{$cleanName}' (ID: {$directMatch->id})");
            return $cleanName;
        }

        // STEP 2: Prova inversione intelligente
        $invertedName = $this->performNameInversion($cleanName);

        if ($invertedName !== $cleanName) {
            // Verifica che la versione invertita esista nel database
            $invertedMatch = DB::table('users')
                ->where('name', $invertedName)
                ->where('user_type', 'referee')
                ->first();

            if ($invertedMatch) {
                $this->command->info("🔄 Inversione riuscita: '{$cleanName}' → '{$invertedName}' (ID: {$invertedMatch->id})");
                return $invertedName;
            }
        }

        // STEP 3: Prova match parziale (fallback)
        $partialMatch = DB::table('users')
            ->where('name', 'LIKE', "%{$cleanName}%")
            ->where('user_type', 'referee')
            ->first();

        if ($partialMatch) {
            $this->command->info("🔍 Match parziale: '{$cleanName}' → '{$partialMatch->name}' (ID: {$partialMatch->id})");
            return $partialMatch->name;
        }

        // STEP 4: Nessun match trovato
        $this->command->warn("⚠️ Nessun match per: '{$cleanName}' (né diretto, né invertito, né parziale)");
        return $cleanName; // Restituisce originale
    }

    /**
     * Esegue inversione intelligente gestendo nomi/cognomi multipli
     */
    private function performNameInversion(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName));
        $numParts = count($parts);

        // Se meno di 2 parti, non può essere invertito
        if ($numParts < 2) {
            return $fullName;
        }

        // CASO 1: Esattamente 2 parti - semplice inversione
        if ($numParts == 2) {
            return $parts[1] . ' ' . $parts[0];
        }

        // CASO 2: 3+ parti - logica intelligente per nomi/cognomi multipli
        return $this->handleMultipleNameParts($parts, $fullName);
    }

    /**
     * Gestisce nomi con parti multiple (es: "De Sanctis Marco Antonio")
     */
    private function handleMultipleNameParts(array $parts, string $originalName): string
    {
        $numParts = count($parts);

        // STRATEGIA 1: Ultimo elemento come nome, resto come cognome
        // "De Sanctis Marco" → "Marco De Sanctis"
        $strategy1 = $parts[$numParts - 1] . ' ' . implode(' ', array_slice($parts, 0, $numParts - 1));

        // STRATEGIA 2: Prime 2 parti come cognome, resto come nome (per cognomi doppi)
        // "Van Der Berg Marco" → "Marco Van Der Berg"
        if ($numParts >= 3) {
            $strategy2 = implode(' ', array_slice($parts, 2)) . ' ' . implode(' ', array_slice($parts, 0, 2));
        } else {
            $strategy2 = $strategy1;
        }

        // STRATEGIA 3: Prima parte come cognome, resto come nome (per nomi doppi)
        // "Rossi Marco Antonio" → "Marco Antonio Rossi"
        $strategy3 = implode(' ', array_slice($parts, 1)) . ' ' . $parts[0];

        // Testa le strategie in ordine di probabilità
        $strategies = [$strategy1, $strategy2, $strategy3];

        if (!$this->dryRun) {
            foreach ($strategies as $index => $candidate) {
                $match = DB::table('users')
                    ->where('name', $candidate)
                    ->where('user_type', 'referee')
                    ->first();

                if ($match) {
                    $this->command->info("🎯 Strategia " . ($index + 1) . " riuscita: '{$originalName}' → '{$candidate}'");
                    return $candidate;
                }
            }
        }

        // Se nessuna strategia funziona, usa la prima (più probabile)
        $this->command->info("🔄 Inversione multipla (strategia 1): '{$originalName}' → '{$strategy1}'");
        return $strategy1;
    }
    // ========================================
    // WRAPPER DRY-RUN E HELPER
    // ========================================

    /**
     * Insert con supporto dry-run
     */
    private function dryRunInsert(string $table, array $data, string $description = ''): bool
    {
        if ($this->dryRun) {
            $this->command->info("🧪 DRY-RUN: " . ($description ?: "Insert in {$table}") . " su tabella '{$table}'");
            return true;
        }

        return DB::table($table)->insert($data);
    }

    /**
     * UpdateOrInsert con supporto dry-run
     */
    private function dryRunUpdateOrInsert(string $table, array $attributes, array $values, string $description = ''): bool
    {
        if ($this->dryRun) {
            $this->command->info("🧪 DRY-RUN: " . ($description ?: "UpdateOrInsert in {$table}") . " su tabella '{$table}'");
            return true;
        }

        return DB::table($table)->updateOrInsert($attributes, $values);
    }

    /**
     * InsertGetId con supporto dry-run
     */
    private function dryRunInsertGetId(string $table, array $data, string $description = ''): ?int
    {
        if ($this->dryRun) {
            $this->command->info("🧪 DRY-RUN: " . ($description ?: "InsertGetId in {$table}") . " su tabella '{$table}'");
            return 999;
        }

        return DB::table($table)->insertGetId($data);
    }

    /**
     * Verifica esistenza tabella
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
            return false;
        }
    }

    /**
     * Inizializza array statistiche
     */
    private function initializeStats()
    {
        $this->stats = [
            'zones' => 0,
            'tournament_types' => 0,
            'arbitri' => 0,
            'conflitti_risolti' => 0,
            'circoli' => 0,
            'tornei' => 0,
            'disponibilita' => 0,
            'assegnazioni' => 0,
            'record_giov_saltati' => 0,
        ];
    }

    /**
     * Report statistiche finali
     */
    private function printFinalStats()
    {
        $this->command->info("\n📊 STATISTICHE MIGRAZIONE MASTER:");
        $this->command->line("┌─────────────────────────────────────┐");
        $this->command->line("│             MIGRAZIONE              │");
        $this->command->line("├─────────────────────────────────────┤");
        $this->command->line("│ Zone:                    {$this->formatStat($this->stats['zones'])} │");
        $this->command->line("│ Tournament Types:        {$this->formatStat($this->stats['tournament_types'])} │");
        $this->command->line("│ Arbitri:                 {$this->formatStat($this->stats['arbitri'])} │");
        $this->command->line("│ Circoli:                 {$this->formatStat($this->stats['circoli'])} │");
        $this->command->line("│ Tornei:                  {$this->formatStat($this->stats['tornei'])} │");
        $this->command->line("│ Disponibilità:           {$this->formatStat($this->stats['disponibilita'])} │");
        $this->command->line("│ Assegnazioni:            {$this->formatStat($this->stats['assegnazioni'])} │");
        $this->command->line("├─────────────────────────────────────┤");
        $this->command->line("│             PULIZIA                 │");
        $this->command->line("├─────────────────────────────────────┤");
        $this->command->line("│ Conflitti email risolti: {$this->formatStat($this->stats['conflitti_risolti'])} │");
        $this->command->line("│ Record GIOV saltati:     {$this->formatStat($this->stats['record_giov_saltati'])} │");
        $this->command->line("└─────────────────────────────────────┘");
    }

    /**
     * Formatta statistiche per output allineato
     */
    private function formatStat(int $value): string
    {
        return str_pad($value, 8, ' ', STR_PAD_LEFT);
    }

    /**
     * Chiudi connessione database reale
     */
    private function closeRealDatabaseConnection()
    {
        try {
            DB::disconnect('real');
            $this->command->info('🔌 Connessione database Sql1466239_4 chiusa');
        } catch (\Exception $e) {
            $this->command->warn('⚠️ Errore chiusura connessione: ' . $e->getMessage());
        }
    }
    /**
     * Calcola availability_deadline 10 giorni prima della StartTime
     */
    private function calculateAvailabilityDeadline($startTime): ?Carbon
    {
        $startDate = $this->parseDate($startTime);

        if (!$startDate) {
            return null;
        }

        // 10 giorni prima della data di inizio
        return $startDate->copy()->subDays(10);
    }
}
