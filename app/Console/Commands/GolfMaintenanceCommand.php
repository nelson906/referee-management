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

