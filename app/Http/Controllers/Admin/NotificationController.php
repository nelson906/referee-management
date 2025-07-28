<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\LetterTemplate;
use App\Models\InstitutionalEmail;
use App\Models\Zone;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of notifications.
     */
    public function index()
    {
        $notifications = Notification::with(['assignment.tournament', 'assignment.user'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.notifications.index', compact('notifications'));
    }

/**
 * Display notification statistics.
 */
public function stats(Request $request)
{
    $days = $request->get('days', 30);

    $stats = [
        'total' => Notification::count(),
        'sent' => Notification::where('status', 'sent')->count(),
        'pending' => Notification::where('status', 'pending')->count(),
        'failed' => Notification::where('status', 'failed')->count(),
        'by_type' => Notification::select('recipient_type', DB::raw('count(*) as total'))
            ->groupBy('recipient_type')
            ->pluck('total', 'recipient_type')
            ->toArray(),
    ];

    $dailyStats = Notification::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('sum(case when status = "sent" then 1 else 0 end) as sent'),
            DB::raw('sum(case when status = "failed" then 1 else 0 end) as failed')
        )
        ->where('created_at', '>=', now()->subDays($days))
        ->groupBy('date')
        ->orderBy('date', 'desc')
        ->pluck('sent', 'date')
        ->toArray();

    $topRecipients = Notification::select('recipient_email', DB::raw('count(*) as count'))
        ->groupBy('recipient_email')
        ->orderBy('count', 'desc')
        ->limit(10)
        ->get();

    $templateUsage = Notification::select('template_used', DB::raw('count(*) as count'))
        ->whereNotNull('template_used')
        ->groupBy('template_used')
        ->orderBy('count', 'desc')
        ->limit(10)
        ->get();

    $failedNotifications = Notification::where('status', 'failed')
        ->where('retry_count', '>=', 3)
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get();

    return view('admin.notifications.stats', compact('days', 'stats', 'dailyStats', 'topRecipients', 'templateUsage', 'failedNotifications'));
}

/**
 * Show assignment notification form
 */
public function showAssignmentForm(Tournament $tournament)
{
    // Check authorization
    $this->checkAssignmentFormAuthorization($tournament);
    $assignments = $this->getTournamentAssignments($tournament);
    $templates = \App\Models\LetterTemplate::where('is_active', true)->get();
            $institutionalEmails = \App\Models\InstitutionalEmail::where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get();
            $groupedEmails = $institutionalEmails->groupBy('category');

    // Get assigned referees
$assignedReferees = $tournament->assignedReferees()->get();

    // Check existing documents
    $documentStatus = $this->checkAndSetExistingConvocation($tournament);

    // Per backward compatibility con la vista
    $hasExistingConvocation = $documentStatus['hasConvocation'] || $documentStatus['hasClubLetter'];

    return view('admin.notifications.assignment_form', compact(
        'tournament',
        'assignedReferees',
        'assignments',
        'groupedEmails',
        'documentStatus',
        'hasExistingConvocation',
        'templates',
        'institutionalEmails'
    ));
}
    /**
     * Show the form for sending assignment notifications.
     */
    public function sendAssignmentForm()
    {
        $tournaments = Tournament::with(['club', 'zone'])
            ->where('status', '!=', 'completed')
            ->orderBy('start_date', 'desc')
            ->get();

        $templates = LetterTemplate::active()
            ->ofType('assignment')
            ->orderBy('name')
            ->get();

        $zones = Zone::where('is_active', true)->orderBy('name')->get();

        return view('admin.notifications.send-assignment', compact('tournaments', 'templates', 'zones'));
    }

    /**
     * Send assignment notifications.
     */
    public function sendAssignment(Request $request)
    {
        $validated = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'template_id' => 'nullable|exists:letter_templates,id',
            'recipients' => 'required|array',
            'recipients.*' => 'in:referees,club,institutional,custom',
            'custom_emails' => 'nullable|string',
            'include_attachments' => 'boolean',
        ]);

        $tournament = Tournament::with(['assignments.user', 'club', 'zone'])->findOrFail($validated['tournament_id']);

        try {
            DB::beginTransaction();

            $results = [
                'sent' => 0,
                'failed' => 0,
                'errors' => []
            ];

            // Invio notifiche ai destinatari selezionati
            foreach ($validated['recipients'] as $recipientType) {
                switch ($recipientType) {
                    case 'referees':
                        $this->sendToReferees($tournament, $validated, $results);
                        break;

                    case 'club':
                        $this->sendToClub($tournament, $validated, $results);
                        break;

                    case 'institutional':
                        $this->sendToInstitutional($tournament, $validated, $results);
                        break;

                    case 'custom':
                        $this->sendToCustomEmails($tournament, $validated, $results);
                        break;
                }
            }

            DB::commit();

            $message = "Inviate {$results['sent']} notifiche con successo.";
            if ($results['failed'] > 0) {
                $message .= " {$results['failed']} invii falliti.";
            }

            return redirect()->route('notifications.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Errore nell\'invio delle notifiche: ' . $e->getMessage());
        }
    }
