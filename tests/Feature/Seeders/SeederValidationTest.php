<?php

namespace Tests\Feature\Seeders;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Zone;
use App\Models\User;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Availability;
use App\Models\Assignment;

class SeederValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Esegui seeding completo
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);
    }

    #[Test]
    public function seeder_creates_correct_number_of_zones()
    {
        $expectedZones = 7;
        $actualZones = Zone::count();

        $this->assertEquals($expectedZones, $actualZones,
            "Expected {$expectedZones} zones, found {$actualZones}");

        // Verifica codici zona corretti
        $zoneCodes = Zone::pluck('code')->sort()->values()->toArray();
        $expectedCodes = ['SZR1', 'SZR2', 'SZR3', 'SZR4', 'SZR5', 'SZR6', 'SZR7'];

        $this->assertEquals($expectedCodes, $zoneCodes,
            "Zone codes don't match expected values");
    }

    #[Test]
    public function seeder_creates_correct_admin_structure()
    {
        // Verifica Super Admin
        $superAdmins = User::where('user_type', 'super_admin')->count();
        $this->assertEquals(1, $superAdmins, "Should have exactly 1 super admin");

        // Verifica National Admin
        $nationalAdmins = User::where('user_type', 'national_admin')->count();
        $this->assertGreaterThanOrEqual(1, $nationalAdmins, "Should have at least 1 national admin");
        $this->assertLessThanOrEqual(2, $nationalAdmins, "Should have at most 2 national admins");

        // Verifica Zone Admin
        $zoneAdmins = User::where('user_type', 'admin')->count();
        $totalZones = Zone::count();
        $this->assertEquals($totalZones, $zoneAdmins,
            "Should have 1 admin per zone");

        // Verifica che ogni zona abbia il suo admin
        $zonesWithoutAdmin = Zone::whereDoesntHave('users', function($query) {
            $query->where('user_type', 'admin');
        })->count();

        $this->assertEquals(0, $zonesWithoutAdmin,
            "All zones should have an admin");
    }

    #[Test]
    public function seeder_maintains_zone_consistency()
    {
        // Verifica che tutti i club appartengano alla zona corretta
        $inconsistentClubs = Club::whereHas('zone')->get()
            ->filter(function($club) {
                return $club->zone_id !== $club->zone->id;
            })->count();

        $this->assertEquals(0, $inconsistentClubs,
            "All clubs should belong to correct zones");

        // Verifica che tutti gli arbitri appartengano alla zona corretta
        $inconsistentReferees = User::where('user_type', 'referee')
            ->whereNotNull('zone_id')
            ->whereHas('zone')
            ->get()
            ->filter(function($referee) {
                return $referee->zone_id !== $referee->zone->id;
            })->count();

        $this->assertEquals(0, $inconsistentReferees,
            "All referees should belong to correct zones");
    }

    #[Test]
    public function seeder_creates_valid_tournament_structure()
    {
        // Verifica tipologie tornei
        $tournamentTypes = TournamentType::count();
        $this->assertEquals(6, $tournamentTypes, "Should have exactly 6 tournament types");

        // Verifica distribuzione zonale/nazionale
        $zonalTypes = TournamentType::where('is_national', false)->count();
        $nationalTypes = TournamentType::where('is_national', true)->count();

        $this->assertEquals(3, $zonalTypes, "Should have 3 zonal tournament types");
        $this->assertEquals(3, $nationalTypes, "Should have 3 national tournament types");

        // Verifica che tutti i tornei abbiano tipologia valida
        $tournamentsWithoutType = Tournament::whereDoesntHave('tournamentType')->count();
        $this->assertEquals(0, $tournamentsWithoutType,
            "All tournaments should have valid tournament type");

        // Verifica coerenza zona torneo-club
        $inconsistentTournaments = Tournament::whereNotNull('zone_id')
            ->whereHas('club')
            ->get()
            ->filter(function($tournament) {
                return $tournament->zone_id !== $tournament->club->zone_id;
            })->count();

        $this->assertEquals(0, $inconsistentTournaments,
            "Tournament zone should match club zone");
    }

    #[Test]
    public function seeder_creates_logical_date_sequences()
    {
        // Verifica che start_date <= end_date
        $invalidDateRanges = Tournament::whereColumn('start_date', '>', 'end_date')->count();
        $this->assertEquals(0, $invalidDateRanges,
            "Tournament end date should be after start date");

        // Verifica che availability_deadline sia prima di start_date
        $invalidDeadlines = Tournament::whereColumn('availability_deadline', '>', 'start_date')->count();
        $this->assertEquals(0, $invalidDeadlines,
            "Availability deadline should be before tournament start");

        // Verifica logica stati e date
        $futureCompletedTournaments = Tournament::where('status', 'completed')
            ->where('start_date', '>', now())
            ->count();

        $this->assertEquals(0, $futureCompletedTournaments,
            "Completed tournaments should be in the past");
    }

    #[Test]
    public function seeder_creates_valid_availability_workflow()
    {
        // Verifica che le disponibilità siano solo per tornei aperti
        $invalidAvailabilities = Availability::whereHas('tournament', function($query) {
            $query->where('status', '!=', 'open');
        })->count();

        $this->assertEquals(0, $invalidAvailabilities,
            "Availabilities should only exist for open tournaments");

        // Verifica che non ci siano duplicati
        $totalAvailabilities = Availability::count();
        $uniqueAvailabilities = Availability::distinct(['tournament_id', 'referee_id'])->count();

        $this->assertEquals($totalAvailabilities, $uniqueAvailabilities,
            "Each referee should have at most one availability per tournament");

        // Verifica che tutti gli arbitri siano attivi
        $inactiveRefereeAvailabilities = Availability::whereHas('referee', function($query) {
            $query->where('is_active', false);
        })->count();

        $this->assertEquals(0, $inactiveRefereeAvailabilities,
            "Only active referees should have availabilities");
    }

    #[Test]
    public function seeder_creates_valid_assignment_logic()
    {
        // Verifica che le assegnazioni rispettino min/max arbitri
        $invalidAssignmentCounts = Tournament::whereIn('status', ['closed', 'assigned', 'completed'])
            ->get()
            ->filter(function($tournament) {
                $assignedCount = $tournament->assignments()->count();
                $minRequired = $tournament->tournamentType->min_referees;
                $maxAllowed = $tournament->tournamentType->max_referees;

                return $assignedCount < $minRequired || $assignedCount > $maxAllowed;
            })->count();

        $this->assertEquals(0, $invalidAssignmentCounts,
            "Tournament assignments should respect min/max referee limits");

        // Verifica che non ci siano duplicati
        $totalAssignments = Assignment::count();
        $uniqueAssignments = Assignment::distinct(['tournament_id', 'referee_id'])->count();

        $this->assertEquals($totalAssignments, $uniqueAssignments,
            "Each referee should have at most one assignment per tournament");

        // Verifica che tutti gli arbitri assegnati siano attivi
        $inactiveRefereeAssignments = Assignment::whereHas('referee', function($query) {
            $query->where('is_active', false);
        })->count();

        $this->assertEquals(0, $inactiveRefereeAssignments,
            "Only active referees should have assignments");
    }

    #[Test]
    public function seeder_maintains_referential_integrity()
    {
        // Verifica che tutte le foreign key siano valide

        // Zone -> Users
        $orphanedZoneUsers = User::whereNotNull('zone_id')
            ->whereDoesntHave('zone')
            ->count();
        $this->assertEquals(0, $orphanedZoneUsers, "All users with zone_id should have valid zone");

        // Zone -> Clubs
        $orphanedZoneClubs = Club::whereNotNull('zone_id')
            ->whereDoesntHave('zone')
            ->count();
        $this->assertEquals(0, $orphanedZoneClubs, "All clubs should have valid zone");

        // Tournament -> Club
        $orphanedTournamentClubs = Tournament::whereNotNull('club_id')
            ->whereDoesntHave('club')
            ->count();
        $this->assertEquals(0, $orphanedTournamentClubs, "All tournaments should have valid club");

        // Tournament -> TournamentType
        $orphanedTournamentTypes = Tournament::whereNotNull('tournament_type_id')
            ->whereDoesntHave('tournamentType')
            ->count();
        $this->assertEquals(0, $orphanedTournamentTypes, "All tournaments should have valid type");

        // Availability -> Tournament
        $orphanedAvailabilityTournaments = Availability::whereDoesntHave('tournament')->count();
        $this->assertEquals(0, $orphanedAvailabilityTournaments, "All availabilities should have valid tournament");

        // Assignment -> Tournament
        $orphanedAssignmentTournaments = Assignment::whereDoesntHave('tournament')->count();
        $this->assertEquals(0, $orphanedAssignmentTournaments, "All assignments should have valid tournament");
    }

    #[Test]
    public function seeder_creates_unique_identifiers()
    {
        // Verifica email univoche
        $totalUsers = User::count();
        $uniqueEmails = User::distinct('email')->count();
        $this->assertEquals($totalUsers, $uniqueEmails, "All user emails should be unique");

        // Verifica codici zona univoci
        $totalZones = Zone::count();
        $uniqueZoneCodes = Zone::distinct('code')->count();
        $this->assertEquals($totalZones, $uniqueZoneCodes, "All zone codes should be unique");

        // Verifica codici arbitro univoci
        $totalRefereeCodes = User::where('user_type', 'referee')
            ->whereNotNull('referee_code')
            ->count();
        $uniqueRefereeCodes = User::where('user_type', 'referee')
            ->distinct('referee_code')
            ->count();
        $this->assertEquals($totalRefereeCodes, $uniqueRefereeCodes, "All referee codes should be unique");

        // Verifica codici club univoci
        $totalClubCodes = Club::whereNotNull('code')->count();
        $uniqueClubCodes = Club::distinct('code')->count();
        $this->assertEquals($totalClubCodes, $uniqueClubCodes, "All club codes should be unique");
    }

    #[Test]
    public function seeder_creates_realistic_distributions()
    {
        // Verifica distribuzione livelli arbitri
        $levelCounts = User::where('user_type', 'referee')
            ->selectRaw('level, COUNT(*) as count')
            ->groupBy('level')
            ->pluck('count', 'level');

        $this->assertArrayHasKey('aspirante', $levelCounts, "Should have aspirante referees");
        $this->assertArrayHasKey('internazionale', $levelCounts, "Should have international referees");

        // Verifica che ci siano più arbitri di livello basso che alto (distribuzione realistica)
        $aspiranti = $levelCounts['aspirante'] ?? 0;
        $internazionali = $levelCounts['internazionale'] ?? 0;

        $this->assertGreaterThan($internazionali, $aspiranti,
            "Should have more aspirante than international referees");

        // Verifica distribuzione stati tornei
        $statusCounts = Tournament::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $this->assertArrayHasKey('open', $statusCounts, "Should have open tournaments");
        $this->assertArrayHasKey('completed', $statusCounts, "Should have completed tournaments");
    }

    #[Test]
    public function seeder_creates_testable_scenarios()
    {
        // Scenario 1: Zone con dati completi per testing
        $testZone = Zone::where('code', 'SZR6')->first();
        $this->assertNotNull($testZone, "Should have SZR6 test zone");

        $zoneClubs = Club::where('zone_id', $testZone->id)->count();
        $this->assertGreaterThan(0, $zoneClubs, "Test zone should have clubs");

        $zoneReferees = User::where('user_type', 'referee')
            ->where('zone_id', $testZone->id)
            ->count();
        $this->assertGreaterThan(0, $zoneReferees, "Test zone should have referees");

        $zoneTournaments = Tournament::where('zone_id', $testZone->id)->count();
        $this->assertGreaterThan(0, $zoneTournaments, "Test zone should have tournaments");

        // Scenario 2: Tornei aperti per test disponibilità
        $openTournaments = Tournament::where('status', 'open')
            ->where('availability_deadline', '>', now())
            ->count();
        $this->assertGreaterThan(0, $openTournaments, "Should have open tournaments for availability testing");

        // Scenario 3: Tornei con assegnazioni per test workflow
        $assignedTournaments = Tournament::whereIn('status', ['assigned', 'completed'])
            ->whereHas('assignments')
            ->count();
        $this->assertGreaterThan(0, $assignedTournaments, "Should have tournaments with assignments");
    }

    #[Test]
    public function seeder_performance_is_acceptable()
    {
        // Test che il database non sia troppo grande per performance
        $totalRecords = 0;
        $tables = ['zones', 'users', 'clubs', 'tournaments', 'availabilities', 'assignments'];

        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            $totalRecords += $count;
        }

        // Assicurati che ci siano abbastanza dati per test significativi ma non troppi per performance
        $this->assertGreaterThan(100, $totalRecords, "Should have enough data for meaningful tests");
        $this->assertLessThan(10000, $totalRecords, "Should not have too much data for performance");

        // Verifica che ci siano indici appropriati (test per query performance)
        $zoneFilterQuery = microtime(true);
        Tournament::where('zone_id', 1)->count();
        $zoneFilterTime = microtime(true) - $zoneFilterQuery;

        $this->assertLessThan(1.0, $zoneFilterTime, "Zone-filtered queries should be fast");
    }

    #[Test]
    public function seeder_creates_valid_test_credentials()
    {
        // Verifica che esistano le credenziali di test documentate
        $superAdmin = User::where('email', 'superadmin@golf.it')->first();
        $this->assertNotNull($superAdmin, "Should have super admin with documented email");

        $nationalAdmin = User::where('email', 'crc@golf.it')->first();
        $this->assertNotNull($nationalAdmin, "Should have national admin with documented email");

        $zoneAdmin = User::where('email', 'admin.szr6@golf.it')->first();
        $this->assertNotNull($zoneAdmin, "Should have zone admin with documented email");

        // Verifica che le password siano hashat correttamente
        $this->assertTrue(\Hash::check('password123', $superAdmin->password),
            "Super admin should have correct password");
    }
}
