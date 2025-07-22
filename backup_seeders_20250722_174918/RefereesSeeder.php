<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\Helpers\SeederHelper;

class RefereesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('⚖️ Creando Arbitri Golf per Zone...');

        // Elimina arbitri esistenti per evitare duplicati
        User::where('user_type', 'referee')->delete();

        $zones = Zone::orderBy('code')->get();
        $totalReferees = 0;

        foreach ($zones as $zone) {
            $refereesCreated = $this->createRefereesForZone($zone);
            $totalReferees += $refereesCreated;
        }

        // Valida e mostra riassunto
        $this->validateReferees();
        $this->showRefereeSummary();

        $this->command->info("🏆 Arbitri creati con successo: {$totalReferees} arbitri totali");
    }

    /**
     * Crea arbitri per una specifica zona
     */
    private function createRefereesForZone(Zone $zone): int
    {
        $this->command->info("📍 Creando arbitri per zona {$zone->code} - {$zone->name}...");

        $levels = SeederHelper::getRefereeLevels();
        $names = SeederHelper::generateRefereeNames();
        $usedNames = [];
        $refereesCreated = 0;
        $sequence = 1;

        foreach ($levels as $levelCode => $levelData) {
            $count = $levelData['count_per_zone'];

            for ($i = 0; $i < $count; $i++) {
                // Genera nome univoco
                do {
                    $firstName = $names['firstNames'][array_rand($names['firstNames'])];
                    $lastName = $names['lastNames'][array_rand($names['lastNames'])];
                    $fullName = "{$firstName} {$lastName}";
                } while (in_array($fullName, $usedNames));

                $usedNames[] = $fullName;

                // Genera email univoca
                $email = SeederHelper::generateEmail($firstName, $lastName, strtolower($zone->code), 'referee');

                // Assicura univocità email globale
                $emailCounter = 1;
                $originalEmail = $email;
                while (User::where('email', $email)->exists()) {
                    $email = str_replace('@', "{$emailCounter}@", $originalEmail);
                    $emailCounter++;
                }

                $referee = User::create([
                    'name' => $fullName,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => SeederHelper::getTestPassword(),
                    'user_type' => 'referee',
                    'zone_id' => $zone->id,
                    'level' => $levelCode,
                    'referee_code' => SeederHelper::generateRefereeCode($zone->code, $sequence),
                    'is_active' => true,
                    'phone' => $this->generateRefereePhone($zone->code),
                    'city' => $this->getRandomCityForZone($zone->code),
                    'qualification_date' => $this->generateQualificationDate($levelCode),
                    'last_course_date' => $this->generateLastCourseDate(),
                    'certifications' => $this->generateCertifications($levelCode),
                    'notes' => "Arbitro {$levelData['name']} della zona {$zone->code}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->command->info("  ✅ {$referee->name} ({$referee->level}) - {$referee->referee_code}");
                $refereesCreated++;
                $sequence++;
            }
        }

        return $refereesCreated;
    }

    /**
     * Genera telefono arbitro
     */
    private function generateRefereePhone(string $zoneCode): string
    {
        $areaCodes = [
            'SZR1' => ['011', '0125', '0165'],
            'SZR2' => ['02', '039', '035', '030'],
            'SZR3' => ['041', '045', '049', '0461'],
            'SZR4' => ['051', '059', '0541', '071'],
            'SZR5' => ['055', '050', '0564', '075'],
            'SZR6' => ['06', '0862', '0874'],
            'SZR7' => ['081', '080', '0925', '070'],
        ];

        $codes = $areaCodes[$zoneCode] ?? ['06'];
        $areaCode = $codes[array_rand($codes)];
        $number = rand(1000000, 9999999);

        return "+39 {$areaCode} {$number}";
    }

    /**
     * Ottieni città casuale per zona
     */
    private function getRandomCityForZone(string $zoneCode): string
    {
        $cities = [
            'SZR1' => ['Torino', 'Aosta', 'Biella', 'Cuneo', 'Alessandria', 'Novara'],
            'SZR2' => ['Milano', 'Bergamo', 'Brescia', 'Como', 'Varese', 'Monza'],
            'SZR3' => ['Venezia', 'Verona', 'Padova', 'Vicenza', 'Trento', 'Bolzano'],
            'SZR4' => ['Bologna', 'Modena', 'Parma', 'Rimini', 'Ancona', 'Pesaro'],
            'SZR5' => ['Firenze', 'Pisa', 'Siena', 'Livorno', 'Perugia', 'Terni'],
            'SZR6' => ['Roma', 'Latina', 'Viterbo', 'L\'Aquila', 'Pescara', 'Campobasso'],
            'SZR7' => ['Napoli', 'Bari', 'Palermo', 'Catania', 'Cagliari', 'Reggio Calabria'],
        ];

        $zoneCities = $cities[$zoneCode] ?? ['Roma'];
        return $zoneCities[array_rand($zoneCities)];
    }

    /**
     * Genera data qualificazione realistica
     */
    private function generateQualificationDate(string $level): string
    {
        $yearsAgo = match($level) {
            'aspirante' => rand(0, 2),
            'primo_livello' => rand(2, 5),
            'regionale' => rand(4, 10),
            'nazionale' => rand(8, 15),
            'internazionale' => rand(10, 20),
            default => rand(1, 5)
        };

        return now()->subYears($yearsAgo)->subDays(rand(0, 365))->format('Y-m-d');
    }

    /**
     * Genera data ultimo corso
     */
    private function generateLastCourseDate(): string
    {
        return now()->subMonths(rand(1, 18))->format('Y-m-d');
    }

    /**
     * Genera certificazioni per livello
     */
    private function generateCertifications(string $level): array
    {
        $baseCertifications = ['Regolamento Golf', 'Primo Soccorso'];

        $additionalCertifications = match($level) {
            'aspirante' => [],
            'primo_livello' => ['Etichetta Golf'],
            'regionale' => ['Etichetta Golf', 'Gestione Tornei'],
            'nazionale' => ['Etichetta Golf', 'Gestione Tornei', 'Arbitraggio Avanzato'],
            'internazionale' => ['Etichetta Golf', 'Gestione Tornei', 'Arbitraggio Avanzato', 'Inglese Tecnico'],
            default => []
        };

        return array_merge($baseCertifications, $additionalCertifications);
    }

    /**
     * Valida arbitri creati
     */
    private function validateReferees(): void
    {
        $this->command->info('🔍 Validando arbitri creati...');

        $zones = Zone::count();
        $refereesPerZone = SeederHelper::getConfig()['referees_per_zone'];
        $expectedReferees = $zones * $refereesPerZone;
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
        $totalCodes = User::where('user_type', 'referee')->whereNotNull('referee_code')->count();
        $uniqueCodes = User::where('user_type', 'referee')->distinct('referee_code')->count();
        if ($totalCodes !== $uniqueCodes) {
            $this->command->error("❌ Errore: codici arbitro non univoci");
            return;
        }

        // Verifica email univoche
        $totalEmails = User::where('user_type', 'referee')->count();
        $uniqueEmails = User::where('user_type', 'referee')->distinct('email')->count();
        if ($totalEmails !== $uniqueEmails) {
            $this->command->error("❌ Errore: email arbitri non univoche");
            return;
        }

        // Verifica distribuzione livelli
        $levels = SeederHelper::getRefereeLevels();
        foreach ($levels as $levelCode => $levelData) {
            $expectedCount = $zones * $levelData['count_per_zone'];
            $actualCount = User::where('user_type', 'referee')
                              ->where('level', $levelCode)
                              ->count();

            if ($actualCount !== $expectedCount) {
                $this->command->error("❌ Errore livello {$levelCode}: attesi {$expectedCount}, trovati {$actualCount}");
                return;
            }
        }

        // Verifica che tutti gli arbitri siano attivi
        $inactiveReferees = User::where('user_type', 'referee')
                               ->where('is_active', false)
                               ->count();
        if ($inactiveReferees > 0) {
            $this->command->warn("⚠️ Attenzione: {$inactiveReferees} arbitri non attivi");
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

        $zones = Zone::orderBy('code')->get();
        $levels = SeederHelper::getRefereeLevels();

        // Riassunto per zona
        foreach ($zones as $zone) {
            $this->command->info("📍 {$zone->code} - {$zone->name}:");

            foreach ($levels as $levelCode => $levelData) {
                $count = User::where('user_type', 'referee')
                            ->where('zone_id', $zone->id)
                            ->where('level', $levelCode)
                            ->count();

                $this->command->info("   {$levelData['name']}: {$count} arbitri");
            }

            $totalZoneReferees = User::where('user_type', 'referee')
                                   ->where('zone_id', $zone->id)
                                   ->count();
            $this->command->info("   Totale zona: {$totalZoneReferees} arbitri");
            $this->command->info('');
        }

        // Statistiche generali
        $this->command->info('📊 STATISTICHE GENERALI PER LIVELLO:');
        foreach ($levels as $levelCode => $levelData) {
            $count = User::where('user_type', 'referee')
                        ->where('level', $levelCode)
                        ->count();
            $this->command->info("   {$levelData['name']}: {$count} arbitri totali");
        }

        $totalReferees = User::where('user_type', 'referee')->count();
        $totalZones = Zone::count();
        $avgRefereesPerZone = round($totalReferees / $totalZones, 1);

        $this->command->info('');
        $this->command->info("📈 RIASSUNTO FINALE:");
        $this->command->info("   Arbitri totali: {$totalReferees}");
        $this->command->info("   Zone coperte: {$totalZones}");
        $this->command->info("   Media arbitri per zona: {$avgRefereesPerZone}");

        // Esempi di login
        $this->command->info('');
        $this->command->info('🔐 ESEMPI LOGIN ARBITRI:');
        $this->command->info('Password per tutti: password123');

        // Mostra un arbitro per ogni livello dalla zona SZR6
        $szr6 = Zone::where('code', 'SZR6')->first();
        if ($szr6) {
            foreach ($levels as $levelCode => $levelData) {
                $referee = User::where('user_type', 'referee')
                              ->where('zone_id', $szr6->id)
                              ->where('level', $levelCode)
                              ->first();
                if ($referee) {
                    $this->command->info("   {$levelData['name']}: {$referee->email}");
                }
            }
        }

        $this->command->info('=====================================');
        $this->command->info('');
    }
}