/**
 * Send unified assignment notification
 */
public function sendTournamentAssignment(Request $request, Tournament $tournament)
{
    $validated = $this->validateAssignmentRequest($request);

    // Create notification record
    $notification = $this->createNotificationRecord($validated, $tournament);

    // Get assignments
    $assignments = $this->getTournamentAssignments($tournament);

    // Prepare email data
    $emailData = [
        'tournament' => $tournament,
        'assignments' => $assignments,
        'subject' => $validated['subject'],
        'message' => $validated['message']
    ];

    // Send emails to different recipient types
    $this->sendToRecipients($request, $notification, $emailData);
    $this->sendToAdditionalEmails($request, $emailData);
    $this->sendToinstitutionalEmail($request, $emailData);
    $this->sendToClub($tournament, $emailData);

    return redirect()->back()->with('success', 'Notification sent successfully');
}

/**
 * Send assignment notification with convocation attachment
 */
public function sendAssignmentWithConvocation(Request $request, Tournament $tournament)
{
    $this->checkAssignmentFormAuthorization($tournament);

    $validated = $this->validateAssignmentWithConvocationRequest($request);

    // Get convocation path if needed
    $convocationData = $this->getConvocationData($tournament, $request);

    // Get assignments
    $assignments = $this->getTournamentAssignments($tournament);

    // Prepare email data with attachments
    $emailData = [
        'tournament' => $tournament,
        'assignments' => $assignments,
        'subject' => $validated['subject'],
        'message' => $validated['message'],
        'convocation' => $convocationData
    ];

    // Create notification record with attachment info
    $notification = $this->createNotificationRecord($validated, $tournament);

    // Store attachment info in notification
    if (!empty($convocationData) && is_array($convocationData)) {
        $attachments = [];
        foreach ($convocationData as $attachment) {
            if (isset($attachment['path']) && $attachment['path']) {
                $attachments[$attachment['type']] = [
                    'path' => $attachment['path'],
                    'filename' => $attachment['filename']
                ];
            }
        }
        if (!empty($attachments)) {
            $notification->attachments = $attachments;
            $notification->save();
        }
    }

    // Send emails to different recipient types
    $this->sendToRecipientsWithAttachment($request, $emailData);
    $this->sendToAdditionalEmailsWithAttachment($request, $emailData);
    $this->sendToinstitutionalEmailWithAttachment($request, $emailData);
    $this->sendToClubWithAttachment($tournament, $emailData);

    // Clear session data after successful sending
    session()->forget(['last_convocation_path', 'last_convocation_filename']);

    $message = 'Notifica inviata con successo';

    // Add attachment info to success message
    if (!empty($convocationData) && is_array($convocationData)) {
        $attachmentNames = [];
        foreach ($convocationData as $attachment) {
            if (isset($attachment['path']) && $attachment['path']) {
                $attachmentNames[] = $attachment['type'] === 'convocation' ? 'convocazione' : 'lettera circolo';
            }
        }

        if (!empty($attachmentNames)) {
            $message .= ' con ' . implode(' e ', $attachmentNames) . ' allegata/e.';
        }
    }

    return redirect()->back()->with('success', $message);
}
    /**
     * Display the specified notification.
     */
    public function show(Notification $notification)
    {
        $notification->load(['assignment.tournament', 'assignment.user']);
        return view('admin.notifications.show', compact('notification'));
    }

    /**
     * Remove the specified notification.
     */
    public function destroy(Notification $notification)
    {
        $notification->delete();

        return redirect()->route('notifications.index')
            ->with('success', 'Notifica eliminata con successo.');
    }

    /**
     * Retry sending a failed notification.
     */
    public function retry(Notification $notification)
    {
        if ($notification->status !== 'failed') {
            return redirect()->back()
                ->with('error', 'Solo le notifiche fallite possono essere ritentate.');
        }

        try {
            $this->notificationService->retryNotification($notification);

            return redirect()->back()
                ->with('success', 'Notifica reinviata con successo.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Errore nel reinvio: ' . $e->getMessage());
        }
    }

    /**
     * Send notifications to referees assigned to tournament.
     */
    private function sendToReferees(Tournament $tournament, array $data, array &$results)
    {
        foreach ($tournament->assignments as $assignment) {
            try {
                $this->notificationService->sendAssignmentNotification($assignment, [
                    'custom_subject' => $data['subject'],
                    'custom_message' => $data['message'],
                    'template_id' => $data['template_id'] ?? null,
                    'include_attachments' => $data['include_attachments'] ?? false,
                ]);

                $results['sent']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Errore invio a {$assignment->user->email}: " . $e->getMessage();
            }
        }
    }

    /**
     * Send notification to club.
     */
    // private function sendToClub(Tournament $tournament, array $data, array &$results)
    // {
    //     if (!$tournament->club->email) {
    //         $results['errors'][] = "Il circolo {$tournament->club->name} non ha un indirizzo email.";
    //         return;
    //     }

    //     try {
    //         $this->notificationService->sendClubNotification($tournament, [
    //             'custom_subject' => $data['subject'],
    //             'custom_message' => $data['message'],
    //             'template_id' => $data['template_id'] ?? null,
    //         ]);

    //         $results['sent']++;
    //     } catch (\Exception $e) {
    //         $results['failed']++;
    //         $results['errors'][] = "Errore invio a circolo: " . $e->getMessage();
    //     }
    // }

    /**
     * Send notifications to institutional emails.
     */
    private function sendToInstitutional(Tournament $tournament, array $data, array &$results)
    {
        $institutionalEmails = InstitutionalEmail::active()
            ->forZone($tournament->zone_id)
            ->forNotificationType('assignment')
            ->get();

        foreach ($institutionalEmails as $email) {
            try {
                $this->notificationService->sendInstitutionalNotification($email, $tournament, [
                    'custom_subject' => $data['subject'],
                    'custom_message' => $data['message'],
                    'template_id' => $data['template_id'] ?? null,
                ]);

                $results['sent']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Errore invio a {$email->email}: " . $e->getMessage();
            }
        }
    }

    /**
     * Send notifications to custom email addresses.
     */
    private function sendToCustomEmails(Tournament $tournament, array $data, array &$results)
    {
        if (empty($data['custom_emails'])) {
            return;
        }

        $emails = array_filter(array_map('trim', explode(',', $data['custom_emails'])));

        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results['errors'][] = "Indirizzo email non valido: {$email}";
                continue;
            }

            try {
                $this->notificationService->sendCustomNotification($email, $tournament, [
                    'custom_subject' => $data['subject'],
                    'custom_message' => $data['message'],
                    'template_id' => $data['template_id'] ?? null,
                ]);

                $results['sent']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Errore invio a {$email}: " . $e->getMessage();
            }
        }
    }
    /**
 * Validate assignment request
 */
