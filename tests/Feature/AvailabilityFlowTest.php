<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Zone;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Availability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AvailabilityFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $zoneReferee;
    protected $nationalReferee;
    protected $zone;
    protected $tournament;
    protected $nationalTournament;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Setup zone and tournament type
        $this->zone = Zone::factory()->create(['name' => 'Test Zone']);

        $zonalType = TournamentType::factory()->create([
            'name' => 'Zonal Tournament',
            'is_national' => false,
        ]);

        $nationalType = TournamentType::factory()->create([
            'name' => 'National Tournament',
            'is_national' => true,
        ]);

        $club = Club::factory()->create(['zone_id' => $this->zone->id]);

        // ✅ Create referees using User-Centric approach
        $this->zoneReferee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'level' => 'primo_livello',
            'referee_code' => 'ARB1001',
            'is_active' => true,
        ]);

        $this->nationalReferee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'level' => 'nazionale',
            'referee_code' => 'ARB2001',
            'is_active' => true,
        ]);

        // ✅ Create tournaments
        $this->tournament = Tournament::factory()->create([
            'name' => 'Test Zonal Tournament',
            'zone_id' => $this->zone->id,
            'club_id' => $club->id,
            'tournament_type_id' => $zonalType->id,
            'start_date' => Carbon::now()->addDays(30),
            'end_date' => Carbon::now()->addDays(32),
            'availability_deadline' => Carbon::now()->addDays(10),
            'status' => 'open',
        ]);

        $this->nationalTournament = Tournament::factory()->create([
            'name' => 'Test National Tournament',
            'zone_id' => $this->zone->id,
            'club_id' => $club->id,
            'tournament_type_id' => $nationalType->id,
            'start_date' => Carbon::now()->addDays(45),
            'end_date' => Carbon::now()->addDays(47),
            'availability_deadline' => Carbon::now()->addDays(15),
            'status' => 'open',
        ]);
    }

    /**
     * Test that zone referee can view tournaments in their zone
     */
    public function test_zone_referee_can_view_zonal_tournaments()
    {
        $this->actingAs($this->zoneReferee);

        $response = $this->get(route('referee.availability.index'));

        $response->assertOk();
        $response->assertSee($this->tournament->name);
        $response->assertViewHas('tournamentsByMonth');
    }

    /**
     * Test that national referee can view both zonal and national tournaments
     */
    public function test_national_referee_can_view_all_tournaments()
    {
        $this->actingAs($this->nationalReferee);

        $response = $this->get(route('referee.availability.index'));

        $response->assertOk();
        $response->assertSee($this->tournament->name); // Zonal tournament in their zone
        $response->assertSee($this->nationalTournament->name); // National tournament
    }

    /**
     * Test referee can declare availability for zonal tournament
     */
    public function test_referee_can_declare_availability_for_zonal_tournament()
    {
        $this->actingAs($this->zoneReferee);

        $availabilityData = [
            'availabilities' => [$this->tournament->id],
            'notes' => [
                $this->tournament->id => 'Available for this tournament'
            ]
        ];

        $response = $this->post(route('referee.availability.save'), $availabilityData);

        $response->assertRedirect(route('referee.availability.index'));
        $response->assertSessionHas('success');

        // ✅ Verify availability was created with User model
        $this->assertDatabaseHas('availabilities', [
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->tournament->id,
            'notes' => 'Available for this tournament',
        ]);
    }

    /**
     * Test national referee can declare availability for national tournament
     */
    public function test_national_referee_can_declare_availability_for_national_tournament()
    {
        $this->actingAs($this->nationalReferee);

        $availabilityData = [
            'availabilities' => [$this->nationalTournament->id],
            'notes' => [
                $this->nationalTournament->id => 'Available for national tournament'
            ]
        ];

        $response = $this->post(route('referee.availability.save'), $availabilityData);

        $response->assertRedirect(route('referee.availability.index'));

        // ✅ Verify availability was created
        $this->assertDatabaseHas('availabilities', [
            'user_id' => $this->nationalReferee->id,
            'tournament_id' => $this->nationalTournament->id,
            'notes' => 'Available for national tournament',
        ]);
    }

    /**
     * Test referee can withdraw availability before deadline
     */
    public function test_referee_can_withdraw_availability_before_deadline()
    {
        $this->actingAs($this->zoneReferee);

        // ✅ First declare availability
        Availability::create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->tournament->id,
            'notes' => 'Initially available',
            'submitted_at' => Carbon::now(),
        ]);

        // ✅ Then withdraw (by not including in new submission)
        $availabilityData = [
            'availabilities' => [], // Empty = withdraw all
        ];

        $response = $this->post(route('referee.availability.save'), $availabilityData);

        $response->assertRedirect(route('referee.availability.index'));

        // ✅ Verify availability was removed
        $this->assertDatabaseMissing('availabilities', [
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->tournament->id,
        ]);
    }

    /**
     * Test referee cannot declare availability after deadline
     */
    public function test_referee_cannot_declare_availability_after_deadline()
    {
        // ✅ Set tournament deadline to past
        $this->tournament->update([
            'availability_deadline' => Carbon::now()->subDays(1)
        ]);

        $this->actingAs($this->zoneReferee);

        $availabilityData = [
            'availabilities' => [$this->tournament->id],
        ];

        $response = $this->post(route('referee.availability.save'), $availabilityData);

        // ✅ Should succeed but not create availability for past deadline tournament
        $response->assertRedirect(route('referee.availability.index'));

        $this->assertDatabaseMissing('availabilities', [
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->tournament->id,
        ]);
    }

    /**
     * Test referee cannot declare availability for tournaments outside their zone (non-national)
     */
    public function test_referee_cannot_declare_availability_outside_zone()
    {
        // ✅ Create tournament in different zone
        $otherZone = Zone::factory()->create(['name' => 'Other Zone']);
        $otherClub = Club::factory()->create(['zone_id' => $otherZone->id]);

        $zonalType = TournamentType::factory()->create(['is_national' => false]);

        $otherTournament = Tournament::factory()->create([
            'zone_id' => $otherZone->id,
            'club_id' => $otherClub->id,
            'tournament_type_id' => $zonalType->id,
            'start_date' => Carbon::now()->addDays(30),
            'availability_deadline' => Carbon::now()->addDays(10),
            'status' => 'open',
        ]);

        $this->actingAs($this->zoneReferee);

        $availabilityData = [
            'availabilities' => [$otherTournament->id],
        ];

        $response = $this->post(route('referee.availability.save'), $availabilityData);

        // ✅ Should not create availability for tournament outside zone
        $this->assertDatabaseMissing('availabilities', [
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $otherTournament->id,
        ]);
    }

    /**
     * Test AJAX toggle availability endpoint
     */
    public function test_referee_can_toggle_availability_via_ajax()
    {
        $this->actingAs($this->zoneReferee);

        // ✅ Test declaring availability via AJAX
        $response = $this->postJson(route('referee.availability.toggle'), [
            'tournament_id' => $this->tournament->id,
            'available' => true,
            'notes' => 'AJAX availability'
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('availabilities', [
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->tournament->id,
            'notes' => 'AJAX availability',
        ]);

        // ✅ Test withdrawing availability via AJAX
        $response = $this->postJson(route('referee.availability.toggle'), [
            'tournament_id' => $this->tournament->id,
            'available' => false,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('availabilities', [
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->tournament->id,
        ]);
    }

    /**
     * Test availability calendar view shows correct data
     */
    public function test_availability_calendar_shows_correct_data()
    {
        $this->actingAs($this->zoneReferee);

        // ✅ Declare availability
        Availability::create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->tournament->id,
            'submitted_at' => Carbon::now(),
        ]);

        $response = $this->get(route('referee.availability.calendar'));

        $response->assertOk();
        $response->assertViewHas('calendarData');

        $calendarData = $response->viewData('calendarData');

        // ✅ Verify tournament is in calendar with correct availability status
        $tournamentEvent = collect($calendarData['tournaments'])->firstWhere('id', $this->tournament->id);

        $this->assertNotNull($tournamentEvent);
        $this->assertTrue($tournamentEvent['extendedProps']['is_available']);
        $this->assertEquals('available', $tournamentEvent['extendedProps']['personal_status']);
    }

    /**
     * Test availability filtering for national referees
     */
    public function test_national_referee_can_filter_by_zone()
    {
        $this->actingAs($this->nationalReferee);

        // ✅ Test filtering by zone
        $response = $this->get(route('referee.availability.index', [
            'zone_id' => $this->zone->id
        ]));

        $response->assertOk();
        $response->assertSee($this->tournament->name);
        $response->assertSee($this->nationalTournament->name);
    }

    /**
     * Test availability updates existing records correctly
     */
    public function test_availability_updates_existing_records()
    {
        $this->actingAs($this->zoneReferee);

        // ✅ Create initial availability
        $availability = Availability::create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->tournament->id,
            'notes' => 'Initial notes',
            'submitted_at' => Carbon::now()->subDay(),
        ]);

        // ✅ Update availability with new notes
        $availabilityData = [
            'availabilities' => [$this->tournament->id],
            'notes' => [
                $this->tournament->id => 'Updated notes'
            ]
        ];

        $response = $this->post(route('referee.availability.save'), $availabilityData);

        $response->assertRedirect(route('referee.availability.index'));

        // ✅ Verify old record was deleted and new one created with updated notes
        $this->assertDatabaseHas('availabilities', [
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->tournament->id,
            'notes' => 'Updated notes',
        ]);

        // ✅ Verify there's only one record (old was replaced)
        $this->assertEquals(1, Availability::where('user_id', $this->zoneReferee->id)
                                         ->where('tournament_id', $this->tournament->id)
                                         ->count());
    }

    /**
     * Test inactive referee cannot declare availability
     */
    public function test_inactive_referee_cannot_declare_availability()
    {
        // ✅ Deactivate referee
        $this->zoneReferee->update(['is_active' => false]);

        $this->actingAs($this->zoneReferee);

        // ✅ Should be blocked by middleware before reaching controller
        $response = $this->get(route('referee.availability.index'));

        // This depends on your middleware implementation
        // Could be redirect to login or 403 error
        $this->assertTrue(
            $response->isRedirect() || $response->status() === 403,
            'Inactive referee should not access availability system'
        );
    }
}
