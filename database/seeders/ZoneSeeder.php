<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\Zone;
use Database\Seeders\Helpers\SeederHelper;

class ZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌍 Creando Zone Geografiche Golf Italia...');

        // ✅ FIXED: Gestisci foreign keys prima del truncate
        Schema::disableForeignKeyConstraints();

        try {
            // Elimina zone esistenti per evitare duplicati
            Zone::truncate();

            $zones = SeederHelper::getZones();

            foreach ($zones as $index => $zoneData) {
                // Genera codice zona automaticamente se non presente
                $code = $zoneData['code'] ?? 'SZR' . ($index + 1);

                $zone = Zone::create([
                    'name' => $code,
                    'code' => $code,
                    'description' => $zoneData['name'],
                    'is_national' => $zoneData['is_national'] ?? false,
                    'is_active' => $zoneData['is_active'] ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->command->info("✅ Zona creata: {$zone->code} - {$zone->name}");
            }

            $this->command->info("🏆 Zone create con successo: " . Zone::count() . " zone totali");

            // Verifica integrità
            $this->validateZones();

        } finally {
            // ✅ FIXED: Riabilita foreign keys
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Valida l'integrità delle zone create
     */
    private function validateZones(): void
    {
        $this->command->info('🔍 Validando integrità zone...');

        // Verifica che ci siano esattamente 8 zone
        $zoneCount = Zone::count();
        if ($zoneCount !== 8) {
            $this->command->error("❌ Errore: dovrebbero esserci 8 zone, trovate {$zoneCount}");
            return;
        }

        // Verifica univocità nomi
        $names = Zone::pluck('name')->toArray();
        if (count($names) !== count(array_unique($names))) {
            $this->command->error("❌ Errore: nomi zona non univoci");
            return;
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

        $zones = Zone::orderBy('name')->get();
        foreach ($zones as $zone) {
            $type = $zone->is_national ? '🌍 NAZIONALE' : '📍 ZONALE';

            $this->command->info(sprintf(
                "%-30s | %s",
                $zone->name,
                $type
            ));
        }

        $this->command->info('=====================================');
        $this->command->info("Totale: {$zones->count()} zone geografiche");
        $this->command->info('');
    }
}
