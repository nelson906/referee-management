<?php

namespace Tests\Feature\Referee;

use Tests\TestCase;
use App\Models\User;
use App\Models\Referee;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $referee;

    protected function setUp(): void
    {
        parent::setUp();

        $zone = Zone::factory()->create();

        $this->referee = User::factory()->create([
            'user_type' => 'referee',
            'zone_id' => $zone->id,
            'name' => 'Test Referee',
            'level' => '1_livello', // ✅ Valore ENUM corretto
        ]);
    }

    /**
     * Test referee can update their profile
     */
    public function test_referee_can_update_profile_user_centric()
    {
        $this->actingAs($this->referee);

        $zone = Zone::factory()->create();

        $updateData = [
            'name' => 'Updated Referee Name',
            'email' => $this->referee->email,
            'phone' => '123456789',
            'city' => 'Roma',
            'level' => 'Regionale', // ✅ Valore ENUM corretto
            'zone_id' => $zone->id,
        ];

        $response = $this->put(route('referee.profile.update'), $updateData);

        $response->assertRedirect(route('referee.dashboard'));

        // ✅ Verify User model was updated
        $this->referee->refresh();
        $this->assertEquals('Updated Referee Name', $this->referee->name);
        $this->assertEquals('Regionale', $this->referee->level); // ✅ Valore ENUM corretto
        $this->assertEquals('123456789', $this->referee->phone);
        $this->assertEquals($zone->id, $this->referee->zone_id);
    }

    /**
     * Test referee updating with extended data creates Referee extension
     */
    public function test_referee_profile_update_creates_extension_when_needed()
    {
        $this->actingAs($this->referee);

        $updateData = [
            'name' => $this->referee->name,
            'email' => $this->referee->email,
            'level' => $this->referee->level,
            'zone_id' => $this->referee->zone_id,
            // Extended data
            'address' => 'Via Profile 789',
            'bio' => 'My referee bio',
            'experience_years' => 5,
            'specializations' => ['match_play'],
        ];

        $response = $this->put(route('referee.profile.update'), $updateData);

        // ✅ Verify Referee extension was created
        $this->assertDatabaseHas('referees', [
            'user_id' => $this->referee->id,
            'address' => 'Via Profile 789',
            'bio' => 'My referee bio',
            'experience_years' => 5,
        ]);

        // ✅ Verify profile marked as completed
        $referee = Referee::where('user_id', $this->referee->id)->first();
        $this->assertNotNull($referee->profile_completed_at);
    }

    /**
     * Test referee password update
     */
    public function test_referee_can_update_password()
    {
        $this->referee->update(['password' => bcrypt('oldpassword')]);
        $this->actingAs($this->referee);

        $response = $this->put(route('referee.profile.update-password'), [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // ✅ Verify password was changed
        $this->assertTrue(\Hash::check('newpassword123', $this->referee->fresh()->password));
    }

    /**
     * Test referee level normalization in profile update
     */
    public function test_referee_profile_level_normalization()
    {
        $this->actingAs($this->referee);

        $updateData = [
            'name' => $this->referee->name,
            'email' => $this->referee->email,
            'level' => 'primo_livello', // ✅ Valore che deve essere normalizzato
            'zone_id' => $this->referee->zone_id,
        ];

        $response = $this->put(route('referee.profile.update'), $updateData);

        // ✅ Verify level was normalized to ENUM value
        $this->referee->refresh();
        $this->assertEquals('1_livello', $this->referee->level);
    }
}
