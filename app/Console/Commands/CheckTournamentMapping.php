<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tournament;

class CheckTournamentMapping extends Command
{
    protected $signature = 'golf:check-mapping {new_db}';
    protected $description = 'Check if tournament data is correctly mapped';

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

        $this->info("🔍 CONTROLLO MAPPING TORNEI");
        
        // Prendi alcuni ID specifici dai tuoi screenshot
        $testIds = [1, 2, 3, 4, 5];
        
        foreach ($testIds as $id) {
            $this->info("\n🎯 TORNEO ID: {$id}");
            
            // Dati attuali
            $current = Tournament::find($id);
            if ($current) {
                $this->info("📍 ATTUALE:");
                $this->info("  Nome: {$current->name}");
                $this->info("  Date: {$current->start_date} - {$current->end_date}");
                $this->info("  Club ID: {$current->club_id}");
                $this->info("  Zone ID: {$current->zone_id}");
            }
            
            // Dati corretti da golf_referee_new
            $correct = DB::connection('new')->table('tournaments')->find($id);
            if ($correct) {
                $this->info("✅ CORRETTO da {$newDb}:");
                $this->info("  Nome: {$correct->name}");
                $this->info("  Date: {$correct->start_date} - {$correct->end_date}");
                $this->info("  Club ID: {$correct->club_id}");
                $this->info("  Zone ID: {$correct->zone_id}");
            }
            
            // Verifica se TUTTI i dati corrispondono
            if ($current && $correct) {
                $matches = [
                    'Nome' => $current->name === $correct->name,
                    'Start' => $current->start_date === $correct->start_date,
                    'End' => $current->end_date === $correct->end_date,
                    'Club' => $current->club_id == $correct->club_id,
                    'Zone' => $current->zone_id == $correct->zone_id,
                ];
                
                $this->info("🔄 CONFRONTO:");
                foreach ($matches as $field => $match) {
                    $status = $match ? '✅' : '❌';
                    $this->info("  {$field}: {$status}");
                }
            }
        }
        
        // Controlla quanti tornei hanno TUTTI i dati diversi
        $this->info("\n📊 STATISTICHE GENERALI:");
        $totalCurrent = Tournament::count();
        $totalCorrect = DB::connection('new')->table('tournaments')->count();
        $this->info("Tornei attuali: {$totalCurrent}");
        $this->info("Tornei corretti: {$totalCorrect}");
    }
}
