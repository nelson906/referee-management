<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Zone;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use App\Models\Availability;
use Carbon\Carbon;

/**
 * Comando per diagnostica del sistema Golf
 */
class GolfDiagnosticCommand extends Command
{
    protected $signature = 'golf:diagnostic
                            {--zone= : Analizza solo una zona specifica}
                            {--detailed : Mostra analisi dettagliata}
                            {--export : Esporta risultati in file}';

    protected $description = 'Esegue diagnostica completa del sistema Golf';

    public function handle(): int
    {
        $this->info('🔍 DIAGNOSTICA SISTEMA GOLF');
        $this->info('============================');

        $zone = $this->option('zone');
        $detailed = $this->option('detailed');
        $export = $this->option('export');

        $diagnostics = [];

        // Analisi generale
        $diagnostics['general'] = $this->analyzeGeneral();

        // Analisi per zona
        if ($zone) {
            $diagnostics['zone'] = $this->analyzeZone($zone);
        } else {
            $diagnostics['zones'] = $this->analyzeAllZones($detailed);
        }

        // Analisi workflow
        $diagnostics['workflow'] = $this->analyzeWorkflow();

        // Analisi performance
        $diagnostics['performance'] = $this->analyzePerformance();

        // Analisi integrità dati
        $diagnostics['integrity'] = $this->analyzeDataIntegrity();

        // Mostra risultati
        $this->displayDiagnostics($diagnostics, $detailed);

        // Export se richiesto
        if ($export) {
            $this->exportDiagnostics($diagnostics);
        }

        return 0;
    }

    private function analyzeGeneral(): array
    {
        $this->info('📊 Analisi Generale...');

        return [
            'zones_count' => Zone::count(),
            'total_users' => User::count(),
            'active_referees' => User::where('user_type', 'referee')->where('is_active', true)->count(),
            'total_tournaments' => Tournament::count(),
            'active_tournaments' => Tournament::whereIn('status', ['open', 'closed', 'assigned'])->count(),
            'total_availabilities' => Availability::count(),
            'total_assignments' => Assignment::count(),
            'database_size_mb' => $this->getDatabaseSize(),
        ];
    }

    private function analyzeZone(string $zoneCode): array
    {
        $zone = Zone::where('code', $zoneCode)->first();

        if (!$zone) {
            $this->error("Zona {$zoneCode} non trovata");
            return [];
        }

        $this->info("📍 Analisi Zona {$zoneCode}...");

        return [
            'zone_name' => $zone->name,
            'clubs_count' => $zone->clubs()->count(),
            'referees_count' => $zone->users()->where('user_type', 'referee')->count(),
            'tournaments_count' => Tournament::where('zone_id', $zone->id)->count(),
            'open_tournaments' => Tournament::where('zone_id', $zone->id)->where('status', 'open')->count(),
            'availabilities_count' => Availability::whereHas('tournament', function($q) use ($zone) {
                $q->where('zone_id', $zone->id);
            })->count(),
            'assignments_count' => Assignment::whereHas('tournament', function($q) use ($zone) {
                $q->where('zone_id', $zone->id);
            })->count(),
        ];
    }

    private function analyzeAllZones(bool $detailed): array
    {
        $this->info('🌍 Analisi Tutte le Zone...');

        $zones = Zone::all();
        $analysis = [];

        foreach ($zones as $zone) {
            $analysis[$zone->code] = [
                'name' => $zone->name,
                'clubs' => $zone->clubs()->count(),
                'referees' => $zone->users()->where('user_type', 'referee')->count(),
                'tournaments' => Tournament::where('zone_id', $zone->id)->count(),
            ];

            if ($detailed) {
                $analysis[$zone->code]['detailed'] = $this->analyzeZone($zone->code);
            }
        }

        return $analysis;
    }

    private function analyzeWorkflow(): array
    {
        $this->info('🔄 Analisi Workflow...');

        return [
            'pending_availabilities' => Tournament::where('status', 'open')
                ->where('availability_deadline', '>', now())
                ->count(),
            'tournaments_without_assignments' => Tournament::where('status', 'closed')
                ->doesntHave('assignments')
                ->count(),
            'unconfirmed_assignments' => Assignment::where('is_confirmed', false)->count(),
            'overdue_tournaments' => Tournament::where('status', 'open')
                ->where('availability_deadline', '<', now())
                ->count(),
            'assignment_confirmation_rate' => $this->calculateConfirmationRate(),
            'average_assignments_per_tournament' => $this->calculateAverageAssignments(),
        ];
    }