private function validateAssignmentRequest(Request $request)
{
    return $request->validate([
        'subject' => 'required|string|max:255',
        'message' => 'required|string',
        'recipients' => 'nullable|array',
    ]);
}

/**
 * Validate assignment with convocation request
 */
private function validateAssignmentWithConvocationRequest(Request $request)
{
    return $request->validate([
        'subject' => 'required|string|max:255',
        'message' => 'required|string',
        'recipients' => 'nullable|array',
        'recipients.*' => 'exists:users,id',
        'fixed_addresses' => 'nullable|array',
        'fixed_addresses.*' => 'exists:fixed_addresses,id',
        'additional_emails' => 'nullable|array',
        'additional_emails.*' => 'nullable|email',
        'additional_names' => 'nullable|array',
        'additional_names.*' => 'nullable|string',
        'attach_convocation' => 'boolean'
    ]);
}

/**
 * Check authorization for assignment form access
 */
private function checkAssignmentFormAuthorization(Tournament $tournament)
{
    if (!Auth::user()->hasRole('admin') && !Auth::user()->hasRole('super_admin') && !Auth::user()->hasRole('national_admin')) {
        abort(403, 'Non hai i permessi per inviare notifiche.');
    }

    if (Auth::user()->hasRole('national_admin') && (!$tournament->type || !$tournament->type->is_national)) {
        abort(403, 'Non hai accesso a questo torneo non nazionale.');
    }
}

