<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Zone;
use App\Helpers\RefereeLevelsHelper;  // ✅ IMPORTA L'HELPER
use Database\Seeders\Helpers\SeederHelper;

class RefereeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('⚖️ Creando Arbitri Golf per Zone...');

        // Gestisci foreign keys
        Schema::disableForeignKeyConstraints();

        try {
            $zones = Zone::orderBy('code')->get();
            if ($zones->isEmpty()) {
                $this->command->error('❌ Nessuna zona trovata. Esegui prima ZoneSeeder.');
                return;
            }

            // ✅ FIXED: Usa valori hardcoded invece del config
            $refereesPerZone = 13;  // Valore fisso
            $totalReferees = 0;

            foreach ($zones as $zone) {
                $refereesCreated = $this->createRefereesForZone($zone, $refereesPerZone);
                $totalReferees += $refereesCreated;
            }

            $this->validateReferees($refereesPerZone);
            $this->showRefereeSummary();

            $this->command->info("🏆 Arbitri creati con successo: {$totalReferees} arbitri totali");

        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Crea arbitri per una specifica zona
     */
    private function createRefereesForZone(Zone $zone, int $refereesPerZone): int
    {
        $this->command->info("📍 Creando arbitri per zona {$zone->code} - {$zone->name}...");

        // Genera dati arbitri direttamente
        $refereeData = $this->generateRefereeData($zone, $refereesPerZone);
        $refereesCreated = 0;

        foreach ($refereeData as $referee) {
            // ✅ NORMALIZZA IL LIVELLO USANDO L'HELPER
            $normalizedLevel = RefereeLevelsHelper::normalize($referee['level']);

            if (!$normalizedLevel) {
                $this->command->warn("⚠️ Livello '{$referee['level']}' non riconosciuto, uso '1_livello'");
                $normalizedLevel = '1_livello';
            }

            $user = User::create([
                'name' => $referee['name'],
                'email' => $referee['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),  // ✅ FIXED: Password hardcoded
                'user_type' => 'referee',
                'zone_id' => $zone->id,
                'level' => $normalizedLevel,  // ✅ USA IL LIVELLO NORMALIZZATO
                'referee_code' => $referee['referee_code'],
                'is_active' => $referee['is_active'] ?? true,
                'phone' => $referee['phone'] ?? null,
                'city' => $referee['city'] ?? $zone->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Mostra livello normalizzato per debug
            $originalLevel = $referee['level'];
            $this->command->info("  ✅ {$user->name} ({$originalLevel} → {$normalizedLevel}) - {$user->referee_code}");
            $refereesCreated++;
        }

        return $refereesCreated;
    }

    /**
     * Valida arbitri creati
     */
    private function validateReferees(int $refereesPerZone): void
    {
        $this->command->info('🔍 Validando arbitri creati...');

        $zones = Zone::count();
        $expectedReferees = $zones * $refereesPerZone;  // ✅ FIXED: Usa parametro
        $actualReferees = User::where('user_type', 'referee')->count();

        if ($actualReferees !== $expectedReferees) {
            $this->command->error("❌ Errore: attesi {$expectedReferees} arbitri, trovati {$actualReferees}");
            return;
        }

        // Verifica che ogni zona abbia i suoi arbitri
        $zonesWithoutReferees = Zone::whereDoesntHave('users', function($query) {
            $query->where('user_type', 'referee');
        })->count();

        if ($zonesWithoutReferees > 0) {
            $this->command->error("❌ Errore: {$zonesWithoutReferees} zone senza arbitri");
            return;
        }

        // Verifica codici arbitro univoci
        $totalCodes = User::where('user_type', 'referee')->count();
        $uniqueCodes = User::where('user_type', 'referee')->distinct('referee_code')->count();
        if ($totalCodes !== $uniqueCodes) {
            $this->command->error("❌ Errore: codici arbitro non univoci");
            return;
        }

        // Verifica livelli validi usando l'helper
        $invalidLevels = User::where('user_type', 'referee')->get()->filter(function($user) {
            return !RefereeLevelsHelper::isValid($user->level);
        })->count();

        if ($invalidLevels > 0) {
            $this->command->error("❌ Errore: {$invalidLevels} arbitri con livelli non validi");
            return;
        }

        $this->command->info('✅ Validazione arbitri completata con successo');
    }

    /**
     * Mostra riassunto arbitri creati
     */
    private function showRefereeSummary(): void
    {
        $this->command->info('');
        $this->command->info('⚖️ RIASSUNTO ARBITRI GOLF:');
        $this->command->info('=====================================');

        $zones = Zone::with(['users' => function($query) {
            $query->where('user_type', 'referee')->orderBy('level')->orderBy('name');
        }])->orderBy('code')->get();

        foreach ($zones as $zone) {
            $this->command->info("📍 {$zone->code} - {$zone->name}:");

            // Raggruppa per livello
            $refereesByLevel = $zone->users->groupBy('level');

            foreach ($refereesByLevel as $level => $referees) {
                $levelLabel = RefereeLevelsHelper::getLabel($level);
                $this->command->info("   {$levelLabel}: {$referees->count()} arbitri");

                foreach ($referees as $referee) {
                    $this->command->info("     • {$referee->name} ({$referee->referee_code}) - {$referee->email}");
                }
            }

            $this->command->info("     Totale arbitri zona: {$zone->users->count()}");
            $this->command->info('');
        }

        // Statistiche generali
        $this->command->info('📊 STATISTICHE GENERALI:');
        $totalReferees = User::where('user_type', 'referee')->count();
        $totalZones = Zone::count();
        $avgRefereesPerZone = round($totalReferees / $totalZones, 1);

        $this->command->info("   Arbitri totali: {$totalReferees}");
        $this->command->info("   Zone coperte: {$totalZones}");
        $this->command->info("   Media arbitri per zona: {$avgRefereesPerZone}");

        // Statistiche per livello
        $levelStats = User::where('user_type', 'referee')
            ->selectRaw('level, count(*) as count')
            ->groupBy('level')
            ->get();

        $this->command->info('');
        $this->command->info('📈 DISTRIBUZIONE PER LIVELLO:');
        foreach ($levelStats as $stat) {
            $levelLabel = RefereeLevelsHelper::getLabel($stat->level);
            $this->command->info("   {$levelLabel}: {$stat->count}");
        }

        $this->command->info('=====================================');
        $this->command->info('');
    }

    /**
     * Genera dati arbitri per una zona
     */
    private function generateRefereeData(Zone $zone, int $count): array
    {
        $names = SeederHelper::generateRefereeNames();
        $referees = [];

        // ✅ FIXED: Distribuzione livelli hardcoded
        $levelDistribution = [
            'aspirante' => 3,
            'primo_livello' => 4,
            'regionale' => 3,
            'nazionale' => 2,
            'internazionale' => 1
        ];

        $levels = [];

        // Crea array livelli
        foreach ($levelDistribution as $level => $qty) {
            for ($i = 0; $i < $qty; $i++) {
                $levels[] = $level;
            }
        }

        // Completa se necessario
        while (count($levels) < $count) {
            $levels[] = 'primo_livello';
        }
        $levels = array_slice($levels, 0, $count);
        shuffle($levels);

        // Estrai numero zona dal codice (es. SZR1 -> 1)
        $zoneNumber = (int) str_replace('SZR', '', $zone->code);

        for ($i = 0; $i < $count; $i++) {
            $firstName = $names['firstNames'][array_rand($names['firstNames'])];
            $lastName = $names['lastNames'][array_rand($names['lastNames'])];
            $name = $firstName . ' ' . $lastName;

            $refereeCode = $zone->code . '-REF-' . sprintf('%03d', $i + 1);

            // ✅ FIXED: Email sempre univoca con indice
            $emailName = strtolower(str_replace(' ', '.', $name));
            $emailName = preg_replace('/[^a-z0-9.]/', '', $emailName);
            $email = $emailName . '.' . strtolower($zone->code) . '.' . sprintf('%03d', $i + 1) . '@golf.it';

            $referees[] = [
                'name' => $name,
                'email' => $email,
                'level' => $levels[$i],
                'referee_code' => $refereeCode,
                'phone' => $this->generatePhone($zoneNumber),
                'city' => $this->getCityForZone($zoneNumber),
                'is_active' => true,
            ];
        }

        return $referees;
    }

    /**
     * Genera telefono per zona
     */
    private function generatePhone(int $zoneNumber): string
    {
        $areaCodes = [
            1 => '011', 2 => '02', 3 => '041', 4 => '051',
            5 => '055', 6 => '06', 7 => '081'
        ];

        $areaCode = $areaCodes[$zoneNumber] ?? '06';
        return '+39 ' . $areaCode . ' ' . rand(1000000, 9999999);
    }

    /**
     * Ottiene città per zona
     */
    private function getCityForZone(int $zoneNumber): string
    {
        $cities = [
            1 => 'Torino', 2 => 'Milano', 3 => 'Venezia',
            4 => 'Bologna', 5 => 'Firenze', 6 => 'Roma', 7 => 'Napoli'
        ];

        return $cities[$zoneNumber] ?? 'Roma';
    }
}
