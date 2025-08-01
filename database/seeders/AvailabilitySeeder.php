<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\Availability;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Zone;
use App\Models\TournamentType;
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

        // Gestisci foreign keys
        Schema::disableForeignKeyConstraints();

        try {
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

        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Crea disponibilità per tornei zonali
     */
    private function createZonalAvailabilities(): int
    {
        $this->command->info("📍 Creando disponibilità per tornei zonali...");

        // ✅ FIXED: Tornei zonali (non nazionali)
        $openTournaments = Tournament::where('status', 'open')
                                   ->whereNotNull('zone_id')
                                   ->where('availability_deadline', '>', now())
                                   ->whereHas('tournamentType', function($query) {
                                       $query->where('is_national', false);
                                   })
                                   ->with(['zone', 'tournamentType'])
                                   ->get();

        $totalCreated = 0;

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

        // ✅ FIXED: Tornei nazionali (hanno zone ma tipo nazionale)
        $nationalTournaments = Tournament::where('status', 'open')
                                        ->whereNotNull('zone_id')  // ✅ FIXED: Anche nazionali hanno zone
                                        ->where('availability_deadline', '>', now())
                                        ->whereHas('tournamentType', function($query) {
                                            $query->where('is_national', true);
                                        })
                                        ->with(['zone', 'tournamentType'])
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
        $availabilityRate = 0.7;  // ✅ FIXED: Hardcoded per evitare config
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
                      ->whereIn('level', ['Nazionale', 'Internazionale'])  // ✅ FIXED: Valori ENUM corretti
                      ->get();
        } else {
            // Per tornei zonali: arbitri della stessa zona + nazionali/internazionali
            $zoneReferees = User::where('user_type', 'referee')
                               ->where('is_active', true)
                               ->where('zone_id', $tournament->zone_id)
                               ->get();

            $nationalReferees = User::where('user_type', 'referee')
                                   ->where('is_active', true)
                                   ->whereIn('level', ['Nazionale', 'Internazionale'])  // ✅ FIXED: Valori ENUM corretti
                                   ->get();

            return $zoneReferees->merge($nationalReferees)->unique('id');
        }
    }

    /**
     * Crea record di disponibilità
     */
    private function createAvailabilityRecord(Tournament $tournament, User $referee): ?Availability
    {
        // ✅ FIXED: Usa user_id invece di referee_id
        $existing = Availability::where('tournament_id', $tournament->id)
                                ->where('user_id', $referee->id)
                                ->first();

        if ($existing) {
            return null;
        }

        // Genera disponibilità realistica
        $isAvailable = $this->generateAvailabilityStatus($referee, $tournament);
        $notes = $this->generateAvailabilityNotes($isAvailable, $referee->level);

        return Availability::create([
            'tournament_id' => $tournament->id,
            'user_id' => $referee->id,  // ✅ FIXED: user_id invece di referee_id
            'submitted_at' => $this->generateSubmissionTime($tournament),
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Genera status disponibilità realistico
     */
    private function generateAvailabilityStatus(User $referee, Tournament $tournament): bool
    {
        // ✅ FIXED: Valori ENUM corretti
        $baseRate = match($referee->level) {
            'Internazionale' => 0.9,
            'Nazionale' => 0.85,
            'Regionale' => 0.75,
            '1_livello' => 0.7,
            'Aspirante' => 0.65,
            default => 0.7
        };

        // Riduce probabilità per tornei durante weekend estivi
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
     * Valida disponibilità create
     */
    private function validateAvailabilities(): void
    {
        $this->command->info('🔍 Validando disponibilità create...');

        // ✅ FIXED: Usa user_id invece di referee_id
        $totalAvailabilities = Availability::count();
        $uniqueAvailabilities = Availability::distinct(['tournament_id', 'user_id'])->count();

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

        // ✅ FIXED: Usa relazione corretta 'user' invece di 'referee'
        $inactiveReferees = Availability::whereHas('user', function($query) {
            $query->where('is_active', false);
        })->count();

        if ($inactiveReferees > 0) {
            $this->command->error("❌ Errore: {$inactiveReferees} disponibilità per arbitri inattivi");
            return;
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

                return [
                    'name' => $tournament->name,
                    'zone' => $tournament->zone ? $tournament->zone->code : 'NAZIONALE',
                    'total' => $total,
                ];
            });

        foreach ($tournamentStats as $stats) {
            $this->command->info("🏆 {$stats['name']} ({$stats['zone']}):");
            $this->command->info("   Totale risposte: {$stats['total']}");
            $this->command->info('');
        }

        // Statistiche generali
        $totalAvailabilities = Availability::count();

        $this->command->info('📊 STATISTICHE GENERALI:');
        $this->command->info("   Dichiarazioni totali: {$totalAvailabilities}");

        // ✅ FIXED: Join corretto con user_id
        $levelStats = Availability::join('users', 'availabilities.user_id', '=', 'users.id')
            ->selectRaw('users.level, COUNT(*) as total')
            ->groupBy('users.level')
            ->get();

        $this->command->info('');
        $this->command->info('👨‍⚖️ DISPONIBILITÀ PER LIVELLO:');
        foreach ($levelStats as $stat) {
            $this->command->info("   {$stat->level}: {$stat->total}");
        }

        $this->command->info('=====================================');
        $this->command->info('');
    }
}
