<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class UnifiedAssignmentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $tournament;
    public $assignments;
    public $messageContent;
    public $recipientName;
    public $isClub;
    public $mailSubject;

    /**
     * Create a new message instance.
     *
     * @param Tournament $tournament
     * @param Collection $assignments
     * @param string $subject
     * @param string $messageContent
     * @param string|null $recipientName
     * @param bool $isClub Whether this is being sent to a club (affects template rendering)
     */
    public function __construct(
        Tournament $tournament,
        Collection $assignments,
        string $subject,
        string $messageContent,
        ?string $recipientName = null,
        bool $isClub = false
    ) {
        $this->tournament = $tournament;
        $this->assignments = $assignments;
        $this->mailSubject = $subject;
        $this->subject($subject);
        $this->messageContent = $messageContent;
        $this->recipientName = $recipientName;
        $this->isClub = $isClub;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // Use different templates depending on recipient
        if ($this->isClub) {
            return $this->markdown('emails.unified-club-assignment-notification');
        } else {
            return $this->markdown('emails.unified-assignment-notification');
        }
    }
}
