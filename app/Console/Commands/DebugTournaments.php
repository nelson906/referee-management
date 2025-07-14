<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tournament;

class DebugTournaments extends Command
{
    protected $signature = 'golf:debug-tournaments {new_db}';
    protected $description = 'Debug tournament names comparison';

    public function handle()
    {
        $newDb = $this->argument('new_db');
        
        config(['database.connections.new' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT'),
            'database' => $newDb,
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        try {
            DB::connection('new')->getPdo();
            $this->info("=== CONFRONTO NOMI TORNEI ===");
            
            // Nomi attuali (primi 10)
            $this->info("\n🔴 NOMI ATTUALI (sbagliati):");
            $currentTournaments = Tournament::take(10)->get(['id', 'name']);
            foreach ($currentTournaments as $t) {
                $this->info("ID {$t->id}: '{$t->name}'");
            }
            
            // Nomi corretti da golf_referee_new (primi 10)
            $this->info("\n🟢 NOMI CORRETTI da {$newDb}:");
            $newTournaments = DB::connection('new')->table('tournaments')->take(10)->get(['id', 'name']);
            foreach ($newTournaments as $t) {
                $this->info("ID {$t->id}: '{$t->name}'");
            }
            
            // Confronto diretto
            $this->info("\n🔄 CONFRONTO DIRETTO:");
            foreach ($newTournaments as $newT) {
                $current = Tournament::find($newT->id);
                if ($current) {
                    $this->info("ID {$newT->id}:");
                    $this->info("  Attuale: '{$current->name}'");
                    $this->info("  Corretto: '{$newT->name}'");
                    $this->info("  Diversi: " . ($current->name !== $newT->name ? 'SÌ' : 'NO'));
                } else {
                    $this->warn("ID {$newT->id}: NON TROVATO nel database attuale");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("ERRORE: " . $e->getMessage());
        }
    }
}
