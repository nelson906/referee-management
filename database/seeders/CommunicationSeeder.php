<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Communication;
use App\Models\User;
use App\Models\Zone;

class CommunicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $nationalAdmin = User::where('user_type', 'national_admin')->first();
        $zones = Zone::all();

        // Comunicazioni globali
        Communication::create([
            'title' => 'Benvenuto nel Sistema Golf',
            'content' => 'Benvenuti nel nuovo sistema di gestione arbitri golf. Qui potrete gestire disponibilità, visualizzare assegnazioni e comunicare con il comitato regionale.',
            'type' => 'announcement',
            'status' => 'published',
            'priority' => 'normal',
            'author_id' => $nationalAdmin->id,
            'published_at' => now(),
        ]);

        Communication::create([
            'title' => 'Manutenzione Programmata Sistema',
            'content' => 'Il sistema sarà offline per manutenzione domenica dalle 02:00 alle 06:00. Durante questo periodo non sarà possibile accedere alle funzionalità.',
            'type' => 'maintenance',
            'status' => 'published',
            'priority' => 'high',
            'author_id' => $nationalAdmin->id,
            'scheduled_at' => now()->addDays(3),
            'expires_at' => now()->addDays(7),
            'published_at' => now(),
        ]);

        // Comunicazioni per zona
        foreach ($zones->take(3) as $zone) {
            $zoneAdmin = User::where('zone_id', $zone->id)
                            ->where('user_type', 'zone_admin')
                            ->first();

            if ($zoneAdmin) {
                Communication::create([
                    'title' => "Riunione Arbitri {$zone->name}",
                    'content' => "Si comunica che la prossima riunione degli arbitri della zona {$zone->name} si terrà il 15 del mese presso la sede regionale. La partecipazione è obbligatoria per tutti gli arbitri di primo livello e superiori.",
                    'type' => 'announcement',
                    'status' => 'published',
                    'priority' => 'normal',
                    'zone_id' => $zone->id,
                    'author_id' => $zoneAdmin->id,
                    'published_at' => now(),
                ]);
            }
        }

        $this->command->info('✅ Communications seeded successfully!');
    }
}
