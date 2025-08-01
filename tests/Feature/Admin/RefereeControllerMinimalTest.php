<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Referee;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class RefereeControllerMinimalTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $zone;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Solo Zone con campi essenziali
        $this->zone = Zone::factory()->create(['name' => 'Test Zone']);

        $this->admin = User::factory()->create([
            'user_type' => 'admin',
            'zone_id' => $this->zone->id,
            'email' => 'admin@test.com'
        ]);
    }

    /**
     * Test basic referee creation with minimal data
     */
    public function test_admin_can_create_referee_minimal()
    {
        $this->actingAs($this->admin);

        $refereeData = [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@test.com',
            'level' => '1_livello', // ✅ Valore ENUM corretto
            'zone_id' => $this->zone->id,
            'is_active' => true,
        ];

        $response = $this->post(route('admin.referees.store'), $refereeData);

        $response->assertRedirect(route('admin.referees.index'));

        // ✅ Verify User was created
        $this->assertDatabaseHas('users', [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@test.com',
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'level' => '1_livello',
        ]);

        // ✅ Verify referee_code was generated
        $user = User::where('email', 'mario.rossi@test.com')->first();
        $this->assertNotNull($user->referee_code);
        $this->assertStringStartsWith('ARB', $user->referee_code);
    }

    /**
     * Test basic referee editing
     */
    public function test_admin_can_edit_referee_minimal()
    {
        $this->actingAs($this->admin);

        $referee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Original Name',
            'level' => 'Aspirante',
        ]);

        $response = $this->get(route('admin.referees.edit', $referee));

        $response->assertOk();
        $response->assertViewIs('admin.referees.edit');
        $response->assertViewHas('referee', $referee);
    }

    /**
     * Test basic referee update
     */
    public function test_admin_can_update_referee_minimal()
    {
        $this->actingAs($this->admin);

        $referee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Original Name',
            'level' => 'Aspirante',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => $referee->email,
            'level' => '1_livello',
            'zone_id' => $this->zone->id,
            'is_active' => true,
        ];

        $response = $this->put(route('admin.referees.update', $referee), $updateData);

        $response->assertRedirect(route('admin.referees.show', $referee));

        // ✅ Verify update
        $referee->refresh();
        $this->assertEquals('Updated Name', $referee->name);
        $this->assertEquals('1_livello', $referee->level);
    }

    /**
     * Test index shows referees
     */
    public function test_admin_can_view_referees_index()
    {
        $this->actingAs($this->admin);

        $referee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'name' => 'Test Referee',
        ]);

        $response = $this->get(route('admin.referees.index'));

        $response->assertOk();
        $response->assertSee('Test Referee');
    }

    /**
     * Test default active filter
     */
    public function test_index_shows_only_active_referees_by_default()
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

        $response = $this->get(route('admin.referees.index'));

        $response->assertOk();
        $response->assertSee('Active Referee');
        $response->assertDontSee('Inactive Referee');
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
            'level' => '1_livello',
            'zone_id' => $otherZone->id,
            'is_active' => true,
        ];

        $response = $this->post(route('admin.referees.store'), $refereeData);

        $response->assertStatus(403);
    }

    /**
     * Test referee levels helper integration
     */
    public function test_referee_levels_helper_normalization()
    {
        $this->actingAs($this->admin);

        $refereeData = [
            'name' => 'Test Normalization',
            'email' => 'norm@test.com',
            'level' => 'primo_livello', // ✅ Should be normalized to '1_livello'
            'zone_id' => $this->zone->id,
        ];

        $response = $this->post(route('admin.referees.store'), $refereeData);

        $user = User::where('email', 'norm@test.com')->first();

        // ✅ Verify normalization worked
        $this->assertEquals('1_livello', $user->level);
    }

    /**
     * Test delete referee (only if no assignments - simplified)
     */
    public function test_admin_can_delete_referee_basic()
    {
        $this->actingAs($this->admin);

        $referee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
        ]);

        $response = $this->delete(route('admin.referees.destroy', $referee));

        $response->assertRedirect(route('admin.referees.index'));

        // ✅ Verify deletion
        $this->assertDatabaseMissing('users', ['id' => $referee->id]);
    }
}
