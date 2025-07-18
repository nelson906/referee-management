<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use App\Models\Availability;
use Carbon\Carbon;

class AssignmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📝 Creazione assignments e availabilities...');

        $referees = User::where('user_type', 'referee')->where('is_active', true)->get();
        $tournaments = Tournament::all();
        $admins = User::whereIn('user_type', ['admin', 'national_admin', 'super_admin'])->get();

        if ($referees->isEmpty() || $tournaments->isEmpty() || $admins->isEmpty()) {
            $this->command->warn('⚠️ Mancano dati prerequisiti (referees, tournaments, admins)');
            return;
        }

        $this->createAvailabilities($referees, $tournaments);
        $this->createAssignments($referees, $tournaments, $admins);

        $this->command->info('✅ Assignments e availabilities creati con successo');
    }

    /**
     * Create sample availabilities
     */
    private function createAvailabilities($referees, $tournaments)
    {
        $this->command->info('📅 Creazione availabilities...');

        $availabilityCount = 0;

        foreach ($tournaments as $tournament) {
            // Skip completed tournaments
            if ($tournament->status === 'completed') {
                continue;
            }

            // Each tournament gets availability from 30-70% of eligible referees
            $eligibleReferees = $this->getEligibleReferees($referees, $tournament);
            $availabilityPercentage = rand(30, 70) / 100;
            $availableCount = max(1, round($eligibleReferees->count() * $availabilityPercentage));

            $availableReferees = $eligibleReferees->random(min($availableCount, $eligibleReferees->count()));

            foreach ($availableReferees as $referee) {
                Availability::create([
                    'user_id' => $referee->id,
                    'tournament_id' => $tournament->id,
                    'notes' => $this->generateAvailabilityNote(),
                    'submitted_at' => $this->generateSubmissionDate($tournament),
                ]);

                $availabilityCount++;
            }
        }

        $this->command->info("📅 Create {$availabilityCount} availabilities");
    }

    /**
     * Create sample assignments
     */
    private function createAssignments($referees, $tournaments, $admins)
    {
        $this->command->info('📝 Creazione assignments...');

        $assignmentCount = 0;

        foreach ($tournaments as $tournament) {
            // Skip draft tournaments
            if ($tournament->status === 'draft') {
                continue;
            }

            // Get available referees for this tournament
            $availableReferees = User::whereHas('availabilities', function($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            })->get();

            if ($availableReferees->isEmpty()) {
                continue;
            }

            // Determine number of referees to assign based on tournament type
            $minReferees = $tournament->tournamentType->min_referees ?? 1;
            $maxReferees = $tournament->tournamentType->max_referees ?? 2;
            $refereesToAssign = rand($minReferees, min($maxReferees, $availableReferees->count()));

            // Select referees to assign (prefer higher level for national tournaments)
            $selectedReferees = $this->selectRefereesForAssignment(
                $availableReferees,
                $refereesToAssign,
                $tournament
            );

            $assignedBy = $this->getAssignedBy($admins, $tournament);

            foreach ($selectedReferees as $index => $referee) {
                $role = $this->determineRole($index, $refereesToAssign, $tournament);

                Assignment::create([
                    'tournament_id' => $tournament->id,
                    'user_id' => $referee->id,
                    'role' => $role,
                    'is_confirmed' => $this->shouldBeConfirmed($tournament),
                    'assigned_at' => $this->generateAssignmentDate($tournament),
                    'assigned_by_id' => $assignedBy->id,
                    'notes' => $this->generateAssignmentNote($role, $tournament),
                ]);

                $assignmentCount++;
            }
        }

        $this->command->info("📝 Creati {$assignmentCount} assignments");
    }

    /**
     * Get eligible referees for tournament
     */
    private function getEligibleReferees($referees, $tournament)
    {
        return $referees->filter(function($referee) use ($tournament) {
            // Check level requirement
            $requiredLevel = $tournament->tournamentType->required_level ?? 'Aspirante';
            $levels = ['Aspirante', '1_livello', 'Regionale', 'Nazionale', 'Internazionale'];

            $refereeLevel = $referee->level ?? 'Aspirante';
            $requiredIndex = array_search($requiredLevel, $levels);
            $refereeIndex = array_search($refereeLevel, $levels);

            if ($requiredIndex === false || $refereeIndex === false || $refereeIndex < $requiredIndex) {
                return false;
            }

            // Check zone eligibility
            if ($tournament->tournamentType->is_national) {
                // National tournaments: all referees eligible
                return true;
            } else {
                // Zone tournaments: only same zone referees
                return $referee->zone_id === $tournament->zone_id;
            }
        });
    }

    /**
     * Select referees for assignment
     */
    private function selectRefereesForAssignment($availableReferees, $count, $tournament)
    {
        if ($tournament->tournamentType->is_national) {
            // For national tournaments, prefer higher level referees
            $sorted = $availableReferees->sortByDesc(function($referee) {
                $levels = ['aspirante' => 1, 'primo_livello' => 2, 'regionale' => 3, 'nazionale' => 4, 'internazionale' => 5];
                return $levels[$referee->level ?? 'aspirante'] ?? 1;
            });

            return $sorted->take($count);
        } else {
            // For zone tournaments, random selection from available
            return $availableReferees->random(min($count, $availableReferees->count()));
        }
    }

    /**
     * Determine who assigned the referee
     */
    private function getAssignedBy($admins, $tournament)
    {
        // Zone admin for zone tournaments, national admin for national tournaments
        if ($tournament->tournamentType->is_national) {
            $nationalAdmins = $admins->whereIn('user_type', ['national_admin', 'super_admin']);
            return $nationalAdmins->isNotEmpty() ? $nationalAdmins->random() : $admins->random();
        } else {
            $zoneAdmins = $admins->where('zone_id', $tournament->zone_id);
            return $zoneAdmins->isNotEmpty() ? $zoneAdmins->random() : $admins->random();
        }
    }

    /**
     * Determine role for assignment
     */
    private function determineRole($index, $totalReferees, $tournament)
    {
        if ($totalReferees === 1) {
            return 'Arbitro';
        }

        if ($index === 0) {
            // First referee is usually tournament director for multi-referee tournaments
            return $tournament->tournamentType->is_national ? 'Direttore di Torneo' : 'Arbitro';
        }

        if ($index === 1 && $totalReferees > 2 && rand(0, 1)) {
            return 'Osservatore';
        }

        return 'Arbitro';
    }

    /**
     * Generate availability note
     */
    private function generateAvailabilityNote()
    {
        $notes = [
            'Disponibile per entrambi i giorni',
            'Disponibile con preferenza per il ruolo di arbitro',
            'Esperienza con questo tipo di torneo',
            'Prima volta in questo circolo',
            'Disponibile per osservazione',
            null, // Many availabilities have no notes
            null,
            null,
        ];

        return $notes[array_rand($notes)];
    }

    /**
     * Generate assignment note
     */
    private function generateAssignmentNote($role, $tournament)
    {
        $roleNotes = [
            'Arbitro' => [
                'Arbitro principale per la gara',
                'Esperienza consolidata in tornei simili',
                'Prima esperienza in questo circolo',
                null,
            ],
            'Direttore di Torneo' => [
                'Responsabile generale della competizione',
                'Esperienza pluriennale come direttore',
                'Gestione delle decisioni tecniche',
            ],
            'Osservatore' => [
                'Osservazione per valutazione arbitro',
                'Supporto tecnico durante la gara',
                'Formazione pratica sul campo',
            ],
        ];

        $notes = $roleNotes[$role] ?? $roleNotes['Arbitro'];
        return $notes[array_rand($notes)];
    }

    /**
     * Generate submission date for availability
     */
    private function generateSubmissionDate($tournament)
    {
        $deadline = $tournament->availability_deadline;
        $startRange = $deadline->copy()->subDays(21); // 3 weeks before deadline
        $endRange = $deadline->copy()->subDays(1); // 1 day before deadline

        $randomDays = rand(0, $startRange->diffInDays($endRange));
        return $startRange->addDays($randomDays);
    }

    /**
     * Generate assignment date
     */
    private function generateAssignmentDate($tournament)
    {
        if ($tournament->status === 'completed') {
            // Assignment happened before tournament
            return $tournament->start_date->copy()->subDays(rand(3, 10));
        }

        $deadline = $tournament->availability_deadline;
        $assignmentStart = $deadline->copy()->addDays(1); // Day after deadline
        $assignmentEnd = $tournament->start_date->copy()->subDays(2); // 2 days before tournament

        if ($assignmentStart >= $assignmentEnd) {
            return $deadline->copy()->addDay();
        }

        $randomDays = rand(0, $assignmentStart->diffInDays($assignmentEnd));
        return $assignmentStart->addDays($randomDays);
    }

    /**
     * Determine if assignment should be confirmed
     */
    private function shouldBeConfirmed($tournament)
    {
        // Most assignments are confirmed, some pending for recent tournaments
        if ($tournament->status === 'completed') {
            return true; // All past assignments are confirmed
        }

        if ($tournament->start_date <= Carbon::now()->addDays(7)) {
            return rand(0, 9) < 9; // 90% confirmed for soon tournaments
        }

        return rand(0, 9) < 7; // 70% confirmed for future tournaments
    }
}
