<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\LetterTemplate;
use App\Models\InstitutionalEmail;
use App\Models\Zone;
use App\Models\User;
use App\Services\NotificationService;
use App\Mail\AssignmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * ✅ INDEX: Lista notifiche inviate (FIXED)
     */
    public function index(Request $request)
    {
        $query = Notification::with(['assignment.tournament', 'assignment.user'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('recipient_type')) {
            $query->where('recipient_type', $request->recipient_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('period')) {
            switch ($request->period) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'week':
                    $query->where('created_at', '>=', now()->subWeek());
                    break;
                case 'month':
                    $query->where('created_at', '>=', now()->subMonth());
                    break;
            }
        }

        $notifications = $query->paginate(20);

        return view('admin.notifications.index', compact('notifications'));
    }

    /**
     * ✅ STATS: Statistiche notifiche
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
            ->get();

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
     * ✅ ASSIGNMENT FORM: Mostra form per inviare notifiche da torneo specifico
     */
    public function showAssignmentForm(Tournament $tournament)
    {
        $this->checkAssignmentFormAuthorization($tournament);

        $assignments = $this->getTournamentAssignments($tournament);
        $templates = LetterTemplate::where('is_active', true)->get();
        $institutionalEmails = InstitutionalEmail::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();
        $groupedEmails = $institutionalEmails->groupBy('category');

        // Get assigned referees
        $assignedReferees = $tournament->assignedReferees()->get();

        // Check existing documents
        $documentStatus = $this->checkAndSetExistingConvocation($tournament);
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
     * ✅ SEND ASSIGNMENT: Invia notifiche senza allegati
     */
    public function sendTournamentAssignment(Request $request, Tournament $tournament)
    {
        $validated = $this->validateAssignmentRequest($request);

        try {
            DB::beginTransaction();

            $assignments = $this->getTournamentAssignments($tournament);
            $emailData = [
                'tournament' => $tournament,
                'assignments' => $assignments,
                'subject' => $validated['subject'],
                'message' => $validated['message']
            ];

            $this->processEmailSending($request, $emailData, $tournament);

            DB::commit();
            return redirect()->back()->with('success', 'Notifiche inviate con successo');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore invio notifiche: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Errore nell\'invio delle notifiche: ' . $e->getMessage());
        }
    }

    /**
     * ✅ SEND WITH ATTACHMENTS: Invia notifiche con allegati
     */
    public function sendAssignmentWithConvocation(Request $request, Tournament $tournament)
    {
        $this->checkAssignmentFormAuthorization($tournament);
        $validated = $this->validateAssignmentWithConvocationRequest($request);

        try {
            DB::beginTransaction();

            // Get convocation data
            $convocationData = $this->getConvocationData($tournament, $request);
            $assignments = $this->getTournamentAssignments($tournament);

            $emailData = [
                'tournament' => $tournament,
                'assignments' => $assignments,
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'convocation' => $convocationData
            ];

            $this->processEmailSending($request, $emailData, $tournament);

            DB::commit();

            $message = 'Notifiche inviate con successo';
            if (!empty($convocationData)) {
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

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore invio notifiche con allegati: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Errore nell\'invio delle notifiche: ' . $e->getMessage());
        }
    }

    /**
     * ✅ SHOW: Dettaglio notifica
     */
    public function show(Notification $notification)
    {
        $notification->load(['assignment.tournament', 'assignment.user']);
        return view('admin.notifications.show', compact('notification'));
    }

    /**
     * ✅ DESTROY: Elimina notifica
     */
    public function destroy(Notification $notification)
    {
        $notification->delete();
        return redirect()->route('admin.notifications.index')
            ->with('success', 'Notifica eliminata con successo.');
    }

    /**
     * ✅ RETRY: Reinvia notifica fallita
     */
    public function retry(Notification $notification)
    {
        if ($notification->status !== 'failed') {
            return redirect()->back()
                ->with('error', 'Solo le notifiche fallite possono essere ritentate.');
        }

        try {
            $this->notificationService->retryNotification($notification);
            return redirect()->back()->with('success', 'Notifica reinviata con successo.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Errore nel reinvio: ' . $e->getMessage());
        }
    }

    // =================================================================
    // 🔧 PRIVATE METHODS
    // =================================================================

    /**
     * Process email sending to all recipients
     */
    private function processEmailSending(Request $request, array $emailData, Tournament $tournament)
    {
        // Send to referees
        if (!empty($request->recipients)) {
            $users = User::whereIn('id', $request->recipients)->get();
            foreach ($users as $user) {
                $this->createAndSendEmail($emailData, $user->email, $user->name);
            }
        }

        // Send to additional emails
        if (!empty($request->additional_emails)) {
            foreach ($request->additional_emails as $index => $email) {
                if (!empty($email)) {
                    $name = $request->additional_names[$index] ?? null;
                    $this->createAndSendEmail($emailData, $email, $name);
                }
            }
        }

        // Send to institutional emails
        if (!empty($request->fixed_addresses)) {
            $institutionalEmails = InstitutionalEmail::whereIn('id', $request->fixed_addresses)->get();
            foreach ($institutionalEmails as $address) {
                $this->createAndSendEmail($emailData, $address->email, $address->name);
            }
        }

        // Send to club
        if ($request->has('send_to_club')) {
            $clubEmail = $this->getClubEmail($tournament->club);
            if ($clubEmail) {
                $this->createAndSendEmail($emailData, $clubEmail, $tournament->club->name, true);
            }
        }
    }

    /**
     * Create and send unified email
     */
    private function createAndSendEmail(array $emailData, string $recipientEmail, ?string $recipientName = null, bool $isClub = false)
    {
        try {
            // Create notification record
            $notification = new Notification([
                'subject' => $emailData['subject'],
                'body' => $this->formatEmailBody($emailData),
                'recipient_email' => $recipientEmail,
                'recipient_type' => $isClub ? 'club' : 'referee',
                'status' => 'pending'
            ]);

            // Add tournament info if available
            if (isset($emailData['tournament'])) {
                $assignment = $emailData['assignments']->first();
                if ($assignment) {
                    $notification->assignment_id = $assignment->id;
                }
            }

            // Handle attachments
            if (isset($emailData['convocation']) && is_array($emailData['convocation'])) {
                $attachments = [];
                foreach ($emailData['convocation'] as $attachment) {
                    if (isset($attachment['path']) && $attachment['path'] && file_exists($attachment['path'])) {

                        // Club letter only for clubs
                        if ($attachment['type'] === 'club_letter' && !$isClub) {
                            continue;
                        }

                        $storagePath = 'mail_attachments/' . uniqid() . '_' . $attachment['filename'];
                        Storage::disk('local')->put($storagePath, file_get_contents($attachment['path']));
                        $attachments[$attachment['filename']] = $storagePath;
                    }
                }

                if (!empty($attachments)) {
                    $notification->attachments = $attachments;
                }
            }

            $notification->save();

            // Send email
            $variables = [
                'tournament' => $emailData['tournament'],
                'assignments' => $emailData['assignments'],
                'recipient_name' => $recipientName,
                'is_club' => $isClub
            ];

            $mail = new AssignmentNotification($notification, $variables);
            Mail::to($recipientEmail)->send($mail);

            $notification->update(['status' => 'sent', 'sent_at' => now()]);

        } catch (\Exception $e) {
            if (isset($notification) && $notification->exists) {
                $notification->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            throw $e;
        }
    }

    // [Altri metodi helper rimangono identici...]
    private function validateAssignmentRequest(Request $request)
    {
        return $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'recipients' => 'nullable|array',
        ]);
    }

    private function validateAssignmentWithConvocationRequest(Request $request)
    {
        return $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'recipients' => 'nullable|array',
            'recipients.*' => 'exists:users,id',
            'fixed_addresses' => 'nullable|array',
            'fixed_addresses.*' => 'exists:institutional_emails,id',
            'additional_emails' => 'nullable|array',
            'additional_emails.*' => 'nullable|email',
            'additional_names' => 'nullable|array',
            'additional_names.*' => 'nullable|string',
            'attach_convocation' => 'boolean'
        ]);
    }

    private function checkAssignmentFormAuthorization(Tournament $tournament)
    {
        if (!Auth::user()->hasRole('admin') && !Auth::user()->hasRole('super_admin') && !Auth::user()->hasRole('national_admin')) {
            abort(403, 'Non hai i permessi per inviare notifiche.');
        }

        if (Auth::user()->hasRole('national_admin') && (!$tournament->tournamentType || !$tournament->tournamentType->is_national)) {
            abort(403, 'Non hai accesso a questo torneo non nazionale.');
        }
    }

    private function getTournamentAssignments(Tournament $tournament)
    {
        try {
            $assignments = Assignment::where('tournament_id', $tournament->id)->get();

            if ($assignments->isEmpty()) {
                return collect();
            }

            $assignments->load(['referee', 'referee.user', 'user']);
            return $assignments;

        } catch (\Exception $e) {
            Log::error('Error in getTournamentAssignments', [
                'error' => $e->getMessage(),
                'tournament_id' => $tournament->id
            ]);
            return collect();
        }
    }

    private function formatEmailBody(array $emailData): string
    {
        $body = $emailData['message'] . "\n\n";

        $body .= "DETTAGLI TORNEO:\n";
        $body .= "Nome: " . $emailData['tournament']->name . "\n";
        $body .= "Data: " . $emailData['tournament']->start_date . "\n";

        if ($emailData['tournament']->club) {
            $body .= "Circolo: " . $emailData['tournament']->club->name . "\n";
        }

        if (!empty($emailData['assignments']) && $emailData['assignments']->count() > 0) {
            $body .= "\nASSEGNAZIONI:\n";
            foreach ($emailData['assignments'] as $assignment) {
                $refereeName = $this->getRefereeName($assignment);
                $body .= "- " . $refereeName . " (" . $assignment->role . ")\n";
            }
        }

        return $body;
    }

    private function getRefereeName($assignment): string
    {
        if ($assignment->referee && $assignment->referee->user) {
            return $assignment->referee->user->name;
        }

        if ($assignment->user) {
            return $assignment->user->name;
        }

        return 'Arbitro non specificato';
    }

    private function getClubEmail($club)
    {
        if (isset($club->email) && filter_var($club->email, FILTER_VALIDATE_EMAIL)) {
            return $club->email;
        }

        $contactInfo = $club->contact_info;
        if (is_string($contactInfo)) {
            try {
                $contactInfo = json_decode($contactInfo, true);
            } catch (\Exception $e) {
                Log::warning("Failed to decode contact_info for club {$club->id}");
            }
        }

        if (is_array($contactInfo) && isset($contactInfo['email']) && !empty($contactInfo['email'])) {
            return $contactInfo['email'];
        }

        return null;
    }

    private function getConvocationData(Tournament $tournament, Request $request)
    {
        $attachments = [];

        if (!$request->has('attach_convocation') || !$request->attach_convocation) {
            return $attachments;
        }

        // Main convocation
        $convocationData = $this->getMainConvocationData($tournament);
        if ($convocationData['path']) {
            $attachments[] = $convocationData;
        }

        // Club letter
        $clubLetterData = $this->getClubLetterData($tournament);
        if ($clubLetterData['path']) {
            $attachments[] = $clubLetterData;
        }

        return $attachments;
    }

    private function getMainConvocationData(Tournament $tournament)
    {
        $convocationData = ['path' => null, 'filename' => 'convocazione.docx', 'type' => 'convocation'];

        // Check session first
        if (session()->has('last_convocation_path') && session()->has('last_convocation_filename')) {
            $sessionPath = session('last_convocation_path');
            $sessionFilename = session('last_convocation_filename');

            if (Storage::disk('public')->exists($sessionPath)) {
                $convocationData['path'] = Storage::disk('public')->path($sessionPath);
                $convocationData['filename'] = $sessionFilename;
                return $convocationData;
            }
        }

        // Check database
        if ($tournament->convocation_file_path && Storage::disk('local')->exists($tournament->convocation_file_path)) {
            $convocationData['path'] = Storage::disk('local')->path($tournament->convocation_file_path);
            $convocationData['filename'] = $tournament->convocation_file_name ?? 'convocazione.docx';
            return $convocationData;
        }

        // Check public disk
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

    private function getClubLetterData(Tournament $tournament)
    {
        $clubLetterData = ['path' => null, 'filename' => 'lettera_circolo.docx', 'type' => 'club_letter'];

        if ($tournament->club_letter_file_path && Storage::disk('public')->exists($tournament->club_letter_file_path)) {
            $clubLetterData['path'] = Storage::disk('public')->path($tournament->club_letter_file_path);
            $clubLetterData['filename'] = $tournament->club_letter_file_name ?? 'lettera_circolo.docx';
            return $clubLetterData;
        }

        // Fallback with naming convention
        $tournamentName = preg_replace('/[^A-Za-z0-9\-]/', '_', $tournament->name);
        $tournamentName = substr($tournamentName, 0, 50);
        $expectedFilename = "lettera_circolo_{$tournament->id}_{$tournamentName}.docx";
        $expectedPath = 'club_letters/' . $expectedFilename;

        if (Storage::disk('public')->exists($expectedPath)) {
            $clubLetterData['path'] = Storage::disk('public')->path($expectedPath);
            $clubLetterData['filename'] = $expectedFilename;
        }

        return $clubLetterData;
    }

    private function checkAndSetExistingConvocation(Tournament $tournament)
    {
        $status = [
            'hasConvocation' => false,
            'hasClubLetter' => false,
            'convocationPath' => null,
            'clubLetterPath' => null
        ];

        $convocationData = $this->getMainConvocationData($tournament);
        if ($convocationData['path']) {
            $status['hasConvocation'] = true;
            $status['convocationPath'] = $convocationData['path'];
        }

        $clubLetterData = $this->getClubLetterData($tournament);
        if ($clubLetterData['path']) {
            $status['hasClubLetter'] = true;
            $status['clubLetterPath'] = $clubLetterData['path'];
        }

        return $status;
    }
}
