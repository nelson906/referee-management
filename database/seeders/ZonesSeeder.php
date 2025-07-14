<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Zone;

class ZonesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zones = [
            [
                'name' => 'Zona Centro',
                'code' => 'CENTRO',
                'description' => 'Zona territoriale del Centro Italia',
                'region' => 'Centro',
                'contact_email' => 'centro@figc.it',
                'contact_phone' => '+39 06 12345678',
                'address' => 'Via del Golf 123',
                'city' => 'Roma',
                'postal_code' => '00100',
                'website' => 'https://centro.figc.it',
                'is_active' => true,
                'sort_order' => 10,
                'settings' => [
                    'max_tournaments_per_month' => 20,
                    'requires_approval' => true,
                    'auto_assign_referees' => false,
                ],
                'coordinates' => '41.9028,12.4964', // Roma
            ],
            [
                'name' => 'Zona Nord',
                'code' => 'NORD',
                'description' => 'Zona territoriale del Nord Italia',
                'region' => 'Nord',
                'contact_email' => 'nord@figc.it',
                'contact_phone' => '+39 02 12345678',
                'address' => 'Via Milano 456',
                'city' => 'Milano',
                'postal_code' => '20100',
                'website' => 'https://nord.figc.it',
                'is_active' => true,
                'sort_order' => 20,
                'settings' => [
                    'max_tournaments_per_month' => 25,
                    'requires_approval' => true,
                    'auto_assign_referees' => false,
                ],
                'coordinates' => '45.4642,9.1900', // Milano
            ],
            [
                'name' => 'Zona Sud',
                'code' => 'SUD',
                'description' => 'Zona territoriale del Sud Italia',
                'region' => 'Sud',
                'contact_email' => 'sud@figc.it',
                'contact_phone' => '+39 081 12345678',
                'address' => 'Via Napoli 789',
                'city' => 'Napoli',
                'postal_code' => '80100',
                'website' => 'https://sud.figc.it',
                'is_active' => true,
                'sort_order' => 30,
                'settings' => [
                    'max_tournaments_per_month' => 15,
                    'requires_approval' => true,
                    'auto_assign_referees' => false,
                ],
                'coordinates' => '40.8518,14.2681', // Napoli
            ],
            [
                'name' => 'Zona Sicilia',
                'code' => 'SICILIA',
                'description' => 'Zona territoriale della Sicilia',
                'region' => 'Isole',
                'contact_email' => 'sicilia@figc.it',
                'contact_phone' => '+39 091 12345678',
                'address' => 'Via Palermo 321',
                'city' => 'Palermo',
                'postal_code' => '90100',
                'website' => 'https://sicilia.figc.it',
                'is_active' => true,
                'sort_order' => 40,
                'settings' => [
                    'max_tournaments_per_month' => 10,
                    'requires_approval' => true,
                    'auto_assign_referees' => false,
                ],
                'coordinates' => '38.1157,13.3613', // Palermo
            ],
            [
                'name' => 'Zona Sardegna',
                'code' => 'SARDEGNA',
                'description' => 'Zona territoriale della Sardegna',
                'region' => 'Isole',
                'contact_email' => 'sardegna@figc.it',
                'contact_phone' => '+39 070 12345678',
                'address' => 'Via Cagliari 654',
                'city' => 'Cagliari',
                'postal_code' => '09100',
                'website' => 'https://sardegna.figc.it',
                'is_active' => true,
                'sort_order' => 50,
                'settings' => [
                    'max_tournaments_per_month' => 8,
                    'requires_approval' => true,
                    'auto_assign_referees' => false,
                ],
                'coordinates' => '39.2238,9.1217', // Cagliari
            ],
        ];

        foreach ($zones as $zoneData) {
            Zone::updateOrCreate(
                ['code' => $zoneData['code']],
                $zoneData
            );
        }

        $this->command->info('✅ Zone create con successo: ' . count($zones));
    }
}
