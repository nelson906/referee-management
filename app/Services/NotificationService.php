<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\InstitutionalEmail;
use App\Models\Notification;
use App\Models\LetterTemplate;
use Illuminate\Support\Facades\Mail;
use App\Mail\AssignmentNotification;

class NotificationService
{
    /**
     * Send assignment notification to referee.
     */
    public function sendAssignmentNotification(Assignment $assignment, array $options = [])
    {
        $tournament = $assignment->tournament;
        $referee = $assignment->user;

        // Prepare variables for template
        $variables = [
            'referee_name' => $referee->name,
            'tournament_name' => $tournament->name,
            'tournament_date' => $tournament->start_date->format('d/m/Y'), // ← singolare!
            'tournament_dates' => $tournament->date_range, // ← plurale per compatibilità
            'club_name' => $tournament->club->name,
            'club_address' => $tournament->club->full_address,
            'role' => $assignment->role, // ← era assignment_role
            'zone_name' => $tournament->zone->name,
            'assigned_date' => now()->format('d/m/Y'),
            'tournament_category' => $tournament->tournamentType->name,
            'contact_person' => $tournament->club->contact_person ?? 'N/A',
            'fee_amount' => '0', // ← aggiungi se serve
        ];

        // Get or create subject and body
        $subject = $options['custom_subject'] ?? $this->getTemplateSubject('assignment', $tournament->zone_id, $variables);
        $body = $options['custom_message'] ?? $this->getTemplateBody('assignment', $tournament->zone_id, $variables);

        // Create notification record
        $notification = Notification::create([
            'assignment_id' => $assignment->id,
            'recipient_type' => 'referee',
            'recipient_email' => $referee->email,
            'subject' => $subject,
            'body' => $body,
            'template_used' => $options['template_id'] ?? 'default',
            'status' => 'pending',
            'attachments' => $options['include_attachments'] ? $this->getAttachments($tournament) : null,
        ]);

        // Send email
        try {
            Mail::to($referee->email, $referee->name)->send(
                new AssignmentNotification($notification, $variables)
            );

            $notification->markAsSent();
            return $notification;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Send notification to club.
     */
    public function sendClubNotification(Tournament $tournament, array $options = [])
    {
        if (!$tournament->club->email) {
            throw new \Exception('Il circolo non ha un indirizzo email configurato.');
        }

        // Prepare variables
        $variables = [
            'tournament_name' => $tournament->name,
            'tournament_dates' => $tournament->date_range,
            'club_name' => $tournament->club->name,
            'club_address' => $tournament->club->full_address,
            'zone_name' => $tournament->zone->name,
            'assigned_date' => now()->format('d/m/Y'),
            'tournament_category' => $tournament->tournamentType->name,
            'referees_count' => $tournament->assignments->count(),
        ];

        $subject = $options['custom_subject'] ?? $this->getTemplateSubject('club', $tournament->zone_id, $variables);
        $body = $options['custom_message'] ?? $this->getTemplateBody('club', $tournament->zone_id, $variables);
        $firstAssignment = $tournament->assignments->first();
        if (!$firstAssignment) {
            throw new \Exception('Nessuna assegnazione trovata per questo torneo');
        }


        // Create notification
        $notification = Notification::create([
            'assignment_id' => $firstAssignment->id,
            'recipient_type' => 'club',
            'recipient_email' => $tournament->club->email,
            'subject' => $subject,
            'body' => $body,
            'template_used' => $options['template_id'] ?? 'default',
            'status' => 'pending',
        ]);

        // Send email
        try {
            Mail::raw($body, function ($message) use ($tournament, $subject) {
                $message->to($tournament->club->email, $tournament->club->name)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            $notification->markAsSent();
            return $notification;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Send notification to institutional email.
     */
    public function sendInstitutionalNotification(InstitutionalEmail $institutional, Tournament $tournament, array $options = [])
    {
        // Prepare variables
        $variables = [
            'institution_name' => $institutional->name,
            'tournament_name' => $tournament->name,
            'tournament_dates' => $tournament->date_range,
            'club_name' => $tournament->club->name,
            'zone_name' => $tournament->zone->name,
            'assigned_date' => now()->format('d/m/Y'),
            'tournament_category' => $tournament->tournamentType->name,
            'referees_count' => $tournament->assignments->count(),
        ];

        $subject = $options['custom_subject'] ?? $this->getTemplateSubject('institutional', $tournament->zone_id, $variables);
        $body = $options['custom_message'] ?? $this->getTemplateBody('institutional', $tournament->zone_id, $variables);

        // Create notification
        $notification = Notification::create([
            'assignment_id' => null,
            'recipient_type' => 'institutional',
            'recipient_email' => $institutional->email,
            'subject' => $subject,
            'body' => $body,
            'template_used' => $options['template_id'] ?? 'default',
            'status' => 'pending',
        ]);

        // Send email
        try {
            Mail::raw($body, function ($message) use ($institutional, $subject) {
                $message->to($institutional->email, $institutional->name)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            $notification->markAsSent();
            return $notification;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Send notification to custom email.
     */
    public function sendCustomNotification(string $email, Tournament $tournament, array $options = [])
    {
        // Prepare variables
        $variables = [
            'tournament_name' => $tournament->name,
            'tournament_dates' => $tournament->date_range,
            'club_name' => $tournament->club->name,
            'zone_name' => $tournament->zone->name,
            'assigned_date' => now()->format('d/m/Y'),
            'tournament_category' => $tournament->tournamentType->name,
            'referees_count' => $tournament->assignments->count(),
        ];

        $subject = $options['custom_subject'] ?? $this->getTemplateSubject('assignment', $tournament->zone_id, $variables);
        $body = $options['custom_message'] ?? $this->getTemplateBody('assignment', $tournament->zone_id, $variables);

        // Create notification
        $notification = Notification::create([
            'assignment_id' => null,
            'recipient_type' => 'custom',
            'recipient_email' => $email,
            'subject' => $subject,
            'body' => $body,
            'template_used' => $options['template_id'] ?? 'default',
            'status' => 'pending',
        ]);

        // Send email
        try {
            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            $notification->markAsSent();
            return $notification;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Retry failed notification.
     */
    public function retryNotification(Notification $notification)
    {
        if (!$notification->canBeRetried()) {
            throw new \Exception('Questa notifica non può essere ritentata.');
        }

        $notification->resetForRetry();

        // Send based on recipient type
        try {
            Mail::raw($notification->body, function ($message) use ($notification) {
                $message->to($notification->recipient_email)
                    ->subject($notification->subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            $notification->markAsSent();
            return $notification;
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Get template subject with variables replaced.
     */
    private function getTemplateSubject(string $type, ?int $zoneId, array $variables): string
    {
        $template = LetterTemplate::getBestTemplate($type, $zoneId);

        if ($template) {
            return $this->replaceVariables($template->subject, $variables);
        }

        // Default subject
        return $this->replaceVariables('Notifica {{tournament_name}}', $variables);
    }

    /**
     * Get template body with variables replaced.
     */
    private function getTemplateBody(string $type, ?int $zoneId, array $variables): string
    {
        $template = LetterTemplate::getBestTemplate($type, $zoneId);

        if ($template) {
            return $this->replaceVariables($template->body, $variables);
        }

        // Default body
        return $this->replaceVariables($this->getDefaultBody($type), $variables);
    }

    /**
     * Replace variables in text.
     */
    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }

    /**
     * Get default body template.
     */
    private function getDefaultBody(string $type): string
    {
        return match ($type) {
            'assignment' => "Gentile {{referee_name}},\n\nLa informiamo che è stato assegnato al torneo {{tournament_name}} che si svolgerà il {{tournament_dates}} presso il {{club_name}}.\n\nIl suo ruolo sarà: {{assignment_role}}\n\nCordiali saluti,\nFederazione Italiana Golf",
            'club' => "Gentile {{club_name}},\n\nLa informiamo che sono stati assegnati gli arbitri per il torneo {{tournament_name}} in programma il {{tournament_dates}}.\n\nCordiali saluti,\nFederazione Italiana Golf",
            'institutional' => "Gentile {{institution_name}},\n\nLa informiamo che sono state completate le assegnazioni per il torneo {{tournament_name}} in programma il {{tournament_dates}} presso {{club_name}}.\n\nCordiali saluti,\nFederazione Italiana Golf",
            default => "Gentile destinatario,\n\nNotifica relativa al torneo {{tournament_name}}.\n\nCordiali saluti,\nFederazione Italiana Golf",
        };
    }

    /**
     * Get tournament attachments.
     */
    private function getAttachments(Tournament $tournament): array
    {
        $attachments = [];

        if ($tournament->convocation_file_path && file_exists(storage_path('app/' . $tournament->convocation_file_path))) {
            $attachments['convocation'] = $tournament->convocation_file_path;
        }

        if ($tournament->club_letter_file_path && file_exists(storage_path('app/' . $tournament->club_letter_file_path))) {
            $attachments['club_letter'] = $tournament->club_letter_file_path;
        }

        return $attachments;
    }
}
