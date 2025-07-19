<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tournament;
use App\Models\TournamentType; // CAMBIATO da TournamentCategory
use App\Models\Zone;
use App\Models\Club;
use App\Models\Availability;
use App\Models\Referee; // AGGIUNTO
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AvailabilityFlowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $zoneReferee;
    protected User $nationalReferee;
    protected Zone $zone;
    protected TournamentType $zoneType; // CAMBIATO da $zoneCategory
    protected TournamentType $nationalType; // CAMBIATO da $nationalCategory
    protected Club $club;
    protected Tournament $zoneTournament;
    protected Tournament $nationalTournament;

    protected function setUp(): void
    {
        parent::setUp();

        // Create zone
        $this->zone = Zone::create([
            'name' => 'Test Zone',
            'description' => 'Test zone for availability flow testing',
            'is_national' => false
        ]);

        // Create club
        $this->club = Club::create([
            'name' => 'Test Golf Club',
            'code' => 'TGC001',
            'city' => 'Test City',
            'zone_id' => $this->zone->id,
            'is_active' => true
        ]);

        // Create tournament types - USA STRUTTURA REALE DATABASE
        $this->zoneType = TournamentType::create([
            'name' => 'Gara Sociale',
            'code' => 'GS',
            'description' => 'Torneo zonale',
            'active' => 1, // NON is_active!
            'is_national' => 0 // NON false ma 0!
        ]);

        $this->nationalType = TournamentType::create([
            'name' => 'Open Nazionale',
            'code' => 'ON',
            'description' => 'Torneo nazionale',
            'active' => 1, // NON is_active!
            'is_national' => 1 // NON true ma 1!
        ]);

        // Create referees (senza level nel User!)
        $this->zoneReferee = User::factory()->create([
            'user_type' => User::TYPE_REFEREE,
            'zone_id' => $this->zone->id,
            'is_active' => true
        ]);

        $this->nationalReferee = User::factory()->create([
            'user_type' => User::TYPE_REFEREE,
            'zone_id' => $this->zone->id,
            'is_active' => true
        ]);

        // Crea i record Referee separatamente con i level
        \App\Models\Referee::create([
            'user_id' => $this->zoneReferee->id,
            'zone_id' => $this->zone->id,
            'referee_code' => 'REF001',
            'level' => 'regionale',
            'category' => 'misto',
            'certified_date' => now()
        ]);

        \App\Models\Referee::create([
            'user_id' => $this->nationalReferee->id,
            'zone_id' => $this->zone->id,
            'referee_code' => 'REF002',
            'level' => 'nazionale',
            'category' => 'misto',
            'certified_date' => now()
        ]);

        // Create tournaments
        $this->zoneTournament = Tournament::create([
            'name' => 'Torneo Zonale Test',
            'start_date' => Carbon::today()->addDays(14),
            'end_date' => Carbon::today()->addDays(16),
            'availability_deadline' => Carbon::today()->addDays(7),
            'club_id' => $this->club->id,
            'tournament_type_id' => $this->zoneType->id, // CAMBIATO da tournament_category_id
            'zone_id' => $this->zone->id,
            'status' => Tournament::STATUS_OPEN
        ]);

        $this->nationalTournament = Tournament::create([
            'name' => 'Torneo Nazionale Test',
            'start_date' => Carbon::today()->addDays(21),
            'end_date' => Carbon::today()->addDays(23),
            'availability_deadline' => Carbon::today()->addDays(10),
            'club_id' => $this->club->id,
            'tournament_type_id' => $this->nationalType->id, // CAMBIATO da tournament_category_id
            'zone_id' => $this->zone->id,
            'status' => Tournament::STATUS_OPEN
        ]);
    }

    #[Test]
    public function referee_can_access_availability_page(): void
    {
        $response = $this
            ->actingAs($this->zoneReferee)
            ->get('/referee/availability');

        $response->assertOk();
        $response->assertViewIs('referee.availability.index');
    }

    #[Test]
    public function non_referee_cannot_access_availability_page(): void
    {
        $user = User::factory()->create(['user_type' => User::TYPE_ADMIN]);

        $response = $this
            ->actingAs($user)
            ->get('/referee/availability');

        $response->assertStatus(403);
    }

    #[Test]
    public function zone_referee_can_see_only_zone_tournaments(): void
    {
        $otherZone = Zone::create([
            'name' => 'Other Zone',
            'description' => 'Another test zone',
            'is_national' => false
        ]);

        $otherClub = Club::create([
            'name' => 'Other Golf Club',
            'code' => 'OGC001',
            'city' => 'Other City',
            'zone_id' => $otherZone->id,
            'is_active' => true
        ]);

        $otherZoneTournament = Tournament::create([
            'name' => 'Other Zone Tournament',
            'start_date' => Carbon::today()->addDays(10),
            'end_date' => Carbon::today()->addDays(12),
            'availability_deadline' => Carbon::today()->addDays(5),
            'zone_id' => $otherZone->id,
            'club_id' => $otherClub->id,
            'tournament_type_id' => $this->zoneType->id, // CAMBIATO
            'status' => Tournament::STATUS_OPEN
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->get('/referee/availability');

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
        $response->assertDontSee($otherZoneTournament->name);
    }

    #[Test]
    public function national_referee_can_see_all_tournaments(): void
    {
        $otherZone = Zone::create([
            'name' => 'Other Zone',
            'description' => 'Another test zone',
            'is_national' => false
        ]);

        $otherClub = Club::create([
            'name' => 'Other Golf Club',
            'code' => 'OGC002',
            'city' => 'Other City',
            'zone_id' => $otherZone->id,
            'is_active' => true
        ]);

        $otherZoneTournament = Tournament::create([
            'name' => 'Other Zone Tournament',
            'start_date' => Carbon::today()->addDays(10),
            'end_date' => Carbon::today()->addDays(12),
            'availability_deadline' => Carbon::today()->addDays(5),
            'zone_id' => $otherZone->id,
            'club_id' => $otherClub->id,
            'tournament_type_id' => $this->zoneType->id, // CAMBIATO
            'status' => Tournament::STATUS_OPEN
        ]);

        $response = $this
            ->actingAs($this->nationalReferee)
            ->get('/referee/availability');

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
        $response->assertSee($this->nationalTournament->name);
        $response->assertSee($otherZoneTournament->name);
    }

    #[Test]
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

    #[Test]
    public function referee_can_bulk_declare_availability(): void
    {
        $additionalTournament = Tournament::create([
            'name' => 'Additional Tournament',
            'start_date' => Carbon::today()->addDays(15),
            'end_date' => Carbon::today()->addDays(17),
            'availability_deadline' => Carbon::today()->addDays(8),
            'zone_id' => $this->zone->id,
            'club_id' => $this->club->id,
            'tournament_type_id' => $this->zoneType->id, // CAMBIATO
            'status' => Tournament::STATUS_OPEN
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

    #[Test]
    public function referee_cannot_declare_availability_for_past_deadline_tournament(): void
    {
        $expiredTournament = Tournament::create([
            'name' => 'Expired Tournament',
            'start_date' => Carbon::today()->addDays(5),
            'end_date' => Carbon::today()->addDays(7),
            'availability_deadline' => Carbon::yesterday(),
            'zone_id' => $this->zone->id,
            'club_id' => $this->club->id,
            'tournament_type_id' => $this->zoneType->id, // CAMBIATO
            'status' => Tournament::STATUS_OPEN
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

    #[Test]
    public function referee_cannot_declare_duplicate_availability(): void
    {
        // First declaration
        Availability::create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id,
            'submitted_at' => Carbon::now()
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

    #[Test]
    public function referee_can_withdraw_availability_before_deadline(): void
    {
        $availability = Availability::create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id,
            'submitted_at' => Carbon::now()
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

    #[Test]
    public function referee_cannot_withdraw_availability_after_deadline(): void
    {
        $expiredTournament = Tournament::create([
            'name' => 'Expired Tournament',
            'start_date' => Carbon::today()->addDays(5),
            'end_date' => Carbon::today()->addDays(7),
            'availability_deadline' => Carbon::yesterday(),
            'zone_id' => $this->zone->id,
            'club_id' => $this->club->id,
            'tournament_type_id' => $this->zoneType->id, // CAMBIATO
            'status' => Tournament::STATUS_OPEN
        ]);

        $availability = Availability::create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $expiredTournament->id,
            'submitted_at' => Carbon::now()->subDays(2)
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->delete("/referee/availability/{$availability->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id
        ]);
    }

    #[Test]
    public function availability_page_can_be_filtered_by_zone(): void
    {
        $response = $this
            ->actingAs($this->nationalReferee)
            ->get('/referee/availability?zone_id=' . $this->zone->id);

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
        $response->assertSee($this->nationalTournament->name);
    }

    #[Test]
    public function availability_page_can_be_filtered_by_category(): void
    {
        $response = $this
            ->actingAs($this->nationalReferee)
            ->get('/referee/availability?type_id=' . $this->zoneType->id); // CAMBIATO da category_id

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
        $response->assertDontSee($this->nationalTournament->name);
    }

    #[Test]
    public function availability_page_can_be_filtered_by_month(): void
    {
        $month = $this->zoneTournament->start_date->format('Y-m');

        $response = $this
            ->actingAs($this->nationalReferee)
            ->get("/referee/availability?month={$month}");

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
    }

    #[Test]
    public function referee_can_see_their_existing_availabilities(): void
    {
        Availability::create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id,
            'notes' => 'Già dichiarato',
            'submitted_at' => Carbon::now()
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->get('/referee/availability');

        $response->assertOk();
        $response->assertSee('Già dichiarato');
    }

    #[Test]
    public function only_open_tournaments_are_shown(): void
    {
        $closedTournament = Tournament::create([
            'name' => 'Closed Tournament',
            'start_date' => Carbon::today()->addDays(10),
            'end_date' => Carbon::today()->addDays(12),
            'availability_deadline' => Carbon::tomorrow(),
            'zone_id' => $this->zone->id,
            'club_id' => $this->club->id,
            'tournament_type_id' => $this->zoneType->id, // CAMBIATO
            'status' => Tournament::STATUS_CLOSED
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->get('/referee/availability');

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
        $response->assertDontSee($closedTournament->name);
    }

    #[Test]
    public function tournaments_past_deadline_are_not_shown(): void
    {
        $pastDeadlineTournament = Tournament::create([
            'name' => 'Past Deadline Tournament',
            'start_date' => Carbon::today()->addDays(10),
            'end_date' => Carbon::today()->addDays(12),
            'availability_deadline' => Carbon::yesterday(),
            'zone_id' => $this->zone->id,
            'club_id' => $this->club->id,
            'tournament_type_id' => $this->zoneType->id, // CAMBIATO
            'status' => Tournament::STATUS_OPEN
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->get('/referee/availability');

        $response->assertOk();
        $response->assertSee($this->zoneTournament->name);
        $response->assertDontSee($pastDeadlineTournament->name);
    }

    #[Test]
    public function referee_can_update_availability_notes(): void
    {
        $availability = Availability::create([
            'user_id' => $this->zoneReferee->id,
            'tournament_id' => $this->zoneTournament->id,
            'notes' => 'Note originali',
            'submitted_at' => Carbon::now()
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

    #[Test]
    public function referee_cannot_modify_others_availability(): void
    {
        $otherReferee = User::factory()->create([
            'user_type' => User::TYPE_REFEREE,
            'zone_id' => $this->zone->id,
            'is_active' => true
        ]);

        // Crea il record Referee separatamente
        \App\Models\Referee::create([
            'user_id' => $otherReferee->id,
            'zone_id' => $this->zone->id,
            'referee_code' => 'REF003',
            'level' => 'regionale',
            'category' => 'misto',
            'certified_date' => now()
        ]);

        $availability = Availability::create([
            'user_id' => $otherReferee->id,
            'tournament_id' => $this->zoneTournament->id,
            'submitted_at' => Carbon::now()
        ]);

        $response = $this
            ->actingAs($this->zoneReferee)
            ->delete("/referee/availability/{$availability->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id
        ]);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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
