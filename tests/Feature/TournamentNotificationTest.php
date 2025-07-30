<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\Notification;
use App\Models\Zone;
use App\Models\Club;
use App\Models\Assignment;
use App\Services\TournamentNotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class TournamentNotificationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $tournament;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup test data
        $this->admin = User::factory()->create([
            'user_type' => 'admin',
            'zone_id' => 1
        ]);

        $zone = Zone::factory()->create(['id' => 1, 'code' => 'SZR6']);
        $club = Club::factory()->create(['zone_id' => 1, 'email' => 'test@golfclub.it']);

        $this->tournament = Tournament::factory()->create([
            'zone_id' => 1,
            'club_id' => $club->id,
            'status' => 'assigned'
        ]);

        // Crea assegnazioni
        Assignment::factory()->count(3)->create([
            'tournament_id' => $this->tournament->id
        ]);

        $this->service = app(TournamentNotificationService::class);
    }

    /** @test */
    public function it_can_create_tournament_notification_record()
    {
        $options = [
            'club_template' => 'club_assignment_standard',
            'referee_template' => 'referee_assignment_formal',
            'institutional_template' => 'institutional_report_standard',
            'sent_by' => $this->admin->id
        ];

        $result = $this->service->sendTournamentNotifications($this->tournament, $options);

        // Verifica record principale creato
        $this->assertDatabaseHas('tournament_notifications', [
            'tournament_id' => $this->tournament->id,
            'sent_by' => $this->admin->id
        ]);

        // Verifica notifiche individuali create
        $this->assertDatabaseCount('notifications', 6); // 1 club + 3 arbitri + 2 istituzionali

        // Verifica risultato
        $this->assertEquals($this->tournament->id, $result['tournament_id']);
        $this->assertEquals(6, $result['total_sent']);
    }

    /** @test */
    public function tournament_notification_groups_individual_notifications()
    {
        $options = [
            'club_template' => 'club_assignment_standard',
            'referee_template' => 'referee_assignment_formal',
            'institutional_template' => 'institutional_report_standard',
            'sent_by' => $this->admin->id
        ];

        $this->service->sendTournamentNotifications($this->tournament, $options);

        $tournamentNotification = TournamentNotification::where('tournament_id', $this->tournament->id)->first();

        // Verifica che esista 1 solo record torneo
        $this->assertEquals(1, TournamentNotification::count());

        // Verifica che raggruppi le notifiche individuali
        $individualNotifications = $tournamentNotification->individualNotifications;
        $this->assertEquals(6, $individualNotifications->count());

        // Verifica tipi destinatari
        $this->assertEquals(1, $individualNotifications->where('recipient_type', 'club')->count());
        $this->assertEquals(3, $individualNotifications->where('recipient_type', 'referee')->count());
        $this->assertEquals(2, $individualNotifications->where('recipient_type', 'institutional')->count());
    }

    /** @test */
    public function it_prevents_duplicate_notifications()
    {
        $options = [
            'club_template' => 'club_assignment_standard',
            'referee_template' => 'referee_assignment_formal',
            'institutional_template' => 'institutional_report_standard',
            'sent_by' => $this->admin->id
        ];

        // Primo invio
        $this->service->sendTournamentNotifications($this->tournament, $options);

        // Secondo invio (dovrebbe essere bloccato o gestito)
        $this->expectException(\Exception::class);
        $this->service->sendTournamentNotifications($this->tournament, $options);
    }

    /** @test */
    public function it_can_resend_failed_notifications()
    {
        // Crea notifica fallita
        $tournamentNotification = TournamentNotification::factory()->create([
            'tournament_id' => $this->tournament->id,
            'status' => 'failed',
            'sent_by' => $this->admin->id
        ]);

        $result = $this->service->resendTournamentNotifications($tournamentNotification);

        // Verifica che la vecchia notifica sia stata eliminata
        $this->assertDatabaseMissing('tournament_notifications', [
            'id' => $tournamentNotification->id
        ]);

        // Verifica che sia stata creata una nuova
        $this->assertDatabaseHas('tournament_notifications', [
            'tournament_id' => $this->tournament->id,
            'status' => 'sent'
        ]);
    }

    /** @test */
    public function tournament_model_has_notification_status_accessor()
    {
        // Senza notifiche
        $status = $this->tournament->notification_status;
        $this->assertEquals('not_sent', $status['status']);
        $this->assertEquals('⏳ Non inviato', $status['status_text']);

        // Con notifiche
        TournamentNotification::factory()->create([
            'tournament_id' => $this->tournament->id,
            'status' => 'sent',
            'total_recipients' => 6
        ]);

        $this->tournament->refresh();
        $status = $this->tournament->notification_status;

        $this->assertEquals('sent', $status['status']);
        $this->assertEquals('✅ Inviato', $status['status_text']);
        $this->assertEquals(6, $status['recipients_count']);
    }

    /** @test */
    public function tournament_can_check_if_ready_for_notification()
    {
        // Torneo con assegnazioni dovrebbe essere pronto
        $this->assertTrue($this->tournament->isReadyForNotification());

        // Torneo senza assegnazioni non dovrebbe essere pronto
        $this->tournament->assignments()->delete();
        $this->tournament->refresh();
        $this->assertFalse($this->tournament->isReadyForNotification());

        // Torneo già notificato non dovrebbe essere pronto
        Assignment::factory()->create(['tournament_id' => $this->tournament->id]);
        TournamentNotification::factory()->create(['tournament_id' => $this->tournament->id]);
        $this->tournament->refresh();
        $this->assertFalse($this->tournament->isReadyForNotification());
    }

    /** @test */
    public function notification_model_can_migrate_to_new_system()
    {
        $assignment = Assignment::first();

        // Crea notifica legacy (senza tournament_id)
        $legacyNotification = Notification::factory()->create([
            'assignment_id' => $assignment->id,
            'tournament_id' => null, // Legacy
            'recipient_type' => 'referee',
            'recipient_email' => 'test@referee.com'
        ]);

        $this->assertTrue($legacyNotification->migrateToNewSystem());

        $legacyNotification->refresh();
        $this->assertEquals($this->tournament->id, $legacyNotification->tournament_id);
        $this->assertTrue($legacyNotification->is_new_system);
    }

    /** @test */
    public function tournament_notification_calculates_stats_correctly()
    {
        $tournamentNotification = TournamentNotification::factory()->create([
            'tournament_id' => $this->tournament->id,
            'total_recipients' => 6,
            'details' => [
                'club' => ['sent' => 1, 'failed' => 0],
                'referees' => ['sent' => 2, 'failed' => 1],
                'institutional' => ['sent' => 2, 'failed' => 0]
            ]
        ]);

        $stats = $tournamentNotification->stats;

        $this->assertEquals(1, $stats['club_sent']);
        $this->assertEquals(2, $stats['referees_sent']);
        $this->assertEquals(2, $stats['institutional_sent']);
        $this->assertEquals(1, $stats['total_failed']);
        $this->assertEquals(83.3, $stats['success_rate']); // 5/6 * 100
    }

    /** @test */
    public function it_handles_missing_email_gracefully()
    {
        // Club senza email
        $this->tournament->club->update(['email' => null]);

        $options = [
            'club_template' => 'club_assignment_standard',
            'referee_template' => 'referee_assignment_formal',
            'institutional_template' => 'institutional_report_standard',
            'sent_by' => $this->admin->id
        ];

        $result = $this->service->sendTournamentNotifications($this->tournament, $options);

        // Verifica che l'invio continui per gli altri destinatari
        $this->assertEquals(5, $result['total_sent']); // Senza club
        $this->assertEquals(1, $result['details']['club']['failed']);
    }

    /** @test */
    public function it_creates_proper_attachments_for_different_recipients()
    {
        $options = [
            'club_template' => 'club_assignment_standard',
            'referee_template' => 'referee_assignment_formal',
            'institutional_template' => 'institutional_report_standard',
            'include_attachments' => true,
            'sent_by' => $this->admin->id
        ];

        $result = $this->service->sendTournamentNotifications($this->tournament, $options);

        // Verifica che le notifiche club abbiano allegati
        $clubNotification = Notification::where('tournament_id', $this->tournament->id)
                                      ->where('recipient_type', 'club')
                                      ->first();

        $this->assertNotEmpty($clubNotification->attachments);
        $this->assertCount(2, $clubNotification->attachments); // Convocazione SZR + Facsimile

        // Verifica che le notifiche arbitri abbiano allegati
        $refereeNotifications = Notification::where('tournament_id', $this->tournament->id)
                                          ->where('recipient_type', 'referee')
                                          ->get();

        foreach ($refereeNotifications as $notification) {
            $this->assertNotEmpty($notification->attachments);
            $this->assertCount(1, $notification->attachments); // Convocazione personalizzata
        }
    }

    /** @test */
    public function controller_can_list_tournament_notifications()
    {
        // Crea alcune notifiche di test
        TournamentNotification::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
                        ->get(route('admin.tournament-notifications.index'));

        $response->assertOk()
                ->assertViewIs('admin.tournament-notifications.index')
                ->assertViewHas('tournamentNotifications');
    }

    /** @test */
    public function controller_can_show_create_form()
    {
        $response = $this->actingAs($this->admin)
                        ->get(route('admin.tournament-notifications.create', $this->tournament));

        $response->assertOk()
                ->assertViewIs('admin.tournament-notifications.create')
                ->assertViewHas(['tournament', 'templates']);
    }

    /** @test */
    public function controller_validates_required_fields()
    {
        $response = $this->actingAs($this->admin)
                        ->post(route('admin.tournament-notifications.store', $this->tournament), [
                            // Dati incompleti
                            'club_template' => '',
                            'referee_template' => '',
                            'institutional_template' => ''
                        ]);

        $response->assertSessionHasErrors(['club_template', 'referee_template', 'institutional_template']);
    }

    /** @test */
    public function controller_can_send_notifications()
    {
        $validData = [
            'club_template' => 'club_assignment_standard',
            'referee_template' => 'referee_assignment_formal',
            'institutional_template' => 'institutional_report_standard',
            'include_attachments' => true,
            'send_to_club' => true,
            'send_to_referees' => true,
            'send_to_institutional' => true
        ];

        $response = $this->actingAs($this->admin)
                        ->post(route('admin.tournament-notifications.store', $this->tournament), $validData);

        $response->assertRedirect(route('admin.tournament-notifications.index'))
                ->assertSessionHas('success');

        $this->assertDatabaseHas('tournament_notifications', [
            'tournament_id' => $this->tournament->id,
            'sent_by' => $this->admin->id
        ]);
    }

    /** @test */
    public function controller_can_show_notification_details()
    {
        $notification = TournamentNotification::factory()->create([
            'tournament_id' => $this->tournament->id
        ]);

        $response = $this->actingAs($this->admin)
                        ->get(route('admin.tournament-notifications.show', $notification));

        $response->assertOk()
                ->assertViewIs('admin.tournament-notifications.show')
                ->assertViewHas('tournamentNotification');
    }

    /** @test */
    public function controller_can_resend_notifications()
    {
        $notification = TournamentNotification::factory()->create([
            'tournament_id' => $this->tournament->id,
            'status' => 'failed'
        ]);

        $response = $this->actingAs($this->admin)
                        ->post(route('admin.tournament-notifications.resend', $notification));

        $response->assertRedirect()
                ->assertSessionHas('success');
    }

    /** @test */
    public function controller_can_delete_notifications()
    {
        $notification = TournamentNotification::factory()->create([
            'tournament_id' => $this->tournament->id
        ]);

        $response = $this->actingAs($this->admin)
                        ->delete(route('admin.tournament-notifications.destroy', $notification));

        $response->assertRedirect(route('admin.tournament-notifications.index'))
                ->assertSessionHas('success');

        $this->assertDatabaseMissing('tournament_notifications', [
            'id' => $notification->id
        ]);
    }

    /** @test */
    public function command_can_list_tournaments()
    {
        $this->artisan('tournaments:notifications list')
             ->assertExitCode(0);
    }

    /** @test */
    public function command_can_send_notifications()
    {
        $this->artisan('tournaments:notifications send --tournament=' . $this->tournament->id . ' --force')
             ->assertExitCode(0);

        $this->assertDatabaseHas('tournament_notifications', [
            'tournament_id' => $this->tournament->id
        ]);
    }

    /** @test */
    public function command_handles_dry_run()
    {
        $this->artisan('tournaments:notifications send --tournament=' . $this->tournament->id . ' --dry-run --force')
             ->assertExitCode(0);

        // Verifica che non sia stato creato nulla in modalità dry-run
        $this->assertDatabaseMissing('tournament_notifications', [
            'tournament_id' => $this->tournament->id
        ]);
    }

    /** @test */
    public function command_shows_stats()
    {
        // Crea dati di test
        TournamentNotification::factory()->count(5)->create();

        $this->artisan('tournaments:notifications stats')
             ->assertExitCode(0);
    }

    /** @test */
    public function system_respects_configuration()
    {
        // Test che il sistema rispetti la configurazione
        config(['tournament-notifications.email.enabled' => false]);

        $options = [
            'club_template' => 'club_assignment_standard',
            'referee_template' => 'referee_assignment_formal',
            'institutional_template' => 'institutional_report_standard',
            'sent_by' => $this->admin->id
        ];

        // In sandbox mode o disabilitato, non dovrebbe inviare email reali
        $result = $this->service->sendTournamentNotifications($this->tournament, $options);

        // Ma dovrebbe comunque creare i record
        $this->assertDatabaseHas('tournament_notifications', [
            'tournament_id' => $this->tournament->id
        ]);
    }

    /** @test */
    public function it_handles_zone_permissions()
    {
        // Crea admin di un'altra zona
        $otherZoneAdmin = User::factory()->create([
            'user_type' => 'admin',
            'zone_id' => 2
        ]);

        // Dovrebbe essere negato l'accesso al torneo di altra zona
        $response = $this->actingAs($otherZoneAdmin)
                        ->get(route('admin.tournament-notifications.create', $this->tournament));

        $response->assertStatus(403);
    }

    /** @test */
    public function legacy_notifications_can_be_migrated()
    {
        // Crea notifiche legacy
        $legacyNotifications = Notification::factory()->count(10)->create([
            'tournament_id' => null // Legacy
        ]);

        $this->artisan('tournaments:notifications migrate --force')
             ->assertExitCode(0);

        // Verifica che siano state migrate
        $migratedCount = Notification::newSystem()->count();
        $this->assertGreaterThan(0, $migratedCount);
    }

    /** @test */
    public function cleanup_removes_old_notifications()
    {
        // Crea notifiche vecchie
        TournamentNotification::factory()->create([
            'sent_at' => now()->subDays(100),
            'status' => 'sent'
        ]);

        $this->artisan('tournaments:notifications cleanup --days=90 --force')
             ->assertExitCode(0);

        // Verifica che siano state eliminate
        $this->assertEquals(0, TournamentNotification::count());
    }

    /** @test */
    public function api_endpoints_work_correctly()
    {
        // Test endpoint statistiche
        $response = $this->actingAs($this->admin)
                        ->get(route('admin.tournament-notifications.stats'));

        $response->assertOk()
                ->assertJsonStructure([
                    'today',
                    'this_week',
                    'this_month',
                    'success_rate',
                    'pending_tournaments'
                ]);
    }
}
