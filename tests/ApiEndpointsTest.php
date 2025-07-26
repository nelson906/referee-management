<?php
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

        $this->user = User::factory()->create();
        $this->user->assignRole('SuperAdmin');
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    /**
     * @test
     * Test API statistiche
     */
    public function test_statistics_api_endpoints()
    {
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
     * Test API template selection
     */
    public function test_template_api_endpoints()
    {
        $zone = Zone::factory()->create();

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
}