/**
 * Get convocation data for attachment
 */
private function getConvocationData(Tournament $tournament, Request $request)
{
    $attachments = [];

    if (!$request->has('attach_convocation') || !$request->attach_convocation) {
        return $attachments;
    }

    // 1. CONVOCAZIONE PRINCIPALE
    $convocationData = $this->getMainConvocationData($tournament);
    if ($convocationData['path']) {
        $attachments[] = $convocationData;
    }

    // 2. LETTERA CIRCOLO
    $clubLetterData = $this->getClubLetterData($tournament);
    if ($clubLetterData['path']) {
        $attachments[] = $clubLetterData;
    }

    return $attachments;
}

/**
 * Get main convocation data
 */
private function getMainConvocationData(Tournament $tournament)
{
    $convocationData = ['path' => null, 'filename' => 'convocazione.docx', 'type' => 'convocation'];

    // Check session first (recently generated files)
    if (session()->has('last_convocation_path') && session()->has('last_convocation_filename')) {
        $sessionPath = session('last_convocation_path');
        $sessionFilename = session('last_convocation_filename');

        // Check if the file exists in public storage
        if (Storage::disk('public')->exists($sessionPath)) {
            $convocationData['path'] = Storage::disk('public')->path($sessionPath);
            $convocationData['filename'] = $sessionFilename;
            return $convocationData;
        }
    }

    // Check database first (old system)
    if ($tournament->convocation_file_path && Storage::disk('local')->exists($tournament->convocation_file_path)) {
        $convocationData['path'] = Storage::disk('local')->path($tournament->convocation_file_path);
        $convocationData['filename'] = $tournament->convocation_file_name ?? 'convocazione.docx';
        return $convocationData;
    }

    // Check public disk (new system)
    $tournamentName = preg_replace('/[^A-Za-z0-9\-]/', '_', $tournament->name);
    $tournamentName = substr($tournamentName, 0, 50);
    $expectedFilename = "convocazione_{$tournament->id}_{$tournamentName}.docx";
    $expectedPath = 'convocations/' . $expectedFilename;

    if (Storage::disk('public')->exists($expectedPath)) {
        $convocationData['path'] = Storage::disk('public')->path($expectedPath);
        $convocationData['filename'] = $expectedFilename;
    }

    return $convocationData;
}

/**
 * Get club letter data
 */
private function getClubLetterData(Tournament $tournament)
{
    $clubLetterData = ['path' => null, 'filename' => 'lettera_circolo.docx', 'type' => 'club_letter'];

    Log::info('Debug lettera circolo per torneo: ' . $tournament->id, [
        'club_letter_file_path' => $tournament->club_letter_file_path ?? 'NULL',
        'club_letter_file_name' => $tournament->club_letter_file_name ?? 'NULL'
    ]);

    // Check database field first
    if ($tournament->club_letter_file_path && Storage::disk('public')->exists($tournament->club_letter_file_path)) {
        $clubLetterData['path'] = Storage::disk('public')->path($tournament->club_letter_file_path);
        $clubLetterData['filename'] = $tournament->club_letter_file_name ?? 'lettera_circolo.docx';
        Log::info('Lettera circolo trovata nel database: ' . $clubLetterData['path']);
        return $clubLetterData;
    }

    // Fallback: check for standard naming convention
    $tournamentName = preg_replace('/[^A-Za-z0-9\-]/', '_', $tournament->name);
    $tournamentName = substr($tournamentName, 0, 50);
    $expectedFilename = "lettera_circolo_{$tournament->id}_{$tournamentName}.docx";
    $expectedPath = 'club_letters/' . $expectedFilename;

    if (Storage::disk('public')->exists($expectedPath)) {
        $clubLetterData['path'] = Storage::disk('public')->path($expectedPath);
        $clubLetterData['filename'] = $expectedFilename;
        Log::info('Lettera circolo trovata con naming convention: ' . $clubLetterData['path']);
    } else {
        Log::info('Lettera circolo non trovata: ' . $expectedPath);
    }

    return $clubLetterData;
}
/**
 * Unified method to create and send email with multiple attachments
 */
