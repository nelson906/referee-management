<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Notification;
use App\Models\InstitutionalEmail;
use App\Models\LetterTemplate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Document generation service
     */
    protected $documentService;

    /**
     * Constructor
     */
    public function __construct(DocumentGenerationService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Send notification for a new assignment
     */
    public function sendAssignmentNotification(Assignment $assignment): void
    {
        try {
            // Load relationships
            $assignment->load(['user', 'tournament.club', 'tournament.zone', 'tournament.tournamentCategory']);

            // 1. Send notification to referee
            $this->sendRefereeNotification($assignment);

            // 2. Send notification to circle
            $this->sendCircleNotification($assignment);

            // 3. Send to institutional emails
            $this->sendInstitutionalNotifications($assignment);

        } catch (\Exception $e) {
            Log::error('Error sending assignment notifications', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send notification to referee
     */
    protected function sendRefereeNotification(Assignment $assignment): void
    {
        $referee = $assignment->user;
        $tournament = $assignment->tournament;

        // Get template
        $template = $this->getTemplate('assignment', $tournament->zone_id);

        // Prepare variables for template
        $variables = [
            'referee_name' => $referee->name,
            'tournament_name' => $tournament->name,
            'tournament_dates' => $tournament->date_range,
            'club_name' => $tournament->club->name,
            'club_address' => $tournament->club->full_address,
            'tournament_category' => $tournament->tournamentCategory->name,
            'role' => $assignment->role,
            'assigned_date' => Carbon::now()->format('d/m/Y'),
        ];

        // Replace variables in template
        $subject = $this->replaceVariables($template->subject ?? 'Assegnazione Torneo', $variables);
        $body = $this->replaceVariables($template->body ?? $this->getDefaultRefereeBody(), $variables);

        // Generate convocation document
        $convocationPath = $this->documentService->generateConvocationLetter($assignment);

        // Create notification record
        $notification = Notification::create([
            'assignment_id' => $assignment->id,
            'recipient_type' => 'referee',
            'recipient_email' => $referee->email,
            'subject' => $subject,
            'body' => $body,
            'template_used' => $template->name ?? 'default',
            'status' => 'pending',
            'attachments' => $convocationPath ? ['convocation' => $convocationPath] : null,
        ]);

        // Send email
        $this->sendEmail($notification);
    }

    /**
     * Send notification to circle
     */
    protected function sendCircleNotification(Assignment $assignment): void
    {
        $circle = $assignment->tournament->club;

        if (!$circle->email) {
            Log::warning('Circle has no email', ['circle_id' => $circle->id]);
            return;
        }

        // Get template
        $template = $this->getTemplate('circle', $assignment->tournament->zone_id);

        // Prepare variables
        $variables = [
            'circle_name' => $circle->name,
            'tournament_name' => $assignment->tournament->name,
            'tournament_dates' => $assignment->tournament->date_range,
            'referee_name' => $assignment->user->name,
            'referee_level' => ucfirst($assignment->user->level),
            'referee_code' => $assignment->user->referee_code,
            'contact_person' => $circle->contact_person,
        ];

        // Replace variables
        $subject = $this->replaceVariables($template->subject ?? 'Arbitro Assegnato', $variables);
        $body = $this->replaceVariables($template->body ?? $this->getDefaultCircleBody(), $variables);

        // Generate club letter if needed
        $clubLetterPath = null;
        if ($assignment->tournament->assignments()->count() === $assignment->tournament->required_referees) {
            $clubLetterPath = $this->documentService->generateClubLetter($assignment->tournament);
        }

        // Create notification
        $notification = Notification::create([
            'assignment_id' => $assignment->id,
            'recipient_type' => 'circle',
            'recipient_email' => $circle->email,
            'subject' => $subject,
            'body' => $body,
            'template_used' => $template->name ?? 'default',
            'status' => 'pending',
            'attachments' => $clubLetterPath ? ['club_letter' => $clubLetterPath] : null,
        ]);

        // Send email
        $this->sendEmail($notification);
    }

    /**
     * Send to institutional emails
     */
    protected function sendInstitutionalNotifications(Assignment $assignment): void
    {
        $tournament = $assignment->tournament;

        // Get institutional emails for zone
        $emails = InstitutionalEmail::active()
            ->where(function ($query) use ($tournament) {
                $query->whereNull('zone_id')
                      ->orWhere('zone_id', $tournament->zone_id);
            })
            ->where(function ($query) {
                $query->where('receive_all_notifications', true)
                      ->orWhereJsonContains('notification_types', 'assignment');
            })
            ->get();

        foreach ($emails as $institutionalEmail) {
            // Prepare variables
            $variables = [
                'tournament_name' => $tournament->name,
                'tournament_dates' => $tournament->date_range,
                'club_name' => $tournament->club->name,
                'referee_name' => $assignment->user->name,
                'zone_name' => $tournament->zone->name,
                'category_name' => $tournament->tournamentCategory->name,
            ];

            $subject = "Assegnazione Arbitro - {$tournament->name}";
            $body = $this->replaceVariables($this->getDefaultInstitutionalBody(), $variables);

            // Create notification
            $notification = Notification::create([
                'assignment_id' => $assignment->id,
                'recipient_type' => 'institutional',
                'recipient_email' => $institutionalEmail->email,
                'subject' => $subject,
                'body' => $body,
                'template_used' => 'institutional_default',
                'status' => 'pending',
            ]);

            // Send email
            $this->sendEmail($notification);
        }
    }

    /**
     * Send email
     */
    protected function sendEmail(Notification $notification): void
    {
        try {
            Mail::send('emails.notification', ['notification' => $notification], function ($message) use ($notification) {
                $message->to($notification->recipient_email)
                        ->subject($notification->subject);

                // Add attachments
                if ($notification->attachments) {
                    foreach ($notification->attachments as $type => $path) {
                        if (file_exists(storage_path('app/public/' . $path))) {
                            $message->attach(storage_path('app/public/' . $path));
                        }
                    }
                }
            });

            // Update notification status
            $notification->update([
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);

        } catch (\Exception $e) {
            // Update notification with error
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count' => $notification->retry_count + 1,
            ]);

            Log::error('Failed to send notification email', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Resend a failed notification
     */
    public function resendNotification(Notification $notification): void
    {
        if ($notification->status !== 'failed') {
            throw new \Exception('Only failed notifications can be resent');
        }

        // Reset status and try again
        $notification->update(['status' => 'pending']);
        $this->sendEmail($notification);
    }

    /**
     * Get template
     */
    protected function getTemplate(string $type, $zoneId = null): ?LetterTemplate
    {
        return LetterTemplate::where('type', $type)
            ->where('is_active', true)
            ->where(function ($query) use ($zoneId) {
                $query->whereNull('zone_id')
                      ->orWhere('zone_id', $zoneId);
            })
            ->orderBy('zone_id', 'desc') // Prefer zone-specific templates
            ->first();
    }

    /**
     * Replace variables in template
     */
    protected function replaceVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }

    /**
     * Get default referee body
     */
    protected function getDefaultRefereeBody(): string
    {
        return "Gentile {{referee_name}},\n\n" .
               "La informiamo che è stato assegnato come arbitro per il torneo:\n\n" .
               "Torneo: {{tournament_name}}\n" .
               "Date: {{tournament_dates}}\n" .
               "Circolo: {{club_name}}\n" .
               "Indirizzo: {{club_address}}\n" .
               "Categoria: {{tournament_category}}\n" .
               "Ruolo: {{role}}\n\n" .
               "In allegato trova la lettera di convocazione ufficiale.\n\n" .
               "Cordiali saluti,\n" .
               "Sistema Gestione Arbitri Golf";
    }

    /**
     * Get default circle body
     */
    protected function getDefaultCircleBody(): string
    {
        return "Spett.le {{circle_name}},\n\n" .
               "Vi informiamo che per il torneo:\n\n" .
               "{{tournament_name}} ({{tournament_dates}})\n\n" .
               "È stato assegnato il seguente arbitro:\n" .
               "{{referee_name}} - {{referee_level}} (Cod. {{referee_code}})\n\n" .
               "L'arbitro è stato informato dell'assegnazione.\n\n" .
               "Cordiali saluti,\n" .
               "Sistema Gestione Arbitri Golf";
    }

    /**
     * Get default institutional body
     */
    protected function getDefaultInstitutionalBody(): string
    {
        return "Comunicazione di assegnazione arbitro:\n\n" .
               "Torneo: {{tournament_name}}\n" .
               "Date: {{tournament_dates}}\n" .
               "Circolo: {{club_name}}\n" .
               "Zona: {{zone_name}}\n" .
               "Categoria: {{category_name}}\n" .
               "Arbitro assegnato: {{referee_name}}\n\n" .
               "Sistema Gestione Arbitri Golf";
    }
}
