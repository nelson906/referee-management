<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tournament;
use App\Models\TournamentType;

class FixTournamentAlignment extends Command
{
    protected $signature = 'golf:fix-alignment {source_db} {--preview} {--confirm}';
    protected $description = 'Fix tournament data alignment from source database';

    public function handle()
    {
        $sourceDb = $this->argument('source_db');
        $preview = $this->option('preview');
        $confirm = $this->option('confirm');
        
        if (!$preview && !$confirm) {
            $this->error("⚠️ ATTENZIONE: Questo riallineerà TUTTI i dati dei tornei!");
            $this->info("Usa --preview per vedere le modifiche o --confirm per applicarle");
            return;
        }

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

        $this->info($preview ? "👀 PREVIEW RIALLINEAMENTO" : "🔧 RIALLINEAMENTO TORNEI");
        $this->info("Source: {$sourceDb}");

        try {
            $this->createMissingTournamentTypes();
            $this->fixTournamentAlignment($sourceDb, $preview);
            
        } catch (\Exception $e) {
            $this->error("ERRORE: " . $e->getMessage());
        }
    }

    private function createMissingTournamentTypes()
    {
        $this->info("\n🎯 Creazione tournament_types mancanti...");
        
        // Aggiungi i tipi mancanti che vediamo nel database
        $types = [
            ['id' => 4, 'name' => 'Coppa Italia', 'code' => 'CI', 'level' => 'nazionale', 'required_level' => 'regionale', 'min_referees' => 2, 'max_referees' => 3],
            ['id' => 5, 'name' => 'Interregionale', 'code' => 'INT', 'level' => 'nazionale', 'required_level' => 'regionale', 'min_referees' => 2, 'max_referees' => 3],
            ['id' => 6, 'name' => 'Regionale', 'code' => 'REG', 'level' => 'zonale', 'required_level' => 'regionale', 'min_referees' => 1, 'max_referees' => 2],
            ['id' => 7, 'name' => 'Societario', 'code' => 'SOC', 'level' => 'zonale', 'required_level' => 'primo_livello', 'min_referees' => 1, 'max_referees' => 2],
            ['id' => 8, 'name' => 'Giovanile', 'code' => 'GIO', 'level' => 'zonale', 'required_level' => 'primo_livello', 'min_referees' => 1, 'max_referees' => 2],
            ['id' => 9, 'name' => 'Nazionale Elite', 'code' => 'NAZ', 'level' => 'nazionale', 'required_level' => 'nazionale', 'min_referees' => 2, 'max_referees' => 4],
        ];

        foreach ($types as $type) {
            TournamentType::updateOrCreate(['id' => $type['id']], $type);
        }
        
        $this->info("✅ Tournament types aggiornati");
    }

    private function fixTournamentAlignment($sourceDb, $preview)
    {
        $this->info("\n🔄 Controllo allineamento tornei...");
        
        $sourceTournaments = DB::connection('source')
            ->table('tournaments')
            ->orderBy('id')
            ->get();

        $fixed = 0;
        $issues = 0;

        foreach ($sourceTournaments as $sourceT) {
            $current = Tournament::find($sourceT->id);
            
            if (!$current) {
                $this->warn("❌ Torneo ID {$sourceT->id} non trovato nel database attuale");
                continue;
            }

            // Verifica se TUTTO è allineato
            $misaligned = $this->checkMisalignment($current, $sourceT);
            
            if ($misaligned) {
                $issues++;
                
                $this->info("\n🔧 ID {$sourceT->id} - DISALLINEATO:");
                $this->info("  Nome: '{$current->name}' → '{$sourceT->name}'");
                $this->info("  Date: {$current->start_date} → {$sourceT->start_date}");
                $this->info("  Club: {$current->club_id} → {$sourceT->club_id}");
                $this->info("  Zone: {$current->zone_id} → {$sourceT->zone_id}");
                
                if (!$preview) {
                    // Fix completo di TUTTI i dati
                    $current->update([
                        'name' => $sourceT->name,
                        'start_date' => $sourceT->start_date,
                        'end_date' => $sourceT->end_date,
                        'club_id' => $sourceT->club_id,
                        'zone_id' => $sourceT->zone_id,
                        'tournament_type_id' => $sourceT->tournament_type_id ?? $current->tournament_type_id,
                        'availability_deadline' => $sourceT->availability_deadline ?? $current->availability_deadline,
                        'status' => $sourceT->status ?? $current->status,
                        'notes' => $sourceT->notes ?? $current->notes,
                    ]);
                    
                    $fixed++;
                }
            }
        }

        if ($preview) {
            $this->info("\n👀 PREVIEW COMPLETATO:");
            $this->info("Tornei con disallineamento: {$issues}");
            $this->info("Usa --confirm per applicare le correzioni");
        } else {
            $this->info("\n✅ RIALLINEAMENTO COMPLETATO:");
            $this->info("Tornei corretti: {$fixed}");
        }
    }

    private function checkMisalignment($current, $source)
    {
        return $current->name !== $source->name ||
               $current->start_date !== $source->start_date ||
               $current->end_date !== $source->end_date ||
               $current->club_id != $source->club_id ||
               $current->zone_id != $source->zone_id;
    }
}
