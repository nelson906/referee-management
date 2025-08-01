<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tournament;
use App\Models\Zone;
use App\Models\Club;
use App\Models\TournamentType;
use App\Models\User;
use Database\Seeders\Helpers\SeederHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class TournamentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏆 Creando Tornei Golf per Zone...');

        // Elimina tornei esistenti per evitare duplicati
        Schema::disableForeignKeyConstraints();
        try {
            Tournament::truncate();

            $zones = Zone::orderBy('code')->get();
            $totalTournaments = 0;

            foreach ($zones as $zone) {
                $tournamentsCreated = $this->createTournamentsForZone($zone);
                $totalTournaments += $tournamentsCreated;
            }

            // Crea alcuni tornei nazionali
            $nationalTournaments = $this->createNationalTournaments();
            $totalTournaments += $nationalTournaments;

            // Valida e mostra riassunto
            $this->validateTournaments();
            $this->showTournamentSummary();

            $this->command->info("🏆 Tornei creati con successo: {$totalTournaments} tornei totali");
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Crea tornei per una specifica zona
     */
    private function createTournamentsForZone(Zone $zone): int
    {
        $this->command->info("📍 Creando tornei per zona {$zone->code} - {$zone->name}...");

        $config = SeederHelper::getConfig()['tournaments_per_zone'];
        $clubs = Club::where('zone_id', $zone->id)->get();
        $zoneAdmin = User::where('user_type', 'admin')->where('zone_id', $zone->id)->first();
        $tournamentTypes = TournamentType::where('is_national', false)->get();

        if ($clubs->isEmpty() || !$zoneAdmin || $tournamentTypes->isEmpty()) {
            $this->command->warn("⚠️ Dati insufficienti per zona {$zone->code}");
            return 0;
        }

        $tournamentsCreated = 0;

        // Crea tornei completati (passati)
        $tournamentsCreated += $this->createTournamentsByStatus(
            $zone,
            $clubs,
            $zoneAdmin,
            $tournamentTypes,
            'completed',
            $config['completed']
        );

        // Crea tornei assegnati (passati/presenti)
        $tournamentsCreated += $this->createTournamentsByStatus(
            $zone,
            $clubs,
            $zoneAdmin,
            $tournamentTypes,
            'assigned',
            $config['assigned']
        );

        // Crea tornei aperti (presenti - per raccolta disponibilità)
        $tournamentsCreated += $this->createTournamentsByStatus(
            $zone,
            $clubs,
            $zoneAdmin,
            $tournamentTypes,
            'open',
            $config['open']
        );

        // Crea tornei chiusi (presenti - per assegnazioni)
        $tournamentsCreated += $this->createTournamentsByStatus(
            $zone,
            $clubs,
            $zoneAdmin,
            $tournamentTypes,
            'closed',
            $config['closed']
        );

        // Crea tornei in bozza (futuri)
        $tournamentsCreated += $this->createTournamentsByStatus(
            $zone,
            $clubs,
            $zoneAdmin,
            $tournamentTypes,
            'draft',
            $config['draft']
        );

        return $tournamentsCreated;
    }

    /**
     * Crea tornei per status specifico
     */
    private function createTournamentsByStatus(
        Zone $zone,
        $clubs,
        User $admin,
        $tournamentTypes,
        string $status,
        int $count
    ): int {
        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            $club = $clubs->random();
            $tournamentType = $tournamentTypes->random();
            $dates = $this->generateDatesForStatus($status);

            $tournament = Tournament::create([
                'name' => $this->generateTournamentName($zone, $tournamentType, $status, $i + 1),
                'description' => $this->generateTournamentDescription($tournamentType, $status),
                'zone_id' => $zone->id,
                'club_id' => $club->id,
                'tournament_type_id' => $tournamentType->id,
                'start_date' => $dates['start_date'],
                'end_date' => $dates['end_date'],
                'availability_deadline' => $dates['availability_deadline'],
                'status' => $status,
                'notes' => "Torneo {$status} per testing zona {$zone->code}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("  ✅ {$tournament->name} ({$status}) - {$club->name}");
            $created++;
        }

        return $created;
    }

    /**
     * Genera date appropriate per lo status
     */
    private function generateDatesForStatus(string $status): array
    {
        $now = Carbon::now();

        return match ($status) {
            'completed' => $this->generateCompletedDates($now),
            'assigned' => $this->generateAssignedDates($now),
            'open' => $this->generateOpenDates($now),
            'closed' => $this->generateClosedDates($now),
            'draft' => $this->generateDraftDates($now),
            default => $this->generateDraftDates($now)
        };
    }

    /**
     * Date per tornei completati (passati)
     */
    private function generateCompletedDates(Carbon $now): array
    {
        $startDate = $now->copy()->subMonths(rand(1, 8))->addDays(rand(0, 29));
        $endDate = $startDate->copy()->addDays(rand(1, 3));

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'availability_deadline' => $startDate->copy()->subDays(21)->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Date per tornei assegnati (recenti/prossimi)
     */
    private function generateAssignedDates(Carbon $now): array
    {
        $startDate = $now->copy()->addDays(rand(5, 30));
        $endDate = $startDate->copy()->addDays(rand(1, 3));

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'availability_deadline' => $startDate->copy()->subDays(14)->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Date per tornei aperti (raccolta disponibilità)
     */
    private function generateOpenDates(Carbon $now): array
    {
        $startDate = $now->copy()->addDays(rand(20, 45));
        $endDate = $startDate->copy()->addDays(rand(1, 3));

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'availability_deadline' => $now->copy()->addDays(rand(5, 15))->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Date per tornei chiusi (in fase di assegnazione)
     */
    private function generateClosedDates(Carbon $now): array
    {
        $startDate = $now->copy()->addDays(rand(10, 25));
        $endDate = $startDate->copy()->addDays(rand(1, 3));

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'availability_deadline' => $now->copy()->subDays(rand(1, 5))->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Date per tornei in bozza
     */
    private function generateDraftDates(Carbon $now): array
    {
        $startDate = $now->copy()->addMonths(rand(2, 6))->addDays(rand(0, 29));
        $endDate = $startDate->copy()->addDays(rand(1, 3));

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'availability_deadline' => $startDate->copy()->subDays(14)->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Date per tornei programmati
     */
    private function generateScheduledDates(Carbon $now): array
    {
        $startDate = $now->copy()->addMonths(rand(1, 4))->addDays(rand(0, 29));
        $endDate = $startDate->copy()->addDays(rand(1, 3));

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'availability_deadline' => $startDate->copy()->subDays(14)->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Genera nome torneo
     */
    private function generateTournamentName(Zone $zone, TournamentType $type, string $status, int $sequence): string
    {
        $baseNames = SeederHelper::generateTournamentNames($zone->code, $type->name);
        $baseName = $baseNames[array_rand($baseNames)];

        $year = Carbon::now()->year;
        $edition = rand(1, 25);

        return "{$baseName} {$year} - {$edition}° Edizione";
    }

    /**
     * Genera descrizione torneo
     */
    private function generateTournamentDescription(TournamentType $type, string $status): string
    {
        $descriptions = [
            'Torneo prestigioso per golfisti di tutte le categorie',
            'Competizione annuale di grande tradizione',
            'Evento sportivo di alto livello tecnico',
            'Gara ufficiale del calendario regionale',
            'Torneo con montepremi significativo'
        ];

        $baseDescription = $descriptions[array_rand($descriptions)];

        return "{$baseDescription}. Categoria: {$type->name}. Status attuale: {$status}.";
    }

    /**
     * Genera quota iscrizione
     */
    private function generateEntryFee(TournamentType $type): int
    {
        return match ($type->priority_level) {
            1 => rand(30, 50),      // Gare sociali
            2 => rand(50, 80),      // Trofei zona
            3 => rand(80, 120),     // Campionati zonali
            4 => rand(120, 180),    // Open nazionali
            5 => rand(180, 250),    // Campionati italiani
            6 => rand(250, 400),    // Major italiani
            default => rand(50, 100)
        };
    }

    /**
     * Genera montepremi
     */
    private function generatePrizePool(TournamentType $type): int
    {
        return match ($type->priority_level) {
            1 => rand(500, 1000),      // Gare sociali
            2 => rand(1000, 2500),     // Trofei zona
            3 => rand(2500, 5000),     // Campionati zonali
            4 => rand(5000, 10000),    // Open nazionali
            5 => rand(10000, 25000),   // Campionati italiani
            6 => rand(25000, 50000),   // Major italiani
            default => rand(1000, 5000)
        };
    }

    /**
     * Genera piano maltempo
     */
    private function generateWeatherBackupPlan(): string
    {
        $plans = [
            'Rinvio di 24 ore in caso di maltempo',
            'Riduzione giro a 9 buche se pioggia',
            'Sospensione temporanea durante temporali',
            'Campo coperto disponibile per putting',
            'Decisione del Direttore di Gara'
        ];

        return $plans[array_rand($plans)];
    }

    /**
     * Crea alcuni tornei nazionali
     */
    private function createNationalTournaments(): int
    {
        $nationalTypes = TournamentType::where('is_national', true)->get();
        $nationalAdmin = User::where('user_type', 'national_admin')->first();

        if ($nationalTypes->isEmpty() || !$nationalAdmin) {
            $this->command->warn("⚠️ Impossibile creare tornei nazionali: dati insufficienti");
            return 0;
        }

        $created = 0;
        $zones = Zone::inRandomOrder()->limit(3)->get(); // Seleziona 3 zone casuali

        foreach ($nationalTypes as $type) {
            $zone = $zones->random();
            $club = Club::where('zone_id', $zone->id)->inRandomOrder()->first();

            if (!$club) continue;

            $dates = $this->generateOpenDates(Carbon::now());

            $tournament = Tournament::create([
                'name' => "Campionato Nazionale {$type->name} " . Carbon::now()->year,
                'description' => "Torneo nazionale di prestigio - {$type->description}",
                'zone_id' => $club->zone_id, // Nazionale
                'club_id' => $club->id,
                'tournament_type_id' => $type->id,
                'start_date' => $dates['start_date'],
                'end_date' => $dates['end_date'],
                'availability_deadline' => $dates['availability_deadline'],
                'status' => 'open',
                'notes' => "Torneo nazionale aperto a tutte le zone",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("  🌍 NAZIONALE: {$tournament->name} - {$club->name}");
            $created++;
        }

        return $created;
    }

    /**
     * Valida tornei creati
     */
    private function validateTournaments(): void
    {
        $this->command->info('🔍 Validando tornei creati...');

        // Verifica che tutti i tornei abbiano zone e club coerenti
        $inconsistentTournaments = Tournament::whereNotNull('zone_id')
            ->whereHas('club', function ($query) {
                $query->whereColumn('clubs.zone_id', '!=', 'tournaments.zone_id');
            })->count();

        if ($inconsistentTournaments > 0) {
            $this->command->error("❌ Errore: {$inconsistentTournaments} tornei con zone/club inconsistenti");
            return;
        }

        // Verifica date logiche
        $invalidDates = Tournament::whereColumn('start_date', '>', 'end_date')->count();
        if ($invalidDates > 0) {
            $this->command->error("❌ Errore: {$invalidDates} tornei con date invalide");
            return;
        }

        // Verifica status
        $validStatuses = ['draft', 'open', 'closed', 'assigned', 'completed', 'cancelled'];
        $invalidStatus = Tournament::whereNotIn('status', $validStatuses)->count();
        if ($invalidStatus > 0) {
            $this->command->error("❌ Errore: {$invalidStatus} tornei con status invalidi");
            return;
        }

        $this->command->info('✅ Validazione tornei completata con successo');
    }

    /**
     * Mostra riassunto tornei creati
     */
    private function showTournamentSummary(): void
    {
        $this->command->info('');
        $this->command->info('🏆 RIASSUNTO TORNEI GOLF:');
        $this->command->info('=====================================');

        // Riassunto per zona
        $zones = Zone::orderBy('code')->get();
        foreach ($zones as $zone) {
            $tournamentCount = Tournament::where('zone_id', $zone->id)->count();
            $this->command->info("📍 {$zone->code}: {$tournamentCount} tornei");

            // Status breakdown per zona
            $statuses = Tournament::where('zone_id', $zone->id)
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            foreach ($statuses as $status => $count) {
                $emoji = $this->getStatusEmoji($status);
                $this->command->info("   {$emoji} {$status}: {$count}");
            }
            $this->command->info('');
        }

        // Tornei nazionali
        $nationalTournaments = Tournament::whereNull('zone_id')->count();
        $this->command->info("🌍 NAZIONALI: {$nationalTournaments} tornei");

        // Statistiche generali
        $this->command->info('');
        $this->command->info('📊 STATISTICHE PER STATUS:');
        $allStatuses = Tournament::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->orderBy('count', 'desc')
            ->pluck('count', 'status');

        foreach ($allStatuses as $status => $count) {
            $emoji = $this->getStatusEmoji($status);
            $this->command->info("   {$emoji} {$status}: {$count} tornei");
        }

        $totalTournaments = Tournament::count();
        $this->command->info('');
        $this->command->info("📈 TOTALE TORNEI: {$totalTournaments}");

        // Statistiche per tipologia
        $this->command->info('');
        $this->command->info('🏅 TORNEI PER TIPOLOGIA:');
        $types = Tournament::with('tournamentType')
            ->get()
            ->groupBy('tournamentType.name')
            ->map->count();

        foreach ($types as $typeName => $count) {
            $this->command->info("   {$typeName}: {$count} tornei");
        }

        $this->command->info('=====================================');
        $this->command->info('');
    }

    /**
     * Ottieni emoji per status
     */
    private function getStatusEmoji(string $status): string
    {
        return match ($status) {
            'draft' => '📝',
            'open' => '🟢',
            'closed' => '🟡',
            'assigned' => '✅',
            'completed' => '🏁',
            'cancelled' => '❌',
            default => '❓'
        };
    }
}