    private function analyzePerformance(): array
    {
        $this->info('⚡ Analisi Performance...');

        $startTime = microtime(true);
        DB::table('tournaments')->join('zones', 'tournaments.zone_id', '=', 'zones.id')->count();
        $joinQueryTime = microtime(true) - $startTime;

        $startTime = microtime(true);
        Tournament::with('zone')->limit(100)->get();
        $eagerLoadTime = microtime(true) - $startTime;

        return [
            'join_query_time' => round($joinQueryTime, 4),
            'eager_load_time' => round($eagerLoadTime, 4),
            'avg_query_time' => $this->getAverageQueryTime(),
            'slow_queries_count' => $this->getSlowQueriesCount(),
        ];
    }

    private function analyzeDataIntegrity(): array
    {
        $this->info('🔒 Analisi Integrità Dati...');

        return [
            'orphaned_clubs' => DB::table('clubs')
                ->leftJoin('zones', 'clubs.zone_id', '=', 'zones.id')
                ->whereNull('zones.id')
                ->count(),
            'orphaned_tournaments' => DB::table('tournaments')
                ->leftJoin('clubs', 'tournaments.club_id', '=', 'clubs.id')
                ->whereNull('clubs.id')
                ->count(),
            'invalid_assignments' => Assignment::whereDoesntHave('tournament')->count(),
            'duplicate_availabilities' => $this->findDuplicateAvailabilities(),
            'email_duplicates' => $this->findEmailDuplicates(),
            'invalid_referee_codes' => $this->findInvalidRefereeCodes(),
        ];
    }

    private function displayDiagnostics(array $diagnostics, bool $detailed): void
    {
        // Display implementation here
        $this->info('📈 RISULTATI DIAGNOSTICA');
        $this->table(['Metrica', 'Valore'], $this->formatDiagnosticsForTable($diagnostics));
    }

    private function exportDiagnostics(array $diagnostics): void
    {
        $filename = 'golf_diagnostics_' . Carbon::now()->format('Y-m-d_H-i-s') . '.json';
        Storage::put($filename, json_encode($diagnostics, JSON_PRETTY_PRINT));
        $this->info("📁 Diagnostica esportata in: {$filename}");
    }

    // Helper methods
    private function getDatabaseSize(): float
    {
        $size = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'size' FROM information_schema.tables WHERE table_schema = ?", [config('database.connections.mysql.database')]);
        return $size[0]->size ?? 0;
    }

    private function calculateConfirmationRate(): float
    {
        $total = Assignment::count();
        $confirmed = Assignment::where('is_confirmed', true)->count();
        return $total > 0 ? round(($confirmed / $total) * 100, 2) : 0;
    }

    private function calculateAverageAssignments(): float
    {
        return Assignment::selectRaw('COUNT(*) / COUNT(DISTINCT tournament_id) as avg')
            ->value('avg') ?? 0;
    }

    private function getAverageQueryTime(): float
    {
        // Simulate query time analysis
        return 0.05; // placeholder
    }

    private function getSlowQueriesCount(): int
    {
        // Would analyze slow query log
        return 0; // placeholder
    }

    private function findDuplicateAvailabilities(): int
    {
        return DB::table('availabilities')
            ->select('tournament_id', 'referee_id')
            ->groupBy('tournament_id', 'referee_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
    }

    private function findEmailDuplicates(): int
    {
        return DB::table('users')
            ->select('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->count();
    }

    private function findInvalidRefereeCodes(): int
    {
        return User::where('user_type', 'referee')
            ->where(function($query) {
                $query->whereNull('referee_code')
                      ->orWhere('referee_code', '');
            })
            ->count();
    }

    private function formatDiagnosticsForTable(array $diagnostics): array
    {
        $table = [];
        foreach ($diagnostics as $category => $data) {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $table[] = [ucfirst($category) . ' - ' . ucfirst($key), is_numeric($value) ? $value : (is_bool($value) ? ($value ? 'Sì' : 'No') : $value)];
                }
            }
        }
        return $table;
    }
}

/**
 * Comando per export dati Golf
 */
class GolfExportCommand extends Command
{
    protected $signature = 'golf:export
                            {type : Tipo di export (zones, users, tournaments, all)}
                            {--zone= : Esporta solo una zona specifica}
                            {--format=json : Formato export (json, csv, excel)}
                            {--output= : File di output personalizzato}';

