<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;

use Illuminate\Console\Command;
use Database\Seeders\MasterMigrationSeeder;

/**
 * Comando personalizzato per eseguire MasterMigrationSeeder con opzioni avanzate
 */
class MasterMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'golf:master-migration
                            {--dry-run : Esegue solo simulazione senza modificare il database}
                            {--force : Forza esecuzione senza conferme}
                            {--stats-only : Mostra solo statistiche senza migrazione}';

    /**
     * The console command description.
     */
    protected $description = 'Esegue la migrazione unificata dal database reale Sql1466239_4';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Master Migration Seeder - Migrazione Unificata');
        $this->info('══════════════════════════════════════════════════');

        // Modalità stats-only
        if ($this->option('stats-only')) {
            $this->showDatabaseStats();
            return;
        }

        // Conferma esecuzione (skip se --force)
        if (!$this->option('force') && !$this->option('dry-run')) {
            if (!$this->confirm('Questa operazione modificherà il database. Continuare?')) {
                $this->warn('Operazione annullata.');
                return;
            }
        }

        // Imposta modalità dry-run se richiesta
        if ($this->option('dry-run')) {
            putenv('MIGRATION_DRY_RUN=true');
            $this->info('🧪 MODALITÀ DRY-RUN ATTIVATA');
        }

        // Esegui il seeder
        try {
            $seeder = new MasterMigrationSeeder();
            $seeder->setCommand($this);
            $seeder->run();

            if ($this->option('dry-run')) {
                $this->info('✅ Simulazione completata con successo!');
                $this->info('💡 Per eseguire realmente: php artisan golf:master-migration');
            } else {
                $this->info('✅ Migrazione completata con successo!');
            }

        } catch (\Exception $e) {
            $this->error('❌ Errore durante la migrazione: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        } finally {
            // Pulisci variabile ambiente
            putenv('MIGRATION_DRY_RUN');
        }

        return 0;
    }

    /**
     * Mostra statistiche del database senza eseguire migrazione
     */
    private function showDatabaseStats()
    {
        $this->info('📊 STATISTICHE DATABASE Sql1466239_4');
        $this->info('═══════════════════════════════════════');

        try {
            // Setup connessione temporanea
            config(['database.connections.stats' => [
                'driver' => 'mysql',
                'host' => config('database.connections.mysql.host'),
                'port' => config('database.connections.mysql.port'),
                'database' => 'Sql1466239_4',
                'username' => config('database.connections.mysql.username'),
                'password' => config('database.connections.mysql.password'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]]);

            // Conta record nelle tabelle principali
            $tables = [
                'arbitri' => 'Arbitri totali',
                'circoli' => 'Circoli totali',
                'gare_2025' => 'Tornei 2025',
            ];

            foreach ($tables as $table => $description) {
                try {
                    $count = \DB::connection('stats')->table($table)->count();
                    $this->line("📋 {$description}: {$count}");
                } catch (\Exception $e) {
                    $this->warn("⚠️  Tabella '{$table}' non accessibile");
                }
            }

            // Statistiche specifiche
            $this->info("\n🔍 DETTAGLI ARBITRI:");
            try {
                $levels = \DB::connection('stats')
                    ->table('arbitri')
                    ->selectRaw('Livello_2025, COUNT(*) as count')
                    ->groupBy('Livello_2025')
                    ->get();

                foreach ($levels as $level) {
                    $this->line("   {$level->Livello_2025}: {$level->count}");
                }
            } catch (\Exception $e) {
                $this->warn("⚠️  Impossibile analizzare livelli arbitri");
            }

            $this->info("\n🔍 DETTAGLI TORNEI:");
            try {
                $withDisponibilita = \DB::connection('stats')
                    ->table('gare_2025')
                    ->whereNotNull('Disponibilità')
                    ->where('Disponibilità', '!=', '')
                    ->count();

                $withAssignments = \DB::connection('stats')
                    ->table('gare_2025')
                    ->where(function($query) {
                        $query->whereNotNull('TD')
                              ->orWhereNotNull('Arbitri')
                              ->orWhereNotNull('Osservatori');
                    })
                    ->count();

                $this->line("   Con disponibilità CSV: {$withDisponibilita}");
                $this->line("   Con assegnazioni CSV: {$withAssignments}");

            } catch (\Exception $e) {
                $this->warn("⚠️  Impossibile analizzare tornei");
            }

        } catch (\Exception $e) {
            $this->error('❌ Impossibile connettersi al database Sql1466239_4');
            $this->error('Errore: ' . $e->getMessage());
            return;
        } finally {
            try {
                \DB::disconnect('stats');
            } catch (\Exception $ignored) {}
        }

        $this->info("\n💡 Per eseguire migrazione:");
        $this->info("   DRY-RUN: php artisan golf:master-migration --dry-run");
        $this->info("   REALE:   php artisan golf:master-migration");
    }
}
