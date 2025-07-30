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
 * 🏛️ Mailable per notifiche istituzionali
 */
class InstitutionalNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tournament $tournament,
        public array $assignments,
        public string $recipientType = 'crc',
        public string $template = 'institutional_report_standard',
        public ?string $customMessage = null
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        $subjects = [
            'crc' => "Report Assegnazione - {$this->tournament->name}",
            'zone' => "Nuova Assegnazione Zona - {$this->tournament->name}",
            'delegate' => "Comunicazione Assegnazione - {$this->tournament->name}"
        ];

        return new Envelope(
            subject: $subjects[$this->recipientType] ?? "Notifica - {$this->tournament->name}",
            from: config('tournament-notifications.email.from.address'),
            tags: ['tournament-notification', 'institutional', $this->recipientType],
            metadata: [
                'tournament_id' => $this->tournament->id,
                'recipient_type' => $this->recipientType,
                'template' => $this->template,
                'assignments_count' => count($this->assignments)
            ]
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament-notifications.institutional',
            with: [
                'tournament' => $this->tournament,
                'assignments' => $this->assignments,
                'club' => $this->tournament->club,
                'zone' => $this->tournament->zone,
                'recipientType' => $this->recipientType,
                'customMessage' => $this->customMessage,
                'template' => $this->template,
                'stats' => $this->calculateStats()
            ]
        );
    }

    public function attachments(): array
    {
        // Le notifiche istituzionali di solito non hanno allegati
        // Ma possono includere report PDF se richiesto
        return [];
    }

    private function calculateStats(): array
    {
        $assignmentsByRole = collect($this->assignments)->groupBy('role');

        return [
            'total_referees' => count($this->assignments),
            'chief_referees' => $assignmentsByRole->get('chief_referee', collect())->count(),
            'referees' => $assignmentsByRole->get('referee', collect())->count(),
            'observers' => $assignmentsByRole->get('observer', collect())->count(),
            'local_referees' => collect($this->assignments)->where('user.zone_id', $this->tournament->zone_id)->count(),
            'external_referees' => collect($this->assignments)->where('user.zone_id', '!=', $this->tournament->zone_id)->count()
        ];
    }
}
