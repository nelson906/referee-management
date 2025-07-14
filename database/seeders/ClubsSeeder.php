<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Club;
use App\Models\Zone;

class ClubsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zones = Zone::all();

        if ($zones->isEmpty()) {
            $this->command->warn('⚠️ Nessuna zona trovata. Esegui prima ZonesSeeder.');
            return;
        }

        $clubs = [];

        // Clubs per ogni zona
        foreach ($zones as $zone) {
            $zoneClubs = [
                [
                    'name' => 'Golf Club ' . $zone->name,
                    'code' => 'GC' . $zone->code,
                    'city' => $zone->city,
                    'province' => $this->getProvinceCode($zone->city),
                    'email' => 'info@gc' . strtolower($zone->code) . '.it',
                    'phone' => '+39 0' . rand(10, 99) . ' ' . rand(100000, 999999),
                    'address' => 'Via del Golf ' . rand(1, 100),
                    'contact_person' => 'Direttore Sportivo',
                    'zone_id' => $zone->id,
                    'notes' => 'Circolo storico della zona ' . $zone->name,
                    'is_active' => true,
                ],
                [
                    'name' => 'Country Club ' . $zone->name,
                    'code' => 'CC' . $zone->code,
                    'city' => $zone->city,
                    'province' => $this->getProvinceCode($zone->city),
                    'email' => 'segreteria@cc' . strtolower($zone->code) . '.it',
                    'phone' => '+39 0' . rand(10, 99) . ' ' . rand(100000, 999999),
                    'address' => 'Viale Country ' . rand(1, 50),
                    'contact_person' => 'Segretario Generale',
                    'zone_id' => $zone->id,
                    'notes' => 'Country club moderno',
                    'is_active' => true,
                ],
            ];

            $clubs = array_merge($clubs, $zoneClubs);
        }

        // Clubs aggiuntivi prestigiosi
        $prestigiousClubs = [
            [
                'name' => 'Royal Golf Club Italia',
                'code' => 'ROYAL',
                'city' => 'Roma',
                'province' => 'RM',
                'email' => 'royal@royalgolf.it',
                'phone' => '+39 06 12345678',
                'address' => 'Via Appia Antica 100',
                'contact_person' => 'Presidente del Club',
                'zone_id' => $zones->where('code', 'CENTRO')->first()?->id ?? $zones->first()->id,
                'notes' => 'Club storico di prestigio internazionale',
                'is_active' => true,
            ],
            [
                'name' => 'Circolo Golf Nazionale',
                'code' => 'NAZIONALE',
                'city' => 'Milano',
                'province' => 'MI',
                'email' => 'info@golfnazionale.it',
                'phone' => '+39 02 87654321',
                'address' => 'Corso di Porta Ticinese 200',
                'contact_person' => 'Direttore Tecnico',
                'zone_id' => $zones->where('code', 'NORD')->first()?->id ?? $zones->first()->id,
                'notes' => 'Sede di tornei nazionali e internazionali',
                'is_active' => true,
            ],
        ];

        $clubs = array_merge($clubs, $prestigiousClubs);

        foreach ($clubs as $clubData) {
            Club::updateOrCreate(
                ['code' => $clubData['code']],
                $clubData
            );
        }

        $this->command->info('✅ Circoli creati con successo: ' . count($clubs));
    }

    /**
     * Get province code from city name
     */
    private function getProvinceCode(string $city): string
    {
        $provinces = [
            'Roma' => 'RM',
            'Milano' => 'MI',
            'Napoli' => 'NA',
            'Palermo' => 'PA',
            'Cagliari' => 'CA',
            'Torino' => 'TO',
            'Firenze' => 'FI',
            'Bologna' => 'BO',
            'Bari' => 'BA',
            'Catania' => 'CT',
        ];

        return $provinces[$city] ?? 'XX';
    }
}
