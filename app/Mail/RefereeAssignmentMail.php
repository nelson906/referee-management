<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Assignment;  // Models, non Mail
use App\Models\Tournament;  // Models, non Mail

class RefereeAssignmentMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * L'assegnazione dell'arbitro
     */
    public Assignment $assignment;

    /**
     * Il torneo di riferimento
     */
    public Tournament $tournament;

    /**
     * I percorsi degli allegati
     */
    public array $attachmentPaths;


    /**
     * Create a new message instance.
     */
    public function __construct($assignment, Tournament $tournament, array $attachmentPaths = [])
    {
        $this->assignment = $assignment;
        $this->tournament = $tournament;
        $this->attachmentPaths = $attachmentPaths;  // Usa un nome diverso

    }

    public function attachments(): array
    {
        $mailAttachments = [];

        foreach ($this->attachmentPaths as $path) {
            if (file_exists($path)) {
                $mailAttachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath($path);
            }
        }

        return $mailAttachments;
    }
    public function build()
    {
        return $this->subject("Convocazione {$this->assignment->role} - {$this->tournament->name}")
            ->view('emails.tournament_assignment_generic')  // ← USA QUESTO
            ->with([
                'assignment' => $this->assignment,
                'tournament' => $this->tournament,
                // Aggiungi le variabili che il template si aspetta
                'recipient_name' => $this->assignment->user->name,
                'tournament_name' => $this->tournament->name,
                'tournament_dates' => $this->tournament->date_range,
                'club_name' => $this->tournament->club->name,
                'referees' => [[
                    'name' => $this->assignment->user->name,
                    'role' => $this->assignment->role,
                    'email' => $this->assignment->user->email
                ]],
                'zone_email' => "szr{$this->tournament->zone_id}@federgolf.it",
                'club_email' => $this->tournament->club->email
            ]);
    }
}