    protected $description = 'Esporta dati del sistema Golf in vari formati';

    public function handle(): int
    {
        $type = $this->argument('type');
        $zone = $this->option('zone');
        $format = $this->option('format');
        $output = $this->option('output');

        $this->info("📤 Export {$type} in formato {$format}...");

        try {
            $data = $this->collectData($type, $zone);
            $filename = $this->exportData($data, $type, $format, $output);

            $this->info("✅ Export completato: {$filename}");
            $this->info("📊 Record esportati: " . count($data));

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Errore durante export: " . $e->getMessage());
            return 1;
        }
    }

    private function collectData(string $type, ?string $zone): array
    {
        $zoneId = $zone ? Zone::where('code', $zone)->value('id') : null;

        return match($type) {
            'zones' => Zone::all()->toArray(),
            'users' => $this->getUsersData($zoneId),
            'tournaments' => $this->getTournamentsData($zoneId),
            'all' => $this->getAllData($zoneId),
            default => throw new \InvalidArgumentException("Tipo export non supportato: {$type}")
        };
    }

    private function getUsersData(?int $zoneId): array
    {
        $query = User::with('zone');
        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }
        return $query->get()->toArray();
    }

    private function getTournamentsData(?int $zoneId): array
    {
        $query = Tournament::with(['zone', 'club', 'tournamentType']);
        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }
        return $query->get()->toArray();
    }

    private function getAllData(?int $zoneId): array
    {
        return [
            'zones' => Zone::all()->toArray(),
            'users' => $this->getUsersData($zoneId),
            'tournaments' => $this->getTournamentsData($zoneId),
            'availabilities' => Availability::with('tournament', 'referee')->get()->toArray(),
            'assignments' => Assignment::with('tournament', 'referee')->get()->toArray(),
        ];
    }

    private function exportData(array $data, string $type, string $format, ?string $output): string
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = $output ?: "golf_export_{$type}_{$timestamp}.{$format}";

        switch ($format) {
            case 'json':
                Storage::put($filename, json_encode($data, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->exportToCsv($data, $filename);
                break;
            case 'excel':
                $this->exportToExcel($data, $filename);
                break;
            default:
                throw new \InvalidArgumentException("Formato non supportato: {$format}");
        }

        return $filename;
    }

    private function exportToCsv(array $data, string $filename): void
    {
        // CSV export implementation
        $csv = fopen('php://temp', 'w+');

        if (!empty($data)) {
            $headers = array_keys($data[0]);
            fputcsv($csv, $headers);

            foreach ($data as $row) {
                fputcsv($csv, $row);
            }
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        Storage::put($filename, $content);
    }

    private function exportToExcel(array $data, string $filename): void
    {
        // Excel export would require additional packages like PhpSpreadsheet
        throw new \Exception("Excel export non ancora implementato");
    }
}

/**
 * Comando per manutenzione sistema Golf
 */
class GolfMaintenanceCommand extends Command
{
    protected $signature = 'golf:maintenance
                            {action : Azione (cleanup, optimize, repair, status)}
                            {--dry-run : Simula senza eseguire}
                            {--force : Forza esecuzione senza conferma}';

    protected $description = 'Strumenti di manutenzione per il sistema Golf';

    public function handle(): int
    {
        $action = $this->argument('action');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("🔧 MANUTENZIONE SISTEMA GOLF - {$action}");
        $this->info('=======================================');

        if ($dryRun) {
            $this->warn('🚨 MODALITÀ DRY-RUN - Nessuna modifica verrà effettuata');
        }

        try {
            return match($action) {
                'cleanup' => $this->performCleanup($dryRun, $force),
                'optimize' => $this->performOptimization($dryRun, $force),
                'repair' => $this->performRepair($dryRun, $force),
                'status' => $this->showMaintenanceStatus(),
                default => $this->showMaintenanceHelp()
            };
        } catch (\Exception $e) {
            $this->error("❌ Errore durante manutenzione: " . $e->getMessage());
            return 1;
        }
    }

    private function performCleanup(bool $dryRun, bool $force): int
    {
        $this->info('🧹 Pulizia dati obsoleti...');

        $actions = [
            'Notifiche scadute' => function() use ($dryRun) {
                $count = 0;
                if (class_exists('\App\Models\Notification')) {
                    $query = \App\Models\Notification::where('read_at', '<', now()->subDays(30));
                    $count = $query->count();
                    if (!$dryRun) $query->delete();
                }
                return $count;
            },
            'Sessioni scadute' => function() use ($dryRun) {
                $count = DB::table('sessions')->where('last_activity', '<', now()->subDays(7))->count();
                if (!$dryRun) DB::table('sessions')->where('last_activity', '<', now()->subDays(7))->delete();
                return $count;
            },
            'Log obsoleti' => function() use ($dryRun) {
                $count = 0;
                if (Schema::hasTable('activity_log')) {
                    $count = DB::table('activity_log')->where('created_at', '<', now()->subDays(90))->count();
                    if (!$dryRun) DB::table('activity_log')->where('created_at', '<', now()->subDays(90))->delete();
                }
                return $count;
            }
        ];

        foreach ($actions as $description => $action) {
            $count = $action();
            $status = $dryRun ? "🔍 Trovati" : "✅ Rimossi";
            $this->info("{$status} {$count} record: {$description}");
        }

        return 0;
    }

    private function performOptimization(bool $dryRun, bool $force): int
    {
        $this->info('⚡ Ottimizzazione database...');

        if (!$dryRun) {
            // Ottimizza tabelle
            $tables = ['zones', 'users', 'tournaments', 'assignments', 'availabilities'];
            foreach ($tables as $table) {
                DB::statement("OPTIMIZE TABLE {$table}");
                $this->info("✅ Ottimizzata tabella: {$table}");
            }

            // Aggiorna statistiche
            DB::statement('ANALYZE TABLE zones, users, tournaments, assignments, availabilities');
            $this->info('✅ Statistiche database aggiornate');
        } else {
            $this->info('🔍 Verifica tabelle da ottimizzare...');
        }

        return 0;
    }

    private function performRepair(bool $dryRun, bool $force): int
    {
        $this->info('🔨 Riparazione inconsistenze...');

        $repairs = [
            'Codici arbitro mancanti' => function() use ($dryRun) {
                $referees = User::where('user_type', 'referee')
                    ->whereNull('referee_code')
                    ->get();

                foreach ($referees as $referee) {
                    if (!$dryRun && $referee->zone) {
                        $referee->referee_code = $referee->zone->code . '-REF-' . str_pad($referee->id, 3, '0', STR_PAD_LEFT);
                        $referee->save();
                    }
                }

                return $referees->count();
            },
            'Disponibilità orfane' => function() use ($dryRun) {
                $orphaned = Availability::whereDoesntHave('tournament')->count();
                if (!$dryRun) {
                    Availability::whereDoesntHave('tournament')->delete();
                }
                return $orphaned;
            }
        ];

        foreach ($repairs as $description => $repair) {
            $count = $repair();
            $status = $dryRun ? "🔍 Trovati" : "🔨 Riparati";
            $this->info("{$status} {$count} problemi: {$description}");
        }

        return 0;
    }

    private function showMaintenanceStatus(): int
    {
        $this->info('📊 Status Manutenzione Sistema');

        $stats = [
            ['Tabelle DB', DB::select("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ?", [config('database.connections.mysql.database')])[0]->count],
            ['Dimensione DB (MB)', $this->getDatabaseSize()],
            ['Utenti totali', User::count()],
            ['Tornei attivi', Tournament::whereIn('status', ['open', 'closed', 'assigned'])->count()],
            ['Disponibilità pending', Availability::whereHas('tournament', function($q) { $q->where('status', 'open'); })->count()],
            ['Assegnazioni non confermate', Assignment::where('is_confirmed', false)->count()],
        ];

        $this->table(['Metrica', 'Valore'], $stats);
        return 0;
    }

    private function showMaintenanceHelp(): int
    {
        $this->info('🔧 COMANDI MANUTENZIONE DISPONIBILI:');
        $this->info('');
        $this->info('cleanup  - Rimuove dati obsoleti (notifiche, sessioni, log)');
        $this->info('optimize - Ottimizza tabelle database e aggiorna statistiche');
        $this->info('repair   - Ripara inconsistenze dati (codici mancanti, relazioni rotte)');
        $this->info('status   - Mostra stato attuale del sistema');
        $this->info('');
        $this->info('Opzioni:');
        $this->info('--dry-run  Simula senza effettuare modifiche');
        $this->info('--force    Salta conferme interattive');

        return 0;
    }

    private function getDatabaseSize(): float
    {
        $size = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'size' FROM information_schema.tables WHERE table_schema = ?", [config('database.connections.mysql.database')]);
        return $size[0]->size ?? 0;
    }
}
