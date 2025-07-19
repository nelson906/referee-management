<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Notification;
use App\Models\Tournament;
use App\Models\Assignment;
use App\Models\User;
use App\Models\InstitutionalEmail;
use App\Models\LetterTemplate;
use App\Mail\AssignmentNotification;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of notifications
     */
    public function index()
    {
        $user = Auth::user();

        $query = Notification::with(['assignment.tournament', 'assignment.user'])
            ->orderBy('created_at', 'desc');

        // Zone-based filtering for admins
        if ($user->hasRole('Admin')) {
            $query->whereHas('assignment.tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        $notifications = $query->paginate(20);

        return view('admin.notifications.index', compact('notifications'));
    }

    /**
     * Display the specified notification
     */
    public function show(Notification $notification)
    {
        $user = Auth::user();

        // Authorization check for zone admins
        if ($user->hasRole('Admin')) {
            if ($notification->assignment->tournament->zone_id !== $user->zone_id) {
                abort(403, 'Non hai il permesso di visualizzare questa notifica.');
            }
        }

        $notification->load([
            'assignment.tournament.club.zone',
            'assignment.user'
        ]);

        return view('admin.notifications.show', compact('notification'));
    }

    /**
     * Show assignment notification form
     */
    public function showAssignmentForm(Tournament $tournament)
    {
        $this->checkAssignmentFormAuthorization($tournament);

        // Get assigned referees
        $assignments = Assignment::where('tournament_id', $tournament->id)
            ->with(['user'])
            ->get();

        // Get institutional emails for this zone
        $institutionalEmails = InstitutionalEmail::where('is_active', true)
            ->where(function ($query) use ($tournament) {
                $query->where('zone_id', $tournament->zone_id)
                      ->orWhere('receive_all_notifications', true);
            })
            ->orderBy('category')
            ->get()
            ->groupBy('category');

        // Get available templates
        $templates = LetterTemplate::where('is_active', true)
            ->where('type', 'assignment')
            ->where(function ($query) use ($tournament) {
                $query->where('zone_id', $tournament->zone_id)
                      ->orWhereNull('zone_id');
            })
            ->get();

        // Check for existing documents
        $documentStatus = $this->checkExistingDocuments($tournament);

        return view('admin.notifications.assignment_form', compact(
            'tournament',
            'assignments',
            'institutionalEmails',
            'templates',
            'documentStatus'
        ));
    }

    /**
     * Send assignment notification
     */
    public function sendAssignmentNotification(Request $request, Tournament $tournament)
    {
        $this->checkAssignmentFormAuthorization($tournament);

        $validated = $this->validateAssignmentRequest($request);

        try {
            // Get assignments for this tournament
            $assignments = Assignment::where('tournament_id', $tournament->id)
                ->with(['user', 'tournament.club'])
                ->get();

            if ($assignments->isEmpty()) {
                return redirect()->back()
                    ->with('error', 'Nessun arbitro assegnato a questo torneo.');
            }

            // Use the extended notification service for bulk sending
            $results = $this->notificationService->sendBulkAssignmentNotifications($tournament, $validated);

            if ($results['sent'] > 0) {
                $message = "Notifiche inviate con successo a {$results['sent']} destinatari.";

                if ($results['failed'] > 0) {
                    $message .= " {$results['failed']} invii falliti.";
                }

                return redirect()->back()->with('success', $message);
            } else {
                $errorMessage = 'Nessuna notifica è stata inviata.';
                if (!empty($results['errors'])) {
                    $errorMessage .= ' Errori: ' . implode(', ', array_slice($results['errors'], 0, 3));
                }

                return redirect()->back()->with('error', $errorMessage);
            }

        } catch (\Exception $e) {
            Log::error('Error sending assignment notifications', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Errore durante l\'invio delle notifiche: ' . $e->getMessage());
        }
    }

    /**
     * Resend failed notification
     */
    public function resend(Notification $notification)
    {
        $user = Auth::user();

        // Authorization check
        if ($user->hasRole('Admin')) {
            if ($notification->assignment->tournament->zone_id !== $user->zone_id) {
                abort(403, 'Non hai il permesso di reinviare questa notifica.');
            }
        }

        if (!$notification->canBeRetried()) {
            return redirect()->back()
                ->with('error', 'Questa notifica non può essere reinviata.');
        }

        try {
            $notification->resetForRetry();
            $this->notificationService->processNotification($notification);

            return redirect()->back()
                ->with('success', 'Notifica reinviata con successo.');

        } catch (\Exception $e) {
            Log::error('Error resending notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Errore durante il reinvio: ' . $e->getMessage());
        }
    }

    /**
     * Cancel pending notification
     */
    public function cancel(Notification $notification)
    {
        $user = Auth::user();

        // Authorization check
        if ($user->hasRole('Admin')) {
            if ($notification->assignment->tournament->zone_id !== $user->zone_id) {
                abort(403, 'Non hai il permesso di annullare questa notifica.');
            }
        }

        if ($notification->status !== 'pending') {
            return redirect()->back()
                ->with('error', 'Solo le notifiche in sospeso possono essere annullate.');
        }

        $notification->markAsCancelled();

        return redirect()->back()
            ->with('success', 'Notifica annullata con successo.');
    }

    /**
     * Show notification statistics
     */
    public function stats(Request $request)
    {
        $days = $request->get('days', 30);
        $user = Auth::user();

        // Get basic statistics
        $stats = $this->notificationService->getNotificationStatistics($days);

        // Zone-specific stats for admins
        if ($user->hasRole('Admin')) {
            $query = Notification::whereHas('assignment.tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        } else {
            $query = Notification::query();
        }

        $query->where('created_at', '>=', now()->subDays($days));

        // Daily statistics for chart
        $dailyStats = $query->clone()
            ->selectRaw('DATE(created_at) as date, status, COUNT(*) as count')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($items) {
                return $items->pluck('count', 'status')->toArray();
            });

        // Top recipients
        $topRecipients = $query->clone()
            ->selectRaw('recipient_email, COUNT(*) as count')
            ->groupBy('recipient_email')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Template usage
        $templateUsage = $query->clone()
            ->whereNotNull('template_used')
            ->selectRaw('template_used, COUNT(*) as count')
            ->groupBy('template_used')
            ->orderByDesc('count')
            ->get();

        // Failed notifications
        $failedNotifications = Notification::getFailedNotificationsRequiringAttention();

        return view('admin.notifications.stats', compact(
            'stats',
            'dailyStats',
            'topRecipients',
            'templateUsage',
            'failedNotifications',
            'days'
        ));
    }

    /**
     * Export notifications to CSV
     */
    public function exportCsv(Request $request)
    {
        $user = Auth::user();

        $query = Notification::with(['assignment.tournament', 'assignment.user']);

        // Zone-based filtering for admins
        if ($user->hasRole('Admin')) {
            $query->whereHas('assignment.tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('recipient_type')) {
            $query->where('recipient_type', $request->recipient_type);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $notifications = $query->orderBy('created_at', 'desc')->get();

        // Create CSV content
        $csvData = [];
        $csvData[] = [
            'Data Creazione',
            'Data Invio',
            'Oggetto',
            'Tipo Destinatario',
            'Email Destinatario',
            'Torneo',
            'Arbitro',
            'Stato',
            'Template Usato',
            'Errore',
            'Tentativi'
        ];

        foreach ($notifications as $notification) {
            $csvData[] = [
                $notification->created_at->format('d/m/Y H:i'),
                $notification->sent_at ? $notification->sent_at->format('d/m/Y H:i') : '',
                $notification->subject,
                $notification->recipient_type_label,
                $notification->recipient_email,
                $notification->assignment ? $notification->assignment->tournament->name : '',
                $notification->assignment ? $notification->assignment->user->name : '',
                $notification->status_label,
                $notification->template_used ?? '',
                $notification->error_message ?? '',
                $notification->retry_count
            ];
        }

        // Generate CSV file
        $filename = 'notifiche_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $output = fopen('php://temp', 'w');
        foreach ($csvData as $row) {
            fputcsv($output, $row, ';');
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Length', strlen($csv));
    }

    // =============================================
    // PRIVATE HELPER METHODS
    // =============================================

    /**
     * Check authorization for assignment form access
     */
    private function checkAssignmentFormAuthorization(Tournament $tournament)
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            abort(403, 'Non hai i permessi per inviare notifiche.');
        }

        if ($user->hasRole('Admin') && $tournament->zone_id !== $user->zone_id) {
            abort(403, 'Non hai accesso a questo torneo di altra zona.');
        }
    }

    /**
     * Validate assignment notification request
     */
    private function validateAssignmentRequest(Request $request)
    {
        return $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'template_id' => 'nullable|exists:letter_templates,id',
            'recipients' => 'nullable|array',
            'recipients.*' => 'exists:users,id',
            'institutional_emails' => 'nullable|array',
            'institutional_emails.*' => 'exists:institutional_emails,id',
            'additional_emails' => 'nullable|array',
            'additional_emails.*' => 'nullable|email',
            'additional_names' => 'nullable|array',
            'additional_names.*' => 'nullable|string',
            'send_to_club' => 'boolean',
            'attach_documents' => 'boolean'
        ]);
    }

    /**
     * Send notification to referee
     */
    private function sendToReferee(Assignment $assignment, array $data, ?LetterTemplate $template)
    {
        $attachments = [];
        if ($data['attach_documents'] ?? false) {
            $attachments = $this->getDocumentAttachments($assignment->tournament);
        }

        $notification = Notification::create([
            'assignment_id' => $assignment->id,
            'recipient_type' => 'referee',
            'recipient_email' => $assignment->user->email,
            'subject' => $data['subject'],
            'body' => $data['message'],
            'template_used' => $template->name ?? null,
            'status' => 'pending',
            'attachments' => $attachments
        ]);

        // Send email
        Mail::to($assignment->user->email)
            ->send(new AssignmentNotification($assignment, $notification, $attachments));

        $notification->markAsSent();
    }

    /**
     * Send to institutional emails
     */
    private function sendToInstitutionalEmails(Tournament $tournament, array $data, ?LetterTemplate $template)
    {
        $institutionalEmails = InstitutionalEmail::whereIn('id', $data['institutional_emails'])
            ->get();

        foreach ($institutionalEmails as $email) {
            // Create a mock assignment for institutional notifications
            $mockAssignment = new Assignment([
                'tournament_id' => $tournament->id,
                'role' => 'Institutional',
            ]);
            $mockAssignment->tournament = $tournament;

            $notification = Notification::create([
                'assignment_id' => null, // No specific assignment
                'recipient_type' => 'institutional',
                'recipient_email' => $email->email,
                'subject' => $data['subject'],
                'body' => $data['message'],
                'template_used' => $template->name ?? null,
                'status' => 'pending'
            ]);

            Mail::to($email->email)
                ->send(new AssignmentNotification($mockAssignment, $notification));

            $notification->markAsSent();
        }
    }

    /**
     * Send to additional emails
     */
    private function sendToAdditionalEmails(Tournament $tournament, array $data, ?LetterTemplate $template)
    {
        foreach ($data['additional_emails'] as $index => $email) {
            if (empty($email)) continue;

            $name = $data['additional_names'][$index] ?? null;

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
                'body' => $data['message'],
                'template_used' => $template->name ?? null,
                'status' => 'pending'
            ]);

            Mail::to($email)
                ->send(new AssignmentNotification($mockAssignment, $notification));

            $notification->markAsSent();
        }
    }

    /**
     * Send to club
     */
    private function sendToClub(Tournament $tournament, array $data, ?LetterTemplate $template)
    {
        $clubEmail = $tournament->club->email;

        if (!$clubEmail) {
            Log::warning('Club has no email', ['club_id' => $tournament->club->id]);
            return;
        }

        $mockAssignment = new Assignment([
            'tournament_id' => $tournament->id,
            'role' => 'Club',
        ]);
        $mockAssignment->tournament = $tournament;

        $attachments = [];
        if ($data['attach_documents'] ?? false) {
            $attachments = $this->getClubDocumentAttachments($tournament);
        }

        $notification = Notification::create([
            'assignment_id' => null,
            'recipient_type' => 'club',
            'recipient_email' => $clubEmail,
            'subject' => $data['subject'],
            'body' => $data['message'],
            'template_used' => $template->name ?? null,
            'status' => 'pending',
            'attachments' => $attachments
        ]);

        Mail::to($clubEmail)
            ->send(new AssignmentNotification($mockAssignment, $notification, $attachments));

        $notification->markAsSent();
    }

    /**
     * Check existing documents
     */
    private function checkExistingDocuments(Tournament $tournament): array
    {
        return [
            'hasConvocation' => !empty($tournament->convocation_file_path) &&
                              Storage::exists($tournament->convocation_file_path),
            'hasClubLetter' => !empty($tournament->club_letter_file_path) &&
                              Storage::exists($tournament->club_letter_file_path),
        ];
    }

    /**
     * Get document attachments for referees
     */
    private function getDocumentAttachments(Tournament $tournament): array
    {
        $attachments = [];

        if ($tournament->convocation_file_path && Storage::exists($tournament->convocation_file_path)) {
            $attachments['convocation'] = Storage::path($tournament->convocation_file_path);
        }

        return $attachments;
    }

    /**
     * Get document attachments for club
     */
    private function getClubDocumentAttachments(Tournament $tournament): array
    {
        $attachments = [];

        if ($tournament->club_letter_file_path && Storage::exists($tournament->club_letter_file_path)) {
            $attachments['club_letter'] = Storage::path($tournament->club_letter_file_path);
        }

        return $attachments;
    }
}
