<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TournamentType;

class TournamentTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tournamentTypes = [
            [
                'name' => 'Open Nazionale',
                'short_name' => 'OPEN_NAZ',
                'description' => 'Tornei aperti di livello nazionale',
                'is_national' => true,
                'required_level' => 'nazionale',
                'level' => 'nazionale',
                'min_referees' => 2,
                'max_referees' => 4,
                'sort_order' => 10,
                'is_active' => true,
                'settings' => [
                    'required_referee_level' => 'nazionale',
                    'min_referees' => 2,
                    'max_referees' => 4,
                    'visibility_zones' => 'all',
                    'special_requirements' => 'Esperienza minima 3 anni',
                ],
            ],
            [
                'name' => 'Campionato Zonale',
                'short_name' => 'CAMP_ZONE',
                'description' => 'Campionati organizzati dalle zone territoriali',
                'is_national' => false,
                'required_level' => 'regionale',
                'level' => 'zonale',
                'min_referees' => 1,
                'max_referees' => 2,
                'sort_order' => 20,
                'is_active' => true,
                'settings' => [
                    'required_referee_level' => 'regionale',
                    'min_referees' => 1,
                    'max_referees' => 2,
                    'visibility_zones' => 'own',
                ],
            ],
            [
                'name' => 'Gara Sociale',
                'short_name' => 'GARA_SOC',
                'description' => 'Gare sociali dei circoli',
                'is_national' => false,
                'required_level' => 'primo_livello',
                'level' => 'zonale',
                'min_referees' => 1,
                'max_referees' => 1,
                'sort_order' => 30,
                'is_active' => true,
                'settings' => [
                    'required_referee_level' => 'primo_livello',
                    'min_referees' => 1,
                    'max_referees' => 1,
                    'visibility_zones' => 'own',
                ],
            ],
            [
                'name' => 'Pro-Am',
                'short_name' => 'PRO_AM',
                'description' => 'Tornei professionali dilettanti',
                'is_national' => true,
                'required_level' => 'nazionale',
                'level' => 'nazionale',
                'min_referees' => 3,
                'max_referees' => 5,
                'sort_order' => 5,
                'is_active' => true,
                'settings' => [
                    'required_referee_level' => 'nazionale',
                    'min_referees' => 3,
                    'max_referees' => 5,
                    'visibility_zones' => 'all',
                    'special_requirements' => 'Certificazione Pro-Am richiesta',
                ],
            ],
            [
                'name' => 'Trofeo Giovanile',
                'short_name' => 'TROF_GIOV',
                'description' => 'Tornei per categorie giovanili',
                'is_national' => false,
                'required_level' => 'primo_livello',
                'level' => 'zonale',
                'min_referees' => 1,
                'max_referees' => 2,
                'sort_order' => 40,
                'is_active' => true,
                'settings' => [
                    'required_referee_level' => 'primo_livello',
                    'min_referees' => 1,
                    'max_referees' => 2,
                    'visibility_zones' => 'own',
                    'special_requirements' => 'Esperienza con categorie giovanili',
                ],
            ],
        ];

        foreach ($tournamentTypes as $typeData) {
            TournamentType::updateOrCreate(
                ['short_name' => $typeData['short_name']],
                $typeData
            );
        }

        $this->command->info('✅ Tipi torneo creati con successo: ' . count($tournamentTypes));
    }
}
