<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Helpers\RefereeLevelsHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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
        // $this->dryRun = env('MIGRATION_DRY_RUN', false) ||
        //$this->command->confirm('Eseguire in modalità DRY-RUN (solo simulazione)?', false);

        // if ($this->dryRun) {
        //     //$this->command->info('🧪 MODALITÀ DRY-RUN ATTIVATA - Nessuna modifica al database');
        // }

        //$this->command->info('🚀 Inizio MasterMigrationSeeder - Migrazione Unificata...');
        $this->setupMockCommand();

        // 1. Setup connessione database reale
        $this->setupRealDatabaseConnection();

        // 2. Verifica database
        if (!$this->checkRealDatabase()) {
            //$this->command->error('❌ Impossibile connettersi al database Sql1466239_4');
            return;
        }

        // 3. Inizializza statistiche
        $this->initializeStats();

        // 4. Esegui migrazione nell'ordine corretto (USER CENTRIC approach)
        //$this->command->info('✅ Database verificato, procedo con migrazione USER CENTRIC...');

        $this->call(ZoneSeeder::class);
        $this->call(TournamentTypeSeeder::class);
        $this->createAdminUsers();      // ✅ AGGIUNTO: Crea admin per ogni zona
        $this->migrateArbitri();        // arbitri → users + referees
        $this->migrateCircoli();        // circoli → clubs
        $this->call(LetterheadSeeder::class);
        $this->call(LetterTemplateSeeder::class);
        $this->call(SettingsSeeder::class);
        $this->call(SupportDataSeeder::class);
        $this->call(CommunicationSeeder::class);

        $this->migrateGare();           // gare_2025 → tournaments

        $this->populateMainTablesFromCurrentYear();

        // 5. Report finale
        $this->printFinalStats();

        // 6. Chiudi connessione
        $this->closeRealDatabaseConnection();

        //$this->command->info('✅ MasterMigrationSeeder completato!');
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

        //$this->command->info("🔗 Connessione al database reale Sql1466239_4 configurata");
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
                    //$this->command->error("❌ Tabella '{$table}' non trovata in Sql1466239_4");
                    return false;
                }
            }

            //$this->command->info('✅ Database Sql1466239_4 verificato');
            return true;
        } catch (\Exception $e) {
            //$this->command->error('❌ Errore connessione Sql1466239_4: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Crea zone manualmente (SZR1-SZR7, CRC)
     */
    private function createZones()
    {
        if (Zone::count() > 0) {
            //$this->command->info('✅ Zone already exist (' . Zone::count() . ' found)');
            return;
        }
        //$this->command->info('📍 Creazione zones...');

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
        //$this->command->info("✅ Create {$this->stats['zones']} zone");
    }

    /**
     * Crea admin zonali e nazionali (SZR1-SZR7 admin, CRC national_admin)
     * Da chiamare DOPO createZones() nel metodo run()
     */
    private function createAdminUsers()
    {
        //$this->command->info('👤 Creazione utenti admin zonali e nazionali...');

        // Controlla se esistono già admin
        $existingAdmins = DB::table('users')
            ->whereIn('user_type', ['admin', 'national_admin', 'super_admin'])
            ->count();

        if ($existingAdmins > 0) {
            //$this->command->info("✅ Admin già esistenti ({$existingAdmins} trovati)");
            return;
        }

        $zones = DB::table('zones')->get();
        $createdCount = 0;

        foreach ($zones as $zone) {
            // Determina il tipo di admin basato sulla zona
            $userType = $zone->is_national ? 'national_admin' : 'admin';

            // Email unica per la zona
            $email = strtolower($zone->code) . '@federgolf.it';

            // Nome admin basato sulla zona
            $adminName = $zone->is_national
                ? "Amministratore Nazionale {$zone->code}"
                : "Amministratore {$zone->name}";

            $adminData = [
                'name' => $adminName,
                'email' => $email,
                'password' => bcrypt($this->generateAdminPassword($zone->code)),
                'user_type' => $userType,
                'zone_id' => $zone->id,
                'email_verified_at' => now(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $success = $this->dryRunInsert(
                'users',
                $adminData,
                "Creazione {$userType}: {$adminName} ({$zone->code})"
            );

            if ($success) {
                $createdCount++;

                if (!$this->dryRun) {
                    //$this->command->info("  ✅ {$adminName}");
                    //$this->command->line("     Email: {$email}");
                    //$this->command->line("     Password temporanea: " . $this->getReadablePassword($zone->code));
                }
            }
        }

        // Crea SUPER ADMIN se non esiste
        $this->createSuperAdmin();

        $this->stats['admin_users'] = $createdCount;
        //$this->command->info("✅ Creati {$createdCount} admin users");

        if (!$this->dryRun) {
            //$this->command->warn("⚠️  IMPORTANTE: Cambiare le password temporanee al primo accesso!");
        }
    }

    /**
     * Crea Super Admin se non esiste
     */
    private function createSuperAdmin()
    {
        if (!$this->dryRun) {
            $existingSuperAdmin = DB::table('users')
                ->where('user_type', 'super_admin')
                ->first();

            if ($existingSuperAdmin) {
                //$this->command->info("✅ Super Admin già esistente: {$existingSuperAdmin->name}");
                return;
            }
        }

        $superAdminData = [
            'name' => 'Super Amministratore FIG',
            'email' => 'superadmin@federgolf.it',
            'password' => bcrypt('password'),
            'user_type' => 'super_admin',
            'zone_id' => null, // Super admin non è limitato a una zona
            'email_verified_at' => now(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $success = $this->dryRunInsert(
            'users',
            $superAdminData,
            "Creazione Super Admin FIG"
        );

        if ($success && !$this->dryRun) {
            //$this->command->info("  ✅ Super Amministratore FIG");
            //$this->command->line("     Email: superadmin@federgolf.it");
            //$this->command->line("     Password temporanea: SuperAdmin@Golf2024!");
        }
    }

    /**
     * Genera password sicura per admin basata sulla zona
     */
    private function generateAdminPassword(string $zoneCode): string
    {
        // Password pattern: ZoneCode + Anno + Simbolo
        return $zoneCode . '2024@Golf';
    }

    /**
     * Ottieni password leggibile per output
     */
    private function getReadablePassword(string $zoneCode): string
    {
        return $this->generateAdminPassword($zoneCode);
    }


    /**
     * Crea tournament types da dati reali - legge i tipi dalla colonna "tipo" in gare_2025
     */
    private function createTournamentTypes()
    {
        if (TournamentType::count() > 0) {
            //$this->command->info('✅ TournamentType already exist (' . TournamentType::count() . ' found)');
            return;
        }
        //$this->command->info('🏆 Creazione tournament types da dati reali (gare_2025.tipo)...');

        // SEMPRE leggi i tipi reali dal database Sql1466239_4
        try {
            $tipiReali = DB::connection('real')
                ->table('gare_2025')
                ->selectRaw('DISTINCT tipo')
                ->whereNotNull('tipo')
                ->where('tipo', '!=', '')
                ->pluck('tipo');

            //$this->command->info("🔍 Trovati {$tipiReali->count()} tipi reali di torneo: " . $tipiReali->implode(', '));
        } catch (\Exception $e) {
            //$this->command->error("❌ Errore lettura tipi torneo: {$e->getMessage()}");
            //$this->command->info("🔄 Fallback a tipi di default...");
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
        //$this->command->info("✅ Creati {$createdCount} tournament types da dati reali");
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
        //$this->command->info('👥 Migrazione arbitri (approccio USER CENTRIC)...');

        // SEMPRE leggi dal database reale (anche in dry-run per statistiche corrette)
        try {
            $arbitri = DB::connection('real')->table('arbitri')->get();
            //$this->command->info("🔍 Trovati {$arbitri->count()} arbitri nel database reale Sql1466239_4");
        } catch (\Exception $e) {
            //$this->command->error("❌ Errore lettura arbitri: {$e->getMessage()}");
            return;
        }

        $processedCount = 0;
        $conflictCount = 0;
        $giovSkipped = 0;

        foreach ($arbitri as $arbitro) {
            // Skip record GIOV - controllo su Livello_2025
            if ($this->isGiovRecord($arbitro)) {
                $giovSkipped++;
                //$this->command->info("⏭️ Saltato record GIOV: {$arbitro->Nome} {$arbitro->Cognome}");
                continue;
            }

            // Auto-risoluzione conflitti email
            $originalEmail = $arbitro->Email ?? null;
            $email = $this->resolveEmailConflict($originalEmail, $arbitro->Nome, $arbitro->Cognome);

            if ($originalEmail !== $email) {
                $conflictCount++;
                //$this->command->info("🔄 Conflitto email risolto: {$originalEmail} → {$email}");
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

        //$this->command->info("✅ Migrati {$processedCount} arbitri (conflitti risolti: {$conflictCount}, GIOV saltati: {$giovSkipped})");
    }


    /**
     * Migra circoli con gestione conflict UNIQUE
     */
    /**
     * 🔧 FIX: Migra circoli SENZA usare $circolo->id (che non esiste)
     */
    private function migrateCircoli()
    {
        //$this->command->info('⛳ Migrazione circoli...');

        try {
            $circoli = DB::connection('real')->table('circoli')->get();
            //$this->command->info("🔍 Trovati {$circoli->count()} circoli nel database reale Sql1466239_4");
        } catch (\Exception $e) {
            //$this->command->error("❌ Errore lettura circoli: {$e->getMessage()}");
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
                    //$this->command->info("⏭️ Club già esistente: {$name} (Codice: {$code})");
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
        //$this->command->info("✅ Migrati {$processedCount} circoli (saltati: {$skippedCount}) + circoli TBA virtuali");
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
            //$this->command->info("🔄 Nome club modificato: '{$originalName}' → '{$name}'");
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
            //$this->command->info("🔄 Codice club modificato: '{$originalCode}' → '{$code}'");
        }

        return $code;
    }

    /**
     * DEBUG: Mostra struttura record circolo per debug
     */
    private function debugCircoloStructure($circolo)
    {
        //$this->command->info("🔍 DEBUG - Struttura record circolo:");
        foreach ($circolo as $key => $value) {
            //$this->command->line("  {$key}: " . ($value ?? 'NULL'));
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
                    //$this->command->info("🎯 Club trovato via {$field}: {$club->name}");
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
                //$this->command->info("🎯 Usato TBA per zona {$zone->code}: {$tbaClub->name}");
                return $tbaClub->id;
            }
        }

        // STEP 4: Fallback finale - primo club disponibile
        $fallbackClub = DB::table('clubs')->first();

        if ($fallbackClub) {
            //$this->command->warn("⚠️ Fallback al primo club disponibile: {$fallbackClub->name}");
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
            //$this->command->info("🏗️ Creazione circoli TBA per {$zones->count()} zone");
        } catch (\Exception $e) {
            //$this->command->error("❌ Errore lettura zone per TBA: {$e->getMessage()}");
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
                    //$this->command->info("⏭️ TBA già esistente per zona {$zone->code}: {$existingTBA->name}");
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

        //$this->command->info("  → Creati {$createdCount} circoli TBA virtuali");
    }


    /**
     * OPTIONAL: Cleanup clubs duplicati prima della migrazione
     */
    private function cleanupDuplicateClubs()
    {
        if ($this->dryRun) {
            //$this->command->info("🧪 DRY-RUN: Cleanup clubs duplicati");
            return;
        }

        //$this->command->info("🧹 Cleanup clubs duplicati...");

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
                //$this->command->info("🗑️ Eliminato club duplicato: {$duplicate->name} (ID: {$duplicate->id})");
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
                //$this->command->info("🗑️ Eliminato club codice duplicato: {$duplicate->code} (ID: {$duplicate->id})");
                DB::table('clubs')->where('id', $duplicate->id)->delete();
            }
        }
    }
    /**
     * Migra tornei (gare_2025 → tournaments)
     */
    /**
     * Migra TUTTI i tornei mantenendo i campi CSV
     */
    private function migrateGare()
    {
        //$this->command->info('🏆 Migrazione tornei multi-anno...');

        $currentYear = date('Y');

        // MIGRA TUTTI GLI ANNI
        for ($year = 2015; $year <= $currentYear; $year++) {
            $sourceTable = "gare_{$year}";

            if (!$this->tableExists('real', $sourceTable)) {
                continue;
            }

            // Crea tournaments_YYYY
            $destTable = "tournaments_{$year}";

            // Copia struttura
            $columns = DB::connection('real')->select("SHOW CREATE TABLE {$sourceTable}")[0];
            $createStatement = $columns->{'Create Table'};
            $createStatement = str_replace($sourceTable, $destTable, $createStatement);
            DB::statement($createStatement);

            // Copia dati
            $data = DB::connection('real')->table($sourceTable)->get();
            foreach ($data as $row) {
                DB::table($destTable)->insert((array) $row);
            }

            //$this->command->info("✅ Anno {$year}: copiati {$data->count()} record in {$destTable}");


            // POPOLA ASSIGNMENTS E AVAILABILITIES PER OGNI ANNO!
            $this->populateAssignmentsFromYear($year);
        }

        // // Anno corrente
        $this->migrateCurrentYear($currentYear);
        // Popola anche tournaments principale per anno corrente
        // $this->populateMainTournaments($currentYear);
    }

    // In MasterMigrationSeeder.php aggiungi:

    public function migrateYearOnly($year)
    {
        // Mock del command
        $this->command = new class {
            public function __call($method, $args)
            {
                echo $args[0] ?? '';
                echo "\n";
            }
        };

        $this->setupRealDatabaseConnection();

        echo "\n🔄 Migrazione anno {$year}...\n";

        $sourceTable = "gare_{$year}";
        $destTable = "tournaments_{$year}";

        try {
            $count = DB::connection('real')->table($sourceTable)->count();
            echo "📊 Trovati {$count} record\n";

            // Crea tabella
            DB::statement("DROP TABLE IF EXISTS {$destTable}");

            $create = DB::connection('real')->select("SHOW CREATE TABLE {$sourceTable}")[0];
            $sql = $create->{'Create Table'};
            $sql = str_replace($sourceTable, $destTable, $sql);
            DB::statement($sql);

            echo "✅ Creata tabella {$destTable}\n";

            // IMPORTANTE: orderBy() PRIMA di chunk()!
            $copied = 0;
            DB::connection('real')
                ->table($sourceTable)
                ->orderBy('id')  // <-- QUESTO MANCAVA!
                ->chunk(50, function ($rows) use ($destTable, &$copied) {
                    foreach ($rows as $row) {
                        DB::table($destTable)->insert((array)$row);
                        $copied++;
                        if ($copied % 50 == 0) {
                            echo "  {$copied} record copiati...\r";
                        }
                    }
                });

            echo "\n✅ Completato: {$copied} record in {$destTable}\n";
        } catch (\Exception $e) {
            echo "❌ ERRORE: " . $e->getMessage() . "\n";
        }
    }

    // Aggiungi questo metodo al MasterMigrationSeeder:

    public function populateTournamentsMain()
    {
        $this->command = new class {
            public function __call($method, $args)
            {
                echo $args[0] ?? '';
                echo "\n";
            }
        };

        $currentYear = date('Y'); // 2025

        echo "\n📅 Popolamento tournaments principale da tournaments_{$currentYear}...\n";

        try {
            // Disabilita foreign keys per poter svuotare
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('tournaments')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // Leggi da tournaments_2025
            $tornei = DB::table("tournaments_{$currentYear}")->get();

            foreach ($tornei as $torneo) {
                DB::table('tournaments')->insert([
                    'id' => $torneo->id,
                    'name' => $torneo->Nome_gara ?? "Torneo #{$torneo->id}",
                    'start_date' => $torneo->StartTime,
                    'end_date' => $torneo->EndTime,
                    'availability_deadline' => $torneo->AvailabilityDeadline ?? null,
                    'club_id' => $this->resolveClubForTournament($torneo),
                    'zone_id' => $this->resolveZoneForTournament($torneo, null),
                    'tournament_type_id' => $this->resolveTournamentType($torneo),
                    'status' => 'open',
                    'notes' => $torneo->note ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $count = DB::table('tournaments')->count();
            echo "✅ Popolati {$count} tornei in tabella tournaments principale\n";
        } catch (\Exception $e) {
            echo "❌ ERRORE: " . $e->getMessage() . "\n";
        }
    }
    // Aggiungi anche questo metodo di debug:
    public function checkYear($year)
    {
        echo "\n📊 STATO ANNO {$year}:\n";

        // Controlla tournaments_YYYY
        $tournamentTable = "tournaments_{$year}";
        if (Schema::hasTable($tournamentTable)) {
            $count = DB::table($tournamentTable)->count();
            echo "✅ {$tournamentTable}: {$count} record\n";

            // Mostra primi 3 tornei
            $sample = DB::table($tournamentTable)->limit(3)->get(['id', 'Nome_gara', 'TD', 'Arbitri']);
            foreach ($sample as $t) {
                echo "   - #{$t->id} {$t->Nome_gara}\n";
                if ($t->TD) echo "     TD: {$t->TD}\n";
                if ($t->Arbitri) echo "     Arbitri: {$t->Arbitri}\n";
            }
        } else {
            echo "❌ Tabella {$tournamentTable} non esiste\n";
        }
    }
    private function populateMainTablesFromCurrentYear()
    {
        $currentYear = date('Y');

        // Popola tournaments da tournaments_2025
        // DB::statement('SET FOREIGN_KEY_CHECKS=0');
        // DB::table('tournaments')->truncate();
        // DB::statement("INSERT INTO tournaments SELECT * FROM tournaments_{$currentYear}");
        // DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Popola assignments da assignments_2025
        DB::table('assignments')->truncate();
        DB::statement("INSERT INTO assignments SELECT * FROM assignments_{$currentYear}");

        // Popola availabilities da availabilities_2025
        DB::table('availabilities')->truncate();
        DB::statement("INSERT INTO availabilities SELECT * FROM availabilities_{$currentYear}");

        //$this->command->info("✅ Copiate tabelle principali dall'anno {$currentYear}");
    }
    /**
     * Migra anno corrente in tournaments + popola assignments/availabilities
     */
    private function migrateCurrentYear($year)
    {
        //$this->command->info("📅 Migrazione anno corrente {$year}...");

        $sourceTable = "gare_{$year}";

        if (!$this->tableExists('real', $sourceTable)) {
            //$this->command->error("❌ Tabella {$sourceTable} non trovata");
            return;
        }

        // FIX: Disabilita foreign key checks per poter svuotare
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('tournaments')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $gare = DB::connection('real')->table($sourceTable)->get();

        foreach ($gare as $gara) {
            DB::table('tournaments')->insert([
                'id' => $gara->id,
                'name' => $gara->Nome_gara ?? "Torneo #{$gara->id}",
                'start_date' => $this->parseDate($gara->StartTime),
                'end_date' => $this->parseDate($gara->EndTime),
                'club_id' => $this->resolveClubForTournament($gara),
                'zone_id' => $this->resolveZoneForTournament($gara, null),
                'tournament_type_id' => $this->resolveTournamentType($gara),
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        //$this->command->info("✅ Inseriti {$gare->count()} tornei in tournaments");

        // STEP 2: Crea anche tournaments_2025 CON I CAMPI CSV
        $destTable = "tournaments_{$year}";

        // Copia struttura completa
        $columns = DB::connection('real')->select("SHOW CREATE TABLE {$sourceTable}")[0];
        $createStatement = $columns->{'Create Table'};
        $createStatement = str_replace($sourceTable, $destTable, $createStatement);
        DB::statement($createStatement);

        // Copia tutti i dati
        $data = DB::connection('real')->table($sourceTable)->get();
        foreach ($data as $row) {
            DB::table($destTable)->insert((array) $row);
        }

        //$this->command->info("✅ Creata anche {$destTable} con tutti i campi CSV");

        // STEP 3: Popola assignments_2025 e availabilities_2025
        $this->populateAssignmentsFromYear($year);
    }

    private function populateMainTournaments($year)
    {
        //$this->command->info("📅 Popolamento tournaments principale da tournaments_{$year}...");

        // Disabilita foreign keys
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('tournaments')->truncate();

        // Copia da tournaments_YYYY a tournaments (solo campi base)
        $tornei = DB::table("tournaments_{$year}")->get();

        foreach ($tornei as $torneo) {
            DB::table('tournaments')->insert([
                'id' => $torneo->id,
                'name' => $torneo->Nome_gara ?? "Torneo #{$torneo->id}",
                'start_date' => $torneo->StartTime,
                'end_date' => $torneo->EndTime,
                'club_id' => $this->resolveClubForTournament((object)$torneo),
                'zone_id' => $this->resolveZoneForTournament((object)$torneo, null),
                'tournament_type_id' => $this->resolveTournamentType((object)$torneo),
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        //$this->command->info("✅ Popolati " . $tornei->count() . " tornei in tabella tournaments principale");
    }
    private function createYearlyTables($year)
    {
        // Crea assignments_YYYY
        $assignTable = "assignments_{$year}";
        if (!Schema::hasTable($assignTable)) {
            Schema::create($assignTable, function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id');
                $table->foreignId('user_id');
                $table->foreignId('assigned_by_id');
                $table->string('role');
                $table->timestamp('assigned_at')->nullable();
                $table->timestamps();
                $table->unique(['tournament_id', 'user_id', 'role']);
            });
        }

        // Crea availabilities_YYYY
        $availTable = "availabilities_{$year}";
        if (!Schema::hasTable($availTable)) {
            Schema::create($availTable, function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id');
                $table->foreignId('tournament_id');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'tournament_id']);
            });
        }
    }
    /**
     * Popola assignments e availabilities dall'anno specificato
     */
    private function populateAssignmentsFromYear($year)
    {
        //$this->command->info("📋 Popolamento assignments_{$year} e availabilities_{$year}...");

        // Crea le tabelle se non esistono
        $this->createYearlyTables($year);

        // Pulisci tabelle anno
        DB::table("assignments_{$year}")->truncate();
        DB::table("availabilities_{$year}")->truncate();

        $tornei = DB::table("tournaments_{$year}")->get();

        foreach ($tornei as $torneo) {
            // DISPONIBILITÀ
            if (!empty($torneo->Disponibili)) {
                $nomi = explode(',', $torneo->Disponibili);
                foreach ($nomi as $nome) {
                    $userId = $this->findUserByFullName(trim($nome), $year); // PASSA L'ANNO!

                    if ($userId) {
                        DB::table("availabilities_{$year}")->insertOrIgnore([
                            'user_id' => $userId,
                            'tournament_id' => $torneo->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // ASSEGNAZIONI - PASSA L'ANNO!
            if (!empty($torneo->TD)) {
                $this->createAssignmentFromName($torneo->id, $torneo->TD, 'Direttore di Torneo', $year);
            }

            if (!empty($torneo->Arbitri)) {
                $arbitri = explode(',', $torneo->Arbitri);
                foreach ($arbitri as $arbitro) {
                    $this->createAssignmentFromName($torneo->id, trim($arbitro), 'Arbitro', $year);
                }
            }

            if (!empty($torneo->Osservatori)) {
                $osservatori = explode(',', $torneo->Osservatori);
                foreach ($osservatori as $osservatore) {
                    $this->createAssignmentFromName($torneo->id, trim($osservatore), 'Osservatore', $year);
                }
            }
        }

        $assignCount = DB::table("assignments_{$year}")->count();
        $availCount = DB::table("availabilities_{$year}")->count();

        $this->stats['assignments'] = ($this->stats['assignments'] ?? 0) + $assignCount;
        $this->stats['availabilities'] = ($this->stats['availabilities'] ?? 0) + $availCount;

        //$this->command->info("✅ Create {$assignCount} assignments e {$availCount} availabilities per anno {$year}");
    }

    /**
     * Helper: costruisce i dati del torneo
     */
    private function buildTournamentData($gara): array
    {
        $clubId = $this->resolveClubForTournament($gara);
        $tournamentTypeId = $this->resolveTournamentType($gara);
        $zoneId = $this->resolveZoneForTournament($gara, $clubId);

        return [
            'name' => $gara->Nome_gara ?? "Torneo #{$gara->id}",
            'description' => $gara->descrizione ?? null,
            'start_date' => $this->parseDate($gara->StartTime),
            'end_date' => $this->parseDate($gara->EndTime),
            'availability_deadline' => $this->calculateAvailabilityDeadline($gara->StartTime),
            'club_id' => $clubId,
            'zone_id' => $zoneId,
            'tournament_type_id' => $tournamentTypeId,
            'status' => $this->mapTournamentStatus($gara->stato ?? 'draft'),
            'notes' => $gara->note ?? null,
            'created_at' => $this->parseDate($gara->created_at ?? null) ?? now(),
            'updated_at' => now(),
        ];
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

            //$this->command->warn("⚠️ Impossibile parsare data: {$dateString}");
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
    private function findUserByFullName(string $fullName, ?int $year = null): ?int
    {
        if (empty($fullName)) {
            return null;
        }

        if ($this->dryRun) {
            return 999;
        }

        $cleanName = trim($fullName);

        // PRE-2021: Solo cognomi in zona SZR6
        if ($year && $year < 2021) {
            $szr6 = DB::table('zones')->where('code', 'SZR6')->first();

            if ($szr6) {
                $user = DB::table('users')
                    ->where('zone_id', $szr6->id)
                    ->where('user_type', 'referee')
                    ->where('name', 'LIKE', "% {$cleanName}")
                    ->first();

                if ($user) {
                    return $user->id; // RITORNA L'ID!
                }
            }
            return null;
        }

        // POST-2021: usa smartNameInversion ma RITORNA L'ID!
        $correctedName = $this->smartNameInversion($cleanName);

        // Cerca l'utente con il nome corretto
        $user = DB::table('users')
            ->where('name', $correctedName)
            ->where('user_type', 'referee')
            ->first();

        return $user ? $user->id : null; // RITORNA L'ID O NULL!
    }

    /**
     * Crea assegnazione da nome (per parsing CSV)
     */
    private function createAssignmentFromName(int $tournamentId, string $fullName, string $role, int $year): bool
    {
        $userId = $this->findUserByFullName($fullName, $year); // PASSA L'ANNO!

        if (!$userId) {
            return false;
        }

        $assignmentTable = "assignments_{$year}"; // USA TABELLA ANNO!

        if (!$this->dryRun) {
            $existing = DB::table($assignmentTable)
                ->where('tournament_id', $tournamentId)
                ->where('user_id', $userId)
                ->where('role', $role)
                ->first();

            if ($existing) {
                return true;
            }
        }

        $success = $this->dryRunInsert(
            $assignmentTable, // USA TABELLA ANNO!
            [
                'tournament_id' => $tournamentId,
                'user_id' => $userId,
                'assigned_by_id' => 1,
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
        if (isset($gara->Tipo) && $gara->Tipo) {
            $type = DB::table('tournament_types')
                ->where('short_name', 'LIKE', "%{$gara->Tipo}%")
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
            //$this->command->info("🧪 DRY-RUN: Inversione nome '{$cleanName}'");
            return $cleanName;
        }

        // STEP 1: Prova il nome così com'è (potrebbe essere già corretto)
        $directMatch = DB::table('users')
            ->where('name', $cleanName)
            ->where('user_type', 'referee')
            ->first();

        if ($directMatch) {
            // //$this->command->info("✅ Match diretto: '{$cleanName}' (ID: {$directMatch->id})");
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
                // //$this->command->info("🔄 Inversione riuscita: '{$cleanName}' → '{$invertedName}' (ID: {$invertedMatch->id})");
                return $invertedName;
            }
        }

        // STEP 3: Prova match parziale (fallback)
        $partialMatch = DB::table('users')
            ->where('name', 'LIKE', "%{$cleanName}%")
            ->where('user_type', 'referee')
            ->first();

        if ($partialMatch) {
            //$this->command->info("🔍 Match parziale: '{$cleanName}' → '{$partialMatch->name}' (ID: {$partialMatch->id})");
            return $partialMatch->name;
        }

        // STEP 4: Nessun match trovato
        //$this->command->warn("⚠️ Nessun match per: '{$cleanName}' (né diretto, né invertito, né parziale)");
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
                    // //$this->command->info("🎯 Strategia " . ($index + 1) . " riuscita: '{$originalName}' → '{$candidate}'");
                    return $candidate;
                }
            }
        }

        // Se nessuna strategia funziona, usa la prima (più probabile)
        // //$this->command->info("🔄 Inversione multipla (strategia 1): '{$originalName}' → '{$strategy1}'");
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
            //$this->command->info("🧪 DRY-RUN: " . ($description ?: "Insert in {$table}") . " su tabella '{$table}'");
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
            //$this->command->info("🧪 DRY-RUN: " . ($description ?: "UpdateOrInsert in {$table}") . " su tabella '{$table}'");
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
            //$this->command->info("🧪 DRY-RUN: " . ($description ?: "InsertGetId in {$table}") . " su tabella '{$table}'");
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
            'admin_users' => 0,        // ✅ AGGIUNTO
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
        //$this->command->info("\n📊 STATISTICHE MIGRAZIONE MASTER:");
        //$this->command->line("┌─────────────────────────────────────┐");
        //$this->command->line("│             MIGRAZIONE              │");
        //$this->command->line("├─────────────────────────────────────┤");
        //$this->command->line("│ Zone:                    {$this->formatStat($this->stats['zones'])} │");
        //$this->command->line("│ Admin Users:             {$this->formatStat($this->stats['admin_users'])} │");  // ✅ AGGIUNTO
        //$this->command->line("│ Tournament Types:        {$this->formatStat($this->stats['tournament_types'])} │");
        //$this->command->line("│ Arbitri:                 {$this->formatStat($this->stats['arbitri'])} │");
        //$this->command->line("│ Circoli:                 {$this->formatStat($this->stats['circoli'])} │");
        //$this->command->line("│ Tornei:                  {$this->formatStat($this->stats['tornei'])} │");
        //$this->command->line("│ Disponibilità:           {$this->formatStat($this->stats['disponibilita'])} │");
        //$this->command->line("│ Assegnazioni:            {$this->formatStat($this->stats['assegnazioni'])} │");
        //$this->command->line("├─────────────────────────────────────┤");
        //$this->command->line("│             PULIZIA                 │");
        //$this->command->line("├─────────────────────────────────────┤");
        //$this->command->line("│ Conflitti email risolti: {$this->formatStat($this->stats['conflitti_risolti'])} │");
        //$this->command->line("│ Record GIOV saltati:     {$this->formatStat($this->stats['record_giov_saltati'])} │");
        //$this->command->line("└─────────────────────────────────────┘");
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
            //$this->command->info('🔌 Connessione database Sql1466239_4 chiusa');
        } catch (\Exception $e) {
            //$this->command->warn('⚠️ Errore chiusura connessione: ' . $e->getMessage());
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


    // AGGIUNGI questo metodo per creare tabelle anno
    private function createTournamentTableForYear($year)
    {
        $tableName = "tournaments_{$year}";

        if (!Schema::hasTable($tableName)) {
            // COPIA STRUTTURA ESATTA da gare_YYYY con TUTTI i campi!
            DB::statement("CREATE TABLE {$tableName} LIKE gare_{$year}");

            //$this->command->info("✅ Creata tabella {$tableName} con TUTTI i campi CSV");
        }
    }


    /**
     * FASE 1: Crea tournaments_yyyy con struttura standard
     */
    public function createStandardTournamentsForAllYears()
    {
        $this->setupMockCommand();
        $this->setupRealDatabaseConnection();

        echo "\n🏗️ FASE 1: Creazione tournaments_yyyy con struttura standard\n";

        for ($year = 2015; $year <= date('Y'); $year++) {
            $this->createStandardTournamentForYear($year);
        }
    }

    private function createStandardTournamentForYear($year)
    {
        $sourceTable = "gare_{$year}";
        $destTable = "tournaments_{$year}";
        $tempTable = "gare_{$year}"; // Tabella temporanea locale

        echo "\n📅 Anno {$year}:\n";

        // Verifica esistenza tabella sorgente
        if (!$this->tableExistsInRemote($sourceTable)) {
            echo "  ⏭️ Tabella {$sourceTable} non trovata in Sql1466239_4, skip\n";
            return;
        }

        try {
            // STEP 1: Crea tournaments_yyyy con struttura standard
            DB::statement("DROP TABLE IF EXISTS {$destTable}");

            // Crea con la STESSA struttura di tournaments
            DB::statement("CREATE TABLE {$destTable} LIKE tournaments");
            echo "  ✅ Creata tabella {$destTable} con struttura standard\n";

            // STEP 2: Crea tabella temporanea gare_yyyy locale con solo campi necessari
            DB::statement("DROP TABLE IF EXISTS {$tempTable}");
            DB::statement("CREATE TABLE {$tempTable} (
            id INT PRIMARY KEY,
            TD VARCHAR(255),
            Arbitri TEXT,
            Osservatori TEXT,
            Zona VARCHAR(50),
            Nome_gara VARCHAR(255),
            StartTime DATE,
            EndTime DATE,
            AvailabilityDeadline DATE,
            Circolo INT,
            zona_id INT,
            tipo INT,
            Disponibili TEXT
        )");
            echo "  ✅ Creata tabella temporanea {$tempTable}\n";

            // STEP 3: Copia dati da remoto a locale (solo campi necessari)
            $count = $this->copyDataFromRemote($sourceTable, $tempTable, $year);
            echo "  ✅ Copiati {$count} record da Sql1466239_4.{$sourceTable} a {$tempTable}\n";

            // STEP 4: Popola tournaments_yyyy trasformando i dati
            $this->populateStandardTournaments($tempTable, $destTable, $year);
        } catch (\Exception $e) {
            echo "  ❌ ERRORE Anno {$year}: " . $e->getMessage() . "\n";
            echo "     File: " . $e->getFile() . " Linea: " . $e->getLine() . "\n";
        }
    }

    private function copyDataFromRemote($sourceTable, $destTable, $year)
    {
        $copied = 0;

        DB::connection('real')
            ->table($sourceTable)
            ->orderBy('id')
            ->chunk(100, function ($records) use ($destTable, &$copied, $year) {
                foreach ($records as $record) {
                    // Estrai solo i campi necessari
                    DB::table($destTable)->insert([
                        'id' => $record->id,
                        'TD' => $record->TD ?? null,
                        'Arbitri' => $record->Arbitri ?? null,
                        'Osservatori' => $record->Osservatori ?? null,
                        'Zona' => $record->Zona ?? null,
                        'Nome_gara' => $record->Nome_gara ?? "Torneo #{$record->id}",
                        'StartTime' => $record->StartTime,
                        'EndTime' => $record->EndTime,
                        'AvailabilityDeadline' => $record->AvailabilityDeadline ?? null,
                        'Circolo' => $record->Circolo ?? null,
                        'zona_id' => $this->resolveZoneId($record->Zona ?? null),
                        'tipo' => $record->tipo ?? 1,
                        'note' => $record->note ?? null,
                        'Disponibili' => $record->Disponibili ?? null
                    ]);
                    $copied++;
                }

                if ($copied % 100 == 0) {
                    echo "    ... {$copied} record\r";
                }
            });

        return $copied;
    }
    private function populateStandardTournaments($sourceTable, $destTable, $year)
    {
        $tornei = DB::table($sourceTable)->get();
        $inserted = 0;

        foreach ($tornei as $torneo) {
            // Risolvi foreign keys
            $clubId = $this->resolveClubForTournament($torneo);
            $zoneId = $torneo->zona_id ?? $this->resolveZoneForTournament($torneo, $clubId);
            $typeId = $this->resolveTournamentType($torneo);

            DB::table($destTable)->insert([
                'id' => $torneo->id,
                'name' => $torneo->Nome_gara,
                'description' => null,
                'start_date' => $torneo->StartTime,
                'end_date' => $torneo->EndTime,
                'availability_deadline' => $this->calculateDeadline($torneo->StartTime),
                'club_id' => $clubId,
                'zone_id' => $zoneId,
                'tournament_type_id' => $typeId,
                'status' => 'completed', // Tornei passati sono completati
                'notes' => $torneo->note,
                'created_at' => $torneo->StartTime . ' 00:00:00',
                'updated_at' => now(),
            ]);
            $inserted++;
        }

        echo "  ✅ Inseriti {$inserted} record in {$destTable}\n";
    }
    /**
     * FASE 1: Copia gare_yyyy da remoto a locale con solo campi necessari
     */
    public function copyGareTablesFromRemote()
    {
        $this->setupMockCommand();
        $this->setupRealDatabaseConnection();

        echo "\n🏗️ FASE 1: Copia tabelle gare_yyyy da Sql1466239_4 a locale\n";

        for ($year = 2015; $year <= date('Y'); $year++) {
            $this->copyGareTableForYear($year);
        }
    }

    private function copyGareTableForYear($year)
    {
        $sourceTable = "gare_{$year}";
        $destTable = "gare_{$year}"; // Tabella locale temporanea

        echo "\n📅 Anno {$year}:\n";

        if (!$this->tableExistsInRemote($sourceTable)) {
            echo "  ⏭️ Tabella {$sourceTable} non trovata in Sql1466239_4, skip\n";
            return;
        }

        try {
            $totalCount = DB::connection('real')->table($sourceTable)->count();
            echo "  📊 Trovati {$totalCount} record in Sql1466239_4.{$sourceTable}\n";

            // STEP 1: Crea tabella locale con campi STRINGA/TEXT per mantenere dati originali
            DB::statement("DROP TABLE IF EXISTS {$destTable}");
            DB::statement("CREATE TABLE {$destTable} (
            id INT PRIMARY KEY,
            TD VARCHAR(255),
            Arbitri TEXT,
            Osservatori TEXT,
            Disponibili TEXT,
            Zona VARCHAR(50),
            Nome_gara VARCHAR(255),
            StartTime DATE,
            EndTime DATE,
            Circolo VARCHAR(255),  -- STRINGA non INT!
            tipo VARCHAR(50)       -- STRINGA non INT!
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            echo "  ✅ Creata tabella locale {$destTable}\n";

            // STEP 2: Copia i dati ESATTAMENTE come sono
            $copied = 0;
            DB::connection('real')
                ->table($sourceTable)
                ->select([
                    'id',
                    'TD',
                    'Arbitri',
                    'Osservatori',
                    'Disponibili',
                    'Zona',
                    'Nome_gara',
                    'StartTime',
                    'EndTime',
                    'Circolo',
                    'tipo'
                ])
                ->orderBy('id')
                ->chunk(100, function ($records) use ($destTable, &$copied) {
                    foreach ($records as $record) {
                        // COPIA ESATTA SENZA RISOLVERE NULLA!
                        DB::table($destTable)->insert([
                            'id' => $record->id,
                            'TD' => $record->TD,
                            'Arbitri' => $record->Arbitri,
                            'Osservatori' => $record->Osservatori,
                            'Disponibili' => $record->Disponibili,
                            'Zona' => $record->Zona,           // COPIA ESATTA!
                            'Nome_gara' => $record->Nome_gara,
                            'StartTime' => $record->StartTime,
                            'EndTime' => $record->EndTime,
                            'Circolo' => $record->Circolo,     // COPIA ESATTA!
                            'tipo' => $record->tipo            // COPIA ESATTA!
                        ]);
                        $copied++;
                    }

                    if ($copied % 100 == 0) {
                        echo "    Copiati {$copied} record...\r";
                    }
                });

            echo "\n  ✅ Copiati {$copied} record in locale {$destTable}\n";
        } catch (\Exception $e) {
            echo "  ❌ ERRORE: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Correggi createTournamentFromGare per risolvere i valori
     */
    private function createTournamentFromGare($year)
    {
        $sourceTable = "gare_{$year}"; // Locale
        $destTable = "tournaments_{$year}";

        echo "\n📅 Anno {$year}:\n";

        if (!Schema::hasTable($sourceTable)) {
            echo "  ⏭️ Tabella locale {$sourceTable} non trovata, skip\n";
            return;
        }

        try {
            DB::statement("DROP TABLE IF EXISTS {$destTable}");
            DB::statement("CREATE TABLE {$destTable} LIKE tournaments");
            echo "  ✅ Creata {$destTable} con struttura standard\n";

            $gare = DB::table($sourceTable)->get();
            $inserted = 0;

            foreach ($gare as $gara) {
                // USA I TUOI METODI ESISTENTI!
                $clubId = $this->resolveClubForTournament($gara);
                $zoneId = $this->resolveZoneForTournament($gara, null);
                $typeId = $this->resolveTournamentType($gara);

                DB::table($destTable)->insert([
                    'id' => $gara->id,
                    'name' => $gara->Nome_gara ?? "Torneo #{$gara->id}",
                    'start_date' => $gara->StartTime,
                    'end_date' => $gara->EndTime,
                    'availability_deadline' => $this->calculateAvailabilityDeadline($gara->StartTime),
                    'club_id' => $clubId,
                    'zone_id' => $zoneId,
                    'tournament_type_id' => $typeId,
                    'status' => $year < date('Y') ? 'completed' : 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            echo "  ✅ Inseriti {$inserted} record in {$destTable}\n";
        } catch (\Exception $e) {
            echo "  ❌ ERRORE: " . $e->getMessage() . "\n";
            echo "     Dettagli: " . $e->getTraceAsString() . "\n";
        }
    }

    /**
     * FASE 2: Crea tournaments_yyyy dalle tabelle gare_yyyy locali
     */
    public function createTournamentsFromLocalGare()
    {
        echo "\n🏗️ FASE 2: Creazione tournaments_yyyy da gare_yyyy locali\n";

        for ($year = 2015; $year <= date('Y'); $year++) {
            $this->createTournamentFromGare($year);
        }
    }


    /**
     * Setup mock command per evitare errori quando chiamato da tinker
     */
    private function setupMockCommand()
    {
        if (!isset($this->command)) {
            $this->command = new class {
                public function info($msg)
                {
                    echo "ℹ️ {$msg}\n";
                }
                public function error($msg)
                {
                    echo "❌ {$msg}\n";
                }
                public function warn($msg)
                {
                    echo "⚠️ {$msg}\n";
                }
                public function line($msg)
                {
                    echo "{$msg}\n";
                }
                public function confirm($msg, $default = false)
                {
                    return $default;
                }
                public function __call($method, $args)
                {
                    echo $args[0] ?? '';
                    echo "\n";
                }
            };
        }
    }

    /**
     * Verifica se tabella esiste nel database remoto
     */
    private function tableExistsInRemote($tableName)
    {
        try {
            $tables = DB::connection('real')->select('SHOW TABLES');
            foreach ($tables as $table) {
                $name = array_values((array)$table)[0];
                if ($name === $tableName) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Helper per risolvere zona da stringa (es. "SZR6" -> 6)
     */
    private function resolveZoneFromString($zonaString)
    {

        if (empty($zonaString)) return null;

        // Estrai numero da SZR6, SZR1, etc
        if (preg_match('/SZR(\d+)/i', $zonaString, $matches)) {
            $zoneCode = 'SZR' . $matches[1];
            $zone = DB::table('zones')->where('code', $zoneCode)->first();
            return $zone ? $zone->id : null;
        }

        return null;
    }
    /**
     * Risolve club da record gara
     */
    private function resolveClubForGara($gara)
    {
        if (empty($gara->Circolo)) {
            return 1; // Fallback al primo club
        }

        // Se è un ID numerico
        if (is_numeric($gara->Circolo)) {
            $club = DB::table('clubs')->find($gara->Circolo);
            if ($club) return $club->id;
        }

        // Cerca per nome
        $club = DB::table('clubs')
            ->where('name', 'LIKE', "%{$gara->Circolo}%")
            ->first();

        return $club ? $club->id : 1;
    }

    /**
     * Risolve tournament type ID
     */
    private function resolveTournamentTypeId($tipo)
    {
        if (empty($tipo)) return 1;

        if (is_numeric($tipo)) {
            $type = DB::table('tournament_types')->find($tipo);
            if ($type) return $type->id;
        }

        return 1; // Default
    }
    /**
     * Risolve club dal nome stringa
     */
    private function resolveClubFromName($clubName)
    {
        if (empty($clubName)) {
            return 1; // Fallback
        }

        // Cerca per nome esatto
        $club = DB::table('clubs')
            ->where('name', $clubName)
            ->first();

        if ($club) return $club->id;

        // Cerca per nome simile
        $club = DB::table('clubs')
            ->where('name', 'LIKE', "%{$clubName}%")
            ->first();

        if ($club) return $club->id;

        echo "    ⚠️ Club non trovato: '{$clubName}', uso default\n";
        return 1; // Default
    }

    /**
     * Risolve tournament type dalla stringa
     */
    private function resolveTournamentTypeFromString($tipoString)
    {
        if (empty($tipoString)) {
            return 1; // Default
        }

        // Cerca per codice esatto
        $type = DB::table('tournament_types')
            ->where('short_name', $tipoString)
            ->first();

        if ($type) return $type->id;

        // Cerca per nome
        $type = DB::table('tournament_types')
            ->where('name', 'LIKE', "%{$tipoString}%")
            ->first();

        if ($type) return $type->id;

        echo "    ⚠️ Tipo torneo non trovato: '{$tipoString}', uso default\n";
        return 1; // Default
    }

    /**
     * Calcola deadline disponibilità (7 giorni prima)
     */
    private function calculateDeadline($startDate)
    {
        if (empty($startDate)) return null;

        try {
            $date = \Carbon\Carbon::parse($startDate);
            return $date->subDays(7)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
