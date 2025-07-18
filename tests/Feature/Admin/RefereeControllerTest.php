<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Referee;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class RefereeControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::factory()->create(['name' => 'Test Zone']);

        $this->admin = User::factory()->create([
            'user_type' => 'admin',
            'zone_id' => $this->zone->id,
            'email' => 'admin@test.com'
        ]);
    }

    /**
     * Test creating referee with User-Centric approach
     */
    public function test_admin_can_create_referee_user_centric()
    {
        $this->actingAs($this->admin);

        $refereeData = [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@test.com',
            'phone' => '123456789',
            'city' => 'Roma',
            'level' => '1_livello', // ✅ Valore ENUM corretto
            'zone_id' => $this->zone->id,
            'notes' => 'Test referee',
            'is_active' => true,
        ];

        $response = $this->post(route('admin.referees.store'), $refereeData);

        $response->assertRedirect(route('admin.referees.index'));
        $response->assertSessionHas('success');

        // ✅ Verify User was created with core data
        $this->assertDatabaseHas('users', [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@test.com',
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'level' => '1_livello', // ✅ Valore ENUM corretto
            'phone' => '123456789',
            'city' => 'Roma',
        ]);

        // ✅ Verify referee_code was generated
        $user = User::where('email', 'mario.rossi@test.com')->first();
        $this->assertNotNull($user->referee_code);
        $this->assertStringStartsWith('ARB', $user->referee_code);

        // ✅ Verify Referee extension was created (because notes provided)
        $this->assertDatabaseHas('referees', [
            'user_id' => $user->id,
            'notes' => 'Test referee',
        ]);
    }

    /**
     * Test creating referee with extended data creates Referee extension
     */
    public function test_admin_can_create_referee_with_extended_data()
    {
        $this->actingAs($this->admin);

        $refereeData = [
            'name' => 'Luigi Verdi',
            'email' => 'luigi.verdi@test.com',
            'phone' => '987654321',
            'level' => 'Regionale', // ✅ Valore ENUM corretto
            'zone_id' => $this->zone->id,
            'is_active' => true,
            // ✅ Extended data
            'address' => 'Via Roma 123',
            'postal_code' => '00100',
            'tax_code' => 'VRDLGU80A01H501Z',
            'qualifications' => ['stroke_play', 'match_play'],
            'languages' => ['it', 'en'],
            'available_for_international' => true,
        ];

        $response = $this->post(route('admin.referees.store'), $refereeData);

        $user = User::where('email', 'luigi.verdi@test.com')->first();

        // ✅ Verify User was created with core data
        $this->assertDatabaseHas('users', [
            'name' => 'Luigi Verdi',
            'user_type' => 'referee',
            'level' => 'Regionale', // ✅ Valore ENUM corretto
        ]);

        // ✅ Verify Referee extension was created with extended data
        $this->assertDatabaseHas('referees', [
            'user_id' => $user->id,
            'address' => 'Via Roma 123',
            'postal_code' => '00100',
            'tax_code' => 'VRDLGU80A01H501Z',
            'available_for_international' => true,
        ]);
    }

    /**
     * Test editing referee follows User-Centric approach
     */
    public function test_admin_can_edit_referee_user_centric()
    {
        $this->actingAs($this->admin);

        // Create referee user
        $referee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Original Name',
            'level' => 'Aspirante', // ✅ Valore ENUM corretto
        ]);

        $response = $this->get(route('admin.referees.edit', $referee));

        $response->assertOk();
        $response->assertViewIs('admin.referees.edit');
        $response->assertViewHas('referee', $referee);
    }

    /**
     * Test updating referee updates User model correctly
     */
    public function test_admin_can_update_referee_user_centric()
    {
        $this->actingAs($this->admin);

        $referee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Original Name',
            'level' => 'Aspirante', // ✅ Valore ENUM corretto
            'phone' => 'old-phone',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => $referee->email,
            'phone' => 'new-phone',
            'city' => 'Milano',
            'level' => '1_livello', // ✅ Valore ENUM corretto
            'zone_id' => $this->zone->id,
            'is_active' => true,
        ];

        $response = $this->put(route('admin.referees.update', $referee), $updateData);

        $response->assertRedirect(route('admin.referees.show', $referee));

        // ✅ Verify User model was updated
        $referee->refresh();
        $this->assertEquals('Updated Name', $referee->name);
        $this->assertEquals('1_livello', $referee->level); // ✅ Valore ENUM corretto
        $this->assertEquals('new-phone', $referee->phone);
        $this->assertEquals('Milano', $referee->city);
    }

    /**
     * Test updating with extended data creates/updates Referee extension
     */
    public function test_admin_can_update_referee_with_extended_data()
    {
        $this->actingAs($this->admin);

        $referee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
        ]);

        $updateData = [
            'name' => $referee->name,
            'email' => $referee->email,
            'level' => 'Nazionale', // ✅ Valore ENUM corretto
            'zone_id' => $this->zone->id,
            'is_active' => true,
            // ✅ Add extended data
            'address' => 'Via Nuova 456',
            'qualifications' => ['certified_instructor'],
            'available_for_international' => true,
        ];

        $response = $this->put(route('admin.referees.update', $referee), $updateData);

        // ✅ Verify User was updated
        $referee->refresh();
        $this->assertEquals('Nazionale', $referee->level); // ✅ Valore ENUM corretto

        // ✅ Verify Referee extension was created
        $this->assertDatabaseHas('referees', [
            'user_id' => $referee->id,
            'address' => 'Via Nuova 456',
            'available_for_international' => true,
        ]);
    }

    /**
     * Test zone access control
     */
    public function test_zone_admin_cannot_create_referee_in_other_zone()
    {
        $this->actingAs($this->admin);

        $otherZone = Zone::factory()->create(['name' => 'Other Zone']);

        $refereeData = [
            'name' => 'Unauthorized Referee',
            'email' => 'unauthorized@test.com',
            'level' => '1_livello', // ✅ Valore ENUM corretto
            'zone_id' => $otherZone->id, // Different zone
            'is_active' => true,
        ];

        $response = $this->post(route('admin.referees.store'), $refereeData);

        $response->assertStatus(403);
    }

    /**
     * Test deleting referee with cascade
     */
    public function test_admin_can_delete_referee_with_cascade()
    {
        $this->actingAs($this->admin);

        $referee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
        ]);

        // Create referee extension
        Referee::factory()->forUser($referee)->create();

        $response = $this->delete(route('admin.referees.destroy', $referee));

        $response->assertRedirect(route('admin.referees.index'));

        // ✅ Verify both User and Referee extension were deleted
        $this->assertDatabaseMissing('users', ['id' => $referee->id]);
        $this->assertDatabaseMissing('referees', ['user_id' => $referee->id]);
    }

    /**
     * Test referee code uniqueness
     */
    public function test_referee_codes_are_unique()
    {
        $this->actingAs($this->admin);

        // Create first referee
        $referee1Data = [
            'name' => 'Referee One',
            'email' => 'ref1@test.com',
            'level' => '1_livello', // ✅ Valore ENUM corretto
            'zone_id' => $this->zone->id,
        ];

        $this->post(route('admin.referees.store'), $referee1Data);

        // Create second referee
        $referee2Data = [
            'name' => 'Referee Two',
            'email' => 'ref2@test.com',
            'level' => '1_livello', // ✅ Valore ENUM corretto
            'zone_id' => $this->zone->id,
        ];

        $this->post(route('admin.referees.store'), $referee2Data);

        // ✅ Verify different referee codes
        $ref1 = User::where('email', 'ref1@test.com')->first();
        $ref2 = User::where('email', 'ref2@test.com')->first();

        $this->assertNotEquals($ref1->referee_code, $ref2->referee_code);
        $this->assertStringStartsWith('ARB', $ref1->referee_code);
        $this->assertStringStartsWith('ARB', $ref2->referee_code);
    }

    /**
     * Test RefereeLevelsHelper integration
     */
    public function test_referee_levels_helper_integration()
    {
        $this->actingAs($this->admin);

        // Test normalizzazione valori
        $refereeData = [
            'name' => 'Test Normalization',
            'email' => 'norm@test.com',
            'level' => 'primo_livello', // ✅ Valore che deve essere normalizzato
            'zone_id' => $this->zone->id,
        ];

        $response = $this->post(route('admin.referees.store'), $refereeData);

        $user = User::where('email', 'norm@test.com')->first();

        // ✅ Verify level was normalized to ENUM value
        $this->assertEquals('1_livello', $user->level);
    }

    /**
     * Test default filter shows only active referees
     */
    public function test_index_shows_only_active_referees_by_default()
    {
        $this->actingAs($this->admin);

        // Create active and inactive referees
        $activeReferee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Active Referee',
            'is_active' => true,
        ]);

        $inactiveReferee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Inactive Referee',
            'is_active' => false,
        ]);

        // ✅ Default call without status parameter
        $response = $this->get(route('admin.referees.index'));

        $response->assertOk();

        // ✅ Should show only active referee
        $response->assertSee('Active Referee');
        $response->assertDontSee('Inactive Referee');
    }

    /**
     * Test explicit status=all shows all referees
     */
    public function test_index_shows_all_referees_when_status_all()
    {
        $this->actingAs($this->admin);

        $activeReferee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Active Referee',
            'is_active' => true,
        ]);

        $inactiveReferee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Inactive Referee',
            'is_active' => false,
        ]);

        // ✅ Explicit status=all parameter
        $response = $this->get(route('admin.referees.index', ['status' => 'all']));

        $response->assertOk();

        // ✅ Should show both active and inactive
        $response->assertSee('Active Referee');
        $response->assertSee('Inactive Referee');
    }

    /**
     * Test status=inactive shows only inactive referees
     */
    public function test_index_shows_only_inactive_referees_when_filtered()
    {
        $this->actingAs($this->admin);

        $activeReferee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Active Referee',
            'is_active' => true,
        ]);

        $inactiveReferee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Inactive Referee',
            'is_active' => false,
        ]);

        // ✅ Filter by inactive status
        $response = $this->get(route('admin.referees.index', ['status' => 'inactive']));

        $response->assertOk();

        // ✅ Should show only inactive referee
        $response->assertDontSee('Active Referee');
        $response->assertSee('Inactive Referee');
    }

    /**
     * Test that search maintains active filter by default
     */
    public function test_search_maintains_active_filter_by_default()
    {
        $this->actingAs($this->admin);

        $activeReferee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Mario Rossi',
            'is_active' => true,
        ]);

        $inactiveReferee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Mario Bianchi',
            'is_active' => false,
        ]);

        // ✅ Search for 'Mario' without status parameter
        $response = $this->get(route('admin.referees.index', ['search' => 'Mario']));

        $response->assertOk();

        // ✅ Should find only active Mario
        $response->assertSee('Mario Rossi');
        $response->assertDontSee('Mario Bianchi');
    }

    /**
     * Test view form shows correct default status selection
     */
    public function test_view_form_shows_active_as_default_selection()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.referees.index'));

        $response->assertOk();

        // ✅ Status dropdown should have 'active' selected by default
        $response->assertSee('value="active" selected', false);
    }

    /**
     * Test pagination maintains filters including default active
     */
    public function test_pagination_maintains_active_filter()
    {
        $this->actingAs($this->admin);

        // Create 25 active referees (more than 20 per page)
        User::factory()->count(25)->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'is_active' => true,
        ]);

        // Create 5 inactive referees
        User::factory()->count(5)->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'is_active' => false,
        ]);

        // ✅ Test first page
        $response = $this->get(route('admin.referees.index'));
        $response->assertOk();

        // ✅ Test second page maintains active filter
        $response = $this->get(route('admin.referees.index', ['page' => 2]));
        $response->assertOk();

        // ✅ Should still show only active referees on page 2
        $referees = $response->viewData('referees');
        foreach ($referees as $referee) {
            $this->assertTrue($referee->is_active, 'Page 2 should only show active referees');
        }
    }
}
