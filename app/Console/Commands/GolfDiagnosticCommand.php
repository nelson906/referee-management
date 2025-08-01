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

/**
 * Trova disponibilità duplicate
 */
private function findDuplicateAvailabilities(): int
{
    // ✅ FIXED: Usa 'user_id' invece di 'referee_id'
    return DB::table('availabilities')
        ->select('tournament_id', 'user_id')  // ← Cambiato da 'referee_id' a 'user_id'
        ->groupBy('tournament_id', 'user_id')  // ← Cambiato da 'referee_id' a 'user_id'
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
                // ✅ FIXED: Gestisce tutti i tipi di valore inclusi array annidati
                $formattedValue = $this->formatValue($value);
                $table[] = [ucfirst($category) . ' - ' . ucfirst($key), $formattedValue];
            }
        }
    }
    return $table;
}

/**
 * ✅ NEW: Helper method per formattare i valori correttamente
 */
private function formatValue($value): string
{
    if (is_array($value)) {
        // Se è un array, converti in stringa leggibile
        if (empty($value)) {
            return '0';
        }

        // Se l'array ha chiavi numeriche consecutive, è una lista
        if (array_keys($value) === range(0, count($value) - 1)) {
            return implode(', ', array_map([$this, 'formatValue'], $value));
        }

        // Se è un array associativo, mostra il conteggio o i primi valori
        $items = [];
        $count = 0;
        foreach ($value as $k => $v) {
            if ($count >= 3) { // Limita a 3 elementi per non sovraccaricare
                $items[] = '...';
                break;
            }
            $items[] = $k . ': ' . $this->formatValue($v);
            $count++;
        }
        return implode(', ', $items);
    }

    if (is_bool($value)) {
        return $value ? 'Sì' : 'No';
    }

    if (is_numeric($value)) {
        return (string) $value;
    }

    if (is_object($value)) {
        return method_exists($value, '__toString') ? (string) $value : get_class($value);
    }

    return (string) $value;
}

}


