<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\Availability;
use App\Models\User;
use Database\Seeders\Helpers\SeederHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AssignmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎯 Creando Assegnazioni Arbitri per Tornei...');

        // Elimina assegnazioni esistenti per evitare duplicati
        Schema::disableForeignKeyConstraints();
        try {
        Assignment::truncate();

        $totalAssignments = 0;

        // Crea assegnazioni per tornei chiusi (in fase di assegnazione)
        $totalAssignments += $this->createAssignmentsForClosedTournaments();

        // Crea assegnazioni per tornei già assegnati
        $totalAssignments += $this->createAssignmentsForAssignedTournaments();

        // Crea assegnazioni per tornei completati (storiche)
        $totalAssignments += $this->createAssignmentsForCompletedTournaments();

        // Valida e mostra riassunto
        $this->validateAssignments();
        $this->showAssignmentSummary();

        $this->command->info("🏆 Assegnazioni create con successo: {$totalAssignments} assegnazioni totali");
        } finally {
            Schema::enableForeignKeyConstraints();
    }
    }
    /**
     * Crea assegnazioni per tornei chiusi
     */
    private function createAssignmentsForClosedTournaments(): int
    {
        $this->command->info("🟡 Creando assegnazioni per tornei chiusi...");

        $closedTournaments = Tournament::where('status', 'closed')
                                      ->with(['tournamentType', 'zone', 'availabilities' => function($query) {
                                          $query->where('is_available', true)->with('referee');
                                      }])
                                      ->get();

        $totalCreated = 0;

        foreach ($closedTournaments as $tournament) {
            $created = $this->createAssignmentsForTournament($tournament, 'partial');
            $totalCreated += $created;
        }

        return $totalCreated;
    }

    /**
     * Crea assegnazioni per tornei assegnati
     */
    private function createAssignmentsForAssignedTournaments(): int
    {
        $this->command->info("✅ Creando assegnazioni per tornei assegnati...");

        $assignedTournaments = Tournament::where('status', 'assigned')
                                        ->with(['tournamentType', 'zone', 'availabilities' => function($query) {
                                            $query->where('is_available', true)->with('referee');
                                        }])
                                        ->get();

        $totalCreated = 0;

        foreach ($assignedTournaments as $tournament) {
            $created = $this->createAssignmentsForTournament($tournament, 'complete');
            $totalCreated += $created;
        }

        return $totalCreated;
    }

    /**
     * Crea assegnazioni per tornei completati
     */
    private function createAssignmentsForCompletedTournaments(): int
    {
        $this->command->info("🏁 Creando assegnazioni per tornei completati...");

        $completedTournaments = Tournament::where('status', 'completed')
                                         ->with(['tournamentType', 'zone'])
                                         ->get();

        $totalCreated = 0;

        foreach ($completedTournaments as $tournament) {
            // Per tornei completati, creiamo assegnazioni fittizie senza disponibilità
            $created = $this->createHistoricalAssignments($tournament);
            $totalCreated += $created;
        }

        return $totalCreated;
    }

    /**
     * Crea assegnazioni per un torneo specifico
     */
    private function createAssignmentsForTournament(Tournament $tournament, string $completionLevel): int
    {
        $this->command->info("  🏆 Processando: {$tournament->name}");

        $availableReferees = $tournament->availabilities
                                       ->where('is_available', true)
                                       ->pluck('referee')
                                       ->filter();

        if ($availableReferees->isEmpty()) {
            $this->command->warn("    ⚠️ Nessun arbitro disponibile");
            return 0;
        }

        // Determina quanti arbitri assegnare
        $targetCount = $this->determineRefereeCount($tournament, $availableReferees);

        if ($targetCount === 0) {
            $this->command->warn("    ⚠️ Impossibile determinare numero arbitri");
            return 0;
        }

        // Seleziona arbitri basandosi su priorità e disponibilità
        $selectedReferees = $this->selectRefereesForAssignment($availableReferees, $targetCount, $tournament);

        // Ottieni admin che fa le assegnazioni
        $assignedBy = $this->getAssigningAdmin($tournament);

        if (!$assignedBy) {
            $this->command->warn("    ⚠️ Nessun admin trovato per assegnazioni");
            return 0;
        }

        // Crea assegnazioni
        $created = 0;
        foreach ($selectedReferees as $index => $referee) {
            $assignment = $this->createAssignmentRecord($tournament, $referee, $assignedBy, $index, $completionLevel);
            if ($assignment) {
                $created++;
            }
        }

        $this->command->info("    ✅ {$created} arbitri assegnati");
        return $created;
    }

    /**
     * Determina numero di arbitri da assegnare
     */
    private function determineRefereeCount(Tournament $tournament, $availableReferees): int
    {
        $minReferees = $tournament->tournamentType->min_referees;
        $maxReferees = $tournament->tournamentType->max_referees;
        $availableCount = $availableReferees->count();

        // Non possiamo assegnare più di quelli disponibili
        $maxPossible = min($maxReferees, $availableCount);

        // Assicuriamoci di rispettare il minimo
        if ($maxPossible < $minReferees) {
            return 0; // Non possiamo soddisfare i requisiti minimi
        }

        // Tendi verso il numero ottimale (75% tra min e max)
        $optimal = $minReferees + (int)(($maxPossible - $minReferees) * 0.75);

        return min($optimal, $maxPossible);
    }

    /**
     * Seleziona arbitri per assegnazione basandosi su priorità
     */
    private function selectRefereesForAssignment($availableReferees, int $targetCount, Tournament $tournament): \Illuminate\Support\Collection
    {
        // Ordina arbitri per priorità
        $prioritizedReferees = $availableReferees->sortByDesc(function($referee) use ($tournament) {
            return $this->calculateRefereePriority($referee, $tournament);
        });

        return $prioritizedReferees->take($targetCount);
    }

    /**
     * Calcola priorità arbitro per assegnazione
     */
    private function calculateRefereePriority(User $referee, Tournament $tournament): int
    {
        $priority = 0;

        // Bonus per livello arbitro
        $priority += match($referee->level) {
            'internazionale' => 100,
            'nazionale' => 80,
            'regionale' => 60,
            'primo_livello' => 40,
            'aspirante' => 20,
            default => 30
        };

        // Bonus se arbitro della stessa zona
        if ($tournament->zone_id === $referee->zone_id) {
            $priority += 20;
        }

        // Bonus per importanza torneo
        $priority += $tournament->tournamentType->priority_level * 5;

        // Penalità casuale per simulare altri fattori
        $priority -= rand(0, 10);

        return $priority;
    }

    /**
     * Ottieni admin che assegna
     */
    private function getAssigningAdmin(Tournament $tournament): ?User
    {
        if ($tournament->zone_id) {
            // Per tornei zonali: admin della zona
            return User::where('user_type', 'admin')
                      ->where('zone_id', $tournament->zone_id)
                      ->first();
        } else {
            // Per tornei nazionali: national admin
            return User::where('user_type', 'national_admin')
                      ->first();
        }
    }

    /**
     * Crea record di assegnazione
     */
    private function createAssignmentRecord(Tournament $tournament, User $referee, User $assignedBy, int $index, string $completionLevel): ?Assignment
    {
        // Verifica che non esista già
        $existing = Assignment::where('tournament_id', $tournament->id)
                             ->where('referee_id', $referee->id)
                             ->first();

        if ($existing) {
            return null;
        }

        $role = $this->assignRole($referee, $index, $tournament);
        $isConfirmed = $this->determineConfirmationStatus($completionLevel);

        return Assignment::create([
            'tournament_id' => $tournament->id,
            'referee_id' => $referee->id,
            'assigned_by_id' => $assignedBy->id,
            'role' => $role,
            'is_confirmed' => $isConfirmed,
            'assigned_at' => $this->generateAssignmentTime($tournament),
            'confirmed_at' => $isConfirmed ? $this->generateConfirmationTime($tournament) : null,
            'notes' => $this->generateAssignmentNotes($role, $referee->level),
            'fee_amount' => $this->calculateFee($role, $tournament),
            'travel_compensation' => $this->calculateTravelCompensation($tournament, $referee),
            'special_instructions' => $this->generateSpecialInstructions($role),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Assegna ruolo basato su posizione e livello
     */
    private function assignRole(User $referee, int $index, Tournament $tournament): string
    {
        $roles = SeederHelper::getAssignmentRoles();

        // Primo arbitro (index 0) spesso è direttore o supervisore se qualificato
        if ($index === 0 && in_array($referee->level, ['nazionale', 'internazionale'])) {
            return rand(0, 1) ? 'Direttore Torneo' : 'Supervisore';
        }

        // Assegna ruolo basato su livello
        return match($referee->level) {
            'internazionale' => ['Direttore Torneo', 'Supervisore', 'Arbitro'][rand(0, 2)],
            'nazionale' => ['Supervisore', 'Arbitro', 'Direttore Torneo'][rand(0, 2)],
            'regionale' => ['Arbitro', 'Osservatore'][rand(0, 1)],
            'primo_livello' => ['Arbitro', 'Assistente'][rand(0, 1)],
            'aspirante' => ['Assistente', 'Osservatore'][rand(0, 1)],
            default => 'Arbitro'
        };
    }

    /**
     * Determina status conferma
     */
    private function determineConfirmationStatus(string $completionLevel): bool
    {
        $confirmationRate = SeederHelper::getConfig()['assignment_rate'];

        return match($completionLevel) {
            'complete' => true, // Tornei assegnati: tutti confermati
            'partial' => rand(1, 100) <= ($confirmationRate * 100), // Tornei chiusi: alcuni confermati
            default => rand(1, 100) <= 50 // Default: 50%
        };
    }

    /**
     * Genera tempo assegnazione
     */
    private function generateAssignmentTime(Tournament $tournament): string
    {
        $deadline = Carbon::parse($tournament->availability_deadline);
        $tournamentStart = Carbon::parse($tournament->start_date);

        // Assegnazione tipicamente 1-5 giorni dopo deadline
        return $deadline->addDays(rand(1, 5))->addHours(rand(9, 17))->format('Y-m-d H:i:s');
    }

    /**
     * Genera tempo conferma
     */
    private function generateConfirmationTime(Tournament $tournament): string
    {
        $assignmentTime = $this->generateAssignmentTime($tournament);
        $assignmentCarbon = Carbon::parse($assignmentTime);

        // Conferma tipicamente 1-3 giorni dopo assegnazione
        return $assignmentCarbon->addDays(rand(1, 3))->addHours(rand(8, 20))->format('Y-m-d H:i:s');
    }

    /**
     * Genera note assegnazione
     */
    private function generateAssignmentNotes(string $role, string $level): ?string
    {
        if (rand(1, 100) > 40) return null; // 60% senza note

        $notes = [
            'Arbitro con esperienza specifica',
            'Prima assegnazione per questo tipo di torneo',
            'Richiesta specifica del club organizzatore',
            'Arbitro locale disponibile',
            'Esperienza pregressa positiva'
        ];

        return $notes[array_rand($notes)];
    }

    /**
     * Calcola compenso
     */
    private function calculateFee(string $role, Tournament $tournament): int
    {
        $baseFee = match($role) {
            'Direttore Torneo' => 200,
            'Supervisore' => 150,
            'Arbitro' => 100,
            'Osservatore' => 80,
            'Assistente' => 60,
            default => 100
        };

        // Moltiplicatore per importanza torneo
        $multiplier = match($tournament->tournamentType->priority_level) {
            1, 2 => 1.0,
            3, 4 => 1.2,
            5, 6 => 1.5,
            default => 1.0
        };

        return (int)($baseFee * $multiplier);
    }

    /**
     * Calcola rimborso viaggio
     */
    private function calculateTravelCompensation(Tournament $tournament, User $referee): int
    {
        // Se stessa zona, rimborso minimo
        if ($tournament->zone_id === $referee->zone_id) {
            return rand(0, 50);
        }

        // Zone diverse: rimborso più alto
        return rand(100, 300);
    }

    /**
     * Genera istruzioni speciali
     */
    private function generateSpecialInstructions(string $role): ?string
    {
        if (rand(1, 100) > 30) return null; // 70% senza istruzioni speciali

        $instructions = match($role) {
            'Direttore Torneo' => [
                'Coordinare briefing pre-torneo',
                'Gestire rapporti con organizzazione',
                'Supervisionare altri arbitri'
            ],
            'Supervisore' => [
                'Osservare e valutare arbitri junior',
                'Supporto decisionale nei casi difficili',
                'Report post-torneo richiesto'
            ],
            default => [
                'Arrivo 30 minuti prima',
                'Abbigliamento formale richiesto',
                'Portare equipaggiamento completo'
            ]
        };

        return $instructions[array_rand($instructions)];
    }

    /**
     * Crea assegnazioni storiche per tornei completati
     */
    private function createHistoricalAssignments(Tournament $tournament): int
    {
        $targetCount = rand($tournament->tournamentType->min_referees, $tournament->tournamentType->max_referees);

        // Ottieni arbitri casuali appropriati per la zona
        $eligibleReferees = $this->getEligibleRefereesForZone($tournament);

        if ($eligibleReferees->count() < $targetCount) {
            return 0;
        }

        $selectedReferees = $eligibleReferees->random($targetCount);
        $assignedBy = $this->getAssigningAdmin($tournament);

        if (!$assignedBy) {
            return 0;
        }

        $created = 0;
        foreach ($selectedReferees as $index => $referee) {
            $assignment = $this->createHistoricalAssignment($tournament, $referee, $assignedBy, $index);
            if ($assignment) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Ottieni arbitri eleggibili per zona (per assegnazioni storiche)
     */
    private function getEligibleRefereesForZone(Tournament $tournament): \Illuminate\Database\Eloquent\Collection
    {
        if ($tournament->zone_id) {
            return User::where('user_type', 'referee')
                      ->where('is_active', true)
                      ->where(function($query) use ($tournament) {
                          $query->where('zone_id', $tournament->zone_id)
                                ->orWhereIn('level', ['nazionale', 'internazionale']);
                      })
                      ->get();
        } else {
            return User::where('user_type', 'referee')
                      ->where('is_active', true)
                      ->whereIn('level', ['nazionale', 'internazionale'])
                      ->get();
        }
    }

    /**
     * Crea assegnazione storica
     */
    private function createHistoricalAssignment(Tournament $tournament, User $referee, User $assignedBy, int $index): ?Assignment
    {
        $role = $this->assignRole($referee, $index, $tournament);

        return Assignment::create([
            'tournament_id' => $tournament->id,
            'referee_id' => $referee->id,
            'assigned_by_id' => $assignedBy->id,
            'role' => $role,
            'is_confirmed' => true, // Tornei completati: sempre confermati
            'assigned_at' => Carbon::parse($tournament->start_date)->subDays(rand(7, 21))->format('Y-m-d H:i:s'),
            'confirmed_at' => Carbon::parse($tournament->start_date)->subDays(rand(3, 10))->format('Y-m-d H:i:s'),
            'notes' => 'Assegnazione storica',
            'fee_amount' => $this->calculateFee($role, $tournament),
            'travel_compensation' => $this->calculateTravelCompensation($tournament, $referee),
            'special_instructions' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Valida assegnazioni create
     */
    private function validateAssignments(): void
    {
        $this->command->info('🔍 Validando assegnazioni create...');

        // Verifica che non ci siano duplicati
        $totalAssignments = Assignment::count();
        $uniqueAssignments = Assignment::distinct(['tournament_id', 'referee_id'])->count();

        if ($totalAssignments !== $uniqueAssignments) {
            $this->command->error("❌ Errore: assegnazioni duplicate trovate");
            return;
        }

        // Verifica rispetto limiti min/max arbitri per torneo
        $invalidCounts = Tournament::whereIn('status', ['closed', 'assigned', 'completed'])
            ->get()
            ->filter(function($tournament) {
                $assignedCount = $tournament->assignments()->count();
                $minRequired = $tournament->tournamentType->min_referees;
                $maxAllowed = $tournament->tournamentType->max_referees;

                return $assignedCount < $minRequired || $assignedCount > $maxAllowed;
            })
            ->count();

        if ($invalidCounts > 0) {
            $this->command->warn("⚠️ Attenzione: {$invalidCounts} tornei con numero arbitri fuori range");
        }

        // Verifica che tutti i referee assegnati siano attivi
        $inactiveReferees = Assignment::whereHas('referee', function($query) {
            $query->where('is_active', false);
        })->count();

        if ($inactiveReferees > 0) {
            $this->command->error("❌ Errore: {$inactiveReferees} assegnazioni per arbitri inattivi");
            return;
        }

        $this->command->info('✅ Validazione assegnazioni completata con successo');
    }

    /**
     * Mostra riassunto assegnazioni create
     */
    private function showAssignmentSummary(): void
    {
        $this->command->info('');
        $this->command->info('🎯 RIASSUNTO ASSEGNAZIONI ARBITRI:');
        $this->command->info('=====================================');

        // Statistiche per status torneo
        $statusStats = Tournament::whereIn('status', ['closed', 'assigned', 'completed'])
            ->withCount('assignments')
            ->get()
            ->groupBy('status')
            ->map(function($tournaments, $status) {
                return [
                    'tournaments' => $tournaments->count(),
                    'total_assignments' => $tournaments->sum('assignments_count'),
                    'avg_assignments' => round($tournaments->avg('assignments_count'), 1)
                ];
            });

        foreach ($statusStats as $status => $stats) {
            $emoji = match($status) {
                'closed' => '🟡',
                'assigned' => '✅',
                'completed' => '🏁',
                default => '❓'
            };

            $this->command->info("{$emoji} TORNEI {$status}:");
            $this->command->info("   Tornei: {$stats['tournaments']}");
            $this->command->info("   Assegnazioni totali: {$stats['total_assignments']}");
            $this->command->info("   Media arbitri per torneo: {$stats['avg_assignments']}");
            $this->command->info('');
        }

        // Statistiche per ruolo
        $this->command->info('👨‍⚖️ ASSEGNAZIONI PER RUOLO:');
        $roleStats = Assignment::selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->orderBy('count', 'desc')
            ->get();

        foreach ($roleStats as $stat) {
            $this->command->info("   {$stat->role}: {$stat->count} assegnazioni");
        }

        // Statistiche conferme
        $this->command->info('');
        $this->command->info('✅ STATO CONFERME:');
        $totalAssignments = Assignment::count();
        $confirmedAssignments = Assignment::where('is_confirmed', true)->count();
        $pendingAssignments = $totalAssignments - $confirmedAssignments;
        $confirmationRate = $totalAssignments > 0 ? round(($confirmedAssignments / $totalAssignments) * 100, 1) : 0;

        $this->command->info("   Totale assegnazioni: {$totalAssignments}");
        $this->command->info("   🟢 Confermate: {$confirmedAssignments} ({$confirmationRate}%)");
        $this->command->info("   🟡 In attesa: {$pendingAssignments}");

        // Top arbitri per assegnazioni
        $this->command->info('');
        $this->command->info('🥇 ARBITRI PIÙ ATTIVI:');
        $topReferees = Assignment::selectRaw('referee_id, COUNT(*) as assignments_count')
            ->with('referee:id,name,level')
            ->groupBy('referee_id')
            ->orderBy('assignments_count', 'desc')
            ->limit(10)
            ->get();

        foreach ($topReferees as $stat) {
            $referee = $stat->referee;
            $this->command->info("   {$referee->name} ({$referee->level}): {$stat->assignments_count} assegnazioni");
        }

        // Statistiche compensi
        $this->command->info('');
        $this->command->info('💰 STATISTICHE COMPENSI:');
        $totalFees = Assignment::sum('fee_amount');
        $totalTravel = Assignment::sum('travel_compensation');
        $avgFee = Assignment::avg('fee_amount');

        $this->command->info("   Compensi totali: €" . number_format($totalFees, 0, ',', '.'));
        $this->command->info("   Rimborsi viaggio: €" . number_format($totalTravel, 0, ',', '.'));
        $this->command->info("   Compenso medio: €" . number_format($avgFee, 0, ',', '.'));

        $this->command->info('=====================================');
        $this->command->info('');
    }
}
