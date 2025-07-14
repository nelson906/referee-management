<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tournament;
use App\Models\TournamentType;

class QuickFixAlignment extends Command
{
    protected $signature = 'golf:quick-fix {source_db}';
    protected $description = 'Quick fix tournament alignment with ID mapping';

    public function handle()
    {
        $sourceDb = $this->argument('source_db');
        
        config(['database.connections.source' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT'),
            'database' => $sourceDb,
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        $this->info("🚀 QUICK FIX ALIGNMENT");

        try {
            // 1. Crea ID 6 mancante
            $this->createMissingTournamentType();
            
            // 2. Fix specifico per Asolo
            $this->fixAsoloProblems($sourceDb);
            
            // 3. Check risultati
            $this->verifyResults();
            
        } catch (\Exception $e) {
            $this->error("ERRORE: " . $e->getMessage());
        }
    }

    private function createMissingTournamentType()
    {
        $this->info("🎯 Creazione tournament_type ID 6...");
        
        try {
            TournamentType::updateOrCreate(
                ['id' => 6],
                [
                    'name' => 'Regionale Speciale',
                    'code' => 'RSP',
                    'level' => 'zonale',
                    'required_level' => 'regionale', 
                    'min_referees' => 1,
                    'max_referees' => 2,
                    'is_active' => true,
                ]
            );
            $this->info("✅ Tournament type ID 6 creato");
        } catch (\Exception $e) {
            $this->warn("Errore ID 6: " . $e->getMessage());
        }
    }

    private function fixAsoloProblems($sourceDb)
    {
        $this->info("\n🎯 Fix specifico problemi Asolo...");
        
        // Trova tutti i tornei con "asolo" nel nome dal source
        $asoloTournaments = DB::connection('source')
            ->table('tournaments')
            ->where('name', 'LIKE', '%asolo%')
            ->orWhere('name', 'LIKE', '%ASOLO%')
            ->get();

        if ($asoloTournaments->isEmpty()) {
            $this->info("Nessun torneo Asolo trovato nel source");
            return;
        }

        foreach ($asoloTournaments as $sourceT) {
            $current = Tournament::find($sourceT->id);
            
            if ($current) {
                $this->info("🔧 Fix ID {$sourceT->id}: '{$sourceT->name}'");
                $this->info("  Club: {$current->club_id} → {$sourceT->club_id}");
                
                $current->update([
                    'name' => $sourceT->name,
                    'club_id' => $sourceT->club_id,
                    'zone_id' => $sourceT->zone_id,
                    'start_date' => $sourceT->start_date,
                    'end_date' => $sourceT->end_date,
                    'tournament_type_id' => $this->mapTournamentType($sourceT->tournament_type_id ?? 1),
                ]);
                
                $this->info("✅ Corretto!");
            }
        }
    }

    private function mapTournamentType($sourceTypeId)
    {
        // Mappa gli ID che potrebbero non esistere
        $mapping = [
            1 => 1, // Zonale
            2 => 2, // Regionale  
            3 => 3, // Nazionale
            4 => 4, // Coppa Italia
            5 => 5, // Interregionale
            6 => 6, // Regionale Speciale (appena creato)
            7 => 7, // Regionale Plus
            8 => 8, // Societario
            9 => 9, // Giovanile
            10 => 10, // Elite
        ];
        
        return $mapping[$sourceTypeId] ?? 1; // Default a Zonale
    }

    private function verifyResults()
    {
        $this->info("\n📊 VERIFICA RISULTATI:");
        
        // Cerca tornei che potrebbero ancora avere problemi
        $asoloCheck = Tournament::where('name', 'LIKE', '%asolo%')
            ->orWhere('name', 'LIKE', '%ASOLO%')
            ->get(['id', 'name', 'club_id']);
        
        if ($asoloCheck->isNotEmpty()) {
            $this->info("🎯 Tornei Asolo nel database:");
            foreach ($asoloCheck as $tournament) {
                $this->info("  ID {$tournament->id}: {$tournament->name} (Club: {$tournament->club_id})");
            }
        }
        
        $this->info("✅ Quick fix completato!");
    }
}
