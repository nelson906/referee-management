<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\Notification;
use App\Services\TournamentNotificationService;

/**
 * 📧 Job principale per invio notifiche torneo
 */
class SendTournamentNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minuti
    public $tries = 3;
    public $backoff = [60, 180, 600]; // 1min, 3min, 10min

    protected $tournament;
    protected $options;

    public function __construct(Tournament $tournament, array $options)
    {
        $this->tournament = $tournament;
        $this->options = $options;

        // Usa queue specifica per notifiche
        $this->onQueue('tournament-notifications');
    }

    public function handle(TournamentNotificationService $service): void
    {
        Log::info('Starting tournament notifications job', [
            'tournament_id' => $this->tournament->id,
            'tournament_name' => $this->tournament->name
        ]);

        try {
            $result = $service->sendTournamentNotifications($this->tournament, $this->options);

            Log::info('Tournament notifications sent successfully', [
                'tournament_id' => $this->tournament->id,
                'total_sent' => $result['total_sent'],
                'details' => $result['details']
            ]);

            // Dispatch eventi di successo
            event('tournament.notifications.sent', [
                'tournament' => $this->tournament,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Tournament notifications job failed', [
                'tournament_id' => $this->tournament->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Crea record fallimento
            TournamentNotification::create([
                'tournament_id' => $this->tournament->id,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_by' => $this->options['sent_by'] ?? null,
                'details' => ['error' => $e->getMessage()]
            ]);

            throw $e; // Re-throw per retry automatico
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Tournament notifications job permanently failed', [
            'tournament_id' => $this->tournament->id,
            'error' => $exception->getMessage()
        ]);

        // Notifica amministratori del fallimento
        event('tournament.notifications.failed', [
            'tournament' => $this->tournament,
            'exception' => $exception
        ]);
    }
}

/**
 * 📧 Job per invio singola email
 */
class SendSingleNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;
    public $backoff = [30, 90, 300];

    protected $notification;
    protected $emailData;

    public function __construct(Notification $notification, array $emailData)
    {
        $this->notification = $notification;
        $this->emailData = $emailData;

        $this->onQueue('emails');
    }

    public function handle(): void
    {
        try {
            // Simula invio email (sostituire con Mail::send reale)
            $this->sendEmail();

            // Aggiorna status notifica
            $this->notification->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null
            ]);

            Log::info('Single notification sent', [
                'notification_id' => $this->notification->id,
                'recipient' => $this->notification->recipient_email
            ]);

        } catch (\Exception $e) {
            $this->notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count' => $this->notification->retry_count + 1
            ]);

            Log::error('Single notification failed', [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function sendEmail(): void
    {
        // Implementazione invio email
        // Mail::send(...);

        // Per ora simula delay invio
        sleep(1);
    }

    public function failed(\Throwable $exception): void
    {
        $this->notification->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage()
        ]);
    }
}

/**
 * 🔄 Job per reinvio notifiche fallite
 */
class ResendFailedNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minuti
    public $tries = 1; // No retry per questo job

    protected $tournamentNotification;

    public function __construct(TournamentNotification $tournamentNotification)
    {
        $this->tournamentNotification = $tournamentNotification;
        $this->onQueue('tournament-notifications');
    }

    public function handle(TournamentNotificationService $service): void
    {
        Log::info('Starting resend job', [
            'tournament_notification_id' => $this->tournamentNotification->id,
            'tournament_id' => $this->tournamentNotification->tournament_id
        ]);

        try {
            $result = $service->resendTournamentNotifications($this->tournamentNotification);

            Log::info('Notifications resent successfully', [
                'tournament_id' => $this->tournamentNotification->tournament_id,
                'resent' => $result['resent'] ?? 0,
                'failed' => $result['failed'] ?? 0
            ]);

        } catch (\Exception $e) {
            Log::error('Resend job failed', [
                'tournament_notification_id' => $this->tournamentNotification->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}

/**
 * 🧹 Job per pulizia notifiche vecchie
 */
class CleanupOldNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 1;

    protected $days;

    public function __construct(int $days = 90)
    {
        $this->days = $days;
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        Log::info('Starting notifications cleanup', ['days' => $this->days]);

        try {
            // Pulisci notifiche torneo vecchie
            $deletedTournament = TournamentNotification::where('sent_at', '<', now()->subDays($this->days))
                                                     ->where('status', 'sent')
                                                     ->delete();

            // Pulisci notifiche individuali vecchie
            $deletedIndividual = Notification::where('sent_at', '<', now()->subDays($this->days))
                                           ->where('status', 'sent')
                                           ->delete();

            Log::info('Notifications cleanup completed', [
                'deleted_tournament' => $deletedTournament,
                'deleted_individual' => $deletedIndividual
            ]);

        } catch (\Exception $e) {
            Log::error('Cleanup job failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}

/**
 * 🔄 Job per migrazione da sistema legacy
 */
class MigrateLegacyNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minuti
    public $tries = 1;

    protected $batchSize;

    public function __construct(int $batchSize = 500)
    {
        $this->batchSize = $batchSize;
        $this->onQueue('migration');
    }

    public function handle(): void
    {
        Log::info('Starting legacy notifications migration', [
            'batch_size' => $this->batchSize
        ]);

        $migrated = 0;
        $failed = 0;

        Notification::legacySystem()
                   ->with(['assignment.tournament'])
                   ->chunk($this->batchSize, function ($notifications) use (&$migrated, &$failed) {

                       foreach ($notifications as $notification) {
                           try {
                               if ($notification->migrateToNewSystem()) {
                                   $migrated++;
                               }
                           } catch (\Exception $e) {
                               $failed++;
                               Log::warning('Failed to migrate notification', [
                                   'notification_id' => $notification->id,
                                   'error' => $e->getMessage()
                               ]);
                           }
                       }
                   });

        Log::info('Legacy migration completed', [
            'migrated' => $migrated,
            'failed' => $failed
        ]);
    }
}

/**
 * 📊 Job per generazione report statistiche
 */
class GenerateNotificationStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 1;

    protected $period;
    protected $format;

    public function __construct(string $period = 'monthly', string $format = 'json')
    {
        $this->period = $period;
        $this->format = $format;
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        Log::info('Generating notification stats', [
            'period' => $this->period,
            'format' => $this->format
        ]);

        try {
            $stats = $this->generateStats();
            $this->saveReport($stats);

            Log::info('Stats report generated successfully');

        } catch (\Exception $e) {
            Log::error('Stats generation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function generateStats(): array
    {
        $dateRange = $this->getDateRange();

        return [
            'period' => $this->period,
            'date_range' => $dateRange,
            'tournament_notifications' => [
                'total' => TournamentNotification::whereBetween('sent_at', $dateRange)->count(),
                'successful' => TournamentNotification::whereBetween('sent_at', $dateRange)
                                                     ->where('status', 'sent')->count(),
                'failed' => TournamentNotification::whereBetween('sent_at', $dateRange)
                                                 ->where('status', 'failed')->count(),
            ],
            'individual_notifications' => [
                'total' => Notification::whereBetween('sent_at', $dateRange)->count(),
                'by_type' => [
                    'club' => Notification::whereBetween('sent_at', $dateRange)
                                        ->where('recipient_type', 'club')->count(),
                    'referee' => Notification::whereBetween('sent_at', $dateRange)
                                           ->where('recipient_type', 'referee')->count(),
                    'institutional' => Notification::whereBetween('sent_at', $dateRange)
                                                 ->where('recipient_type', 'institutional')->count(),
                ],
            ],
            'generated_at' => now()->toISOString()
        ];
    }

    private function getDateRange(): array
    {
        return match($this->period) {
            'daily' => [now()->startOfDay(), now()->endOfDay()],
            'weekly' => [now()->startOfWeek(), now()->endOfWeek()],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth()],
            'yearly' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()]
        };
    }

    private function saveReport(array $stats): void
    {
        $filename = "notification_stats_{$this->period}_" . now()->format('Y_m_d') . ".{$this->format}";
        $path = storage_path("app/reports/notifications/{$filename}");

        // Crea directory se non esiste
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        // Salva report
        if ($this->format === 'json') {
            file_put_contents($path, json_encode($stats, JSON_PRETTY_PRINT));
        } else {
            // Implementa altri formati se necessari (CSV, PDF, etc.)
            file_put_contents($path, json_encode($stats, JSON_PRETTY_PRINT));
        }
    }
}

/**
 * 📧 Job batch per invio massivo
 */
class SendBulkTournamentNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 ora
    public $tries = 1;

    protected $tournamentIds;
    protected $options;

    public function __construct(array $tournamentIds, array $options)
    {
        $this->tournamentIds = $tournamentIds;
        $this->options = $options;
        $this->onQueue('bulk-notifications');
    }

    public function handle(TournamentNotificationService $service): void
    {
        Log::info('Starting bulk notifications job', [
            'tournament_count' => count($this->tournamentIds)
        ]);

        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($this->tournamentIds as $tournamentId) {
            try {
                $tournament = Tournament::with(['club', 'zone', 'assignments.user'])->find($tournamentId);

                if (!$tournament || !$tournament->canSendNotifications()) {
                    $results['failed']++;
                    $results['errors'][] = "Tournament {$tournamentId} cannot receive notifications";
                    continue;
                }

                $service->sendTournamentNotifications($tournament, $this->options);
                $results['success']++;

                // Delay tra invii per evitare sovraccarico
                sleep(config('tournament-notifications.email.send_delay', 2));

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Tournament {$tournamentId}: " . $e->getMessage();

                Log::error('Bulk notification failed for tournament', [
                    'tournament_id' => $tournamentId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Bulk notifications completed', $results);

        // Invia report ai admin
        event('tournament.notifications.bulk_completed', [
            'results' => $results,
            'tournament_count' => count($this->tournamentIds)
        ]);
    }
}
