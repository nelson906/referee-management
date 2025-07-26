<?php

/**
 * TASK 6: Testing End-to-End Automatizzato
 *
 * OBIETTIVO: Test automatici per validare workflow completo sistema
 * TEMPO STIMATO: 3-4 ore
 * COMPLESSITÀ: Media
 *
 * UTILIZZO: php artisan test --group=e2e
 */

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Zone;
use App\Models\Tournament;
use App\Models\Assignment;
use App\Models\Availability;
use App\Models\LetterTemplate;
use App\Models\InstitutionalEmail;
use App\Services\NotificationService;
use App\Services\TemplateService;

/**
 * Test completo workflow: Torneo → Disponibilità → Assegnazione → Notifica
 *
 * @group e2e
 */
class CompleteWorkflowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $superAdmin;
    private $zoneAdmin;
    private $referee;
    private $zone;
    private $tournament;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestData();
    }

    /**
     * Setup dati di test
     */
    private function setupTestData()
    {
        // Crea zona
        $this->zone = Zone::factory()->create([
            'name' => 'Test Zone SZR1',
            'code' => 'SZR1',
            'is_active' => true
        ]);

        // Crea utenti con ruoli
        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin@test.com',
            'user_type' => 'admin'
        ]);
        $this->superAdmin->assignRole('SuperAdmin');

        $this->zoneAdmin = User::factory()->create([
            'email' => 'admin.szr1@test.com',
            'user_type' => 'admin',
            'zone_id' => $this->zone->id
        ]);
        $this->zoneAdmin->assignRole('Admin');

        $this->referee = User::factory()->create([
            'email' => 'arbitro@test.com',
            'user_type' => 'referee',
            'zone_id' => $this->zone->id,
            'level' => 'regionale'
        ]);
        $this->referee->assignRole('Referee');

        // Crea template e email istituzionali
        $this->createTemplatesAndEmails();
    }

    private function createTemplatesAndEmails()
    {
        // Template assignment
        LetterTemplate::factory()->create([
            'name' => 'Template Test Assignment',
            'type' => 'assignment',
            'subject' => 'Test Assignment: {{tournament_name}}',
            'body' => 'Caro {{referee_name}}, sei assegnato a {{tournament_name}} come {{assignment_role}}.',
            'is_default' => true,
            'is_active' => true
        ]);

        // Email istituzionale
        InstitutionalEmail::factory()->create([
            'name' => 'Test Zone Email',
            'email' => 'test.szr1@federgolf.it',
            'category' => 'zone',
            'zone_id' => $this->zone->id,
            'notification_types' => ['assignment'],
            'is_active' => true
        ]);
    }

    /**
     * @test
     * Test workflow completo: creazione torneo → disponibilità → assegnazione → notifica
     */
    public function test_complete_tournament_workflow()
    {
        // STEP 1: Zone Admin crea torneo
        $this->actingAs($this->zoneAdmin);

        $tournamentData = [
            'name' => 'Torneo Test E2E',
            'start_date' => now()->addDays(30)->format('Y-m-d'),
            'end_date' => now()->addDays(31)->format('Y-m-d'),
            'zone_id' => $this->zone->id,
            'club_id' => 1, // Assume club exists
            'tournament_type_id' => 1,
            'status' => 'open',
            'availability_deadline' => now()->addDays(20)->format('Y-m-d H:i:s')
        ];

        $response = $this->post('/admin/tournaments', $tournamentData);
        $response->assertRedirect();

        $tournament = Tournament::where('name', 'Torneo Test E2E')->first();
        $this->assertNotNull($tournament);
        $this->assertEquals('open', $tournament->status);

        // STEP 2: Arbitro dichiara disponibilità
        $this->actingAs($this->referee);

        $availabilityData = [
            'tournament_id' => $tournament->id,
            'available' => true,
            'notes' => 'Disponibile per questo torneo'
        ];

        $response = $this->post('/referee/availability', $availabilityData);
        $response->assertRedirect();

        $availability = Availability::where('tournament_id', $tournament->id)
            ->where('user_id', $this->referee->id)
            ->first();

        $this->assertNotNull($availability);
        $this->assertTrue($availability->available);

        // STEP 3: Zone Admin chiude disponibilità e assegna arbitri
        $this->actingAs($this->zoneAdmin);

        // Chiudi disponibilità
        $tournament->update(['status' => 'closed']);

        // Crea assegnazione
        $assignmentData = [
            'tournament_id' => $tournament->id,
            'user_id' => $this->referee->id,
            'role' => 'Arbitro',
            'notes' => 'Assegnazione test'
        ];

        $response = $this->post('/admin/assignments', $assignmentData);
        $response->assertRedirect();

        $assignment = Assignment::where('tournament_id', $tournament->id)
            ->where('user_id', $this->referee->id)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertEquals('Arbitro', $assignment->role);

        // STEP 4: Verifica invio notifiche
        $notificationService = app(NotificationService::class);
        $result = $notificationService->sendAssignmentNotification($assignment);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['notifications_sent']);

        // STEP 5: Verifica contenuto notifica
        $this->assertDatabaseHas('notifications', [
            'assignment_id' => $assignment->id,
            'recipient_email' => $this->referee->email,
            'status' => 'sent'
        ]);

        // STEP 6: Zone Admin conferma torneo
        $tournament->update(['status' => 'confirmed']);
        $assignment->update(['status' => 'confirmed']);

        $this->assertEquals('confirmed', $tournament->fresh()->status);
        $this->assertEquals('confirmed', $assignment->fresh()->status);
    }

    /**
     * @test
     * Test autorizzazioni per zone
     */
    public function test_zone_access_restrictions()
    {
        // Crea seconda zona e admin
        $zone2 = Zone::factory()->create(['code' => 'SZR2']);
        $admin2 = User::factory()->create([
            'user_type' => 'admin',
            'zone_id' => $zone2->id
        ]);
        $admin2->assignRole('Admin');

        $tournament2 = Tournament::factory()->create([
            'zone_id' => $zone2->id
        ]);

        // Zone Admin 1 non deve vedere tornei Zone 2
        $this->actingAs($this->zoneAdmin);
        $response = $this->get("/admin/tournaments/{$tournament2->id}");
        $response->assertStatus(403);

        // SuperAdmin deve vedere tutto
        $this->actingAs($this->superAdmin);
        $response = $this->get("/admin/tournaments/{$tournament2->id}");
        $response->assertStatus(200);
    }

    /**
     * @test
     * Test sistema template e notifiche
     */
    public function test_template_and_notification_system()
    {
        $templateService = app(TemplateService::class);

        // Crea assignment per test
        $assignment = Assignment::factory()->create([
            'tournament_id' => $this->tournament->id ?? Tournament::factory()->create(['zone_id' => $this->zone->id])->id,
            'user_id' => $this->referee->id,
            'role' => 'Direttore di Gara'
        ]);

        // Test selezione template
        $template = $templateService->selectBestTemplate('assignment', $assignment);
        $this->assertNotNull($template);
        $this->assertEquals('assignment', $template->type);

        // Test generazione contenuto
        $content = $templateService->generateContent($template, $assignment);

        $this->assertArrayHasKey('subject', $content);
        $this->assertArrayHasKey('body', $content);
        $this->assertArrayHasKey('letterhead', $content);

        // Verifica sostituzione variabili
        $this->assertStringContainsString($this->referee->name, $content['body']);
        $this->assertStringContainsString('Direttore di Gara', $content['body']);

        // Verifica che non ci siano variabili non sostituite
        $this->assertStringNotContainsString('{{', $content['body']);
        $this->assertStringNotContainsString('}}', $content['body']);
    }

    /**
     * @test
     * Test performance con molti dati
     */
    public function test_system_performance_with_bulk_data()
    {
        $startTime = microtime(true);

        // Crea molti arbitri
        $referees = User::factory(50)->create([
            'user_type' => 'referee',
            'zone_id' => $this->zone->id
        ]);

        foreach ($referees as $referee) {
            $referee->assignRole('Referee');
        }

        // Crea torneo
        $tournament = Tournament::factory()->create([
            'zone_id' => $this->zone->id,
            'status' => 'open'
        ]);

        // Crea disponibilità per tutti
        foreach ($referees as $referee) {
            Availability::factory()->create([
                'tournament_id' => $tournament->id,
                'user_id' => $referee->id,
                'available' => $this->faker->boolean(70) // 70% disponibili
            ]);
        }

        // Test query performance
        $availableReferees = User::role('Referee')
            ->whereHas('availabilities', function($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id)
                  ->where('available', true);
            })
            ->get();

        $executionTime = microtime(true) - $startTime;

        $this->assertLessThan(2.0, $executionTime, 'Query troppo lenta per 50 arbitri');
        $this->assertGreaterThan(0, $availableReferees->count());
    }

    /**
     * @test
     * Test error handling e resilienza
     */
    public function test_error_handling_and_resilience()
    {
        $notificationService = app(NotificationService::class);

        // Test con assignment invalido
        $invalidAssignment = new Assignment([
            'tournament_id' => 99999, // Non esiste
            'user_id' => 99999,
            'role' => 'Test'
        ]);

        $result = $notificationService->sendAssignmentNotification($invalidAssignment);
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);

        // Test con email template mancante
        LetterTemplate::where('type', 'assignment')->delete();

        $validAssignment = Assignment::factory()->create([
            'tournament_id' => Tournament::factory()->create(['zone_id' => $this->zone->id])->id,
            'user_id' => $this->referee->id
        ]);

        $result = $notificationService->sendAssignmentNotification($validAssignment);
        // Sistema deve essere resiliente anche senza template
        $this->assertIsArray($result);
    }

    /**
     * @test
     * Test integrità dati durante operazioni
     */
    public function test_data_integrity_during_operations()
    {
        $tournament = Tournament::factory()->create([
            'zone_id' => $this->zone->id,
            'status' => 'open'
        ]);

        // Crea disponibilità
        $availability = Availability::factory()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $this->referee->id,
            'available' => true
        ]);

        // Crea assegnazione
        $assignment = Assignment::factory()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $this->referee->id,
            'role' => 'Arbitro'
        ]);

        // Verifica coerenza dati
        $this->assertEquals($tournament->id, $availability->tournament_id);
        $this->assertEquals($tournament->id, $assignment->tournament_id);
        $this->assertEquals($this->referee->id, $availability->user_id);
        $this->assertEquals($this->referee->id, $assignment->user_id);

        // Test cascade delete
        $tournament->delete();

        $this->assertDatabaseMissing('availabilities', ['id' => $availability->id]);
        $this->assertDatabaseMissing('assignments', ['id' => $assignment->id]);
    }
}
