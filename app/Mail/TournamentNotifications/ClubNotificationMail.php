<?php

namespace App\Mail\TournamentNotifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Models\Tournament;
use App\Models\Assignment;

/**
 * 🏌️ Mailable per notifiche circoli
 */
class ClubNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tournament $tournament,
        public array $referees,
        public string $template = 'club_assignment_standard',
        public array $attachments = [],
        public ?string $customMessage = null
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Assegnazione Arbitri - {$this->tournament->name}",
            from: config('tournament-notifications.email.from.address'),
            replyTo: $this->tournament->zone->email ?? config('tournament-notifications.email.from.address'),
            tags: ['tournament-notification', 'club'],
            metadata: [
                'tournament_id' => $this->tournament->id,
                'template' => $this->template,
                'zone' => $this->tournament->zone->code
            ]
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament-notifications.club',
            with: [
                'tournament' => $this->tournament,
                'club' => $this->tournament->club,
                'referees' => $this->referees,
                'zone' => $this->tournament->zone,
                'customMessage' => $this->customMessage,
                'template' => $this->template
            ]
        );
    }

    public function attachments(): array
    {
        $attachmentObjects = [];

        foreach ($this->attachments as $attachment) {
            if (isset($attachment['path']) && file_exists(storage_path('app/' . $attachment['path']))) {
                $attachmentObjects[] = Attachment::fromStorage($attachment['path'])
                                               ->as($attachment['name'] ?? basename($attachment['path']))
                                               ->withMime($attachment['mime'] ?? 'application/pdf');
            }
        }

        // Allegati predefiniti per circoli
        $defaultAttachments = [
            'convocazione_szr.pdf' => 'Convocazione SZR',
            'facsimile_convocazione.pdf' => 'Facsimile Lettera Convocazione'
        ];

        foreach ($defaultAttachments as $file => $name) {
            $path = "attachments/templates/{$file}";
            if (file_exists(storage_path("app/{$path}"))) {
                $attachmentObjects[] = Attachment::fromStorage($path)
                                               ->as($name . '.pdf')
                                               ->withMime('application/pdf');
            }
        }

        return $attachmentObjects;
    }

    /**
     * 🔧 Personalizza based on template
     */
    public function build()
    {
        // Personalizzazioni specifiche per template
        switch ($this->template) {
            case 'club_assignment_detailed':
                $this->subject("Comunicazione Comitato di Gara - {$this->tournament->name}");
                break;
            case 'club_assignment_minimal':
                $this->subject("Arbitri - {$this->tournament->name}");
                break;
        }

        return $this;
    }
}


