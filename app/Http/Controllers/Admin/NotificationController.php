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
    private function sendToClub(Tournament $tournament, array $data, array &$results)
    {
        if (!$tournament->club->email) {
            $results['errors'][] = "Il circolo {$tournament->club->name} non ha un indirizzo email.";
            return;
        }

        try {
            $this->notificationService->sendClubNotification($tournament, [
                'custom_subject' => $data['subject'],
                'custom_message' => $data['message'],
                'template_id' => $data['template_id'] ?? null,
            ]);

            $results['sent']++;
        } catch (\Exception $e) {
            $results['failed']++;
            $results['errors'][] = "Errore invio a circolo: " . $e->getMessage();
        }
    }

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
}
