<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use Carbon\Carbon;

class AssignmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * ✅ SOLO ASSIGNMENTS - Availabilities create da AvailabilitySeeder
     */
    public function run(): void
    {
        $this->command->info('📝 Creazione assignments...');

        $admins = User::whereIn('user_type', ['admin', 'national_admin', 'super_admin'])->get();
        $tournaments = Tournament::whereIn('status', ['closed', 'assigned', 'completed'])->get();

        if ($admins->isEmpty()) {
            $this->command->warn('⚠️ Nessun admin trovato');
            return;
        }

        if ($tournaments->isEmpty()) {
            $this->command->warn('⚠️ Nessun torneo da assegnare trovato');
            return;
        }

        $assignmentCount = 0;

        foreach ($tournaments as $tournament) {
            $this->command->line("🎯 Processando {$tournament->name}...");

            // ✅ STEP 1: Arbitri con disponibilità dichiarata
            $availableReferees = User::whereHas('availabilities', function($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            })->where('user_type', 'referee')->where('is_active', true)->get();

            // ✅ STEP 2: Altri arbitri della zona (fallback)
            $possibleReferees = User::where('user_type', 'referee')
                ->where('is_active', true)
                ->where('zone_id', $tournament->zone_id)
                ->whereDoesntHave('availabilities', function ($q) use ($tournament) {
                    $q->where('tournament_id', $tournament->id);
                })
                ->get();

            // ✅ STEP 3: Arbitri nazionali per tornei nazionali
            $nationalReferees = collect();
            if ($tournament->tournamentType && $tournament->tournamentType->is_national) {
                $nationalReferees = User::where('user_type', 'referee')
                    ->where('is_active', true)
                    ->whereIn('level', ['nazionale', 'internazionale'])
                    ->whereDoesntHave('availabilities', function ($q) use ($tournament) {
                        $q->where('tournament_id', $tournament->id);
                    })
                    ->get();
            }

            // ✅ Pool totale: prima disponibili, poi possibili, poi nazionali
            $allCandidates = $availableReferees
                ->concat($possibleReferees)
                ->concat($nationalReferees)
                ->unique('id');

            if ($allCandidates->isEmpty()) {
                $this->command->line("⚠️ Nessun candidato per {$tournament->name}");
                continue;
            }

            // Determina quanti arbitri assegnare
            $minReferees = $tournament->tournamentType->min_referees ?? 1;
            $maxReferees = $tournament->tournamentType->max_referees ?? 2;
            $refereesToAssign = rand($minReferees, min($maxReferees, $allCandidates->count()));

            // ✅ Logica di priorità: prima disponibili, poi altri
            $selectedReferees = $this->selectRefereesWithPriority(
                $availableReferees,
                $possibleReferees,
                $nationalReferees,
                $refereesToAssign,
                $tournament
            );

            $this->command->line("   Disponibili: {$availableReferees->count()}, Possibili: {$possibleReferees->count()}, Assegno: {$selectedReferees->count()}");

            $assignedBy = $this->getAssignedBy($admins, $tournament);

            foreach ($selectedReferees as $index => $referee) {
                $role = $this->determineRole($index, $selectedReferees->count(), $tournament);

                // Check for existing assignment
                $existingAssignment = Assignment::where('tournament_id', $tournament->id)
                                                ->where('user_id', $referee->id)
                                                ->exists();

                if (!$existingAssignment) {
                    Assignment::create([
                        'tournament_id' => $tournament->id,
                        'user_id' => $referee->id,
                        'role' => $role,
                        'is_confirmed' => $this->shouldBeConfirmed($tournament),
                        'assigned_at' => $this->generateAssignmentDate($tournament),
                        'assigned_by_id' => $assignedBy->id,
                        'notes' => $this->generateAssignmentNote($role, $availableReferees->contains($referee)),
                    ]);

                    $assignmentCount++;
                }
            }
        }

        $this->command->info("✅ Creati {$assignmentCount} assignments per {$tournaments->count()} tornei");
    }

    /**
     * ✅ Selezione con priorità: prima disponibili, poi altri
     */
    private function selectRefereesWithPriority($available, $possible, $national, $count, $tournament)
    {
        $selected = collect();

        // STEP 1: Prima tutti i disponibili (massima priorità)
        $availableSelected = $this->selectByLevel($available, min($count, $available->count()));
        $selected = $selected->concat($availableSelected);
        $remaining = $count - $selected->count();

        if ($remaining > 0) {
            // STEP 2: Poi gli altri della zona
            $possibleSelected = $this->selectByLevel($possible, min($remaining, $possible->count()));
            $selected = $selected->concat($possibleSelected);
            $remaining = $count - $selected->count();

            if ($remaining > 0 && $tournament->tournamentType && $tournament->tournamentType->is_national) {
                // STEP 3: Infine nazionali (solo per tornei nazionali)
                $nationalSelected = $this->selectByLevel($national, min($remaining, $national->count()));
                $selected = $selected->concat($nationalSelected);
            }
        }

        return $selected;
    }

    /**
     * ✅ Selezione per livello (preferenza ai livelli più alti)
     */
    private function selectByLevel($referees, $count)
    {
        if ($count <= 0 || $referees->isEmpty()) {
            return collect();
        }

        $levelPriority = ['internazionale', 'nazionale', 'regionale', 'primo_livello', 'aspirante'];

        $sorted = $referees->sortBy(function ($referee) use ($levelPriority) {
            $level = $referee->level ?? 'aspirante';
            return array_search($level, $levelPriority);
        });

        return $sorted->take($count);
    }

    /**
     * ✅ Get admin assegnatore
     */
    private function getAssignedBy($admins, $tournament)
    {
        // Preferenza admin di zona
        if (!$tournament->tournamentType || !$tournament->tournamentType->is_national) {
            $zoneAdmins = $admins->where('zone_id', $tournament->zone_id);
            if ($zoneAdmins->isNotEmpty()) {
                return $zoneAdmins->random();
            }
        }

        // Admin nazionale
        $nationalAdmins = $admins->whereIn('user_type', ['national_admin', 'super_admin']);
        return $nationalAdmins->isNotEmpty() ? $nationalAdmins->random() : $admins->random();
    }

    /**
     * ✅ Determina ruolo
     */
    private function determineRole($index, $totalReferees, $tournament)
    {
        if ($totalReferees === 1) {
            return 'Arbitro';
        }

        return $index === 0 ? 'Direttore di Torneo' : 'Arbitro';
    }

    /**
     * ✅ Conferma assegnazione
     */
    private function shouldBeConfirmed($tournament): bool
    {
        return match($tournament->status) {
            'completed' => true,
            'assigned' => rand(0, 9) < 9,  // 90%
            'closed' => rand(0, 9) < 7,    // 70%
            default => rand(0, 9) < 5      // 50%
        };
    }

    /**
     * ✅ Data assegnazione
     */
    private function generateAssignmentDate($tournament)
    {
        $baseDate = $tournament->availability_deadline ?? $tournament->start_date->subDays(7);
        return $baseDate->copy()->addDays(rand(1, 5));
    }

    /**
     * ✅ Note assegnazione (indica se aveva disponibilità)
     */
    private function generateAssignmentNote($role, $hadAvailability): ?string
    {
        if ($hadAvailability) {
            $notes = [
                null,
                'Confermato per ' . $role,
                'Disponibilità confermata',
                'Selezionato da disponibili',
            ];
        } else {
            $notes = [
                null,
                'Chiamato per necessità',
                'Assegnazione diretta',
                'Convocato dalla zona',
            ];
        }

        return $notes[array_rand($notes)];
    }
}
