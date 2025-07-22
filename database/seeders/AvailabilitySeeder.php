<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Availability;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\Helpers\SeederHelper;
use Carbon\Carbon;

class AvailabilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📅 Creando Disponibilità Arbitri per Tornei...');

        // Elimina disponibilità esistenti per evitare duplicati
        Availability::truncate();

        $totalAvailabilities = 0;

        // Crea disponibilità per tornei aperti (zonali)
        $totalAvailabilities += $this->createZonalAvailabilities();

        // Crea disponibilità per tornei nazionali
        $totalAvailabilities += $this->createNationalAvailabilities();

        // Valida e mostra riassunto
        $this->validateAvailabilities();
        $this->showAvailabilitySummary();

        $this->command->info("🏆 Disponibilità create con successo: {$totalAvailabilities} dichiarazioni totali");
    }

    /**
     * Crea disponibilità per tornei zonali
     */
    private function createZonalAvailabilities(): int
    {
        $this->command->info("📍 Creando disponibilità per tornei zonali...");

        $openTournaments = Tournament::where('status', 'open')
                                   ->whereNotNull('zone_id')
                                   ->where('availability_deadline', '>', now())
                                   ->with(['zone'])
                                   ->get();

        $totalCreated = 0;
        $config = SeederHelper::getConfig();

        foreach ($openTournaments as $tournament) {
            $created = $this->createAvailabilitiesForTournament($tournament, 'zonal');
            $totalCreated += $created;
        }

        return $totalCreated;
    }

    /**
     * Crea disponibilità per tornei nazionali
     */
    private function createNationalAvailabilities(): int
    {
        $this->command->info("🌍 Creando disponibilità per tornei nazionali...");

        $nationalTournaments = Tournament::where('status', 'open')
                                        ->whereNull('zone_id')
                                        ->where('availability_deadline', '>', now())
                                        ->get();

        $totalCreated = 0;

        foreach ($nationalTournaments as $tournament) {
            $created = $this->createAvailabilitiesForTournament($tournament, 'national');
            $totalCreated += $created;
        }

        return $totalCreated;
    }

    /**
     * Crea disponibilità per un torneo specifico
     */
    private function createAvailabilitiesForTournament(Tournament $tournament, string $scope): int
    {
        $this->command->info("  🏆 Processando: {$tournament->name}");

        // Ottieni arbitri eleggibili
        $eligibleReferees = $this->getEligibleReferees($tournament, $scope);

        if ($eligibleReferees->isEmpty()) {
            $this->command->warn("    ⚠️ Nessun arbitro eleggibile trovato");
            return 0;
        }

        // Calcola quanti arbitri dovrebbero dichiarare disponibilità (70% circa)
        $availabilityRate = SeederHelper::getConfig()['availability_rate'];
        $availableCount = (int) ceil($eligibleReferees->count() * $availabilityRate);

        // Seleziona arbitri casuali che dichiareranno disponibilità
        $availableReferees = $eligibleReferees->random(min($availableCount, $eligibleReferees->count()));

        $created = 0;
        foreach ($availableReferees as $referee) {
            $availability = $this->createAvailabilityRecord($tournament, $referee);
            if ($availability) {
                $created++;
            }
        }

        $this->command->info("    ✅ {$created}/{$eligibleReferees->count()} arbitri hanno dichiarato disponibilità");
        return $created;
    }

    /**
     * Ottieni arbitri eleggibili per il torneo
     */
    private function getEligibleReferees(Tournament $tournament, string $scope): \Illuminate\Database\Eloquent\Collection
    {
        if ($scope === 'national') {
            // Per tornei nazionali: tutti gli arbitri di livello nazionale e internazionale
            return User::where('user_type', 'referee')
                      ->where('is_active', true)
                      ->whereIn('level', ['nazionale', 'internazionale'])
                      ->get();
        } else {
            // Per tornei zonali: arbitri della stessa zona + nazionali/internazionali
            $zoneReferees = User::where('user_type', 'referee')
                               ->where('is_active', true)
                               ->where('zone_id', $tournament->zone_id)
                               ->get();

            $nationalReferees = User::where('user_type', 'referee')
                                   ->where('is_active', true)
                                   ->whereIn('level', ['nazionale', 'internazionale'])
                                   ->get();

            return $zoneReferees->merge($nationalReferees)->unique('id');
        }
    }

    /**
     * Crea record di disponibilità
     */
    private function createAvailabilityRecord(Tournament $tournament, User $referee): ?Availability
    {
        // Verifica che non esista già
        $existing = Availability::where('tournament_id', $tournament->id)
                                ->where('referee_id', $referee->id)
                                ->first();

        if ($existing) {
            return null;
        }

        // Genera disponibilità realistica
        $isAvailable = $this->generateAvailabilityStatus($referee, $tournament);
        $notes = $this->generateAvailabilityNotes($isAvailable, $referee->level);

        return Availability::create([
            'tournament_id' => $tournament->id,
            'referee_id' => $referee->id,
            'is_available' => $isAvailable,
            'submitted_at' => $this->generateSubmissionTime($tournament),
            'notes' => $notes,
            'travel_required' => $this->determineTravelRequired($tournament, $referee),
            'accommodation_needed' => $this->determineAccommodationNeeded($tournament, $referee),
            'preferred_role' => $this->generatePreferredRole($referee->level),
            'conflicts' => $this->generateConflicts($isAvailable),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Genera status disponibilità realistico
     */
    private function generateAvailabilityStatus(User $referee, Tournament $tournament): bool
    {
        // Probabilità più alta per arbitri di livello superiore
        $baseRate = match($referee->level) {
            'internazionale' => 0.9,
            'nazionale' => 0.85,
            'regionale' => 0.75,
            'primo_livello' => 0.7,
            'aspirante' => 0.65,
            default => 0.7
        };

        // Riduce probabilità per tornei durante weekend estivi (più impegni personali)
        $tournamentDate = Carbon::parse($tournament->start_date);
        if ($tournamentDate->month >= 6 && $tournamentDate->month <= 8) {
            $baseRate *= 0.8;
        }

        return rand(1, 100) <= ($baseRate * 100);
    }

    /**
     * Genera tempo di sottomissione realistico
     */
    private function generateSubmissionTime(Tournament $tournament): string
    {
        $deadline = Carbon::parse($tournament->availability_deadline);
        $now = Carbon::now();

        // La maggior parte risponde entro la prima metà del periodo
        $availableDays = $now->diffInDays($deadline);
        $submissionDays = rand(0, max(1, (int)($availableDays * 0.7)));

        return $now->subDays($submissionDays)->addHours(rand(8, 22))->format('Y-m-d H:i:s');
    }

    /**
     * Genera note disponibilità
     */
    private function generateAvailabilityNotes(bool $isAvailable, string $level): ?string
    {
        if (!$isAvailable) {
            $unavailableReasons = [
                'Impegno familiare già programmato',
                'Altro torneo già assegnato',
                'Viaggio di lavoro programmato',
                'Impegno personale non rimandabile',
                'Problemi di salute temporanei'
            ];
            return $unavailableReasons[array_rand($unavailableReasons)];
        }

        if (rand(1, 100) <= 30) { // 30% chance di note anche se disponibile
            $availableNotes = [
                'Disponibile con piacere',
                'Preferenza per ruolo di osservatore',
                'Possibile arrivo nel pomeriggio del giorno precedente',
                'Disponibile per tutto il weekend',
                'Esperienza specifica in questa tipologia di torneo'
            ];
            return $availableNotes[array_rand($availableNotes)];
        }

        return null;
    }

    /**
     * Determina se è richiesto viaggio
     */
    private function determineTravelRequired(Tournament $tournament, User $referee): bool
    {
        // Se il torneo è nella stessa zona dell'arbitro, probabilmente non serve viaggio
        if ($tournament->zone_id === $referee->zone_id) {
            return rand(1, 100) <= 20; // 20% di possibilità anche nella stessa zona
        }

        // Se zone diverse o torneo nazionale, probabile viaggio
        return rand(1, 100) <= 80;
    }

    /**
     * Determina se serve alloggio
     */
    private function determineAccommodationNeeded(Tournament $tournament, User $referee): bool
    {
        // Se non serve viaggio, non serve alloggio
        if (!$this->determineTravelRequired($tournament, $referee)) {
            return false;
        }

        // Se serve viaggio, 60% di possibilità di servire alloggio
        return rand(1, 100) <= 60;
    }

    /**
     * Genera ruolo preferito
     */
    private function generatePreferredRole(string $level): ?string
    {
        $roles = SeederHelper::getAssignmentRoles();

        // Arbitri senior preferiscono ruoli di responsabilità
        if (in_array($level, ['nazionale', 'internazionale'])) {
            $preferredRoles = ['Direttore Torneo', 'Supervisore', 'Arbitro'];
            return $preferredRoles[array_rand($preferredRoles)];
        }

        // Arbitri junior preferiscono ruoli di supporto
        if (in_array($level, ['aspirante', 'primo_livello'])) {
            $preferredRoles = ['Arbitro', 'Assistente', 'Osservatore'];
            return $preferredRoles[array_rand($preferredRoles)];
        }

        // Arbitri intermedi: qualsiasi ruolo
        return rand(1, 100) <= 50 ? $roles[array_rand($roles)] : null;
    }

    /**
     * Genera conflitti se non disponibile
     */
    private function generateConflicts(bool $isAvailable): ?array
    {
        if ($isAvailable || rand(1, 100) > 40) {
            return null;
        }

        $conflicts = [
            'Altro torneo stesso weekend',
            'Corso di aggiornamento',
            'Matrimonio familiare',
            'Viaggio programmato',
            'Impegno lavorativo'
        ];

        return [array_rand(array_flip($conflicts))];
    }

    /**
     * Valida disponibilità create
     */
    private function validateAvailabilities(): void
    {
        $this->command->info('🔍 Validando disponibilità create...');

        // Verifica che non ci siano duplicati
        $totalAvailabilities = Availability::count();
        $uniqueAvailabilities = Availability::distinct(['tournament_id', 'referee_id'])->count();

        if ($totalAvailabilities !== $uniqueAvailabilities) {
            $this->command->error("❌ Errore: disponibilità duplicate trovate");
            return;
        }

        // Verifica che tutte le disponibilità siano per tornei aperti
        $invalidTournaments = Availability::whereHas('tournament', function($query) {
            $query->where('status', '!=', 'open');
        })->count();

        if ($invalidTournaments > 0) {
            $this->command->error("❌ Errore: {$invalidTournaments} disponibilità per tornei non aperti");
            return;
        }

        // Verifica che tutte le disponibilità siano per arbitri attivi
        $inactiveReferees = Availability::whereHas('referee', function($query) {
            $query->where('is_active', false);
        })->count();

        if ($inactiveReferees > 0) {
            $this->command->error("❌ Errore: {$inactiveReferees} disponibilità per arbitri inattivi");
            return;
        }

        // Verifica coerenza zone per tornei zonali
        $inconsistentZones = Availability::whereHas('tournament', function($query) {
            $query->whereNotNull('zone_id');
        })->whereHas('referee', function($query) {
            $query->whereNotNull('zone_id');
        })->whereRaw('
            NOT EXISTS (
                SELECT 1 FROM tournaments t
                WHERE t.id = availabilities.tournament_id
                AND (
                    t.zone_id = (SELECT zone_id FROM users WHERE id = availabilities.referee_id)
                    OR (SELECT level FROM users WHERE id = availabilities.referee_id) IN ("nazionale", "internazionale")
                )
            )
        ')->count();

        if ($inconsistentZones > 0) {
            $this->command->warn("⚠️ Attenzione: {$inconsistentZones} disponibilità con possibili incoerenze di zona");
        }

        $this->command->info('✅ Validazione disponibilità completata con successo');
    }

    /**
     * Mostra riassunto disponibilità create
     */
    private function showAvailabilitySummary(): void
    {
        $this->command->info('');
        $this->command->info('📅 RIASSUNTO DISPONIBILITÀ ARBITRI:');
        $this->command->info('=====================================');

        // Statistiche per torneo
        $tournamentStats = Tournament::where('status', 'open')
            ->with(['availabilities'])
            ->get()
            ->map(function($tournament) {
                $total = $tournament->availabilities->count();
                $available = $tournament->availabilities->where('is_available', true)->count();
                $unavailable = $total - $available;

                return [
                    'name' => $tournament->name,
                    'zone' => $tournament->zone ? $tournament->zone->code : 'NAZIONALE',
                    'total' => $total,
                    'available' => $available,
                    'unavailable' => $unavailable,
                    'rate' => $total > 0 ? round(($available / $total) * 100, 1) : 0
                ];
            });

        foreach ($tournamentStats as $stats) {
            $this->command->info("🏆 {$stats['name']} ({$stats['zone']}):");
            $this->command->info("   Totale risposte: {$stats['total']}");
            $this->command->info("   🟢 Disponibili: {$stats['available']} ({$stats['rate']}%)");
            $this->command->info("   🔴 Non disponibili: {$stats['unavailable']}");
            $this->command->info('');
        }

        // Statistiche generali
        $totalAvailabilities = Availability::count();
        $totalAvailable = Availability::where('is_available', true)->count();
        $totalUnavailable = $totalAvailabilities - $totalAvailable;
        $overallRate = $totalAvailabilities > 0 ? round(($totalAvailable / $totalAvailabilities) * 100, 1) : 0;

        $this->command->info('📊 STATISTICHE GENERALI:');
        $this->command->info("   Dichiarazioni totali: {$totalAvailabilities}");
        $this->command->info("   🟢 Disponibili: {$totalAvailable} ({$overallRate}%)");
        $this->command->info("   🔴 Non disponibili: {$totalUnavailable}");

        // Statistiche per livello arbitro
        $this->command->info('');
        $this->command->info('👨‍⚖️ DISPONIBILITÀ PER LIVELLO:');
        $levelStats = Availability::join('users', 'availabilities.referee_id', '=', 'users.id')
            ->selectRaw('users.level,
                        COUNT(*) as total,
                        SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as available')
            ->groupBy('users.level')
            ->get();

        foreach ($levelStats as $stat) {
            $rate = $stat->total > 0 ? round(($stat->available / $stat->total) * 100, 1) : 0;
            $this->command->info("   {$stat->level}: {$stat->available}/{$stat->total} ({$rate}%)");
        }

        // Tornei con più richieste
        $this->command->info('');
        $this->command->info('🔥 TORNEI PIÙ RICHIESTI:');
        $popularTournaments = Tournament::withCount(['availabilities' => function($query) {
            $query->where('is_available', true);
        }])
        ->where('status', 'open')
        ->orderBy('availabilities_count', 'desc')
        ->limit(5)
        ->get();

        foreach ($popularTournaments as $tournament) {
            $zone = $tournament->zone ? $tournament->zone->code : 'NAZ';
            $this->command->info("   🏆 {$tournament->name} ({$zone}): {$tournament->availabilities_count} disponibili");
        }

        $this->command->info('=====================================');
        $this->command->info('');
    }
}
