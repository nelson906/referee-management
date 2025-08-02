<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\Tournament;

class InstitutionalNotificationMail extends Mailable
{
    public function __construct(
        public Tournament $tournament,
        public string $notificationType
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->notificationType}] Assegnazione {$this->tournament->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament_assignment_generic',
            with: [
                'recipient_name' => 'Ufficio Campionati',
                'tournament_name' => $this->tournament->name,
                'tournament_dates' => $this->tournament->date_range,
                'club_name' => $this->tournament->club->name,
                'referees' => []  // Per ora vuoto
            ]
        );
    }
}
