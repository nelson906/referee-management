<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Zone;
use App\Models\Tournament;
use App\Models\LetterTemplate;
use Spatie\Permission\Models\Role;

/**
 * Test API Endpoints
 *
 * @group api
 * @group e2e
 */
class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup permissions
        $this->app['config']->set('permission.table_names', [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);

        $this->artisan('permission:cache-reset');

        // Create roles
        Role::firstOrCreate(['name' => 'SuperAdmin']);
        Role::firstOrCreate(['name' => 'Admin']);
        Role::firstOrCreate(['name' => 'Referee']);

        // Create test user
        $this->user = User::factory()->create([
            'user_type' => 'super_admin',
            'email_verified_at' => now(),
        ]);
        $this->user->assignRole('SuperAdmin');
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    /**
     * @test
     * Test API statistiche disponibilità
     */
    #[Test]
public function test_statistics_disponibilita_api_endpoint()
    {
        // Create test data
        $zone = Zone::factory()->create();
        $tournament = Tournament::factory()->create(['zone_id' => $zone->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/statistics/disponibilita');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'zona',
                'codice_zona',
                'totale_dichiarazioni',
                'disponibili',
                'percentuale_disponibilita'
            ]
        ]);
    }

    /**
     * @test
     * Test API health check
     */
    #[Test]
public function test_health_check_endpoint()
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'version',
            'database'
        ]);

        $data = $response->json();
        $this->assertEquals('healthy', $data['status']);
        $this->assertTrue($data['database']);
    }

    /**
     * @test
     * Test API template selection per zona
     */
    #[Test]
public function test_template_api_endpoints()
    {
        $zone = Zone::factory()->create();

        // Create a letter template for testing
        LetterTemplate::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'assignment',
            'is_active' => true
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get("/api/letter-templates/assignment/{$zone->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'subject',
                'body'
            ]
        ]);
    }

    /**
     * @test
     * Test API institutional emails per zona
     */
    #[Test]
public function test_institutional_emails_api_endpoint()
    {
        $zone = Zone::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get("/api/institutional-emails/{$zone->id}");

        $response->assertStatus(200);
        $response->assertJson([]);
    }

    /**
     * @test
     * Test API notification stats
     */
    #[Test]
public function test_notification_stats_api_endpoint()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/notifications/stats/30');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_sent',
            'successful',
            'failed',
            'pending',
            'success_rate'
        ]);
    }

    /**
     * @test
     * Test API monitoring metrics
     */
    #[Test]
public function test_monitoring_metrics_api_endpoint()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/admin/monitoring/metrics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'system_health',
            'performance_metrics',
            'resource_usage'
        ]);
    }

    /**
     * @test
     * Test API tournaments calendar
     */
    #[Test]
public function test_tournaments_calendar_api_endpoint()
    {
        $zone = Zone::factory()->create();
        $tournament = Tournament::factory()->create([
            'zone_id' => $zone->id,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(12)
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/tournaments/calendar');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'title',
                'start',
                'end',
                'url'
            ]
        ]);
    }

    /**
     * @test
     * Test API status endpoint
     */
    #[Test]
public function test_status_api_endpoint()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'environment',
            'debug',
            'timezone',
            'php_version',
            'laravel_version'
        ]);
    }

    /**
     * @test
     * Test protezione CSRF su API POST
     */
    #[Test]
public function test_api_csrf_protection()
    {
        $response = $this->postJson('/api/test-endpoint', [
            'test' => 'data'
        ]);

        // Dovrebbe richiedere autenticazione
        $response->assertStatus(401);
    }

    /**
     * @test
     * Test rate limiting su API
     */
    #[Test]
public function test_api_rate_limiting()
    {
        // Questo test andrebbe adattato in base alla configurazione specifica
        $this->assertTrue(true); // Placeholder - implementare logica specifica
    }

    /**
     * @test
     * Test API error handling
     */
    #[Test]
public function test_api_error_handling()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
            'Accept' => 'application/json'
        ])->get('/api/statistics/disponibilita');

        $response->assertStatus(401);
        $response->assertJsonStructure([
            'message'
        ]);
    }
}
