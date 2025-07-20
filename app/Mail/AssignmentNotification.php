<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class AssignmentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $notification;
    public $variables;

    /**
     * Create a new message instance.
     */
    public function __construct(Notification $notification, array $variables = [])
    {
        $this->notification = $notification;
        $this->variables = $variables;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: $this->notification->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            text: 'mail.assignment-notification-text',
            with: [
                'notification' => $this->notification,
                'variables' => $this->variables,
                'body' => $this->notification->body,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->notification->hasAttachments()) {
            foreach ($this->notification->attachments as $name => $path) {
                if (file_exists(storage_path('app/' . $path))) {
                    $attachments[] = Attachment::fromStorageDisk('local', $path)
                        ->as($name . '.' . pathinfo($path, PATHINFO_EXTENSION));
                }
            }
        }

        return $attachments;
    }
}
