<?php
// File: app/Mail/AssignmentNotification.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Assignment;
use App\Models\Notification;

class AssignmentNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $assignment;
    public $notification;
    public $attachments;
    public $recipientName;
    public $isClub;

    /**
     * Create a new message instance.
     */
    public function __construct(Assignment $assignment, Notification $notification, array $attachments = [], ?string $recipientName = null, bool $isClub = false)
    {
        $this->assignment = $assignment;
        $this->notification = $notification;
        $this->attachments = $attachments;
        $this->recipientName = $recipientName;
        $this->isClub = $isClub;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $mail = $this->from(config('mail.from.address'), config('mail.from.name'))
                     ->subject($this->notification->subject)
                     ->view('mail.assignment-notification')
                     ->with([
                         'assignment' => $this->assignment,
                         'notification' => $this->notification,
                         'recipientName' => $this->recipientName,
                         'isClub' => $this->isClub,
                         'tournament' => $this->assignment->tournament,
                         'messageContent' => $this->notification->body,
                     ]);

        // Add attachments based on recipient type
        foreach ($this->attachments as $type => $path) {
            if (file_exists($path)) {
                // Club gets club letter, referees get convocation
                if ($type === 'club_letter' && $this->isClub) {
                    $mail->attach($path, [
                        'as' => 'lettera_circolo.docx',
                        'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    ]);
                } elseif ($type === 'convocation' && !$this->isClub) {
                    $mail->attach($path, [
                        'as' => 'convocazione.docx',
                        'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    ]);
                }
            }
        }

        return $mail;
    }
}
