<?php
// File: app/Jobs/SendNotificationJob.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;
use App\Mail\AssignmentNotification;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public $maxExceptions = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 60;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30 seconds, 1 minute, 2 minutes
    }

    /**
     * The notification instance.
     */
    protected $notification;

    /**
     * Create a new job instance.
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification;

        // Set queue priority based on notification priority
        $this->onQueue($this->getQueueName());
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if notification is still pending
            if ($this->notification->status !== 'pending') {
                Log::info('Notification already processed', [
                    'notification_id' => $this->notification->id,
                    'status' => $this->notification->status
                ]);
                return;
            }

            // Check if notification has expired
            if ($this->notification->expires_at && $this->notification->expires_at < now()) {
                $this->notification->markAsCancelled();
                Log::info('Notification expired', [
                    'notification_id' => $this->notification->id,
                    'expired_at' => $this->notification->expires_at
                ]);
                return;
            }

            // Check if notification is scheduled for future
            if ($this->notification->scheduled_at && $this->notification->scheduled_at > now()) {
                // Re-queue for later
                $delay = $this->notification->scheduled_at->diffInSeconds(now());
                static::dispatch($this->notification)->delay($delay);

                Log::info('Notification rescheduled', [
                    'notification_id' => $this->notification->id,
                    'scheduled_at' => $this->notification->scheduled_at,
                    'delay_seconds' => $delay
                ]);
                return;
            }

            // Send the notification
            $this->sendNotification();

            Log::info('Notification sent successfully', [
                'notification_id' => $this->notification->id,
                'recipient' => $this->notification->recipient_email,
                'attempt' => $this->attempts()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'notification_id' => $this->notification->id,
                'recipient' => $this->notification->recipient_email,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries
            ]);

            // Mark as failed and increment retry count
            $this->notification->markAsFailed($e->getMessage());

            // If this was the last attempt, log it
            if ($this->attempts() >= $this->tries) {
                Log::error('Notification permanently failed', [
                    'notification_id' => $this->notification->id,
                    'recipient' => $this->notification->recipient_email,
                    'final_error' => $e->getMessage()
                ]);

                // Optionally notify administrators about permanent failure
                $this->notifyAdminsOfFailure($e);
            }

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Send the actual notification
     */
    private function sendNotification(): void
    {
        // Reload the notification to get fresh data
        $this->notification->refresh();
        // PREPARA LE VARIABILI
        $mailVariables = [
            'tournament_name' => $this->notification->tournament->name ?? 'N/A',
            'tournament_date' => $this->notification->tournament->start_date->format('d/m/Y') ?? 'N/A',
            'club_name' => $this->notification->tournament->club->name ?? 'N/A',
            'referee_name' => $this->notification->recipient_name ?? 'N/A',
            'role' => $this->notification->assignment->role ?? 'Arbitro',
            'tournament' => $this->notification->tournament,
            'subject' => $this->notification->subject,
            'body' => $this->notification->body
        ];

        if ($this->notification->assignment) {
            // Send using the AssignmentNotification mailable
            $assignment = $this->notification->assignment;
            $attachments = $this->notification->attachments ?? [];
            $isClub = $this->notification->recipient_type === 'club';

            // Determine recipient name
            $recipientName = null;
            if ($this->notification->recipient_type === 'referee' && $assignment->user) {
                $recipientName = $assignment->user->name;
            } elseif ($this->notification->recipient_type === 'club' && $assignment->tournament->club) {
                $recipientName = $assignment->tournament->club->name;
            }

            Mail::to($this->notification->recipient_email)
                ->send(new AssignmentNotification(
                    $this->notification,
                    $mailVariables  // solo 2 parametri!
                ));
        } else {
            // For standalone notifications, use a simple mail
            Mail::raw($this->notification->body, function ($message) {
                $message->to($this->notification->recipient_email)
                    ->subject($this->notification->subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });
        }

        // Mark as sent
        $this->notification->markAsSent();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Mark notification as permanently failed
        $this->notification->markAsFailed($exception->getMessage());

        Log::error('SendNotificationJob permanently failed', [
            'notification_id' => $this->notification->id,
            'recipient' => $this->notification->recipient_email,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Optionally send admin notification
        $this->notifyAdminsOfFailure($exception);
    }

    /**
     * Get the appropriate queue name based on priority
     */
    private function getQueueName(): string
    {
        $priority = $this->notification->priority ?? 0;

        if ($priority >= 20) {
            return 'high'; // Urgent notifications
        } elseif ($priority >= 10) {
            return 'normal'; // Regular notifications
        } else {
            return 'low'; // Low priority notifications
        }
    }

    /**
     * Notify administrators of permanent failure
     */
    private function notifyAdminsOfFailure(\Throwable $exception): void
    {
        try {
            $adminEmails = config('app.admin_emails', []);

            if (empty($adminEmails)) {
                return;
            }

            $subject = 'Notification System: Permanent Failure';
            $body = "A notification has permanently failed to send.\n\n" .
                "Notification ID: {$this->notification->id}\n" .
                "Recipient: {$this->notification->recipient_email}\n" .
                "Subject: {$this->notification->subject}\n" .
                "Error: {$exception->getMessage()}\n" .
                "Time: " . now()->format('Y-m-d H:i:s');

            foreach ($adminEmails as $adminEmail) {
                Mail::raw($body, function ($message) use ($adminEmail, $subject) {
                    $message->to($adminEmail)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify admins of notification failure', [
                'original_notification_id' => $this->notification->id,
                'admin_notification_error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'notification',
            'notification:' . $this->notification->id,
            'recipient:' . $this->notification->recipient_type,
            'priority:' . ($this->notification->priority ?? 0)
        ];
    }
}
