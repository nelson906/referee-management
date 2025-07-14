<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Zone;
use App\Models\Club;
use Carbon\Carbon;

class TournamentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zones = Zone::all();
        $types = TournamentType::all();
        $clubs = Club::all();

        if ($zones->isEmpty() || $types->isEmpty() || $clubs->isEmpty()) {
            $this->command->warn('⚠️ Mancano dati prerequisiti. Esegui prima gli altri seeder.');
            return;
        }

        $tournaments = [];

        // Tornei futuri (prossimi 6 mesi)
        for ($month = 0; $month < 6; $month++) {
            $startDate = Carbon::now()->addMonths($month)->startOfMonth();

            foreach ($zones as $zone) {
                $zoneClubs = $clubs->where('zone_id', $zone->id);
                if ($zoneClubs->isEmpty()) continue;

                // 2-3 tornei per zona per mese
                for ($i = 0; $i < rand(2, 3); $i++) {
                    $tournamentDate = $startDate->copy()->addDays(rand(0, 28));
                    $club = $zoneClubs->random();
                    $type = $types->random();

                    // Determina durata in base al tipo
                    $duration = $type->is_national ? rand(2, 4) : rand(1, 2);

                    $tournaments[] = [
                        'name' => $this->generateTournamentName($type, $club, $zone),
                        'start_date' => $tournamentDate,
                        'end_date' => $tournamentDate->copy()->addDays($duration - 1),
                        'availability_deadline' => $tournamentDate->copy()->subDays(rand(7, 14)),
                        'club_id' => $club->id,
                        'tournament_type_id' => $type->id,
                        'zone_id' => $zone->id,
                        'notes' => $this->generateTournamentNotes($type),
                        'status' => $this->determineStatus($tournamentDate),
                        'document_version' => 1,
                    ];
                }
            }
        }

        // Tornei storici (ultimi 3 mesi)
        for ($month = 1; $month <= 3; $month++) {
            $startDate = Carbon::now()->subMonths($month)->startOfMonth();

            foreach ($zones->take(2) as $zone) { // Solo alcune zone per non esagerare
                $zoneClubs = $clubs->where('zone_id', $zone->id);
                if ($zoneClubs->isEmpty()) continue;

                for ($i = 0; $i < rand(1, 2); $i++) {
                    $tournamentDate = $startDate->copy()->addDays(rand(0, 28));
                    $club = $zoneClubs->random();
                    $type = $types->random();

                    $tournaments[] = [
                        'name' => $this->generateTournamentName($type, $club, $zone) . ' (Passato)',
                        'start_date' => $tournamentDate,
                        'end_date' => $tournamentDate->copy()->addDays(rand(1, 3)),
                        'availability_deadline' => $tournamentDate->copy()->subDays(rand(7, 14)),
                        'club_id' => $club->id,
                        'tournament_type_id' => $type->id,
                        'zone_id' => $zone->id,
                        'notes' => 'Torneo completato',
                        'status' => 'completed',
                        'document_version' => 1,
                    ];
                }
            }
        }

        foreach ($tournaments as $tournamentData) {
            Tournament::create($tournamentData);
        }

        $this->command->info('✅ Tornei creati con successo: ' . count($tournaments));
    }

    /**
     * Generate tournament name
     */
    private function generateTournamentName(TournamentType $type, Club $club, Zone $zone): string
    {
        $names = [
            'Open ' . $club->name,
            'Trofeo ' . $zone->name,
            'Coppa ' . $club->code,
            'Memorial ' . $this->getRandomName(),
            'Campionato ' . $zone->name,
        ];

        return $names[array_rand($names)] . ' - ' . $type->name;
    }

    /**
     * Generate tournament notes
     */
    private function generateTournamentNotes(TournamentType $type): string
    {
        $notes = [
            'Torneo stroke play 18 buche',
            'Gara medal play con classifica scratch e handicap',
            'Louisiana a coppie',
            'Fourball better ball',
            'Greensome modificato',
        ];

        return $notes[array_rand($notes)] . '. ' . $type->description;
    }

    /**
     * Determine tournament status based on date
     */
    private function determineStatus(Carbon $date): string
    {
        $now = Carbon::now();

        if ($date->lt($now)) {
            return 'completed';
        }

        if ($date->diffInDays($now) <= 7) {
            return 'assigned';
        }

        if ($date->diffInDays($now) <= 21) {
            return rand(0, 1) ? 'closed' : 'assigned';
        }

        return 'open';
    }

    /**
     * Get random name for memorial tournaments
     */
    private function getRandomName(): string
    {
        $names = [
            'Giovanni Bianchi',
            'Maria Rossi',
            'Luigi Verdi',
            'Anna Neri',
            'Francesco Blu',
            'Giulia Gialli',
        ];

        return $names[array_rand($names)];
    }
}
