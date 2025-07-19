<?php
// File: tests/Feature/NotificationSystemTest.php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use App\Models\Notification;
use App\Models\LetterTemplate;
use App\Models\InstitutionalEmail;
use App\Models\Zone;
use App\Models\Club;
use App\Models\TournamentType;
use App\Services\NotificationService;
use App\Jobs\SendNotificationJob;
use App\Mail\AssignmentNotification;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $referee;
    protected $tournament;
    protected $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->setupTestData();
    }

    /** @test */
    public function admin_can_access_notification_index()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('notifications.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.notifications.index');
    }

    /** @test */
    public function referee_cannot_access_notification_index()
    {
        $response = $this->actingAs($this->referee)
            ->get(route('notifications.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_view_assignment_form()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('tournaments.send-assignment-form', $this->tournament));

        $response->assertStatus(200);
        $response->assertViewIs('admin.notifications.assignment_form');
        $response->assertViewHas('tournament');
        $response->assertViewHas('assignments');
    }

    /** @test */
    public function admin_can_send_assignment_notification()
    {
        Mail::fake();
        Queue::fake();

        $data = [
            'subject' => 'Test Assignment Notification',
            'message' => 'This is a test notification for assignment.',
            'recipients' => [$this->referee->id],
            'send_to_club' => true,
            'attach_documents' => false
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('tournaments.send-assignment', $this->tournament), $data);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check that notifications were created
        $this->assertDatabaseHas('notifications', [
            'assignment_id' => $this->assignment->id,
            'recipient_type' => 'referee',
            'recipient_email' => $this->referee->email,
            'subject' => 'Test Assignment Notification'
        ]);
    }

    /** @test */
    public function notification_service_can_send_bulk_notifications()
    {
        Mail::fake();

        $service = app(NotificationService::class);

        $data = [
            'subject' => 'Bulk Test Notification',
            'message' => 'This is a bulk test notification.',
            'recipients' => [$this->referee->id],
            'send_to_club' => true,
            'institutional_emails' => [],
            'additional_emails' => ['test@example.com'],
            'additional_names' => ['Test User'],
            'attach_documents' => false
        ];

        $results = $service->sendBulkAssignmentNotifications($this->tournament, $data);

        $this->assertGreaterThan(0, $results['sent']);
        $this->assertEquals(0, $results['failed']);
    }

    /** @test */
    public function notification_job_processes_correctly()
    {
        Mail::fake();

        $notification = Notification::create([
            'assignment_id' => $this->assignment->id,
            'recipient_type' => 'referee',
            'recipient_email' => $this->referee->email,
            'subject' => 'Test Job Notification',
            'body' => 'This is a test job notification.',
            'status' => 'pending'
        ]);

        $job = new SendNotificationJob($notification);
        $job->handle();

        // Refresh notification from database
        $notification->refresh();

        $this->assertEquals('sent', $notification->status);
        $this->assertNotNull($notification->sent_at);

        Mail::assertSent(AssignmentNotification::class);
    }

    /** @test */
    public function letter_template_can_be_created()
    {
        $templateData = [
            'name' => 'Test Template',
            'type' => 'assignment',
            'subject' => 'Test Subject - {{tournament_name}}',
            'body' => 'Hello {{referee_name}}, you are assigned to {{tournament_name}}.',
            'is_active' => true,
            'is_default' => false
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('letter-templates.store'), $templateData);

        $response->assertRedirect(route('letter-templates.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('letter_templates', [
            'name' => 'Test Template',
            'type' => 'assignment'
        ]);
    }

    /** @test */
    public function institutional_email_can_be_created()
    {
        $emailData = [
            'name' => 'Test Institution',
            'email' => 'test@institution.com',
            'description' => 'Test institutional email',
            'category' => 'federazione',
            'receive_all_notifications' => true,
            'is_active' => true
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('institutional-emails.store'), $emailData);

        $response->assertRedirect(route('institutional-emails.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('institutional_emails', [
            'name' => 'Test Institution',
            'email' => 'test@institution.com'
        ]);
    }

    /** @test */
    public function notification_statistics_are_calculated_correctly()
    {
        // Create some test notifications
        Notification::create([
            'assignment_id' => $this->assignment->id,
            'recipient_type' => 'referee',
            'recipient_email' => 'test1@example.com',
            'subject' => 'Test 1',
            'body' => 'Test body 1',
            'status' => 'sent',
            'sent_at' => now()
        ]);

        Notification::create([
            'assignment_id' => $this->assignment->id,
            'recipient_type' => 'club',
            'recipient_email' => 'test2@example.com',
            'subject' => 'Test 2',
            'body' => 'Test body 2',
            'status' => 'failed',
            'error_message' => 'Test error'
        ]);

        $service = app(NotificationService::class);
        $stats = $service->getNotificationStatistics(30);

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['sent']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertArrayHasKey('by_type', $stats);
    }

    /** @test */
    public function zone_admin_can_only_access_own_zone_notifications()
    {
        // Create another zone and admin
        $otherZone = Zone::factory()->create(['name' => 'Other Zone']);
        $otherAdmin = User::factory()->create();
        $otherAdmin->assignRole('Admin');

        // Create referee in other zone
        $otherReferee = $otherAdmin; // Simplification for test
        $otherReferee->referee->update(['zone_id' => $otherZone->id]);

        // Try to access current tournament (should fail)
        $response = $this->actingAs($otherAdmin)
            ->get(route('tournaments.send-assignment-form', $this->tournament));

        $response->assertStatus(403);
    }

    /** @test */
    public function template_variables_are_replaced_correctly()
    {
        $template = LetterTemplate::create([
            'name' => 'Variable Test Template',
            'type' => 'assignment',
            'subject' => 'Assignment for {{tournament_name}}',
            'body' => 'Hello {{referee_name}}, you are assigned to {{tournament_name}} at {{club_name}}.',
            'is_active' => true
        ]);

        $service = app(NotificationService::class);

        $variables = [
            '{{tournament_name}}' => $this->tournament->name,
            '{{referee_name}}' => $this->referee->name,
            '{{club_name}}' => $this->tournament->club->name
        ];

        $result = $template->replaceVariables($variables);

        $this->assertStringContainsString($this->tournament->name, $result['subject']);
        $this->assertStringContainsString($this->referee->name, $result['body']);
        $this->assertStringContainsString($this->tournament->club->name, $result['body']);
    }

    /**
     * Setup test data
     */
    private function setupTestData(): void
    {
        // Create zone
        $zone = Zone::factory()->create(['name' => 'Test Zone']);

        // Create admin user
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        // Create referee user
        $this->referee = User::factory()->create();
        $this->referee->assignRole('Referee');

        // Set zone for both users
        if ($this->admin->referee) {
            $this->admin->referee->update(['zone_id' => $zone->id]);
        }
        if ($this->referee->referee) {
            $this->referee->referee->update(['zone_id' => $zone->id]);
        }

        // Create club
        $club = Club::factory()->create(['zone_id' => $zone->id]);

        // Create tournament type
        $tournamentType = TournamentType::factory()->create([
            'name' => 'Test Tournament Type',
            'is_active' => true
        ]);

        // Create tournament
        $this->tournament = Tournament::factory()->create([
            'club_id' => $club->id,
            'tournament_type_id' => $tournamentType->id
        ]);

        // Create assignment
        $this->assignment = Assignment::create([
            'tournament_id' => $this->tournament->id,
            'user_id' => $this->referee->id,
            'assigned_by_id' => $this->admin->id,
            'role' => 'Arbitro',
            'assigned_at' => now()
        ]);
    }
}

