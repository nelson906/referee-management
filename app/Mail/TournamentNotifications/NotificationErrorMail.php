<?php

namespace App\Mail\TournamentNotifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Services\ConvocationPdfService;
use App\Models\Tournament;
use App\Models\Assignment;

/**
 * 🚨 Mailable per notifiche di errore agli admin
 */
class NotificationErrorMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tournament $tournament,
        public \Throwable $exception,
        public array $failedRecipients = [],
        public ?string $context = null
    ) {
        $this->onQueue('priority');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🚨 Errore Sistema Notifiche - {$this->tournament->name}",
            from: config('tournament-notifications.email.from.address'),
            tags: ['system-error', 'notification-failure'],
            metadata: [
                'tournament_id' => $this->tournament->id,
                'error_type' => get_class($this->exception),
                'failed_count' => count($this->failedRecipients)
            ]
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament-notifications.error',
            with: [
                'tournament' => $this->tournament,
                'exception' => $this->exception,
                'failedRecipients' => $this->failedRecipients,
                'context' => $this->context,
                'errorDetails' => $this->getErrorDetails()
            ]
        );
    }

    private function getErrorDetails(): array
    {
        return [
            'error_message' => $this->exception->getMessage(),
            'error_code' => $this->exception->getCode(),
            'file' => $this->exception->getFile(),
            'line' => $this->exception->getLine(),
            'timestamp' => now()->toISOString(),
            'server' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
    }
}
