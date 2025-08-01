<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tournament;

class FixTournamentNames extends Command
{
    protected $signature = 'golf:fix-names {new_db}';
    protected $description = 'Fix tournament names from golf_referee_new';

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
            $this->info("✅ Connesso a {$newDb}");
            
            $newTournaments = DB::connection('new')->table('tournaments')->get();
            $fixed = 0;

            foreach ($newTournaments as $newTournament) {
                $current = Tournament::find($newTournament->id);
                
                if ($current && ($current->name === 'Torneo' || strlen($current->name) < 5)) {
                    $current->update(['name' => $newTournament->name]);
                    $this->info("Fixed: {$newTournament->name}");
                    $fixed++;
                }
            }

            $this->info("✅ Nomi tornei ripristinati: {$fixed}");
            
        } catch (\Exception $e) {
            $this->error("ERRORE: " . $e->getMessage());
        }
    }
}
