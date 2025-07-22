<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Zone;
use App\Models\User;
use App\Models\Club;
use App\Models\Tournament;
use Database\Seeders\Helpers\SeederHelper;
use Carbon\Carbon;

class GolfMigrationHelper extends Command
{
    protected $signature = 'golf:migrate-data
                            {--source= : Database di origine}
                            {--mapping= : File mapping campi}
                            {--dry-run : Simula senza scrivere}
                            {--backup : Crea backup prima di migrare}
                            {--chunk=100 : Dimensione chunk per processing}';

    protected $description = 'Migra dati esistenti nel nuovo sistema Golf seeded';

    private array $migrationLog = [];
    private array $fieldMapping = [];
    private int $totalRecords = 0;
    private int $migratedRecords = 0;
    private int $errorRecords = 0;

    public function handle(): int
    {
        $this->info('🔄 GOLF DATA MIGRATION HELPER');
        $this->info('============================');

        if (!$this->validateEnvironment()) {
            return 1;
        }

        $this->loadFieldMapping();

        if ($this->option('backup')) {
            $this->createBackup();
        }

        // Analisi dati esistenti
        $this->analyzeExistingData();

        // Conferma migrazione
        if (!$this->option('dry-run') && !$this->confirm('Procedere con la migrazione?')) {
            $this->info('Migrazione annullata');
            return 0;
        }

        // Esecuzione migrazione
        $this->executeMigration();

        // Report finale
        $this->showMigrationReport();

        return 0;
    }

