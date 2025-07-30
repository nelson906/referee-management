<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Services\TournamentNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TournamentNotificationController extends Controller
{
    protected $notificationService;

    public function __construct(TournamentNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * 📋 Vista principale - Notifiche raggruppate per torneo
     */
    public function index(Request $request)
    {
        $query = TournamentNotification::with(['tournament.club', 'tournament.zone'])
            ->orderBy('sent_at', 'desc');

        // Filtri
        if ($request->filled('zone_id')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('sent_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sent_at', '<=', $request->date_to);
        }

        $tournamentNotifications = $query->paginate(20);

        // Statistiche per dashboard
        $stats = [
            'total_sent' => TournamentNotification::where('status', 'sent')->sum('total_recipients'),
            'total_failed' => TournamentNotification::where('status', 'failed')->sum('total_recipients'),
            'this_month' => TournamentNotification::whereMonth('sent_at', now()->month)->sum('total_recipients'),
            'pending_tournaments' => Tournament::whereIn('status', ['closed', 'assigned'])->doesntHave('notifications')->count()
        ];

        return view('admin.tournament-notifications.index', compact('tournamentNotifications', 'stats'));
    }

    /**
     * 🎯 Form invio notifiche per torneo specifico
     */
    public function create(Tournament $tournament)
    {
        // Verifica che il torneo abbia assegnazioni
        if ($tournament->assignments->isEmpty()) {
            return redirect()->back()
                ->with('error', 'Il torneo non ha arbitri assegnati. Completare prima le assegnazioni.');
        }

        // Verifica che non sia già stato notificato
        if ($tournament->notifications()->exists()) {
            return redirect()->back()
                ->with('warning', 'Il torneo è già stato notificato. Usare la funzione "Reinvia" se necessario.');
        }

        // Template email disponibili
        $emailTemplates = [
            'tournament_assignment_generic' => 'Template Generico Standard',
            'tournament_assignment_formal' => 'Template Formale Ufficiale',
            'tournament_assignment_urgent' => 'Template Urgente',
            'tournament_assignment_casual' => 'Template Informale'
        ];

        return view('admin.tournament-notifications.create', compact('tournament', 'emailTemplates'));
    }

    /**
     * 📧 Invio unificato di tutte le notifiche del torneo
     */
    public function store(Request $request, Tournament $tournament)
    {
        $validator = Validator::make($request->all(), [
            'email_template' => 'required|string',
            'include_attachments' => 'boolean',
            'send_to_club' => 'boolean',
            'send_to_referees' => 'boolean',
            'send_to_institutional' => 'boolean',
            'custom_message' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            // 🎯 Chiamata service con template email selezionato
            $result = $this->notificationService->sendTournamentNotifications($tournament, [
                'email_template' => $request->email_template,
                'include_attachments' => $request->boolean('include_attachments', true),
                'send_to_club' => $request->boolean('send_to_club', true),
                'send_to_referees' => $request->boolean('send_to_referees', true),
                'send_to_institutional' => $request->boolean('send_to_institutional', true),
                'custom_message' => $request->custom_message,
                'sent_by' => auth()->id()
            ]);

            DB::commit();

            $totalFailed = $result['details']['club']['failed'] + $result['details']['referees']['failed'] + $result['details']['institutional']['failed'];
            $message = "✅ Notifiche torneo '{$tournament->name}' inviate con successo a {$result['total_sent']} destinatari";

            if ($totalFailed > 0) {
                $message .= " ({$totalFailed} falliti)";
            }

            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Tournament notification store error', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Errore nell\'invio delle notifiche: ' . $e->getMessage());
        }
    }

    /**
     * 👁️ Dettagli notifiche torneo con espansione
     */
    public function show(TournamentNotification $tournamentNotification)
    {
        $tournamentNotification->load([
            'tournament.club',
            'tournament.assignments.referee',
            'tournament.assignments.user',
            'tournament.zone',
            'sentBy'
        ]);

        // Recupera le singole notifiche per dettagli
        $individualNotifications = $tournamentNotification->individualNotifications()
            ->with(['assignment.referee', 'assignment.user'])
            ->orderBy('recipient_type')
            ->orderBy('created_at')
            ->get();

        return view('admin.tournament-notifications.show', compact('tournamentNotification', 'individualNotifications'));
    }

    /**
     * 🔄 Reinvio notifiche torneo
     */
    public function resend(TournamentNotification $tournamentNotification)
    {
        if (!$tournamentNotification->canBeResent()) {
            return redirect()->back()
                ->with('error', 'Questo torneo non può essere reinviato al momento.');
        }

        DB::beginTransaction();
        try {
            $result = $this->notificationService->resendTournamentNotifications($tournamentNotification);

            DB::commit();

            $totalFailed = $result['details']['club']['failed'] + $result['details']['referees']['failed'] + $result['details']['institutional']['failed'];
            $message = "Notifiche reinviate: {$result['total_sent']} successi";

            if ($totalFailed > 0) {
                $message .= ", {$totalFailed} fallimenti";
            }

            return redirect()->back()
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Errore nel reinvio: ' . $e->getMessage());
        }
    }

    /**
     * 🗑️ Eliminazione notifica torneo
     */
    public function destroy(TournamentNotification $tournamentNotification)
    {
        DB::beginTransaction();
        try {
            // Elimina anche le notifiche individuali correlate
            $tournamentNotification->individualNotifications()->delete();
            $tournamentNotification->delete();

            DB::commit();

            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', 'Notifiche torneo eliminate con successo.');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Errore nell\'eliminazione: ' . $e->getMessage());
        }
    }

    /**
     * 📊 API per statistiche dashboard
     */
    public function stats(Request $request)
    {
        $stats = [
            'today' => TournamentNotification::whereDate('sent_at', today())->sum('total_recipients'),
            'this_week' => TournamentNotification::whereBetween('sent_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('total_recipients'),
            'this_month' => TournamentNotification::whereMonth('sent_at', now()->month)->sum('total_recipients'),
            'success_rate' => $this->calculateSuccessRate(),
            'pending_tournaments' => Tournament::whereIn('status', ['closed', 'assigned'])->doesntHave('notifications')->count(),
            'failed_today' => TournamentNotification::whereDate('sent_at', today())->where('status', 'failed')->count(),
            'partial_today' => TournamentNotification::whereDate('sent_at', today())->where('status', 'partial')->count()
        ];

        // Statistiche per zona se richiesto
        if ($request->filled('zone_id')) {
            $stats['zone_stats'] = TournamentNotification::forZone($request->zone_id)
                ->selectRaw('status, COUNT(*) as count, SUM(total_recipients) as recipients')
                ->groupBy('status')
                ->get();
        }

        return response()->json($stats);
    }

    /**
     * 📈 Esporta dati notifiche per analisi
     */
    public function export(Request $request)
    {
        $query = TournamentNotification::with(['tournament.club', 'tournament.zone', 'sentBy'])
            ->orderBy('sent_at', 'desc');

        // Applica filtri export
        if ($request->filled('date_from')) {
            $query->whereDate('sent_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sent_at', '<=', $request->date_to);
        }

        if ($request->filled('zone_id')) {
            $query->forZone($request->zone_id);
        }

        $notifications = $query->limit(1000)->get();

        $filename = 'tournament_notifications_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($notifications) {
            $file = fopen('php://output', 'w');

            // Header CSV
            fputcsv($file, [
                'ID',
                'Torneo',
                'Zona',
                'Circolo',
                'Data Invio',
                'Stato',
                'Destinatari Totali',
                'Destinatari Club',
                'Destinatari Arbitri',
                'Destinatari Istituzionali',
                'Fallimenti',
                'Tasso Successo %',
                'Inviato Da',
                'Template Club',
                'Template Arbitri',
                'Template Istituzionali'
            ]);

            // Dati
            foreach ($notifications as $notification) {
                $stats = $notification->stats;
                fputcsv($file, [
                    $notification->id,
                    $notification->tournament->name,
                    $notification->tournament->zone->code ?? 'N/A',
                    $notification->tournament->club->name ?? 'N/A',
                    $notification->sent_at->format('Y-m-d H:i:s'),
                    $notification->status,
                    $notification->total_recipients,
                    $stats['club_sent'],
                    $stats['referees_sent'],
                    $stats['institutional_sent'],
                    $stats['total_failed'],
                    $stats['success_rate'],
                    $notification->sentBy->name ?? 'N/A',
                    $notification->templates_used['club'] ?? 'N/A',
                    $notification->templates_used['referee'] ?? 'N/A',
                    $notification->templates_used['institutional'] ?? 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * 🎯 Calcolo percentuale successo
     */
    private function calculateSuccessRate(): float
    {
        $total = TournamentNotification::sum('total_recipients');
        $failed = TournamentNotification::where('status', 'failed')->sum('total_recipients');

        return $total > 0 ? round((($total - $failed) / $total) * 100, 1) : 0;
    }
}
