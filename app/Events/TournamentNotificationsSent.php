<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Tournament;
use App\Models\TournamentNotification;

/**
 * 📧 Evento: Notifiche torneo inviate con successo
 */
class TournamentNotificationsSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tournament;
    public $tournamentNotification;
    public $results;
    public $sentBy;

    public function __construct(Tournament $tournament, TournamentNotification $tournamentNotification, array $results, $sentBy = null)
    {
        $this->tournament = $tournament;
        $this->tournamentNotification = $tournamentNotification;
        $this->results = $results;
        $this->sentBy = $sentBy;
    }

    /**
     * 📡 Canali broadcast (se necessario)
     */
    public function broadcastOn()
    {
        return new PrivateChannel('admin.tournament-notifications');
    }

    /**
     * 📊 Dati da broadcastare
     */
    public function broadcastWith()
    {
        return [
            'tournament_id' => $this->tournament->id,
            'tournament_name' => $this->tournament->name,
            'total_recipients' => $this->results['total_sent'],
            'notification_id' => $this->tournamentNotification->id,
            'sent_at' => $this->tournamentNotification->sent_at->toISOString()
        ];
    }
}

/**
 * ❌ Evento: Notifiche torneo fallite
 */
class TournamentNotificationsFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tournament;
    public $exception;
    public $attemptedBy;

    public function __construct(Tournament $tournament, \Throwable $exception, $attemptedBy = null)
    {
        $this->tournament = $tournament;
        $this->exception = $exception;
        $this->attemptedBy = $attemptedBy;
    }
}

/**
 * 🔄 Evento: Notifiche torneo reinviate
 */
class TournamentNotificationsResent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tournament;
    public $originalNotification;
    public $newNotification;
    public $resentBy;

    public function __construct(Tournament $tournament, TournamentNotification $originalNotification, TournamentNotification $newNotification, $resentBy = null)
    {
        $this->tournament = $tournament;
        $this->originalNotification = $originalNotification;
        $this->newNotification = $newNotification;
        $this->resentBy = $resentBy;
    }
}

/**
 * 📊 Evento: Invio massivo completato
 */
class BulkNotificationsCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $results;
    public $tournamentCount;
    public $completedAt;

    public function __construct(array $results, int $tournamentCount)
    {
        $this->results = $results;
        $this->tournamentCount = $tournamentCount;
        $this->completedAt = now();
    }
}

/**
 * 📧 Evento: Singola notifica inviata
 */
class SingleNotificationSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    public $emailData;

    public function __construct($notification, array $emailData)
    {
        $this->notification = $notification;
        $this->emailData = $emailData;
    }
}

/**
 * ❌ Evento: Singola notifica fallita
 */
class SingleNotificationFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    public $exception;
    public $retryCount;

    public function __construct($notification, \Throwable $exception, int $retryCount = 0)
    {
        $this->notification = $notification;
        $this->exception = $exception;
        $this->retryCount = $retryCount;
    }
}

// ===========================
// LISTENERS
// ===========================

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Events\TournamentNotificationsSent;
use App\Events\TournamentNotificationsFailed;
use App\Events\TournamentNotificationsResent;
use App\Events\BulkNotificationsCompleted;
use App\Events\SingleNotificationSent;
use App\Events\SingleNotificationFailed;
use App\Models\User;
use App\Notifications\AdminNotification;

/**
 * 📧 Listener: Notifiche torneo inviate con successo
 */
class TournamentNotificationsSentListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TournamentNotificationsSent $event): void
    {
        // Log successo
        Log::info('Tournament notifications sent successfully', [
            'tournament_id' => $event->tournament->id,
            'tournament_name' => $event->tournament->name,
            'notification_id' => $event->tournamentNotification->id,
            'total_recipients' => $event->results['total_sent'],
            'sent_by' => $event->sentBy
        ]);

        // Invia notifica di conferma all'admin che ha inviato
        if ($event->sentBy) {
            $admin = User::find($event->sentBy);
            if ($admin) {
                $admin->notify(new AdminNotification(
                    "✅ Notifiche inviate con successo",
                    "Le notifiche per il torneo '{$event->tournament->name}' sono state inviate a {$event->results['total_sent']} destinatari.",
                    'success'
                ));
            }
        }

        // Aggiorna cache statistiche
        $this->updateNotificationStats();

        // Trigger webhook se configurato
        $this->triggerWebhook('tournament.notifications.sent', [
            'tournament_id' => $event->tournament->id,
            'notification_id' => $event->tournamentNotification->id,
            'recipients_count' => $event->results['total_sent']
        ]);
    }

    private function updateNotificationStats(): void
    {
        // Aggiorna cache statistiche per dashboard
        cache()->forget('tournament_notifications_stats');
        cache()->remember('tournament_notifications_stats', 300, function () {
            return \App\Models\TournamentNotification::getGlobalStats();
        });
    }

    private function triggerWebhook(string $event, array $data): void
    {
        $webhookUrl = config('tournament-notifications.webhook_url');
        if (!$webhookUrl) return;

        try {
            // Invia webhook asincrono
            \Http::timeout(10)->post($webhookUrl, [
                'event' => $event,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::warning('Webhook failed', [
                'url' => $webhookUrl,
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }
}

/**
 * ❌ Listener: Notifiche torneo fallite
 */
class TournamentNotificationsFailedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TournamentNotificationsFailed $event): void
    {
        Log::error('Tournament notifications failed', [
            'tournament_id' => $event->tournament->id,
            'tournament_name' => $event->tournament->name,
            'error' => $event->exception->getMessage(),
            'attempted_by' => $event->attemptedBy
        ]);

        // Notifica amministratori del fallimento
        $this->notifyAdminsOfFailure($event);

        // Crea ticket di supporto automatico se configurato
        $this->createSupportTicket($event);

        // Trigger webhook
        $this->triggerWebhook('tournament.notifications.failed', [
            'tournament_id' => $event->tournament->id,
            'error' => $event->exception->getMessage()
        ]);
    }

    private function notifyAdminsOfFailure(TournamentNotificationsFailed $event): void
    {
        // Notifica admin di zona
        $zoneAdmins = User::where('user_type', 'admin')
                         ->where('zone_id', $event->tournament->zone_id)
                         ->get();

        foreach ($zoneAdmins as $admin) {
            $admin->notify(new AdminNotification(
                "❌ Errore invio notifiche",
                "Le notifiche per il torneo '{$event->tournament->name}' sono fallite: {$event->exception->getMessage()}",
                'error'
            ));
        }

        // Notifica super admin
        $superAdmins = User::where('user_type', 'super_admin')->get();
        foreach ($superAdmins as $admin) {
            $admin->notify(new AdminNotification(
                "🚨 Sistema notifiche - Errore critico",
                "Fallimento notifiche torneo ID {$event->tournament->id}: {$event->exception->getMessage()}",
                'critical'
            ));
        }
    }

    private function createSupportTicket(TournamentNotificationsFailed $event): void
    {
        // Implementa creazione ticket automatico se hai sistema ticketing
        Log::info('Auto-creating support ticket for notification failure', [
            'tournament_id' => $event->tournament->id,
            'error' => $event->exception->getMessage()
        ]);
    }

    private function triggerWebhook(string $event, array $data): void
    {
        // Stesso metodo del listener precedente
    }
}

/**
 * 🔄 Listener: Notifiche torneo reinviate
 */
class TournamentNotificationsResentListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TournamentNotificationsResent $event): void
    {
        Log::info('Tournament notifications resent', [
            'tournament_id' => $event->tournament->id,
            'original_notification_id' => $event->originalNotification->id,
            'new_notification_id' => $event->newNotification->id,
            'resent_by' => $event->resentBy
        ]);

        // Notifica admin del reinvio
        if ($event->resentBy) {
            $admin = User::find($event->resentBy);
            if ($admin) {
                $admin->notify(new AdminNotification(
                    "🔄 Notifiche reinviate",
                    "Le notifiche per il torneo '{$event->tournament->name}' sono state reinviate con successo.",
                    'info'
                ));
            }
        }
    }
}

/**
 * 📊 Listener: Invio massivo completato
 */
class BulkNotificationsCompletedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(BulkNotificationsCompleted $event): void
    {
        Log::info('Bulk notifications completed', [
            'tournament_count' => $event->tournamentCount,
            'results' => $event->results
        ]);

        // Genera e invia report agli admin
        $this->sendBulkReport($event);
    }

    private function sendBulkReport(BulkNotificationsCompleted $event): void
    {
        $superAdmins = User::where('user_type', 'super_admin')->get();

        $message = "📊 Report invio massivo notifiche:\n\n";
        $message .= "Tornei processati: {$event->tournamentCount}\n";
        $message .= "Successi: {$event->results['success']}\n";
        $message .= "Fallimenti: {$event->results['failed']}\n";

        if (!empty($event->results['errors'])) {
            $message .= "\nErrori:\n" . implode("\n", $event->results['errors']);
        }

        foreach ($superAdmins as $admin) {
            $admin->notify(new AdminNotification(
                "📊 Invio massivo completato",
                $message,
                $event->results['failed'] > 0 ? 'warning' : 'success'
            ));
        }
    }
}

/**
 * 📧 Listener: Singola notifica inviata
 */
class SingleNotificationSentListener
{
    public function handle(SingleNotificationSent $event): void
    {
        // Log dettagliato solo se richiesto
        if (config('tournament-notifications.logging.log_successful_sends')) {
            Log::debug('Single notification sent', [
                'notification_id' => $event->notification->id,
                'recipient' => $event->notification->recipient_email,
                'type' => $event->notification->recipient_type
            ]);
        }

        // Aggiorna metriche in tempo reale
        $this->updateRealTimeMetrics();
    }

    private function updateRealTimeMetrics(): void
    {
        // Aggiorna contatori cache per dashboard real-time
        cache()->increment('notifications_sent_today');
        cache()->increment('notifications_sent_total');
    }
}

/**
 * ❌ Listener: Singola notifica fallita
 */
class SingleNotificationFailedListener
{
    public function handle(SingleNotificationFailed $event): void
    {
        Log::warning('Single notification failed', [
            'notification_id' => $event->notification->id,
            'recipient' => $event->notification->recipient_email,
            'error' => $event->exception->getMessage(),
            'retry_count' => $event->retryCount
        ]);

        // Se supera il limite retry, notifica admin
        if ($event->retryCount >= 3) {
            $this->notifyAdminOfPermanentFailure($event);
        }

        // Aggiorna metriche errori
        cache()->increment('notifications_failed_today');
    }

    private function notifyAdminOfPermanentFailure(SingleNotificationFailed $event): void
    {
        // Notifica admin solo per fallimenti permanenti
        $zoneAdmins = User::where('user_type', 'admin')
                         ->where('zone_id', $event->notification->tournament->zone_id ?? null)
                         ->get();

        foreach ($zoneAdmins as $admin) {
            $admin->notify(new AdminNotification(
                "⚠️ Notifica permanentemente fallita",
                "La notifica ID {$event->notification->id} per {$event->notification->recipient_email} è fallita definitivamente dopo 3 tentativi.",
                'warning'
            ));
        }
    }
}

// ===========================
// EVENT SERVICE PROVIDER
// ===========================

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * 📧 Event listener mappings per sistema notifiche
     */
    protected $listen = [
        // ... altri eventi esistenti ...

        // Eventi notifiche torneo
        \App\Events\TournamentNotificationsSent::class => [
            \App\Listeners\TournamentNotificationsSentListener::class,
        ],

        \App\Events\TournamentNotificationsFailed::class => [
            \App\Listeners\TournamentNotificationsFailedListener::class,
        ],

        \App\Events\TournamentNotificationsResent::class => [
            \App\Listeners\TournamentNotificationsResentListener::class,
        ],

        \App\Events\BulkNotificationsCompleted::class => [
            \App\Listeners\BulkNotificationsCompletedListener::class,
        ],

        \App\Events\SingleNotificationSent::class => [
            \App\Listeners\SingleNotificationSentListener::class,
        ],

        \App\Events\SingleNotificationFailed::class => [
            \App\Listeners\SingleNotificationFailedListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Event wildcard listeners
        Event::listen('tournament.notifications.*', function ($eventName, $data) {
            Log::info('Tournament notification event fired', [
                'event' => $eventName,
                'data' => $data
            ]);
        });

        // Event per monitoring
        Event::listen('tournament.notifications.sent', function ($data) {
            // Aggiorna cache statistiche real-time
            cache()->forget('dashboard_stats');
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
