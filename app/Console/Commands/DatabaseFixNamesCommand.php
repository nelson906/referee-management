<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

class DatabaseFixNamesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:fix-names 
                          {--analyze : Solo analizza senza modificare} 
                          {--backup : Crea backup prima delle modifiche}
                          {--execute : Esegue le modifiche}
                          {--table=users : Tabella da processare}
                          {--column=name : Colonna da processare}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Investigazione database e correzione nomi da Cognome-Nome a Nome Cognome';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== INVESTIGAZIONE DATABASE E CORREZIONE NOMI ===');
        $this->line('');

        // Investigazione problemi export
        $this->investigateDatabaseIssues();

        $table = $this->option('table');
        $column = $this->option('column');

        if ($this->option('analyze')) {
            $this->analyzeNames($table, $column);
        }

        if ($this->option('backup')) {
            $this->createBackup($table, $column);
        }

        if ($this->option('execute')) {
            if ($this->confirm('ATTENZIONE: Stai per modificare i dati nel database. Hai fatto un backup?')) {
                $this->fixNames($table, $column);
            }
        }

        if (!$this->option('analyze') && !$this->option('backup') && !$this->option('execute')) {
            $this->showHelp();
        }
    }

    /**
     * Investigazione problemi database per export
     */
    private function investigateDatabaseIssues()
    {
        $this->info('1. INVESTIGAZIONE PROBLEMI DATABASE');
        $this->line('=====================================');

        try {
            // Informazioni generali database
            $dbName = DB::connection()->getDatabaseName();
            $this->line("Database corrente: {$dbName}");

            // Dimensione database
            $size = DB::select("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                    COUNT(*) AS table_count
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");

            if (!empty($size)) {
                $this->line("Dimensione database: {$size[0]->size_mb} MB");
                $this->line("Numero tabelle: {$size[0]->table_count}");
            }

            // Tabelle più grandi
            $this->info("\nTabelle più grandi (possibili cause di timeout export):");
            $largeTables = DB::select("
                SELECT 
                    TABLE_NAME,
                    ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                    table_rows
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
                LIMIT 5
            ");

            foreach ($largeTables as $table) {
                $this->line("- {$table->TABLE_NAME}: {$table->size_mb} MB ({$table->table_rows} righe)");
            }

            // Configurazione MySQL critica
            $this->info("\nConfigurazione MySQL per export:");
            $config = DB::select("
                SHOW VARIABLES WHERE Variable_name IN (
                    'max_allowed_packet', 
                    'wait_timeout', 
                    'interactive_timeout'
                )
            ");

            foreach ($config as $var) {
                $this->line("- {$var->Variable_name}: {$var->Value}");
            }

            // Processi attivi
            $processes = DB::select("SHOW PROCESSLIST");
            $activeProcesses = count($processes);
            $this->line("Processi MySQL attivi: {$activeProcesses}");

        } catch (\Exception $e) {
            $this->error("Errore durante l'investigazione: " . $e->getMessage());
        }

        $this->line('');
    }

    /**
     * Analizza i nomi nella tabella
     */
    private function analyzeNames($table, $column)
    {
        $this->info("2. ANALISI NOMI - Tabella: {$table}, Colonna: {$column}");
        $this->line('=================================================');

        try {
            if (!Schema::hasTable($table)) {
                $this->error("Tabella '{$table}' non trovata!");
                return;
            }

            if (!Schema::hasColumn($table, $column)) {
                $this->error("Colonna '{$column}' non trovata nella tabella '{$table}'!");
                return;
            }

            // Conteggi generali
            $stats = DB::table($table)
                ->selectRaw("
                    COUNT(*) as totale,
                    SUM(CASE WHEN {$column} LIKE '%-%' AND {$column} NOT LIKE '% %' THEN 1 ELSE 0 END) as da_correggere,
                    SUM(CASE WHEN {$column} LIKE '% %' THEN 1 ELSE 0 END) as con_spazio,
                    SUM(CASE WHEN {$column} IS NULL OR {$column} = '' THEN 1 ELSE 0 END) as vuoti
                ")
                ->whereNotNull($column)
                ->first();

            $this->line("Totale record: {$stats->totale}");
            $this->line("Da correggere (Cognome-Nome): {$stats->da_correggere}");
            $this->line("Già corretti (con spazio): {$stats->con_spazio}");
            $this->line("Vuoti o null: {$stats->vuoti}");

            // Esempi di nomi da correggere
            if ($stats->da_correggere > 0) {
                $this->info("\nEsempi di nomi da correggere:");
                
                $examples = DB::table($table)
                    ->select('id', $column)
                    ->selectRaw("
                        CASE 
                            WHEN {$column} LIKE '%-%' AND {$column} NOT LIKE '% %' THEN 
                                CONCAT(
                                    TRIM(SUBSTRING_INDEX({$column}, '-', -1)), 
                                    ' ', 
                                    TRIM(SUBSTRING_INDEX({$column}, '-', 1))
                                )
                            ELSE {$column}
                        END AS nome_corretto
                    ")
                    ->whereRaw("{$column} LIKE '%-%' AND {$column} NOT LIKE '% %'")
                    ->limit(10)
                    ->get();

                foreach ($examples as $example) {
                    $original = $example->$column;
                    $corrected = $example->nome_corretto;
                    $this->line("ID {$example->id}: '{$original}' -> '{$corrected}'");
                }
            }

        } catch (\Exception $e) {
            $this->error("Errore durante l'analisi: " . $e->getMessage());
        }

        $this->line('');
    }

    /**
     * Crea backup della tabella
     */
    private function createBackup($table, $column)
    {
        $this->info("3. CREAZIONE BACKUP");
        $this->line('===================');

        try {
            $backupTable = "{$table}_backup_names_" . date('Y_m_d_H_i_s');
            
            DB::statement("
                CREATE TABLE {$backupTable} AS 
                SELECT id, {$column}, created_at, updated_at 
                FROM {$table}
                WHERE {$column} IS NOT NULL
            ");

            $count = DB::table($backupTable)->count();
            $this->info("✓ Backup creato: {$backupTable} ({$count} record)");

        } catch (\Exception $e) {
            $this->error("Errore durante il backup: " . $e->getMessage());
        }

        $this->line('');
    }

    /**
     * Esegue la correzione dei nomi
     */
    private function fixNames($table, $column)
    {
        $this->info("4. CORREZIONE NOMI");
        $this->line('===================');

        try {
            // Conta quanti record verranno modificati
            $toUpdate = DB::table($table)
                ->whereRaw("{$column} LIKE '%-%' AND {$column} NOT LIKE '% %'")
                ->whereRaw("LENGTH(TRIM(SUBSTRING_INDEX({$column}, '-', -1))) > 0")
                ->whereRaw("LENGTH(TRIM(SUBSTRING_INDEX({$column}, '-', 1))) > 0")
                ->count();

            if ($toUpdate == 0) {
                $this->info("Nessun record da correggere.");
                return;
            }

            $this->line("Record da aggiornare: {$toUpdate}");

            // Esegui l'aggiornamento
            $updated = DB::table($table)
                ->whereRaw("{$column} LIKE '%-%' AND {$column} NOT LIKE '% %'")
                ->whereRaw("LENGTH(TRIM(SUBSTRING_INDEX({$column}, '-', -1))) > 0")
                ->whereRaw("LENGTH(TRIM(SUBSTRING_INDEX({$column}, '-', 1))) > 0")
                ->update([
                    $column => DB::raw("
                        CONCAT(
                            TRIM(SUBSTRING_INDEX({$column}, '-', -1)), 
                            ' ', 
                            TRIM(SUBSTRING_INDEX({$column}, '-', 1))
                        )
                    "),
                    'updated_at' => now()
                ]);

            $this->info("✓ Record aggiornati: {$updated}");

            // Verifica finale
            $remaining = DB::table($table)
                ->whereRaw("{$column} LIKE '%-%' AND {$column} NOT LIKE '% %'")
                ->count();

            if ($remaining == 0) {
                $this->info("✓ Tutti i nomi sono stati corretti!");
            } else {
                $this->warn("⚠ Rimangono {$remaining} nomi da correggere (potrebbero avere formati diversi)");
            }

        } catch (\Exception $e) {
            $this->error("Errore durante la correzione: " . $e->getMessage());
        }
    }

    /**
     * Mostra l'aiuto per il comando
     */
    private function showHelp()
    {
        $this->info('UTILIZZO DEL COMANDO:');
        $this->line('');
        $this->line('1. Analizzare i nomi senza modificare:');
        $this->line('   php artisan db:fix-names --analyze');
        $this->line('');
        $this->line('2. Creare backup:');
        $this->line('   php artisan db:fix-names --backup');
        $this->line('');
        $this->line('3. Correggere i nomi (dopo backup!):');
        $this->line('   php artisan db:fix-names --execute');
        $this->line('');
        $this->line('4. Processo completo:');
        $this->line('   php artisan db:fix-names --analyze --backup --execute');
        $this->line('');
        $this->line('OPZIONI AVANZATE:');
        $this->line('--table=nome_tabella  (default: users)');
        $this->line('--column=nome_colonna (default: name)');
        $this->line('');
        $this->warn('ATTENZIONE: Fare sempre backup prima di modificare dati!');
    }
}
