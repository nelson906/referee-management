<?php
// File: app/Services/NotificationService.php - ESTENSIONE DEL SERVICE ESISTENTE

namespace App\Services;

use App\Models\Assignment;
use App\Models\Notification;
use App\Models\InstitutionalEmail;
use App\Models\LetterTemplate;
use App\Models\Tournament;
use App\Models\User;
use App\Mail\AssignmentNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
    public function __construct(?DocumentGenerationService $documentService = null)
    {
        $this->documentService = $documentService;
    }

    // ========================================
    // METODI ESISTENTI (MANTENIAMO)
    // ========================================

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

    // ========================================
    // NUOVI METODI PER IL SISTEMA ESTESO
    // ========================================

    /**
     * Send bulk notifications for tournament assignments
     */
    public function sendBulkAssignmentNotifications(Tournament $tournament, array $data): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            // Get template if specified
            $template = null;
            if (!empty($data['template_id'])) {
                $template = LetterTemplate::find($data['template_id']);
            }

            // Get document attachments
            $attachments = [];
            if ($data['attach_documents'] ?? false) {
                $attachments = $this->getDocumentAttachments($tournament);
            }

            // Send to selected referees
            if (!empty($data['recipients'])) {
                $assignments = Assignment::where('tournament_id', $tournament->id)
                    ->whereIn('user_id', $data['recipients'])
                    ->with(['user', 'tournament.club'])
                    ->get();

                foreach ($assignments as $assignment) {
                    try {
                        $this->sendToReferee($assignment, $data, $template, $attachments);
                        $results['sent']++;
                    } catch (\Exception $e) {
                        $results['failed']++;
                        $results['errors'][] = "Errore invio a {$assignment->user->name}: {$e->getMessage()}";
                        Log::error('Failed to send to referee', [
                            'assignment_id' => $assignment->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Send to club
            if ($data['send_to_club'] ?? false) {
                try {
                    $this->sendToClub($tournament, $data, $template, $attachments);
                    $results['sent']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Errore invio al circolo: {$e->getMessage()}";
                }
            }

            // Send to institutional emails
            if (!empty($data['institutional_emails'])) {
                $institutionalResults = $this->sendToInstitutionalEmails($tournament, $data, $template);
                $results['sent'] += $institutionalResults['sent'];
                $results['failed'] += $institutionalResults['failed'];
                $results['errors'] = array_merge($results['errors'], $institutionalResults['errors']);
            }

            // Send to additional emails
            if (!empty($data['additional_emails'])) {
                $additionalResults = $this->sendToAdditionalEmails($tournament, $data, $template);
                $results['sent'] += $additionalResults['sent'];
                $results['failed'] += $additionalResults['failed'];
                $results['errors'] = array_merge($results['errors'], $additionalResults['errors']);
            }

        } catch (\Exception $e) {
            Log::error('Error in bulk notification sending', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        return $results;
    }

    /**
     * Send notification to a specific referee
     */
    protected function sendToReferee(Assignment $assignment, array $data, ?LetterTemplate $template, array $attachments = []): void
    {
        // Filter attachments for referees (only convocation)
        $refereeAttachments = [];
        if (isset($attachments['convocation'])) {
            $refereeAttachments['convocation'] = $attachments['convocation'];
        }

        $notification = Notification::create([
            'assignment_id' => $assignment->id,
            'recipient_type' => 'referee',
            'recipient_email' => $assignment->user->email,
            'subject' => $data['subject'],
            'body' => $this->replaceVariables($data['message'], $assignment),
            'template_used' => $template->name ?? null,
            'status' => 'pending',
            'attachments' => $refereeAttachments
        ]);

        try {
            Mail::to($assignment->user->email)
                ->send(new AssignmentNotification(
                    $assignment,
                    $notification,
                    $refereeAttachments,
                    $assignment->user->name,
                    false
                ));

            $notification->markAsSent();
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Send notification to club
     */
    protected function sendToClub(Tournament $tournament, array $data, ?LetterTemplate $template, array $attachments = []): void
    {
        $clubEmail = $tournament->club->email;

        if (!$clubEmail) {
            throw new \Exception('Circolo senza email configurata');
        }

        // Filter attachments for club (only club letter)
        $clubAttachments = [];
        if (isset($attachments['club_letter'])) {
            $clubAttachments['club_letter'] = $attachments['club_letter'];
        }

        // Create a mock assignment for club notification
        $mockAssignment = new Assignment([
            'tournament_id' => $tournament->id,
            'role' => 'Club Notification',
        ]);
        $mockAssignment->tournament = $tournament;

        $notification = Notification::create([
            'assignment_id' => null,
            'recipient_type' => 'club',
            'recipient_email' => $clubEmail,
            'subject' => $data['subject'],
            'body' => $this->replaceVariables($data['message'], $mockAssignment),
            'template_used' => $template->name ?? null,
            'status' => 'pending',
            'attachments' => $clubAttachments
        ]);

        try {
            Mail::to($clubEmail)
                ->send(new AssignmentNotification(
                    $mockAssignment,
                    $notification,
                    $clubAttachments,
                    $tournament->club->name,
                    true
                ));

            $notification->markAsSent();
        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Send to institutional emails
     */
    protected function sendToInstitutionalEmails(Tournament $tournament, array $data, ?LetterTemplate $template): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        $institutionalEmails = InstitutionalEmail::whereIn('id', $data['institutional_emails'])
            ->where('is_active', true)
            ->get();

        foreach ($institutionalEmails as $email) {
            try {
                $mockAssignment = new Assignment([
                    'tournament_id' => $tournament->id,
                    'role' => 'Institutional',
                ]);
                $mockAssignment->tournament = $tournament;

                $notification = Notification::create([
                    'assignment_id' => null,
                    'recipient_type' => 'institutional',
                    'recipient_email' => $email->email,
                    'subject' => $data['subject'],
                    'body' => $this->replaceVariables($data['message'], $mockAssignment),
                    'template_used' => $template->name ?? null,
                    'status' => 'pending'
                ]);

                Mail::to($email->email)
                    ->send(new AssignmentNotification(
                        $mockAssignment,
                        $notification,
                        [],
                        $email->name,
                        false
                    ));

                $notification->markAsSent();
                $results['sent']++;

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Errore invio a {$email->name}: {$e->getMessage()}";
                Log::error('Failed to send to institutional email', [
                    'email' => $email->email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Send to additional emails
     */
    protected function sendToAdditionalEmails(Tournament $tournament, array $data, ?LetterTemplate $template): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        foreach ($data['additional_emails'] as $index => $email) {
            if (empty($email)) continue;

            try {
                $name = $data['additional_names'][$index] ?? 'Destinatario';

                $mockAssignment = new Assignment([
                    'tournament_id' => $tournament->id,
                    'role' => 'Additional',
                ]);
                $mockAssignment->tournament = $tournament;

                $notification = Notification::create([
                    'assignment_id' => null,
                    'recipient_type' => 'institutional',
                    'recipient_email' => $email,
                    'subject' => $data['subject'],
                    'body' => $this->replaceVariables($data['message'], $mockAssignment),
                    'template_used' => $template->name ?? null,
                    'status' => 'pending'
                ]);

                Mail::to($email)
                    ->send(new AssignmentNotification(
                        $mockAssignment,
                        $notification,
                        [],
                        $name,
                        false
                    ));

                $notification->markAsSent();
                $results['sent']++;

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Errore invio a {$email}: {$e->getMessage()}";
                Log::error('Failed to send to additional email', [
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Get document attachments for notifications
     */
    protected function getDocumentAttachments(Tournament $tournament): array
    {
        $attachments = [];

        // Convocazione (for referees)
        if ($tournament->convocation_file_path && Storage::exists($tournament->convocation_file_path)) {
            $attachments['convocation'] = Storage::path($tournament->convocation_file_path);
        }

        // Club letter (for club)
        if ($tournament->club_letter_file_path && Storage::exists($tournament->club_letter_file_path)) {
            $attachments['club_letter'] = Storage::path($tournament->club_letter_file_path);
        }

        return $attachments;
    }

    /**
     * Process notification queue (for failed notifications)
     */
    public function processNotification(Notification $notification): void
    {
        if (!$notification->canBeRetried()) {
            throw new \Exception('Notification cannot be retried');
        }

        try {
            // Recreate the assignment and send again
            if ($notification->assignment) {
                $assignment = $notification->assignment;
                $data = [
                    'subject' => $notification->subject,
                    'message' => $notification->body
                ];

                if ($notification->recipient_type === 'referee') {
                    $this->sendToReferee($assignment, $data, null, $notification->attachments ?? []);
                } elseif ($notification->recipient_type === 'club') {
                    $this->sendToClub($assignment->tournament, $data, null, $notification->attachments ?? []);
                }
            } else {
                // For institutional/additional emails, create a simple mail
                $mockAssignment = new Assignment(['tournament_id' => 1]);
                Mail::to($notification->recipient_email)
                    ->send(new AssignmentNotification($mockAssignment, $notification));

                $notification->markAsSent();
            }

        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Get statistics for notifications
     */
    public function getNotificationStatistics(int $days = 30): array
    {
        return Notification::getStatistics($days);
    }

    /**
     * Replace variables in message content
     */
    protected function replaceVariables(string $text, $assignment): string
    {
        if (!$assignment || !$assignment->tournament) {
            return $text;
        }

        $tournament = $assignment->tournament;
        $club = $tournament->club ?? null;
        $user = $assignment->user ?? null;

        $variables = [
            '{{tournament_name}}' => $tournament->name ?? '',
            '{{tournament_dates}}' => $tournament->start_date
                ? $tournament->start_date->format('d/m/Y') .
                  (!$tournament->start_date->isSameDay($tournament->end_date)
                    ? ' - ' . $tournament->end_date->format('d/m/Y')
                    : '')
                : '',
            '{{club_name}}' => $club->name ?? '',
            '{{club_address}}' => $club->address ?? '',
            '{{club_phone}}' => $club->phone ?? '',
            '{{club_email}}' => $club->email ?? '',
            '{{zone_name}}' => $club->zone->name ?? '',
            '{{tournament_category}}' => $tournament->tournamentType->name ?? '',
            '{{referee_name}}' => $user->name ?? '',
            '{{referee_email}}' => $user->email ?? '',
            '{{referee_phone}}' => $user->phone ?? '',
            '{{assignment_role}}' => $assignment->role ?? '',
            '{{assigned_date}}' => $assignment->assigned_at
                ? $assignment->assigned_at->format('d/m/Y')
                : Carbon::now()->format('d/m/Y'),
        ];

        foreach ($variables as $key => $value) {
            $text = str_replace($key, $value, $text);
        }

        return $text;
    }

    // ========================================
    // METODI ESISTENTI (COMPATIBILITÀ)
    // ========================================

    /**
     * Send notification to referee (existing method - updated)
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
            'club_address' => $tournament->club->full_address ?? $tournament->club->address,
            'tournament_category' => $tournament->tournamentType->name, // ← FIXED: tournamentType
            'role' => $assignment->role,
            'assigned_date' => Carbon::now()->format('d/m/Y'),
        ];

        // Replace variables in template
        $subject = $this->replaceVariables($template->subject ?? 'Assegnazione Torneo', $assignment);
        $body = $this->replaceVariables($template->body ?? $this->getDefaultRefereeBody(), $assignment);

        // Generate convocation document
        $convocationPath = null;
        if ($this->documentService) {
            $convocationPath = $this->documentService->generateConvocationLetter($assignment);
        }

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
     * Send notification to club (existing method - updated)
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
            'referee_level' => ucfirst($assignment->user->level ?? ''),
            'referee_code' => $assignment->user->referee_code ?? '',
            'contact_person' => $club->contact_person ?? '',
            'club_address' => $club->full_address ?? $club->address,
            'club_phone' => $club->phone ?? '',
            'club_email' => $club->email,
        ];

        // Replace variables
        $subject = $this->replaceVariables($template->subject ?? 'Arbitro Assegnato', $assignment);
        $body = $this->replaceVariables($template->body ?? $this->getDefaultClubBody(), $assignment);

        // Generate club letter if needed
        $clubLetterPath = null;
        $tournament = $assignment->tournament;

        // ✅ FIXED: tournamentType instead of tournamentCategory
        if ($tournament->assignments()->count() >= $tournament->tournamentType->min_referees) {
            if ($this->documentService) {
                $clubLetterPath = $this->documentService->generateClubLetter($tournament);
            }
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
     * Send to institutional notifications (existing method)
     */
    protected function sendInstitutionalNotifications(Assignment $assignment): void
    {
        $tournament = $assignment->tournament;

        // Get institutional emails for this zone
        $institutionalEmails = InstitutionalEmail::where('is_active', true)
            ->where(function ($query) use ($tournament) {
                $query->where('zone_id', $tournament->zone_id)
                      ->orWhere('receive_all_notifications', true);
            })
            ->get();

        foreach ($institutionalEmails as $email) {
            // Get template
            $template = $this->getTemplate('institutional', $tournament->zone_id);

            $subject = $this->replaceVariables($template->subject ?? 'Nuova Assegnazione', $assignment);
            $body = $this->replaceVariables($template->body ?? $this->getDefaultInstitutionalBody(), $assignment);

            $notification = Notification::create([
                'assignment_id' => $assignment->id,
                'recipient_type' => 'institutional',
                'recipient_email' => $email->email,
                'subject' => $subject,
                'body' => $body,
                'template_used' => $template->name ?? 'default',
                'status' => 'pending',
            ]);

            $this->sendEmail($notification);
        }
    }

    /**
     * Get template for notification type
     */
    protected function getTemplate(string $type, int $zoneId): ?LetterTemplate
    {
        return LetterTemplate::where('type', $type)
            ->where('is_active', true)
            ->where(function ($query) use ($zoneId) {
                $query->where('zone_id', $zoneId)
                      ->orWhereNull('zone_id');
            })
            ->where('is_default', true)
            ->first();
    }

    /**
     * Send email notification (existing method)
     */
    protected function sendEmail(Notification $notification): void
    {
        try {
            // Use new mailable class
            if ($notification->assignment) {
                Mail::to($notification->recipient_email)
                    ->send(new AssignmentNotification(
                        $notification->assignment,
                        $notification,
                        $notification->attachments ?? [],
                        null,
                        $notification->recipient_type === 'club'
                    ));
            }

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
               "La informiamo che è stato assegnato come {{assignment_role}} per il torneo:\n\n" .
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
        return "Gentile {{club_name}},\n\n" .
               "La informiamo che per il torneo {{tournament_name}} è stato assegnato l'arbitro:\n\n" .
               "Arbitro: {{referee_name}}\n" .
               "Ruolo: {{assignment_role}}\n\n" .
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
               "Arbitro: {{referee_name}}\n" .
               "Ruolo: {{assignment_role}}";
    }
}
