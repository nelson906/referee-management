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
 * ⚖️ Mailable per notifiche arbitri
 */
class RefereeNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Assignment $assignment,
        public string $template = 'referee_assignment_formal',
        public array $attachments = [],
        public ?string $customMessage = null
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        $roleTranslations = [
            'chief_referee' => 'Direttore di Torneo',
            'referee' => 'Arbitro',
            'observer' => 'Osservatore'
        ];

        $role = $roleTranslations[$this->assignment->role] ?? $this->assignment->role;

        return new Envelope(
            subject: "Convocazione {$role} - {$this->assignment->tournament->name}",
            from: config('tournament-notifications.email.from.address'),
            replyTo: $this->assignment->tournament->zone->email ?? config('tournament-notifications.email.from.address'),
            tags: ['tournament-notification', 'referee'],
            metadata: [
                'tournament_id' => $this->assignment->tournament_id,
                'assignment_id' => $this->assignment->id,
                'referee_id' => $this->assignment->user_id,
                'role' => $this->assignment->role,
                'template' => $this->template
            ]
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament-notifications.referee',
            with: [
                'assignment' => $this->assignment,
                'referee' => $this->assignment->user,
                'tournament' => $this->assignment->tournament,
                'club' => $this->assignment->tournament->club,
                'zone' => $this->assignment->tournament->zone,
                'customMessage' => $this->customMessage,
                'template' => $this->template,
                'role' => $this->translateRole($this->assignment->role)
            ]
        );
    }

    public function attachments(): array
    {
        $attachmentObjects = [];

        // Allegati specifici dell'assignment
        foreach ($this->attachments as $attachment) {
            if (isset($attachment['path']) && file_exists(storage_path('app/' . $attachment['path']))) {
                $attachmentObjects[] = Attachment::fromStorage($attachment['path'])
                                               ->as($attachment['name'] ?? basename($attachment['path']))
                                               ->withMime($attachment['mime'] ?? 'application/pdf');
            }
        }

        // Genera convocazione personalizzata se non presente
        if (empty($attachmentObjects)) {
            $convocationPath = $this->generatePersonalizedConvocation();
            if ($convocationPath) {
                $attachmentObjects[] = Attachment::fromStorage($convocationPath)
                                               ->as('Convocazione_Ufficiale.pdf')
                                               ->withMime('application/pdf');
            }
        }

        return $attachmentObjects;
    }

    private function translateRole(string $role): string
    {
        return match($role) {
            'chief_referee' => 'Direttore di Torneo',
            'referee' => 'Arbitro',
            'observer' => 'Osservatore',
            default => ucfirst($role)
        };
    }

    private function generatePersonalizedConvocation(): ?string
    {
        // Genera PDF convocazione personalizzata
        try {
            $pdfService = app(\App\Services\ConvocationPdfService::class);
            return $pdfService->generateForAssignment($this->assignment);
        } catch (\Exception $e) {
            \Log::warning('Failed to generate personalized convocation', [
                'assignment_id' => $this->assignment->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
