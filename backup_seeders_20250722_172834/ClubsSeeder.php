<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Club;
use App\Models\Zone;
use Database\Seeders\Helpers\SeederHelper;

class ClubsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('⛳ Creando Circoli Golf per Zone...');

        // Elimina club esistenti per evitare duplicati
        Club::truncate();

        $zones = Zone::orderBy('code')->get();
        $config = SeederHelper::getConfig();
        $totalClubs = 0;

        foreach ($zones as $zone) {
            $clubsCreated = $this->createClubsForZone($zone);
            $totalClubs += $clubsCreated;
        }

        // Valida e mostra riassunto
        $this->validateClubs();
        $this->showClubSummary();

        $this->command->info("🏆 Club creati con successo: {$totalClubs} circoli totali");
    }

    /**
     * Crea club per una specifica zona
     */
    private function createClubsForZone(Zone $zone): int
    {
        $this->command->info("📍 Creando club per zona {$zone->code} - {$zone->name}...");

        $clubNames = SeederHelper::generateClubNames($zone->id);
        $clubsCreated = 0;

        foreach ($clubNames as $index => $clubData) {
            $clubCode = SeederHelper::generateClubCode($zone->code, $index + 1);

            $club = Club::create([
                'name' => $clubData['name'],
                'code' => $clubCode,
                'city' => $clubData['city'],
                'province' => $clubData['province'],
                'address' => $this->generateAddress($clubData['city']),
                'postal_code' => $this->generatePostalCode($clubData['province']),
                'zone_id' => $zone->id,
                'contact_person' => $this->generateContactPerson(),
                'email' => $this->generateClubEmail($clubData['name']),
                'phone' => $this->generateClubPhone($clubData['province']),
                'website' => $this->generateWebsite($clubData['name']),
                'is_active' => true,
                'founded_year' => $this->generateFoundedYear(),
                'holes_count' => $this->generateHolesCount(),
                'par' => $this->generatePar(),
                'course_rating' => $this->generateCourseRating(),
                'slope_rating' => $this->generateSlopeRating(),
                'notes' => "Circolo affiliato alla zona {$zone->code}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("  ✅ {$club->name} ({$club->code}) - {$club->city} ({$club->province})");
            $clubsCreated++;
        }

        return $clubsCreated;
    }

    /**
     * Genera indirizzo realistico
     */
    private function generateAddress(string $city): string
    {
        $streetTypes = ['Via', 'Viale', 'Corso', 'Piazza', 'Strada'];
        $streetNames = ['Golf', 'dei Pini', 'delle Rose', 'Panoramica', 'del Circolo', 'Sportiva', 'Verde'];

        $streetType = $streetTypes[array_rand($streetTypes)];
        $streetName = $streetNames[array_rand($streetNames)];
        $number = rand(1, 200);

        return "{$streetType} {$streetName}, {$number}";
    }

    /**
     * Genera codice postale per provincia
     */
    private function generatePostalCode(string $province): string
    {
        $postalCodes = [
            'TO' => '10' . rand(100, 199),
            'AO' => '11' . rand(000, 099),
            'MI' => '20' . rand(100, 199),
            'MB' => '20' . rand(800, 900),
            'BG' => '24' . rand(000, 199),
            'BS' => '25' . rand(000, 199),
            'VE' => '30' . rand(100, 199),
            'VR' => '37' . rand(000, 199),
            'PD' => '35' . rand(000, 199),
            'TN' => '38' . rand(000, 199),
            'BO' => '40' . rand(100, 199),
            'MO' => '41' . rand(000, 199),
            'RN' => '47' . rand(800, 900),
            'AN' => '60' . rand(100, 199),
            'FI' => '50' . rand(100, 199),
            'PI' => '56' . rand(000, 199),
            'GR' => '58' . rand(000, 199),
            'PG' => '06' . rand(000, 199),
            'RM' => '00' . rand(100, 199),
            'NA' => '80' . rand(100, 199),
            'BA' => '70' . rand(000, 199),
            'AG' => '92' . rand(000, 199),
            'CA' => '09' . rand(000, 199),
        ];

        return $postalCodes[$province] ?? '00100';
    }

    /**
     * Genera nome persona di contatto
     */
    private function generateContactPerson(): string
    {
        $names = SeederHelper::generateRefereeNames();
        $firstName = $names['firstNames'][array_rand($names['firstNames'])];
        $lastName = $names['lastNames'][array_rand($names['lastNames'])];

        $roles = ['Direttore', 'Presidente', 'Segretario', 'Responsabile'];
        $role = $roles[array_rand($roles)];

        return "{$role} {$firstName} {$lastName}";
    }

    /**
     * Genera email club
     */
    private function generateClubEmail(string $clubName): string
    {
        $clubName = strtolower(str_replace(['Golf Club ', 'Circolo ', 'Country Club '], '', $clubName));
        $clubName = str_replace([' ', '\''], '', $clubName);

        return "info@{$clubName}golf.it";
    }

    /**
     * Genera telefono club
     */
    private function generateClubPhone(string $province): string
    {
        $areaCodes = [
            'TO' => '011', 'AO' => '0165', 'MI' => '02', 'MB' => '039',
            'BG' => '035', 'BS' => '030', 'VE' => '041', 'VR' => '045',
            'PD' => '049', 'TN' => '0461', 'BO' => '051', 'MO' => '059',
            'RN' => '0541', 'AN' => '071', 'FI' => '055', 'PI' => '050',
            'GR' => '0564', 'PG' => '075', 'RM' => '06', 'NA' => '081',
            'BA' => '080', 'AG' => '0925', 'CA' => '070'
        ];

        $areaCode = $areaCodes[$province] ?? '06';
        $number = rand(1000000, 9999999);

        return "+39 {$areaCode} {$number}";
    }

    /**
     * Genera sito web
     */
    private function generateWebsite(string $clubName): string
    {
        $clubName = strtolower(str_replace(['Golf Club ', 'Circolo ', 'Country Club '], '', $clubName));
        $clubName = str_replace([' ', '\''], '', $clubName);

        return "https://www.{$clubName}golf.it";
    }

    /**
     * Genera anno di fondazione
     */
    private function generateFoundedYear(): int
    {
        return rand(1920, 2010);
    }

    /**
     * Genera numero buche
     */
    private function generateHolesCount(): int
    {
        $holeCounts = [9, 18, 27];
        return $holeCounts[array_rand($holeCounts)];
    }

    /**
     * Genera par campo
     */
    private function generatePar(): int
    {
        return rand(70, 72);
    }

    /**
     * Genera course rating
     */
    private function generateCourseRating(): float
    {
        return round(rand(680, 740) / 10, 1);
    }

    /**
     * Genera slope rating
     */
    private function generateSlopeRating(): int
    {
        return rand(110, 140);
    }

    /**
     * Valida club creati
     */
    private function validateClubs(): void
    {
        $this->command->info('🔍 Validando circoli creati...');

        $zones = Zone::count();
        $expectedClubs = $zones * SeederHelper::getConfig()['clubs_per_zone'];
        $actualClubs = Club::count();

        if ($actualClubs !== $expectedClubs) {
            $this->command->error("❌ Errore: attesi {$expectedClubs} club, trovati {$actualClubs}");
            return;
        }

        // Verifica che ogni zona abbia i suoi club
        $zonesWithoutClubs = Zone::whereDoesntHave('clubs')->count();
        if ($zonesWithoutClubs > 0) {
            $this->command->error("❌ Errore: {$zonesWithoutClubs} zone senza club");
            return;
        }

        // Verifica codici univoci
        $totalCodes = Club::count();
        $uniqueCodes = Club::distinct('code')->count();
        if ($totalCodes !== $uniqueCodes) {
            $this->command->error("❌ Errore: codici club non univoci");
            return;
        }

        // Verifica email univoche
        $totalEmails = Club::count();
        $uniqueEmails = Club::distinct('email')->count();
        if ($totalEmails !== $uniqueEmails) {
            $this->command->error("❌ Errore: email club non univoche");
            return;
        }

        // Verifica che tutti i club siano attivi
        $inactiveClubs = Club::where('is_active', false)->count();
        if ($inactiveClubs > 0) {
            $this->command->warn("⚠️ Attenzione: {$inactiveClubs} club non attivi");
        }

        $this->command->info('✅ Validazione circoli completata con successo');
    }

    /**
     * Mostra riassunto club creati
     */
    private function showClubSummary(): void
    {
        $this->command->info('');
        $this->command->info('⛳ RIASSUNTO CIRCOLI GOLF:');
        $this->command->info('=====================================');

        $zones = Zone::with(['clubs' => function($query) {
            $query->orderBy('name');
        }])->orderBy('code')->get();

        foreach ($zones as $zone) {
            $this->command->info("📍 {$zone->code} - {$zone->name}:");

            foreach ($zone->clubs as $club) {
                $holes = $club->holes_count ? " ({$club->holes_count} buche)" : "";
                $this->command->info("  ⛳ {$club->name} - {$club->city} ({$club->province}){$holes}");
                $this->command->info("     📧 {$club->email} | ☎️  {$club->phone}");
            }

            $this->command->info("     Totale club zona: {$zone->clubs->count()}");
            $this->command->info('');
        }

        $this->command->info('📊 STATISTICHE GENERALI:');
        $totalClubs = Club::count();
        $totalZones = Zone::count();
        $avgClubsPerZone = round($totalClubs / $totalZones, 1);

        $this->command->info("   Circoli totali: {$totalClubs}");
        $this->command->info("   Zone coperte: {$totalZones}");
        $this->command->info("   Media club per zona: {$avgClubsPerZone}");

        // Statistiche buche
        $nineHoles = Club::where('holes_count', 9)->count();
        $eighteenHoles = Club::where('holes_count', 18)->count();
        $twentySevenHoles = Club::where('holes_count', 27)->count();

        $this->command->info("   Club 9 buche: {$nineHoles}");
        $this->command->info("   Club 18 buche: {$eighteenHoles}");
        $this->command->info("   Club 27 buche: {$twentySevenHoles}");

        $this->command->info('=====================================');
        $this->command->info('');
    }
}
