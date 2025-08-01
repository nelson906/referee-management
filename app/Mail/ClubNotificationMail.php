<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Tournament;

class ClubNotificationMail extends Mailable
{
    use SerializesModels;

    public $tournament;
    public $attachments;

    /**
     * Create a new message instance.
     */
    public function __construct(Tournament $tournament, array $attachments = [])
    {
        $this->tournament = $tournament;
        $this->attachments = $attachments;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Arbitri Assegnati - {$this->tournament->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament_assignment_generic',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $mailAttachments = [];

        foreach ($this->attachments as $path) {
            if (file_exists($path)) {
                $mailAttachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath($path);
            }
        }

        return $mailAttachments;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // Prepara i dati per la view
        $referees = $this->tournament->assignments->map(function($assignment) {
            return [
                'name' => $assignment->user->name,
                'role' => $assignment->role,
                'email' => $assignment->user->email
            ];
        })->toArray();

        return $this->view('emails.tournament_assignment_generic')
                    ->with([
                        'recipient_name' => $this->tournament->club->name,
                        'tournament_name' => $this->tournament->name,
                        'tournament_dates' => $this->tournament->date_range,
                        'club_name' => $this->tournament->club->name,
                        'referees' => $referees,
                        'zone_email' => "szr{$this->tournament->zone_id}@federgolf.it",
                        'club_email' => $this->tournament->club->email,
                        'attachments_info' => count($this->attachments) > 0 ?
                            ['Facsimile convocazione in formato Word'] : null
                    ]);
    }
}
