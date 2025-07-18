<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\Zone;
use App\Models\Club;
use App\Models\Availability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;

class AvailabilityFlowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $zoneReferee;
    protected User $nationalReferee;
    protected Zone $zone;
    protected TournamentCategory $zoneCategory;
    protected TournamentCategory $nationalCategory;
    protected Club $club;
    protected Tournament $zoneTournament;
    protected Tournament $nationalTournament;

    protected function setUp(): void
    {
        parent::setUp();

        // Create zone
        $this->zone = Zone::factory()->create([
            'name' => 'Test Zone',
            'code' => 'TZ'
        ]);

        // Create club
        $this->club = Club::factory()->create([
            'name' => 'Test Golf Club',
            'zone_id' => $this->zone->id
        ]);

        // Create tournament categories
        $this->zoneCategory = TournamentCategory::factory()->create([
            'name' => 'Gara Sociale',
            'level' => 'zonale'
        ]);

        $this->nationalCategory = TournamentCategory::factory()->create([
            'name' => 'Open Nazionale',
            'level' => 'nazionale'
        ]);

        // Create referees
        $this->zoneReferee = User::factory()->create([
            'role' => 'referee',
            'level' => 'regionale',
            'zone_id' => $this->zone->id
        ]);

        $this->nationalReferee = User::factory()->create([
            'role' => 'referee',
            'level' => 'nazionale',
            'zone_id' => $this->zone->id
        ]);

        // Create tournaments
        $this->zoneTournament = Tournament::factory()->create([
            'name' => 'Torneo Zonale Test',
            'start_date' => Carbon::today()->addDays(14),
            'end_date' => Carbon::today()->addDays(16),
            'availability_deadline' => Carbon::today()->addDays(7),
            'club_id' => $this->club->id,
            'tournament_category_id' => $this->zoneCategory->id,
            'zone_id' => $this->zone->id,
            'status' => Tournament::STATUS_OPEN
        ]);

        $this->nationalTournament = Tournament::factory()->create([
            'name' => 'Torneo Nazionale Test',
            'start_date' => Carbon::today()->addDays(21),
            'end_date' => Carbon::today()->addDays(23),
            'availability_deadline' => Carbon::today()->addDays(10),
            'club_id' => $this->club->id,
            'tournament_category_id' => $this->nationalCategory->id,
            'zone_id' => $this->zone->id,
            'status' => Tournament::STATUS_OPEN
        ]);
    }

    public function referee_can_access_availability_page(): void
    {
        $response = $this
            ->actingAs($this->zoneReferee)
            ->get('/referee/availability');

        $response->assertOk();
        $response->assertViewIs('referee.availability.index');
    }

    public function non_referee_cannot_access_availability_page(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this
            ->actingAs($user)
            ->get('/referee/availability');

        $response->assertStatus(403);
    }

    public function zone_referee_can_see_only_zone_tournaments(): void
    {
        $otherZone = Zone::factory()->create();
        $otherClub = Club::factory()->create(['zone_id' => $otherZone->id]);
        $otherZoneTournament = Tournament::factory()->create([
            'zone_id' => $otherZone->id,
            'club_id' => $otherClub->id,
            'tournament_category_id' => $this->zoneCategory->id,
            'status' => Tournament::STATUS_OPEN,
            'availability_deadline' => Carbon::today()->addDays(5)
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->get('/referee/availability');

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
        $response->assertDontSee($otherZoneTournament->name);
    }

    public function national_referee_can_see_all_tournaments(): void
    {
        $otherZone = Zone::factory()->create();
        $otherClub = Club::factory()->create(['zone_id' => $otherZone->id]);
        $otherZoneTournament = Tournament::factory()->create([
            'zone_id' => $otherZone->id,
            'club_id' => $otherClub->id,
            'tournament_category_id' => $this->zoneCategory->id,
            'status' => Tournament::STATUS_OPEN,
            'availability_deadline' => Carbon::today()->addDays(5)
        ]);

        $response = $this
            ->actingAs($this->nationalReferee)
            ->get('/referee/availability');

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
        $response->assertSee($this->nationalTournament->name);
        $response->assertSee($otherZoneTournament->name);
    }

    public function referee_can_declare_availability_for_tournament(): void
    {
        $response = $this
            ->actingAs($this->zoneReferee)
            ->post('/referee/availability', [
                'tournament_id' => $this->zoneTournament->id,
                'notes' => 'Disponibile per tutto il torneo'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('availabilities', [
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id,
            'notes' => 'Disponibile per tutto il torneo'
        ]);
    }

    public function referee_can_bulk_declare_availability(): void
    {
        $additionalTournament = Tournament::factory()->create([
            'zone_id' => $this->zone->id,
            'club_id' => $this->club->id,
            'tournament_category_id' => $this->zoneCategory->id,
            'status' => Tournament::STATUS_OPEN,
            'availability_deadline' => Carbon::today()->addDays(8)
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->post('/referee/availability/bulk', [
                'tournament_ids' => [
                    $this->zoneTournament->id,
                    $additionalTournament->id
                ],
                'notes' => 'Disponibile per entrambi'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('availabilities', [
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id
        ]);

        $this->assertDatabaseHas('availabilities', [
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $additionalTournament->id
        ]);
    }

    public function referee_cannot_declare_availability_for_past_deadline_tournament(): void
    {
        $expiredTournament = Tournament::factory()->create([
            'zone_id' => $this->zone->id,
            'club_id' => $this->club->id,
            'tournament_category_id' => $this->zoneCategory->id,
            'status' => Tournament::STATUS_OPEN,
            'availability_deadline' => Carbon::yesterday()
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->post('/referee/availability', [
                'tournament_id' => $expiredTournament->id,
                'notes' => 'Tentativo tardivo'
            ]);

        $response->assertSessionHasErrors(['tournament_id']);

        $this->assertDatabaseMissing('availabilities', [
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $expiredTournament->id
        ]);
    }

    public function referee_cannot_declare_duplicate_availability(): void
    {
        // First declaration
        Availability::factory()->create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id
        ]);

        // Attempt duplicate
        $response = $this
            ->actingAs($this->zoneReferee)
            ->post('/referee/availability', [
                'tournament_id' => $this->zoneTournament->id,
                'notes' => 'Duplicato'
            ]);

        $response->assertSessionHasErrors(['tournament_id']);

        $this->assertEquals(1, Availability::where([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id
        ])->count());
    }

    public function referee_can_withdraw_availability_before_deadline(): void
    {
        $availability = Availability::factory()->create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->delete("/referee/availability/{$availability->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('availabilities', [
            'id' => $availability->id
        ]);
    }

    public function referee_cannot_withdraw_availability_after_deadline(): void
    {
        $expiredTournament = Tournament::factory()->create([
            'zone_id' => $this->zone->id,
            'club_id' => $this->club->id,
            'tournament_category_id' => $this->zoneCategory->id,
            'status' => Tournament::STATUS_OPEN,
            'availability_deadline' => Carbon::yesterday()
        ]);

        $availability = Availability::factory()->create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $expiredTournament->id
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->delete("/referee/availability/{$availability->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id
        ]);
    }

    public function availability_page_can_be_filtered_by_zone(): void
    {
        $response = $this
            ->actingAs($this->nationalReferee)
            ->get('/referee/availability?zone_id=' . $this->zone->id);

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
        $response->assertSee($this->nationalTournament->name);
    }

    public function availability_page_can_be_filtered_by_category(): void
    {
        $response = $this
            ->actingAs($this->nationalReferee)
            ->get('/referee/availability?category_id=' . $this->zoneCategory->id);

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
        $response->assertDontSee($this->nationalTournament->name);
    }

    public function availability_page_can_be_filtered_by_month(): void
    {
        $month = $this->zoneTournament->start_date->format('Y-m');

        $response = $this
            ->actingAs($this->nationalReferee)
            ->get("/referee/availability?month={$month}");

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
    }

    public function referee_can_see_their_existing_availabilities(): void
    {
        Availability::factory()->create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id,
            'notes' => 'Già dichiarato'
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->get('/referee/availability');

        $response->assertOk();
        $response->assertSee('Già dichiarato');
    }

    public function only_open_tournaments_are_shown(): void
    {
        $closedTournament = Tournament::factory()->create([
            'zone_id' => $this->zone->id,
            'club_id' => $this->club->id,
            'tournament_category_id' => $this->zoneCategory->id,
            'status' => Tournament::STATUS_CLOSED,
            'availability_deadline' => Carbon::tomorrow()
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->get('/referee/availability');

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
        $response->assertDontSee($closedTournament->name);
    }

    public function tournaments_past_deadline_are_not_shown(): void
    {
        $pastDeadlineTournament = Tournament::factory()->create([
            'zone_id' => $this->zone->id,
            'club_id' => $this->club->id,
            'tournament_category_id' => $this->zoneCategory->id,
            'status' => Tournament::STATUS_OPEN,
            'availability_deadline' => Carbon::yesterday()
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->get('/referee/availability');

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
        $response->assertDontSee($pastDeadlineTournament->name);
    }

    public function referee_can_update_availability_notes(): void
    {
        $availability = Availability::factory()->create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id,
            'notes' => 'Note originali'
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->patch("/referee/availability/{$availability->id}", [
                'notes' => 'Note aggiornate'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $availability->refresh();
        $this->assertEquals('Note aggiornate', $availability->notes);
    }

    public function referee_cannot_modify_others_availability(): void
    {
        $otherReferee = User::factory()->create([
            'role' => 'referee',
            'level' => 'regionale',
            'zone_id' => $this->zone->id
        ]);

        $availability = Availability::factory()->create([
            'user_id' => $otherReferee->id,
            'tournament_id' => $this->zoneTournament->id
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->delete("/referee/availability/{$availability->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id
        ]);
    }

    public function availability_includes_submission_timestamp(): void
    {
        $beforeSubmission = Carbon::now();

        $this
            ->actingAs($this->zoneReferee)
            ->post('/referee/availability', [
                'tournament_id' => $this->zoneTournament->id,
                'notes' => 'Test timestamp'
            ]);

        $availability = Availability::where([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id
        ])->first();

        $this->assertNotNull($availability->submitted_at);
        $this->assertTrue($availability->submitted_at->gte($beforeSubmission));
    }

    public function validation_requires_valid_tournament_id(): void
    {
        $response = $this
            ->actingAs($this->zoneReferee)
            ->post('/referee/availability', [
                'tournament_id' => 999999,
                'notes' => 'Invalid tournament'
            ]);

        $response->assertSessionHasErrors(['tournament_id']);
    }

    public function validation_allows_empty_notes(): void
    {
        $response = $this
            ->actingAs($this->zoneReferee)
            ->post('/referee/availability', [
                'tournament_id' => $this->zoneTournament->id
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('availabilities', [
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id,
            'notes' => null
        ]);
    }
}
