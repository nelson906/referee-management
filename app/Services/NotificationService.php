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
            // Load relationships - ✅ FIXED: tournamentType instead of tournamentCategory
            $assignment->load(['user', 'tournament.club', 'tournament.zone', 'tournament.tournamentType']);

            // 1. Send notification to referee
            $this->sendRefereeNotification($assignment);

            // 2. Send notification to club
            $this->sendClubNotification($assignment);

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

        // Prepare variables for template - ✅ FIXED: tournamentType instead of tournamentCategory
        $variables = [
            'referee_name' => $referee->name,
            'tournament_name' => $tournament->name,
            'tournament_dates' => $tournament->date_range,
            'club_name' => $tournament->club->name,
            'club_address' => $tournament->club->full_address,
            'tournament_category' => $tournament->tournamentType->name, // ← FIXED: tournamentType
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
     * Send notification to club
     */
    protected function sendClubNotification(Assignment $assignment): void
    {
        $club = $assignment->tournament->club;

        if (!$club->email) {
            Log::warning('Club has no email', ['club_id' => $club->id]);
            return;
        }

        // Get template
        $template = $this->getTemplate('club', $assignment->tournament->zone_id);

        // Prepare variables
        $variables = [
            'club_name' => $club->name,
            'tournament_name' => $assignment->tournament->name,
            'tournament_dates' => $assignment->tournament->date_range,
            'referee_name' => $assignment->user->name,
            'referee_level' => ucfirst($assignment->user->level),
            'referee_code' => $assignment->user->referee_code,
            'contact_person' => $club->contact_person,
            'club_address' => $club->full_address,
            'club_phone' => $club->phone,
            'club_email' => $club->email,
        ];

        // Replace variables
        $subject = $this->replaceVariables($template->subject ?? 'Arbitro Assegnato', $variables);
        $body = $this->replaceVariables($template->body ?? $this->getDefaultClubBody(), $variables);

        // Generate club letter if needed
        $clubLetterPath = null;
        $tournament = $assignment->tournament;

        // ✅ FIXED: tournamentType instead of tournamentCategory
        if ($tournament->assignments()->count() >= $tournament->tournamentType->min_referees) {
            $clubLetterPath = $this->documentService->generateClubLetter($tournament);
        }

        // Create notification
        $notification = Notification::create([
            'assignment_id' => $assignment->id,
            'recipient_type' => 'club',
            'recipient_email' => $club->email,
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
     * Send notifications to institutional emails
     */
    protected function sendInstitutionalNotifications(Assignment $assignment): void
    {
        $tournament = $assignment->tournament;

        // Get institutional emails for this zone
        $institutionalEmails = InstitutionalEmail::active()
            ->forZone($tournament->zone_id)
            ->forNotificationType('assignment')
            ->get();

        foreach ($institutionalEmails as $institutionalEmail) {
            // Prepare variables
            $variables = [
                'institution_name' => $institutionalEmail->name,
                'tournament_name' => $tournament->name,
                'tournament_dates' => $tournament->date_range,
                'club_name' => $tournament->club->name,
                'zone_name' => $tournament->zone->name,
                'referee_name' => $assignment->user->name,
                'referee_level' => ucfirst($assignment->user->level),
                'role' => $assignment->role,
                'assigned_date' => Carbon::now()->format('d/m/Y'),
                // ✅ FIXED: tournamentType instead of tournamentCategory
                'tournament_category' => $tournament->tournamentType->name,
            ];

            // Get template
            $template = $this->getTemplate('institutional', $tournament->zone_id);

            // Replace variables
            $subject = $this->replaceVariables($template->subject ?? 'Nuova Assegnazione', $variables);
            $body = $this->replaceVariables($template->body ?? $this->getDefaultInstitutionalBody(), $variables);

            // Create notification
            $notification = Notification::create([
                'assignment_id' => $assignment->id,
                'recipient_type' => 'institutional',
                'recipient_email' => $institutionalEmail->email,
                'subject' => $subject,
                'body' => $body,
                'template_used' => $template->name ?? 'default',
                'status' => 'pending',
            ]);

            // Send email
            $this->sendEmail($notification);
        }
    }

    /**
     * Get template for notification type and zone
     */
    protected function getTemplate(string $type, ?int $zoneId = null): ?LetterTemplate
    {
        return LetterTemplate::active()
            ->ofType($type)
            ->forZone($zoneId)
            ->first();
    }

    /**
     * Replace variables in text
     */
    protected function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }

        return $text;
    }

    /**
     * Send email notification
     */
    protected function sendEmail(Notification $notification): void
    {
        try {
            // Send email logic here
            // This is a simplified version - you'd implement actual email sending

            $notification->markAsSent();

        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            Log::error('Failed to send notification email', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get default referee notification body
     */
    protected function getDefaultRefereeBody(): string
    {
        return "Gentile {{referee_name}},\n\n" .
               "La informiamo che è stato assegnato come {{role}} per il torneo:\n\n" .
               "Torneo: {{tournament_name}}\n" .
               "Date: {{tournament_dates}}\n" .
               "Circolo: {{club_name}}\n" .
               "Categoria: {{tournament_category}}\n\n" .
               "Cordiali saluti";
    }

    /**
     * Get default club notification body
     */
    protected function getDefaultClubBody(): string
    {
        return "Gentile {{contact_person}},\n\n" .
               "La informiamo che per il torneo {{tournament_name}} è stato assegnato l'arbitro:\n\n" .
               "Arbitro: {{referee_name}}\n" .
               "Livello: {{referee_level}}\n" .
               "Codice: {{referee_code}}\n\n" .
               "Date torneo: {{tournament_dates}}\n\n" .
               "Cordiali saluti";
    }

    /**
     * Get default institutional notification body
     */
    protected function getDefaultInstitutionalBody(): string
    {
        return "Nuova assegnazione torneo:\n\n" .
               "Torneo: {{tournament_name}}\n" .
               "Categoria: {{tournament_category}}\n" .
               "Date: {{tournament_dates}}\n" .
               "Circolo: {{club_name}}\n" .
               "Zona: {{zone_name}}\n" .
               "Arbitro: {{referee_name}} ({{referee_level}})\n" .
               "Ruolo: {{role}}";
    }
}
