<?php
// app/Console/Commands/FixedDebugDatabaseAnalysisCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixedDebugDatabaseAnalysisCommand extends Command
{
    protected $signature = 'golf:debug-analysis {old_db_name}';
    protected $description = 'Analizza il database originale per identificare dati mancanti e strategie di recupero';

    private $oldDb;

    public function handle()
    {
        $this->oldDb = $this->argument('old_db_name');

        $this->info("🔍 ANALISI DEBUG DATABASE: {$this->oldDb}");
        $this->info(str_repeat("=", 60));

        try {
            $this->setupConnection();
            $this->analyzeTableStructure();
            $this->analyzeDataCounts();
            $this->analyzeRelationships();
            $this->identifyMissingData();
            $this->recommendStrategy();

        } catch (\Exception $e) {
            $this->error("❌ ERRORE: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function setupConnection()
    {
        config(['database.connections.old_db' => [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => $this->oldDb,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        DB::connection('old_db')->getPdo();
        $this->info("✅ Connessione stabilita con {$this->oldDb}");
    }

    private function analyzeTableStructure()
    {
        $this->info("\n📋 ANALISI STRUTTURA TABELLE");
        $this->info(str_repeat("-", 40));

        $tables = DB::connection('old_db')->select("SHOW TABLES");
        $tableNames = array_map(function($table) {
            return array_values((array)$table)[0];
        }, $tables);

        $expectedTables = ['arbitri', 'circoli', 'gare_2025', 'users', 'referees', 'availabilities', 'assignments'];

        foreach ($expectedTables as $table) {
            $exists = in_array($table, $tableNames);
            $status = $exists ? "✅" : "❌";
            $this->info("{$status} {$table}");

            if ($exists) {
                $this->analyzeTableDetails($table);
            }
        }

        $this->info("\n📊 TABELLE EXTRA TROVATE:");
        $extraTables = array_diff($tableNames, $expectedTables);
        foreach ($extraTables as $table) {
            $this->info("🔍 {$table}");
        }
    }

    private function analyzeTableDetails($tableName)
    {
        try {
            // Conta record
            $count = DB::connection('old_db')->table($tableName)->count();

            // Ottieni struttura
            $columns = DB::connection('old_db')->select("DESCRIBE {$tableName}");
            $columnCount = count($columns);

            $this->info("    📊 Record: {$count} | Colonne: {$columnCount}");

            // Per tabelle chiave, mostra sample data
            if (in_array($tableName, ['arbitri', 'users', 'referees']) && $count > 0) {
                $sample = DB::connection('old_db')->table($tableName)->first();
                $this->info("    🔍 Sample ID: " . ($sample->id ?? 'N/A'));

                // Mostra colonne chiave
                $keyColumns = $this->getKeyColumns($tableName);
                foreach ($keyColumns as $col) {
                    if (isset($sample->$col)) {
                        $value = is_string($sample->$col) ? substr($sample->$col, 0, 30) : $sample->$col;
                        $this->info("      {$col}: {$value}");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn("    ⚠️ Errore nell'analisi: " . $e->getMessage());
        }
    }

    private function getKeyColumns($tableName)
    {
        switch($tableName) {
            case 'arbitri':
                return ['id', 'Nome', 'Cognome', 'Email', 'Livello_2025'];
            case 'users':
                return ['id', 'name', 'email', 'user_type'];
            case 'referees':
                return ['id', 'user_id'];
            case 'circoli':
                return ['Id', 'Circolo_Nome'];
            case 'gare_2025':
                return ['id', 'Nome_gare'];
            default:
                return ['id'];
        }
    }

    private function analyzeDataCounts()
    {
        $this->info("\n📈 CONTEGGI DATI vs ATTESI");
        $this->info(str_repeat("-", 40));

        $expectedCounts = [
            'users' => 505,
            'referees' => 496,
            'clubs' => 319,
            'tournaments' => 248,
            'assignments' => 17,
            'availabilities' => 903
        ];

        $actualCounts = [];
        $tableMapping = [
            'users' => 'users',
            'referees' => 'referees',
            'clubs' => 'circoli',
            'tournaments' => 'gare_2025',
            'assignments' => 'assignments',
            'availabilities' => 'availabilities'
        ];

        foreach ($tableMapping as $logical => $physical) {
            try {
                $count = DB::connection('old_db')->table($physical)->count();
                $actualCounts[$logical] = $count;
                $expected = $expectedCounts[$logical] ?? 0;
                $diff = $count - $expected;
                $status = $diff == 0 ? "✅" : ($diff > 0 ? "📈" : "📉");

                $this->info("{$status} {$logical}: {$count} (attesi: {$expected}, diff: {$diff})");
            } catch (\Exception $e) {
                $this->warn("❌ {$logical}: TABELLA NON TROVATA");
            }
        }

        // Analisi speciale per arbitri
        try {
            $arbitriCount = DB::connection('old_db')->table('arbitri')->count();
            $this->info("🎯 arbitri: {$arbitriCount} (fonte potenziale per referees)");
        } catch (\Exception $e) {
            $this->warn("❌ arbitri: TABELLA NON TROVATA");
        }
    }

    private function analyzeRelationships()
    {
        $this->info("\n🔗 ANALISI RELAZIONI");
        $this->info(str_repeat("-", 40));

        // 1. Relazione Users ↔ Referees
        $this->analyzeUserRefereeRelation();

        // 2. Relazione Arbitri ↔ Users
        $this->analyzeArbitriUserRelation();

        // 3. Relazione Gare ↔ Circoli
        $this->analyzeGareCircoliRelation();
    }

    private function analyzeUserRefereeRelation()
    {
        try {
            $usersCount = DB::connection('old_db')->table('users')->count();
            $refereesCount = DB::connection('old_db')->table('referees')->count();

            if ($usersCount > 0 && $refereesCount > 0) {
                $joinCount = DB::connection('old_db')
                    ->table('users')
                    ->join('referees', 'users.id', '=', 'referees.user_id')
                    ->count();

                $this->info("👥 Users: {$usersCount} | Referees: {$refereesCount} | Join: {$joinCount}");

                if ($joinCount != $refereesCount) {
                    $this->warn("⚠️ PROBLEMA: Non tutti i referees hanno un user corrispondente");
                }
            } else {
                $this->warn("⚠️ Users: {$usersCount}, Referees: {$refereesCount} - Relazione impossibile");
            }
        } catch (\Exception $e) {
            $this->warn("❌ Errore analisi Users-Referees: " . $e->getMessage());
        }
    }

    private function analyzeArbitriUserRelation()
    {
        try {
            $arbitriCount = DB::connection('old_db')->table('arbitri')->count();
            $usersCount = DB::connection('old_db')->table('users')->count();

            if ($arbitriCount > 0) {
                // Conta quanti arbitri hanno un user corrispondente per email
                $emailMatches = DB::connection('old_db')
                    ->table('arbitri')
                    ->join('users', 'arbitri.Email', '=', 'users.email')
                    ->count();

                $this->info("⚖️ Arbitri: {$arbitriCount} | Users: {$usersCount} | Email Match: {$emailMatches}");

                if ($emailMatches < $arbitriCount) {
                    $this->warn("⚠️ ATTENZIONE: {$arbitriCount - $emailMatches} arbitri senza user corrispondente");
                }
            }
        } catch (\Exception $e) {
            $this->warn("❌ Errore analisi Arbitri-Users: " . $e->getMessage());
        }
    }

    private function analyzeGareCircoliRelation()
    {
        try {
            $gareCount = DB::connection('old_db')->table('gare_2025')->count();
            $circoliCount = DB::connection('old_db')->table('circoli')->count();

            $this->info("🏆 Gare: {$gareCount} | Circoli: {$circoliCount}");

        } catch (\Exception $e) {
            $this->warn("❌ Errore analisi Gare-Circoli: " . $e->getMessage());
        }
    }

    private function identifyMissingData()
    {
        $this->info("\n🕵️ IDENTIFICAZIONE DATI MANCANTI");
        $this->info(str_repeat("-", 40));

        $issues = [];

        // 1. Controlla se referees è vuoto
        try {
            $refereesCount = DB::connection('old_db')->table('referees')->count();
            if ($refereesCount == 0) {
                $issues[] = "❌ CRITICO: Tabella referees vuota - necessaria ricostruzione da arbitri";
            }
        } catch (\Exception $e) {
            $issues[] = "❌ CRITICO: Tabella referees non esiste";
        }

        // 2. Controlla arbitri senza livello
        try {
            $arbitriSenzaLivello = DB::connection('old_db')
                ->table('arbitri')
                ->where(function($q) {
                    $q->whereNull('Livello_2025')
                      ->orWhere('Livello_2025', '');
                })
                ->count();

            if ($arbitriSenzaLivello > 0) {
                $issues[] = "⚠️ {$arbitriSenzaLivello} arbitri senza livello 2025";
            }
        } catch (\Exception $e) {
            $issues[] = "❌ Impossibile verificare livelli arbitri";
        }

        // 3. Controlla email duplicate
        try {
            $emailDuplicates = DB::connection('old_db')
                ->table('arbitri')
                ->select('Email')
                ->groupBy('Email')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($emailDuplicates > 0) {
                $issues[] = "⚠️ {$emailDuplicates} email duplicate in arbitri";
            }
        } catch (\Exception $e) {
            // Ignora se la tabella non esiste
        }

        foreach ($issues as $issue) {
            $this->info($issue);
        }

        if (empty($issues)) {
            $this->info("✅ Nessun problema critico identificato");
        }
    }

    private function recommendStrategy()
    {
        $this->info("\n💡 STRATEGIA RACCOMANDATA");
        $this->info(str_repeat("-", 40));

        try {
            $refereesCount = DB::connection('old_db')->table('referees')->count();
            $arbitriCount = DB::connection('old_db')->table('arbitri')->count();
            $usersCount = DB::connection('old_db')->table('users')->count();

            if ($refereesCount == 0 && $arbitriCount > 0) {
                $this->info("🎯 STRATEGIA PRIMARIA: Ricostruzione completa da arbitri");
                $this->info("   1. Crea/aggiorna users da arbitri");
                $this->info("   2. Crea referees da arbitri usando mapping database_map.txt");
                $this->info("   3. Unifica livelli (1_livello, non primo_livello)");

            } elseif ($refereesCount > 0 && $usersCount > 0) {
                $this->info("🎯 STRATEGIA SECONDARIA: Consolidamento esistente");
                $this->info("   1. Consolida dati users + referees");
                $this->info("   2. Integra con dati arbitri mancanti");

            } else {
                $this->info("🎯 STRATEGIA FALLBACK: Analisi manuale necessaria");
                $this->info("   1. Verifica integrità database");
                $this->info("   2. Considera backup alternativi");
            }

            // Sample dei dati per verifica
            $this->showSampleData();

        } catch (\Exception $e) {
            $this->error("❌ Impossibile generare strategia: " . $e->getMessage());
        }
    }

    private function showSampleData()
    {
        $this->info("\n📋 CAMPIONI DATI (primi 3 record)");
        $this->info(str_repeat("-", 40));

        $tables = ['arbitri', 'users', 'referees'];

        foreach ($tables as $table) {
            try {
                $samples = DB::connection('old_db')->table($table)->limit(3)->get();

                if ($samples->count() > 0) {
                    $this->info("📊 {$table}:");
                    foreach ($samples as $sample) {
                        $data = (array)$sample;
                        $preview = array_slice($data, 0, 5, true); // Prime 5 colonne
                        $formatted = collect($preview)->map(function($value, $key) {
                            $value = is_string($value) ? substr($value, 0, 20) : $value;
                            return "{$key}:{$value}";
                        })->implode(' | ');

                        $this->info("   {$formatted}");
                    }
                } else {
                    $this->warn("📊 {$table}: VUOTA");
                }
            } catch (\Exception $e) {
                $this->warn("📊 {$table}: NON ACCESSIBILE");
            }
        }
    }
}
