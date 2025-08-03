<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\LetterTemplate;
use App\Models\InstitutionalEmail;
use App\Models\TournamentNotification;
use App\Models\Zone;
use App\Models\User;
use App\Services\FileStorageService;
use App\Services\NotificationService;
use App\Services\DocumentGenerationService;
use App\Mail\AssignmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OldNotificationController extends Controller
{
    protected $fileStorage;
    protected $documentService;
    protected $notificationService;

    public function __construct(
        FileStorageService $fileStorage,
        DocumentGenerationService $documentService,
        NotificationService $notificationService
    ) {
        $this->fileStorage = $fileStorage;
        $this->documentService = $documentService;
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

        return view('admin.tournament-notifications.index', compact('notifications'));
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
    // CREA O TROVA LA NOTIFICATION
    $notification = TournamentNotification::firstOrCreate(
        ['tournament_id' => $tournament->id],
        [
            'status' => 'pending',
            'referee_list' => $tournament->assignments->pluck('user.name')->implode(', '),
            'total_recipients' => $tournament->assignments->count() + 1,
            'sent_by' => auth()->id()
        ]
    );

    // Genera documenti se non esistono
    if (empty($notification->attachments)) {
        $pdfPath = $this->documentService->generateConvocationPDF($tournament);
        $docxData = $this->documentService->generateClubDocument($tournament);
        $docxPath = $this->fileStorage->storeInZone($docxData, $tournament, 'docx');

        $notification->update([
            'attachments' => [
                'convocation' => basename($pdfPath),
                'club_letter' => basename($docxPath)
            ]
        ]);
    }

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
        $documentStatus = $this->checkDocumentStatus($tournament);
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
        return redirect()->route('admin.tournament-notifications.index')
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
     * ✅ FIXED: Process email sending to all recipients WITH NAMES
     */
    /**
     * ✅ FIXED: createAndSendEmail() with correct priority mapping
     */
    private function createAndSendEmail(array $emailData, string $recipientEmail, ?string $recipientName = null, bool $isClub = false)
    {
        try {
            $tournament = $emailData['tournament'];
            $assignments = $emailData['assignments'];

            // Find specific assignment for this recipient
            $assignment = null;
            if (!$isClub && $recipientEmail) {
                $assignment = $assignments->first(function ($assignment) use ($recipientEmail) {
                    if ($assignment->user && $assignment->user->email === $recipientEmail) {
                        return true;
                    }
                    if ($assignment->referee && $assignment->referee->user && $assignment->referee->user->email === $recipientEmail) {
                        return true;
                    }
                    return false;
                });
            }

            if (!$assignment) {
                $assignment = $assignments->first();
            }

            // Prepare variables for replacement
            $variables = [
                'tournament_name' => $tournament->name,
                'tournament_date' => $tournament->start_date->format('d/m/Y'),
                'tournament_dates' => $tournament->start_date->format('d/m/Y') .
                    ($tournament->start_date->ne($tournament->end_date) ? ' - ' . $tournament->end_date->format('d/m/Y') : ''),
                'club_name' => $tournament->club->name,
                'club_address' => $tournament->club->address ?? 'Indirizzo non disponibile',
                'zone_name' => $tournament->club->zone->name ?? 'N/A',
                'assigned_date' => now()->format('d/m/Y'),
                'tournament_category' => $tournament->tournamentType->name ?? 'N/A',
                'referee_name' => $recipientName ?? 'N/A',
                'assignment_role' => $assignment?->role ?? 'N/A',
                'role' => $assignment?->role ?? 'N/A',
            ];

            // Replace variables in subject and message
            $subject = $this->replaceVariables($emailData['subject'], $variables);
            $body = $this->replaceVariables($emailData['message'], $variables);

            // ✅ FIXED: Create notification with correct priority (INTEGER)
            $notification = new Notification([
                'assignment_id' => $assignment?->id,
                'subject' => $subject,
                'body' => $body,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName, // ✅ ADD this field to fillable if missing
                'recipient_type' => $this->getRecipientType($isClub, $recipientName),
                'status' => 'pending',
                'priority' => 10, // ✅ FIXED: Use INTEGER (0=low, 10=normal, 20=high)
                'template_used' => 'custom',
                'retry_count' => 0,
            ]);

            // Handle attachments
            if (isset($emailData['convocation']) && is_array($emailData['convocation'])) {
                $attachments = [];
                foreach ($emailData['convocation'] as $attachment) {
                    if (isset($attachment['path']) && $attachment['path'] && file_exists($attachment['path'])) {

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

            // ✅ Save notification BEFORE sending
            $notification->save();

            // Send email
            $mailVariables = array_merge($variables, [
                'tournament' => $tournament,
                'assignments' => $assignments,
                'recipient_name' => $recipientName,
                'is_club' => $isClub
            ]);

            $mail = new AssignmentNotification($notification, $mailVariables);
            Mail::to($recipientEmail)->send($mail);

            // ✅ Update status to sent
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Email sent successfully', [
                'notification_id' => $notification->id,
                'recipient' => $recipientEmail,
                'recipient_name' => $recipientName,
                'subject' => $subject,
                'assignment_id' => $assignment?->id
            ]);
        } catch (\Exception $e) {
            if (isset($notification) && $notification->exists) {
                $notification->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'retry_count' => ($notification->retry_count ?? 0) + 1
                ]);
            }

            Log::error('Error sending email', [
                'recipient' => $recipientEmail,
                'recipient_name' => $recipientName,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * ✅ ADDED: Priority mapping helper
     */
    private function getPriorityValue(string $priorityString): int
    {
        return match ($priorityString) {
            'low' => 0,
            'normal' => 10,
            'high' => 20,
            default => 10 // default to normal
        };
    }

    /**
     * ✅ Replace variables in text (same logic as NotificationService)
     */
    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }

    /**
     * ✅ Determine recipient type
     */
    private function getRecipientType(bool $isClub, ?string $recipientName): string
    {
        if ($isClub) {
            return 'club';
        }

        // If we have a name, it's likely a referee
        if ($recipientName) {
            return 'referee';
        }

        // Otherwise it's probably institutional
        return 'institutional';
    }

    /**
     * ✅ FIXED: Process email sending with proper variable replacement
     */
    private function processEmailSending(Request $request, array $emailData, Tournament $tournament)
    {
        $totalSent = 0;
        $totalFailed = 0;
        $errors = [];

        try {
            // ✅ 1. Send to referees
            if (!empty($request->recipients)) {
                $users = User::whereIn('id', $request->recipients)->get();
                foreach ($users as $user) {
                    try {
                        $this->createAndSendEmail($emailData, $user->email, $user->name);
                        $totalSent++;
                    } catch (\Exception $e) {
                        $totalFailed++;
                        $errors[] = "Errore invio a {$user->name}: " . $e->getMessage();
                    }
                }
            }

            // ✅ 2. Send to additional emails
            if (!empty($request->additional_emails)) {
                foreach ($request->additional_emails as $index => $email) {
                    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        try {
                            $name = $request->additional_names[$index] ?? explode('@', $email)[0];
                            $this->createAndSendEmail($emailData, $email, $name);
                            $totalSent++;
                        } catch (\Exception $e) {
                            $totalFailed++;
                            $errors[] = "Errore invio a {$email}: " . $e->getMessage();
                        }
                    }
                }
            }

            // ✅ 3. Send to institutional emails (using fixed_addresses)
            if (!empty($request->fixed_addresses)) {
                $institutionalEmails = InstitutionalEmail::whereIn('id', $request->fixed_addresses)->get();
                foreach ($institutionalEmails as $address) {
                    try {
                        $this->createAndSendEmail($emailData, $address->email, $address->name);
                        $totalSent++;
                    } catch (\Exception $e) {
                        $totalFailed++;
                        $errors[] = "Errore invio a {$address->name}: " . $e->getMessage();
                    }
                }
            }

            // ✅ 4. Send to club
            if ($request->has('send_to_club')) {
                $clubEmail = $this->getClubEmail($tournament->club);
                if ($clubEmail) {
                    try {
                        $this->createAndSendEmail($emailData, $clubEmail, $tournament->club->name, true);
                        $totalSent++;
                    } catch (\Exception $e) {
                        $totalFailed++;
                        $errors[] = "Errore invio al circolo: " . $e->getMessage();
                    }
                } else {
                    $errors[] = "Email del circolo non trovata.";
                }
            }

            // ✅ Log and flash messages
            Log::info('Email batch completed', [
                'tournament_id' => $tournament->id,
                'total_sent' => $totalSent,
                'total_failed' => $totalFailed
            ]);

            if ($totalSent > 0) {
                $message = "Inviate {$totalSent} notifiche con successo";
                if ($totalFailed > 0) {
                    $message .= " ({$totalFailed} falliti)";
                }
                session()->flash('success', $message);
            }

            if (!empty($errors)) {
                $errorSummary = implode('; ', array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $errorSummary .= '... e altri ' . (count($errors) - 3) . ' errori.';
                }
                session()->flash('warning', 'Alcuni invii hanno avuto problemi: ' . $errorSummary);
            }
        } catch (\Exception $e) {
            Log::error('Critical error in processEmailSending', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
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

    /**
     * ✅ IMPROVED: Get tournament assignments with better error handling
     */
    private function getTournamentAssignments(Tournament $tournament)
    {
        try {
            Log::info('Getting tournament assignments', [
                'tournament_id' => $tournament->id,
                'tournament_name' => $tournament->name
            ]);

            // ✅ 1. Try to get assignments from database
            $assignments = Assignment::where('tournament_id', $tournament->id)->get();

            if ($assignments->isEmpty()) {
                Log::warning('No assignments found for tournament', [
                    'tournament_id' => $tournament->id
                ]);

                // ✅ 2. Try alternative: get from pivot table (assignedReferees)
                $assignedReferees = $tournament->assignedReferees ?? collect();

                if ($assignedReferees->isNotEmpty()) {
                    Log::info('Found assigned referees via pivot', [
                        'count' => $assignedReferees->count()
                    ]);

                    // Convert pivot data to assignment-like structure
                    $mockAssignments = $assignedReferees->map(function ($referee) use ($tournament) {
                        return (object)[
                            'id' => $referee->pivot->id ?? uniqid(),
                            'tournament_id' => $tournament->id,
                            'user_id' => $referee->id,
                            'role' => $referee->pivot->role ?? 'Arbitro',
                            'user' => $referee,  // ✅ CORRETTO: referee è l'user
                            'assigned_at' => $referee->pivot->assigned_at ?? now(),
                            'tournament' => $tournament
                        ];
                    });

                    return $mockAssignments;
                }

                // ✅ 3. Final fallback: return empty but log it
                Log::warning('No assignments or assigned referees found', [
                    'tournament_id' => $tournament->id,
                    'tournament_name' => $tournament->name
                ]);

                return collect();
            }

            // ✅ 4. CORREZIONE CRITICA: Load relations corrette
            $assignments->load(['user', 'assignedBy', 'tournament']);
            // ❌ ELIMINATA: ['referee', 'referee.user', 'user'] - CAUSAVA L'ERRORE!

            Log::info('Assignments loaded successfully', [
                'count' => $assignments->count(),
                'tournament_id' => $tournament->id
            ]);

            return $assignments;
        } catch (\Exception $e) {
            Log::error('Error in getTournamentAssignments', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty collection on error
            return collect();
        }
    }

    /**
     * ✅ IMPROVED: Get referee name with better fallback logic
     */
    private function getRefereeName($assignment): string
    {
        // Handle object vs array structure
        if (is_object($assignment)) {
            // New structure: referee->user
            if ($assignment->referee && $assignment->referee->user) {
                return $assignment->referee->user->name;
            }

            // Old structure: user directly
            if ($assignment->user) {
                return $assignment->user->name;
            }

            // Mock assignment structure (from pivot)
            if (isset($assignment->user) && $assignment->user) {
                return $assignment->user->name;
            }
        }

        // Array structure (from pivot)
        if (is_array($assignment)) {
            if (isset($assignment['user']['name'])) {
                return $assignment['user']['name'];
            }
            if (isset($assignment['name'])) {
                return $assignment['name'];
            }
        }

        return 'Arbitro non specificato';
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

    private function generateDocumentsIfNeeded(Tournament $tournament)
    {
        try {
            // Genera sempre entrambi i documenti
            $this->documentService->generateConvocationPDF($tournament);

            $docxData = $this->documentService->generateClubDocument($tournament);
            $this->fileStorage->storeInZone($docxData, $tournament, 'docx');
        } catch (\Exception $e) {
            Log::error('Errore generazione documenti: ' . $e->getMessage());
        }
    }

    private function checkDocumentStatus(Tournament $tournament)
    {
        $zone = $this->fileStorage->getZoneFolder($tournament);
        $basePath = "convocazioni/{$zone}/generated/";

        // Lista tutti i file
        $files = Storage::disk('public')->files($basePath);

        Log::info('Checking documents for tournament: ' . $tournament->name);
        Log::info('Zone: ' . $zone);
        Log::info('Base path: ' . $basePath);
        Log::info('Files found: ', $files);

        $hasConvocation = false;
        $hasClubLetter = false;

        foreach ($files as $file) {
            $filename = basename($file);
            Log::info('Checking file: ' . $filename);

            if (
                str_contains(strtolower($filename), 'convocazione') &&
                str_ends_with($filename, '.pdf')
            ) {
                $hasConvocation = true;
                Log::info('Found convocation: ' . $filename);
            }

            if (
                str_contains(strtolower($filename), 'facsimile') &&
                str_ends_with($filename, '.docx')
            ) {
                $hasClubLetter = true;
                Log::info('Found club letter: ' . $filename);
            }
        }

        $status = [
            'hasConvocation' => $hasConvocation,
            'hasClubLetter' => $hasClubLetter
        ];

        Log::info('Document status:', $status);

        return $status;
    }
}
