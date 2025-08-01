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
use App\Services\CsvReportService;

/**
 * ✅ Mailable per conferme invio
 */
class NotificationConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tournament $tournament,
        public array $results,
        public string $adminName
    ) {
        // Priorità normale per conferme
    }

    public function envelope(): Envelope
    {
        $status = $results['details']['failed'] > 0 ? '⚠️ Parziale' : '✅ Completato';

        return new Envelope(
            subject: "{$status} - Notifiche {$this->tournament->name}",
            from: config('tournament-notifications.email.from.address'),
            tags: ['confirmation', 'admin-notification'],
            metadata: [
                'tournament_id' => $this->tournament->id,
                'total_sent' => $this->results['total_sent'],
                'admin' => $this->adminName
            ]
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament-notifications.confirmation',
            with: [
                'tournament' => $this->tournament,
                'results' => $this->results,
                'adminName' => $this->adminName,
                'summary' => $this->generateSummary()
            ]
        );
    }

    private function generateSummary(): array
    {
        $details = $this->results['details'];

        return [
            'total_sent' => $this->results['total_sent'],
            'total_failed' => ($details['club']['failed'] ?? 0) +
                             ($details['referees']['failed'] ?? 0) +
                             ($details['institutional']['failed'] ?? 0),
            'success_rate' => $this->results['total_sent'] > 0
                ? round((($this->results['total_sent'] - $this->getTotalFailed()) / $this->results['total_sent']) * 100, 1)
                : 0,
            'by_type' => [
                'club' => [
                    'sent' => $details['club']['sent'] ?? 0,
                    'failed' => $details['club']['failed'] ?? 0
                ],
                'referees' => [
                    'sent' => $details['referees']['sent'] ?? 0,
                    'failed' => $details['referees']['failed'] ?? 0
                ],
                'institutional' => [
                    'sent' => $details['institutional']['sent'] ?? 0,
                    'failed' => $details['institutional']['failed'] ?? 0
                ]
            ]
        ];
    }

    private function getTotalFailed(): int
    {
        $details = $this->results['details'];
        return ($details['club']['failed'] ?? 0) +
               ($details['referees']['failed'] ?? 0) +
               ($details['institutional']['failed'] ?? 0);
    }
}
