<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Zone;
use Database\Seeders\Helpers\SeederHelper;

class ZonesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌍 Creando Zone Geografiche Golf Italia...');

        // Elimina zone esistenti per evitare duplicati
        Zone::truncate();

        $zones = SeederHelper::getZones();

        foreach ($zones as $index => $zoneData) {
            $zone = Zone::create([
                'name' => $zoneData['name'],
                'code' => $zoneData['code'],
                'description' => $zoneData['description'],
                'is_active' => $zoneData['is_active'],
                'is_national' => $zoneData['is_national'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("✅ Zona creata: {$zone->code} - {$zone->name}");
        }

        $this->command->info("🏆 Zone create con successo: " . Zone::count() . " zone totali");

        // Verifica integrità
        $this->validateZones();
    }

    /**
     * Valida l'integrità delle zone create
     */
    private function validateZones(): void
    {
        $this->command->info('🔍 Validando integrità zone...');

        // Verifica che ci siano esattamente 7 zone
        $zoneCount = Zone::count();
        if ($zoneCount !== 7) {
            $this->command->error("❌ Errore: dovrebbero esserci 7 zone, trovate {$zoneCount}");
            return;
        }

        // Verifica univocità codici
        $codes = Zone::pluck('code')->toArray();
        if (count($codes) !== count(array_unique($codes))) {
            $this->command->error("❌ Errore: codici zona non univoci");
            return;
        }

        // Verifica formato codici
        foreach ($codes as $code) {
            if (!preg_match('/^SZR[1-7]$/', $code)) {
                $this->command->error("❌ Errore: formato codice zona invalido: {$code}");
                return;
            }
        }

        // Verifica che tutte le zone siano attive
        $inactiveZones = Zone::where('is_active', false)->count();
        if ($inactiveZones > 0) {
            $this->command->warn("⚠️ Attenzione: {$inactiveZones} zone non attive");
        }

        // Verifica che nessuna zona sia nazionale (tutte zonali)
        $nationalZones = Zone::where('is_national', true)->count();
        if ($nationalZones > 0) {
            $this->command->warn("⚠️ Attenzione: {$nationalZones} zone marcate come nazionali");
        }

        $this->command->info('✅ Validazione zone completata con successo');

        // Mostra summary
        $this->showZoneSummary();
    }

    /**
     * Mostra riassunto delle zone create
     */
    private function showZoneSummary(): void
    {
        $this->command->info('');
        $this->command->info('📊 RIASSUNTO ZONE GEOGRAFICHE:');
        $this->command->info('=====================================');

        $zones = Zone::orderBy('code')->get();
        foreach ($zones as $zone) {
            $status = $zone->is_active ? '🟢 ATTIVA' : '🔴 INATTIVA';
            $type = $zone->is_national ? '🌍 NAZIONALE' : '📍 ZONALE';

            $this->command->info(sprintf(
                "%-6s | %-25s | %s | %s",
                $zone->code,
                $zone->name,
                $status,
                $type
            ));
        }

        $this->command->info('=====================================');
        $this->command->info("Totale: {$zones->count()} zone geografiche");
        $this->command->info('');
    }
}
