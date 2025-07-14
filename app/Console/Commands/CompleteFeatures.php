<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Availability;
use App\Models\Assignment;

class CompleteFeatures extends Command
{
    protected $signature = 'golf:complete-features {source_db}';
    protected $description = 'Completa availabilities e assignments dal database originale';

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

        $this->info("🔧 COMPLETAMENTO FUNZIONALITÀ da {$sourceDb}");

        try {
            $this->importAvailabilities($sourceDb);
            $this->importAssignments($sourceDb);
            $this->showFinalStats();
            
        } catch (\Exception $e) {
            $this->error("ERRORE: " . $e->getMessage());
        }
    }

    private function importAvailabilities($sourceDb)
    {
        $this->info("\n📋 IMPORTAZIONE AVAILABILITIES...");
        
        try {
            $availabilities = DB::connection('source')->table('availabilities')->get();
            $count = 0;

            foreach ($availabilities as $avail) {
                $user = User::find($avail->user_id ?? $avail->referee_id);
                $tournament = Tournament::find($avail->tournament_id);
                
                if ($user && $tournament) {
                    Availability::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'tournament_id' => $tournament->id
                        ],
                        [
                            'notes' => $avail->notes ?? null,
                            'submitted_at' => $avail->submitted_at ?? $avail->created_at ?? now(),
                            'created_at' => $avail->created_at ?? now(),
                            'updated_at' => $avail->updated_at ?? now(),
                        ]
                    );
                    $count++;
                }
            }

            $this->info("✅ Availabilities importate: {$count}");

        } catch (\Exception $e) {
            $this->warn("Availabilities non trovate in {$sourceDb}: " . $e->getMessage());
        }
    }

    private function importAssignments($sourceDb)
    {
        $this->info("\n📝 IMPORTAZIONE ASSIGNMENTS...");
        
        try {
            $assignments = DB::connection('source')->table('assignments')->get();
            $count = 0;

            foreach ($assignments as $assign) {
                $user = User::find($assign->user_id ?? $assign->referee_id);
                $tournament = Tournament::find($assign->tournament_id);
                
                if ($user && $tournament) {
                    Assignment::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'tournament_id' => $tournament->id
                        ],
                        [
                            'role' => $assign->role ?? 'Arbitro',
                            'is_confirmed' => $assign->is_confirmed ?? false,
                            'assigned_at' => $assign->assigned_at ?? now(),
                            'assigned_by_id' => $assign->assigned_by_id ?? 1,
                            'notes' => $assign->notes ?? null,
                            'created_at' => $assign->created_at ?? now(),
                            'updated_at' => $assign->updated_at ?? now(),
                        ]
                    );
                    $count++;
                }
            }

            $this->info("✅ Assignments importati: {$count}");

        } catch (\Exception $e) {
            $this->warn("Assignments non trovati in {$sourceDb}: " . $e->getMessage());
        }
    }

    private function showFinalStats()
    {
        $this->info("\n📊 STATISTICHE FINALI:");
        $this->info("Users: " . User::count());
        $this->info("Tournaments: " . Tournament::count());
        $this->info("Availabilities: " . Availability::count());
        $this->info("Assignments: " . Assignment::count());
        $this->info("✅ SISTEMA COMPLETO!");
    }
}
