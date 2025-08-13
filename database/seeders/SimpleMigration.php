<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SimpleMigration
{
    public static function migrateYear($year)
    {
        echo "\n🔄 Migrazione anno {$year}...\n";

        // Configura connessione remota
        config(['database.connections.temp_real' => [
            'driver' => 'mysql',
            'host' => env('REAL_DB_HOST', '89.46.111.59'),
            'port' => env('REAL_DB_PORT', '3306'),
            'database' => env('REAL_DB_DATABASE', 'Sql1466239_4'),
            'username' => env('REAL_DB_USERNAME', 'Sql1466239'),
            'password' => env('REAL_DB_PASSWORD', '0475ef8287'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        $sourceTable = "gare_{$year}";
        $destTable = "tournaments_{$year}";

        try {
            // Verifica se esiste
            $exists = DB::connection('temp_real')->select("SHOW TABLES LIKE '{$sourceTable}'");
            if (empty($exists)) {
                echo "❌ Tabella {$sourceTable} non trovata\n";
                return;
            }

            // Conta record
            $count = DB::connection('temp_real')->table($sourceTable)->count();
            echo "📊 Trovati {$count} record\n";

            // Crea tabella locale
            DB::statement("DROP TABLE IF EXISTS {$destTable}");
            $create = DB::connection('temp_real')->select("SHOW CREATE TABLE {$sourceTable}")[0];
            $sql = $create->{'Create Table'};
            $sql = str_replace($sourceTable, $destTable, $sql);
            DB::statement($sql);

            // Copia dati
            $copied = 0;
            DB::connection('temp_real')->table($sourceTable)
                ->orderBy('id')
                ->chunk(50, function($rows) use ($destTable, &$copied) {
                    foreach ($rows as $row) {
                        DB::table($destTable)->insert((array)$row);
                        $copied++;
                    }
                    echo "  {$copied} record copiati\r";
                });

            echo "\n✅ Completato: {$copied} record in {$destTable}\n";

        } catch (\Exception $e) {
            echo "❌ ERRORE: " . $e->getMessage() . "\n";
        }
    }
}
