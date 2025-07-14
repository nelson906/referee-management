<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tournament;
use App\Models\TournamentType;

class FixedAlignmentCommand extends Command
{
    protected $signature = 'golf:fix-alignment-safe {source_db} {--preview} {--confirm}';
    protected $description = 'Fix tournament alignment safely without duplicating tournament types';

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
            $this->checkTournamentTypes();
            $this->fixTournamentAlignment($sourceDb, $preview);

        } catch (\Exception $e) {
            $this->error("ERRORE: " . $e->getMessage());
        }
    }

    private function checkTournamentTypes()
    {
        $this->info("\n📋 Controllo tournament_types esistenti...");

        $existing = TournamentType::pluck('name', 'id')->toArray();
        $this->info("Tournament types attuali:");
        foreach ($existing as $id => $name) {
            $this->info("  ID {$id}: {$name}");
        }

        // Aggiungi solo i tipi che NON esistono
        $newTypes = [
            ['id' => 4, 'name' => 'Coppa Italia', 'code' => 'CI4', 'level' => 'nazionale', 'required_level' => 'regionale'],
            ['id' => 5, 'name' => 'Interregionale', 'code' => 'INT', 'level' => 'nazionale', 'required_level' => 'regionale'],
            ['id' => 6, 'name' => 'Regionale Plus', 'code' => 'REG2', 'level' => 'zonale', 'required_level' => 'regionale'],
            ['id' => 7, 'name' => 'Societario', 'code' => 'SOC', 'level' => 'zonale', 'required_level' => 'primo_livello'],
            ['id' => 8, 'name' => 'Giovanile', 'code' => 'GIO', 'level' => 'zonale', 'required_level' => 'primo_livello'],
            ['id' => 9, 'name' => 'Elite', 'code' => 'ELI', 'level' => 'nazionale', 'required_level' => 'nazionale'],
            ['id' => 10, 'name' => 'Speciale', 'code' => 'SPE', 'level' => 'zonale', 'required_level' => 'primo_livello'],
        ];

        $added = 0;
        foreach ($newTypes as $type) {
            if (!isset($existing[$type['id']])) {
                try {
                    TournamentType::create([
                        'id' => $type['id'],
                        'name' => $type['name'],
                        'code' => $type['code'],
                        'level' => $type['level'],
                        'required_level' => $type['required_level'],
                        'min_referees' => 1,
                        'max_referees' => 2,
                        'is_active' => true,
                    ]);
                    $this->info("✅ Creato: {$type['name']} (ID {$type['id']})");
                    $added++;
                } catch (\Exception $e) {
                    $this->warn("⚠️ Errore creando {$type['name']}: " . $e->getMessage());
                }
            }
        }

        if ($added === 0) {
            $this->info("✅ Tutti i tournament types necessari già esistono");
        }
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
        $specificIssues = [];

        foreach ($sourceTournaments as $sourceT) {
            $current = Tournament::find($sourceT->id);

            if (!$current) {
                $this->warn("❌ Torneo ID {$sourceT->id} non trovato nel database attuale");
                continue;
            }

            // Verifica se c'è disallineamento
            $misaligned = $this->checkMisalignment($current, $sourceT);

            if ($misaligned) {
                $issues++;
                $specificIssues[] = [
                    'id' => $sourceT->id,
                    'current_name' => $current->name,
                    'correct_name' => $sourceT->name,
                    'current_club' => $current->club_id,
                    'correct_club' => $sourceT->club_id,
                ];

                // Mostra solo i primi 10 per non intasare l'output
                if ($issues <= 10) {
                    $this->info("\n🔧 ID {$sourceT->id} - DISALLINEATO:");
                    $this->info("  Nome: '{$current->name}' → '{$sourceT->name}'");
                    $this->info("  Club: {$current->club_id} → {$sourceT->club_id}");

                    // Verifica problemi specifici come Asolo
                    if (strpos(strtolower($sourceT->name), 'asolo') !== false && $current->club_id != $sourceT->club_id) {
                        $this->warn("  ⚠️ PROBLEMA ASOLO RILEVATO!");
                    }
                }

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

        if ($issues > 10) {
            $this->info("\n... e altri " . ($issues - 10) . " tornei disallineati");
        }

        // Mostra i problemi specifici di Asolo
        $this->showAsoloProblems($specificIssues);

        if ($preview) {
            $this->info("\n👀 PREVIEW COMPLETATO:");
            $this->info("Tornei con disallineamento: {$issues}");
            $this->info("Usa --confirm per applicare le correzioni");
        } else {
            $this->info("\n✅ RIALLINEAMENTO COMPLETATO:");
            $this->info("Tornei corretti: {$fixed}");
        }
    }

    private function showAsoloProblems($issues)
    {
        $asoloProblems = array_filter($issues, function($issue) {
            return strpos(strtolower($issue['correct_name']), 'asolo') !== false;
        });

        if (!empty($asoloProblems)) {
            $this->info("\n🎯 PROBLEMI ASOLO SPECIFICI:");
            foreach ($asoloProblems as $problem) {
                $this->info("ID {$problem['id']}: '{$problem['correct_name']}'");
                $this->info("  Club sbagliato: {$problem['current_club']} → {$problem['correct_club']}");
            }
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