private function createAndSendEmail(array $emailData, string $recipientEmail, ?string $recipientName = null, bool $isClub = false)
{
    $mail = new UnifiedAssignmentNotification(
        $emailData['tournament'],
        $emailData['assignments'],
        $emailData['subject'],
        $emailData['message'],
        $recipientName,
        $isClub
    );

    // Add multiple attachments if provided
    if (isset($emailData['convocation']) && is_array($emailData['convocation'])) {
        foreach ($emailData['convocation'] as $attachment) {
            if (isset($attachment['path']) && $attachment['path'] && file_exists($attachment['path'])) {

                // **CONTROLLO SPECIFICO: La lettera circolo va solo al circolo**
                if ($attachment['type'] === 'club_letter' && !$isClub) {
                    // Salta l'allegato lettera circolo se non è il circolo destinatario
                    Log::info('Lettera circolo non allegata - destinatario non è il circolo', [
                        'recipient' => $recipientEmail,
                        'is_club' => $isClub
                    ]);
                    continue;
                }

                $mail->attach($attachment['path'], [
                    'as' => $attachment['filename'],
                    'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ]);

                Log::info('Allegato aggiunto all\'email', [
                    'recipient' => $recipientEmail,
                    'attachment_type' => $attachment['type'],
                    'attachment_filename' => $attachment['filename']
                ]);
            }
        }
    }

    // Send the email
    try {
        Mail::to($recipientEmail)->send($mail);
        Log::info('Email inviata con successo', [
            'recipient' => $recipientEmail,
            'subject' => $emailData['subject']
        ]);
    } catch (\Exception $e) {
        Log::error('Errore invio email', [
            'recipient' => $recipientEmail,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

/**
 * Send emails to selected recipients
 */
private function sendToRecipients(Request $request, Notification $notification, array $emailData)
{
    if (empty($request->recipients)) return;

    $notification->recipients()->attach($request->recipients);

    foreach ($notification->recipients as $recipient) {
        $this->createAndSendEmail($emailData, $recipient->email, $recipient->name);
    }
}

/**
 * Send emails to additional email addresses
 */
private function sendToAdditionalEmails(Request $request, array $emailData)
{
    if (empty($request->additional_emails)) return;

    foreach ($request->additional_emails as $index => $email) {
        if (!empty($email)) {
            $name = $request->additional_names[$index] ?? null;
            $this->createAndSendEmail($emailData, $email, $name);
        }
    }
}

/**
 * Send emails to fixed addresses
 */
private function sendToinstitutionalEmail(Request $request, array $emailData)
{
    if (empty($request->fixed_addresses)) return;

    $institutionalEmail = FixedAddress::whereIn('id', $request->fixed_addresses)->get();
    foreach ($institutionalEmail as $address) {
        $this->createAndSendEmail($emailData, $address->email, $address->name);
    }
}

/**
 * Send email to club
 */
private function sendToClub(Tournament $tournament, array $emailData)
{
    $clubEmail = $this->getClubEmail($tournament->club);
    if ($clubEmail) {
        $this->createAndSendEmail($emailData, $clubEmail, $tournament->club->name, true);
    }
}

// VERSIONI "WithAttachment" per invio con allegati
private function sendToRecipientsWithAttachment(Request $request, array $emailData)
{
    if (empty($request->recipients)) return;

    $users = User::whereIn('id', $request->recipients)->get();
    foreach ($users as $user) {
        $this->createAndSendEmail($emailData, $user->email, $user->name);
    }
}

private function sendToAdditionalEmailsWithAttachment(Request $request, array $emailData)
{
    if (empty($request->additional_emails)) return;

    foreach ($request->additional_emails as $index => $email) {
        if (!empty($email)) {
            $name = $request->additional_names[$index] ?? null;
            $this->createAndSendEmail($emailData, $email, $name);
        }
    }
}

private function sendToinstitutionalEmailWithAttachment(Request $request, array $emailData)
{
    if (empty($request->fixed_addresses)) return;

    $institutionalEmail = FixedAddress::whereIn('id', $request->fixed_addresses)->get();
    foreach ($institutionalEmail as $address) {
        $this->createAndSendEmail($emailData, $address->email, $address->name);
    }
}

private function sendToClubWithAttachment(Tournament $tournament, array $emailData)
{
    $clubEmail = $this->getClubEmail($tournament->club);
    if ($clubEmail) {
        $this->createAndSendEmail($emailData, $clubEmail, $tournament->club->name, true);
    }
}

/**
 * Get tournament assignments - FIXED per referee-management
 */
private function getTournamentAssignments(Tournament $tournament)
{
    \Log::info('Debug getTournamentAssignments', [
        'tournament_id' => $tournament->id,
        'tournament_name' => $tournament->name
    ]);

    try {
        // Carica gli assignments step by step per evitare conflitti
        $assignments = Assignment::where('tournament_id', $tournament->id)->get();
        \Log::info('Assignments found', ['count' => $assignments->count()]);

        if ($assignments->isEmpty()) {
            return collect();
        }

        // Carica le relazioni manualmente per evitare problemi con eager loading
        $assignments->load(['referee', 'referee.user', 'referee.zone']);

        \Log::info('Assignments with relations loaded', [
            'count' => $assignments->count()
        ]);

        return $assignments;

    } catch (\Exception $e) {
        \Log::error('Error in getTournamentAssignments', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return collect();
    }
}
/**
 * Extract email from club data
 */
private function getClubEmail($club)
{
    // Check for direct email property
    if (isset($club->email) && filter_var($club->email, FILTER_VALIDATE_EMAIL)) {
        return $club->email;
    }

    // Try to extract from contact_info JSON
    $contactInfo = $club->contact_info;

    // If it's a JSON string, try to decode it
    if (is_string($contactInfo)) {
        try {
            $contactInfo = json_decode($contactInfo, true);
        } catch (\Exception $e) {
            Log::warning("Failed to decode contact_info for club {$club->id}: {$e->getMessage()}");
        }
    }

    // Check if we have an array with email
    if (is_array($contactInfo) && isset($contactInfo['email']) && !empty($contactInfo['email'])) {
        return $contactInfo['email'];
    }

    // As a fallback, look for any key containing 'email'
    if (is_array($contactInfo)) {
        foreach ($contactInfo as $key => $value) {
            if (stripos($key, 'email') !== false && is_string($value) && !empty($value)) {
                return $value;
            }
        }
    }

    return null;
}

/**
 * Create notification record
 */
private function createNotificationRecord(array $validated, Tournament $tournament)
{
    $notification = new Notification([
        'subject' => $validated['subject'],
        'message' => $validated['message'],
        'tournament_id' => $tournament->id,
        'sender_id' => Auth::id(),
    ]);
    $notification->save();

    return $notification;
}

/**
 * Check and set existing convocation files
 */
private function checkAndSetExistingConvocation(Tournament $tournament)
{
    $status = [
        'hasConvocation' => false,
        'hasClubLetter' => false,
        'convocationPath' => null,
        'clubLetterPath' => null
    ];

    // Check convocation
    $convocationData = $this->getMainConvocationData($tournament);
    if ($convocationData['path']) {
        $status['hasConvocation'] = true;
        $status['convocationPath'] = $convocationData['path'];
    }

    // Check club letter
    $clubLetterData = $this->getClubLetterData($tournament);
    if ($clubLetterData['path']) {
        $status['hasClubLetter'] = true;
        $status['clubLetterPath'] = $clubLetterData['path'];
    }

    return $status;
}
}
