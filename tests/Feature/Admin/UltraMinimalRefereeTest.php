<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class UltraMinimalRefereeTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $zone;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Crea zone con insert diretto
        $this->zone = Zone::create([
            'name' => 'Test Zone',
            'is_active' => true,
        ]);

        // ✅ Crea admin con referee_code (richiesto da schema)
        $this->admin = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
            'zone_id' => $this->zone->id,
            'referee_code' => 'ADM001', // ✅ Aggiungi referee_code
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Test basic referee creation
     */
    public function test_can_create_referee_basic()
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

        // ✅ Verifica redirect (anche se non per forza alla index)
        $this->assertTrue($response->isRedirect());

        // ✅ Verify User was created
        $this->assertDatabaseHas('users', [
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@test.com',
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'level' => '1_livello',
        ]);
    }

    /**
     * Test basic index view
     */
    public function test_can_view_referees_index()
    {
        $this->actingAs($this->admin);

        // ✅ Crea referee con create diretto
        $referee = User::create([
            'name' => 'Test Referee',
            'email' => 'referee@test.com',
            'password' => Hash::make('password'),
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'level' => 'Aspirante',
            'referee_code' => 'ARB001', // ✅ Aggiungi referee_code
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->get(route('admin.referees.index'));

        $response->assertOk();
        $response->assertSee('Test Referee');
    }

    /**
     * Test edit form
     */
    public function test_can_view_edit_form()
    {
        $this->actingAs($this->admin);

        $referee = User::create([
            'name' => 'Edit Test Referee',
            'email' => 'edit@test.com',
            'password' => Hash::make('password'),
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'level' => 'Aspirante',
            'referee_code' => 'ARB002', // ✅ Aggiungi referee_code
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->get(route('admin.referees.edit', $referee));

        $response->assertOk();
        $response->assertSee('Edit Test Referee');
    }

    /**
     * Test update referee
     */
    public function test_can_update_referee()
    {
        $this->actingAs($this->admin);

        $referee = User::create([
            'name' => 'Original Name',
            'email' => 'original@test.com',
            'password' => Hash::make('password'),
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'level' => 'Aspirante',
            'referee_code' => 'ARB003', // ✅ Aggiungi referee_code
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'original@test.com',
            'level' => '1_livello',
            'zone_id' => $this->zone->id,
            'is_active' => true,
        ];

        $response = $this->put(route('admin.referees.update', $referee), $updateData);

        // ✅ Verifica che abbia redirected
        $this->assertTrue($response->isRedirect());

        // ✅ Verify update
        $referee->refresh();
        $this->assertEquals('Updated Name', $referee->name);
        $this->assertEquals('1_livello', $referee->level);
    }

    /**
     * Test default active filter
     */
    public function test_shows_only_active_referees_by_default()
    {
        $this->actingAs($this->admin);

        // ✅ Crea referee attivo
        $activeReferee = User::create([
            'name' => 'Active Referee',
            'email' => 'active@test.com',
            'password' => Hash::make('password'),
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'level' => 'Aspirante',
            'referee_code' => 'ARB004', // ✅ Aggiungi referee_code
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // ✅ Crea referee inattivo
        $inactiveReferee = User::create([
            'name' => 'Inactive Referee',
            'email' => 'inactive@test.com',
            'password' => Hash::make('password'),
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'level' => 'Aspirante',
            'referee_code' => 'ARB005', // ✅ Aggiungi referee_code
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $response = $this->get(route('admin.referees.index'));

        $response->assertOk();
        $response->assertSee('Active Referee');
        $response->assertDontSee('Inactive Referee');
    }

    /**
     * Test RefereeLevelsHelper integration
     */
    public function test_referee_levels_helper_works()
    {
        // ✅ Test senza autenticazione, solo helper
        $this->assertEquals('1_livello', \App\Helpers\RefereeLevelsHelper::normalize('primo_livello'));
        $this->assertEquals('Aspirante', \App\Helpers\RefereeLevelsHelper::normalize('aspirante'));
        $this->assertTrue(\App\Helpers\RefereeLevelsHelper::isValid('1_livello'));

        $levels = \App\Helpers\RefereeLevelsHelper::getSelectOptions();
        $this->assertIsArray($levels);
        $this->assertArrayHasKey('1_livello', $levels);
    }

    /**
     * Test zone access control
     */
    public function test_zone_access_control()
    {
        $this->actingAs($this->admin);

        $otherZone = Zone::create([
            'name' => 'Other Zone',
            'is_active' => true,
        ]);

        $refereeData = [
            'name' => 'Unauthorized Referee',
            'email' => 'unauthorized@test.com',
            'level' => '1_livello',
            'zone_id' => $otherZone->id, // ✅ Zona diversa
            'is_active' => true,
        ];

        $response = $this->post(route('admin.referees.store'), $refereeData);

        // ✅ Dovrebbe essere 403 Forbidden
        $response->assertStatus(403);
    }

    /**
     * Test creation with normalization
     */
    public function test_level_normalization_during_creation()
    {
        $this->actingAs($this->admin);

        $refereeData = [
            'name' => 'Normalization Test',
            'email' => 'norm@test.com',
            'level' => 'primo_livello', // ✅ Should normalize to '1_livello'
            'zone_id' => $this->zone->id,
            'is_active' => true,
        ];

        $response = $this->post(route('admin.referees.store'), $refereeData);

        $user = User::where('email', 'norm@test.com')->first();

        // ✅ Verify normalization worked
        $this->assertNotNull($user);
        $this->assertEquals('1_livello', $user->level);
    }
}