    private function validateEnvironment(): bool
    {
        $this->info('🔍 Validando ambiente...');

        // Verifica sistema seeded
        if (Zone::count() === 0) {
            $this->error('Sistema non ancora seeded. Eseguire prima: php artisan golf:seed');
            return false;
        }

        // Verifica tabelle esistenti
        $requiredTables = ['zones', 'users', 'clubs', 'tournaments'];
        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("Tabella mancante: {$table}");
                return false;
            }
        }

        $this->info('✅ Ambiente validato');
        return true;
    }

    private function loadFieldMapping(): void
    {
        $mappingFile = $this->option('mapping') ?: 'config/golf/field-mapping.php';

        if (file_exists($mappingFile)) {
            $this->fieldMapping = include $mappingFile;
            $this->info("📋 Mapping caricato da: {$mappingFile}");
        } else {
            $this->fieldMapping = $this->getDefaultFieldMapping();
            $this->info('📋 Utilizzando mapping predefinito');
        }
    }

    private function getDefaultFieldMapping(): array
    {
        return [
            'users' => [
                'old_id' => 'id',
                'nome_completo' => 'name',
                'email_address' => 'email',
                'telefono_cellulare' => 'phone',
                'citta_residenza' => 'city',
                'livello_arbitro' => 'level',
                'codice_arbitrale' => 'referee_code',
                'attivo' => 'is_active',
                'zona_appartenenza' => 'zone_mapping'
            ],
            'clubs' => [
                'old_id' => 'id',
                'denominazione' => 'name',
                'codice_club' => 'code',
                'comune' => 'city',
                'provincia' => 'province',
                'indirizzo_completo' => 'address',
                'email_contatto' => 'email',
                'telefono_contatto' => 'phone',
                'zona_riferimento' => 'zone_mapping'
            ],
            'tournaments' => [
                'old_id' => 'id',
                'nome_torneo' => 'name',
                'descrizione_evento' => 'description',
                'data_inizio' => 'start_date',
                'data_fine' => 'end_date',
                'club_ospitante' => 'club_mapping',
                'tipologia' => 'tournament_type_mapping',
                'stato_torneo' => 'status_mapping'
            ]
        ];
    }

    private function createBackup(): void
    {
        $this->info('💾 Creando backup...');

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $backupFile = "golf_backup_before_migration_{$timestamp}.sql";

        $command = sprintf(
            'mysqldump -h %s -u %s -p%s %s > storage/app/golf-backups/%s',
            config('database.connections.mysql.host'),
            config('database.connections.mysql.username'),
            config('database.connections.mysql.password'),
            config('database.connections.mysql.database'),
            $backupFile
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            $this->info("✅ Backup creato: {$backupFile}");
        } else {
            $this->warn("⚠️ Backup fallito, continuando comunque...");
        }
    }

    private function analyzeExistingData(): void
    {
        $this->info('📊 Analizzando dati esistenti...');

        $analysis = [
            'users' => $this->analyzeTable('old_users'),
            'clubs' => $this->analyzeTable('old_clubs'),
            'tournaments' => $this->analyzeTable('old_tournaments')
        ];

        $this->table(
            ['Tabella', 'Record', 'Campi', 'Problemi'],
            array_map(function($table, $data) {
                return [
                    $table,
                    $data['count'] ?? 0,
                    $data['fields'] ?? 0,
                    $data['issues'] ?? 0
                ];
            }, array_keys($analysis), $analysis)
        );
    }

    private function analyzeTable(string $tableName): array
    {
        if (!Schema::hasTable($tableName)) {
            return ['count' => 0, 'fields' => 0, 'issues' => 1];
        }

        $count = DB::table($tableName)->count();
        $fields = count(Schema::getColumnListing($tableName));

        // Analisi problemi comuni
        $issues = 0;
        $issues += DB::table($tableName)->whereNull('email')->count(); // Email nulle
        $issues += DB::table($tableName)->where('email', '')->count(); // Email vuote

        return [
            'count' => $count,
            'fields' => $fields,
            'issues' => $issues
        ];
    }

    private function executeMigration(): void
    {
        $this->info('🚀 Avviando migrazione...');

        // Migrazione in ordine di dipendenza
        $this->migrateUsers();
        $this->migrateClubs();
        $this->migrateTournaments();
        $this->migrateRelatedData();

        if (!$this->option('dry-run')) {
            $this->updateSequences();
            $this->validateMigratedData();
        }
    }

    private function migrateUsers(): void
    {
        $this->info('👥 Migrando utenti...');

        if (!Schema::hasTable('old_users')) {
            $this->warn('Tabella old_users non trovata, saltando...');
            return;
        }

        $chunkSize = $this->option('chunk');
        $bar = $this->output->createProgressBar(DB::table('old_users')->count());

        DB::table('old_users')->orderBy('id')->chunk($chunkSize, function($users) use ($bar) {
            foreach ($users as $oldUser) {
                try {
                    $this->migrateUser($oldUser);
                    $this->migratedRecords++;
                } catch (\Exception $e) {
                    $this->errorRecords++;
                    $this->migrationLog[] = [
                        'type' => 'error',
                        'table' => 'users',
                        'old_id' => $oldUser->id,
                        'error' => $e->getMessage()
                    ];
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migrateUser($oldUser): void
    {
        // Mappa zona
        $zone = $this->mapZone($oldUser->zona_appartenenza ?? '');

        // Determina tipo utente
        $userType = $this->determineUserType($oldUser);

        // Pulisci e valida dati
        $userData = [
            'name' => $this->cleanString($oldUser->nome_completo),
            'email' => $this->cleanEmail($oldUser->email_address),
            'phone' => $this->cleanPhone($oldUser->telefono_cellulare ?? ''),
            'city' => $this->cleanString($oldUser->citta_residenza ?? ''),
            'user_type' => $userType,
            'zone_id' => $zone ? $zone->id : null,
            'is_active' => $this->parseBoolean($oldUser->attivo ?? true),
            'level' => $this->mapRefereeLevel($oldUser->livello_arbitro ?? ''),
            'referee_code' => $oldUser->codice_arbitrale ?? null,
            'password' => SeederHelper::getTestPassword(),
            'email_verified_at' => now(),
            'created_at' => $this->parseDate($oldUser->created_at ?? now()),
            'updated_at' => now(),
            'migrated_from_old_id' => $oldUser->id
        ];

        if (!$this->option('dry-run')) {
            // Controlla duplicati
            if (User::where('email', $userData['email'])->exists()) {
                $userData['email'] = $this->generateUniqueEmail($userData['email']);
            }

            User::create($userData);
        }

        $this->migrationLog[] = [
            'type' => 'success',
            'table' => 'users',
            'old_id' => $oldUser->id,
            'new_data' => $userData
        ];
    }

    private function migrateClubs(): void
    {
        $this->info('⛳ Migrando circoli...');

        if (!Schema::hasTable('old_clubs')) {
            $this->warn('Tabella old_clubs non trovata, saltando...');
            return;
        }

        $chunkSize = $this->option('chunk');
        $bar = $this->output->createProgressBar(DB::table('old_clubs')->count());

        DB::table('old_clubs')->orderBy('id')->chunk($chunkSize, function($clubs) use ($bar) {
            foreach ($clubs as $oldClub) {
                try {
                    $this->migrateClub($oldClub);
                    $this->migratedRecords++;
                } catch (\Exception $e) {
                    $this->errorRecords++;
                    $this->migrationLog[] = [
                        'type' => 'error',
                        'table' => 'clubs',
                        'old_id' => $oldClub->id,
                        'error' => $e->getMessage()
                    ];
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    private function migrateClub($oldClub): void
    {
        $zone = $this->mapZone($oldClub->zona_riferimento ?? '');

        $clubData = [
            'name' => $this->cleanString($oldClub->denominazione),
            'code' => $oldClub->codice_club ?? $this->generateClubCode($zone),
            'city' => $this->cleanString($oldClub->comune ?? ''),
            'province' => $this->cleanString($oldClub->provincia ?? ''),
            'address' => $this->cleanString($oldClub->indirizzo_completo ?? ''),
            'email' => $this->cleanEmail($oldClub->email_contatto ?? ''),
            'phone' => $this->cleanPhone($oldClub->telefono_contatto ?? ''),
            'zone_id' => $zone ? $zone->id : null,
            'is_active' => $this->parseBoolean($oldClub->attivo ?? true),
            'created_at' => $this->parseDate($oldClub->created_at ?? now()),
            'updated_at' => now(),
            'migrated_from_old_id' => $oldClub->id
        ];

        if (!$this->option('dry-run')) {
            Club::create($clubData);
        }
    }

    private function migrateTournaments(): void
    {
        $this->info('🏆 Migrando tornei...');

        if (!Schema::hasTable('old_tournaments')) {
            $this->warn('Tabella old_tournaments non trovata, saltando...');
            return;
        }

        // Implementation similar to clubs and users...
    }

    private function migrateRelatedData(): void
    {
        $this->info('🔗 Migrando dati correlati...');

        // Migra disponibilità, assegnazioni, notifiche se esistono
        // Implementation...
    }

    // Helper methods
    private function mapZone(string $oldZoneIdentifier): ?Zone
    {
        $zoneMapping = [
            'piemonte' => 'SZR1',
            'lombardia' => 'SZR2',
            'veneto' => 'SZR3',
            'emilia' => 'SZR4',
            'toscana' => 'SZR5',
            'lazio' => 'SZR6',
            'sud' => 'SZR7',
            // Aggiungi altri mapping...
        ];

        $normalized = strtolower(trim($oldZoneIdentifier));
        $zoneCode = $zoneMapping[$normalized] ?? null;

        return $zoneCode ? Zone::where('code', $zoneCode)->first() : null;
    }

    private function determineUserType($oldUser): string
    {
        // Logica per determinare tipo utente da dati vecchi
        if (isset($oldUser->is_admin) && $oldUser->is_admin) {
            return 'admin';
        }

        if (isset($oldUser->livello_arbitro) && !empty($oldUser->livello_arbitro)) {
            return 'referee';
        }

        return 'referee'; // Default
    }

    private function mapRefereeLevel(string $oldLevel): string
    {
        $levelMapping = [
            'asp' => 'aspirante',
            'aspirante' => 'aspirante',
            '1°' => 'primo_livello',
            'primo' => 'primo_livello',
            'reg' => 'regionale',
            'regionale' => 'regionale',
            'naz' => 'nazionale',
            'nazionale' => 'nazionale',
            'int' => 'internazionale',
            'internazionale' => 'internazionale',
        ];

        $normalized = strtolower(trim($oldLevel));
        return $levelMapping[$normalized] ?? 'aspirante';
    }

    private function cleanString(?string $value): string
    {
        return trim($value ?? '');
    }

    private function cleanEmail(?string $email): string
    {
        $cleaned = strtolower(trim($email ?? ''));
        return filter_var($cleaned, FILTER_VALIDATE_EMAIL) ? $cleaned : '';
    }

    private function cleanPhone(?string $phone): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone ?? '');
        return $cleaned;
    }

    private function parseBoolean($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return (bool) $value;

        $value = strtolower(trim($value));
        return in_array($value, ['true', 'yes', 'si', '1', 'attivo']);
    }

    private function parseDate($value): Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return now();
        }
    }

    private function generateUniqueEmail(string $baseEmail): string
    {
        $counter = 1;
        $originalEmail = $baseEmail;

        while (User::where('email', $baseEmail)->exists()) {
            $parts = explode('@', $originalEmail);
            $baseEmail = $parts[0] . $counter . '@' . $parts[1];
            $counter++;
        }

        return $baseEmail;
    }

    private function generateClubCode(?Zone $zone): string
    {
        $prefix = $zone ? $zone->code : 'UNK';
        $sequence = Club::where('zone_id', $zone?->id)->count() + 1;

        return $prefix . '-CLB-' . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    private function updateSequences(): void
    {
        $this->info('🔄 Aggiornando sequenze...');

        // Reset auto-increment per evitare conflitti
        $tables = ['users', 'clubs', 'tournaments'];

        foreach ($tables as $table) {
            $maxId = DB::table($table)->max('id') ?? 0;
            DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = " . ($maxId + 1));
        }
    }

    private function validateMigratedData(): void
    {
        $this->info('✅ Validando dati migrati...');

        $validations = [
            'Utenti con email valide' => User::whereNotNull('email')->where('email', '!=', '')->count(),
            'Circoli con zone valide' => Club::whereNotNull('zone_id')->count(),
            'Tornei con club validi' => Tournament::whereNotNull('club_id')->count(),
        ];

        foreach ($validations as $description => $count) {
            $this->info("  {$description}: {$count}");
        }
    }

    private function showMigrationReport(): void
    {
        $this->info('');
        $this->info('📊 REPORT MIGRAZIONE');
        $this->info('===================');

        $this->table(['Metrica', 'Valore'], [
            ['Record totali analizzati', $this->totalRecords],
            ['Record migrati con successo', $this->migratedRecords],
            ['Record con errori', $this->errorRecords],
            ['Percentuale successo', $this->totalRecords > 0 ? round(($this->migratedRecords / $this->totalRecords) * 100, 2) . '%' : '0%']
        ]);

        // Mostra errori se presenti
        $errors = array_filter($this->migrationLog, fn($log) => $log['type'] === 'error');

        if (!empty($errors)) {
            $this->warn('⚠️ Errori durante migrazione:');
            foreach (array_slice($errors, 0, 10) as $error) {
                $this->warn("  {$error['table']} ID {$error['old_id']}: {$error['error']}");
            }

            if (count($errors) > 10) {
                $this->warn("  ... e altri " . (count($errors) - 10) . " errori");
            }
        }

        // Salva log completo
        $logFile = 'golf_migration_' . Carbon::now()->format('Y-m-d_H-i-s') . '.json';
        file_put_contents(storage_path("app/golf-exports/{$logFile}"), json_encode($this->migrationLog, JSON_PRETTY_PRINT));

        $this->info("📁 Log completo salvato in: {$logFile}");
    }
}
