<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Services\TournamentNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;

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
            $query->whereHas('tournament', function ($q) use ($request) {
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
        // 🔥 DEBUG: Log inizio
        Log::info('=== INIZIO STORE CONTROLLER ===', [
            'tournament_id' => $tournament->id,
            'request_data' => $request->all(),
            'user_id' => auth()->id()
        ]);

        $validator = Validator::make($request->all(), [
            'email_template' => 'required|string',
            'include_attachments' => 'boolean',
            'send_to_club' => 'boolean',
            'send_to_referees' => 'boolean',
            'send_to_institutional' => 'boolean',
            'custom_message' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', ['errors' => $validator->errors()]);
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // 🔥 DEBUG: Log prima del service
        Log::info('=== CHIAMATA SERVICE ===', [
            'options' => [
                'email_template' => $request->email_template,
                'include_attachments' => $request->boolean('include_attachments', true),
                'send_to_club' => $request->boolean('send_to_club', true),
                'send_to_referees' => $request->boolean('send_to_referees', true),
                'send_to_institutional' => $request->boolean('send_to_institutional', true),
                'sent_by' => auth()->id()
            ]
        ]);

        DB::beginTransaction();
        try {
            $result = $this->notificationService->sendTournamentNotifications($tournament, [
                'email_template' => $request->email_template,
                'include_attachments' => $request->boolean('include_attachments', true),
                'send_to_club' => $request->boolean('send_to_club', true),
                'send_to_referees' => $request->boolean('send_to_referees', true),
                'send_to_institutional' => $request->boolean('send_to_institutional', true),
                'custom_message' => $request->custom_message,
                'sent_by' => auth()->id()
            ]);

            // 🔥 DEBUG: Log risultato service
            Log::info('=== RISULTATO SERVICE ===', ['result' => $result]);

            DB::commit();

            Log::info('=== COMMIT SUCCESSFUL ===');

            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', "✅ Test completato");
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('=== ERRORE CONTROLLER ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->withInput()
                ->with('error', 'Errore: ' . $e->getMessage());
        }
    }

    /**
     * 👁️ Dettagli notifiche torneo con espansione
     */
    public function show(TournamentNotification $notification)
    {
        $tournamentNotification = $notification;  // RINOMINA
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
    // In app/Http/Controllers/Admin/TournamentNotificationController.php

    public function edit(TournamentNotification $notification)
    {
        $tournamentNotification = $notification;
        return view('admin.tournament-notifications.edit', compact('tournamentNotification'));
    }

    public function send(TournamentNotification $notification)
    {
        $tournament = Tournament::with('club')->find($notification->tournament_id);
        $sent = 0;

        // Nel metodo send(), prima di tutto recupera i nomi:
        $refereeNames = DB::table('assignments')
            ->join('users', 'assignments.user_id', '=', 'users.id')
            ->where('assignments.tournament_id', $tournament->id)
            ->pluck('users.name')
            ->implode(', ');

            // Aggiorna il record con i nomi
        $notification->update(['referee_list' => $refereeNames]);

        // 1. INVIA AGLI ARBITRI (codice esistente)
        $assignments = DB::table('assignments')
            ->join('users', 'assignments.user_id', '=', 'users.id')
            ->where('assignments.tournament_id', $tournament->id)
            ->select('assignments.*', 'users.email', 'users.name as user_name')
            ->get();

        foreach ($assignments as $assignment) {
            try {
                Mail::raw(
                    "Gentile {$assignment->user_name},\n\nSei convocato come {$assignment->role} per il torneo:\n{$tournament->name}\n\nDate: {$tournament->start_date->format('d/m/Y')}\nCircolo: {$tournament->club->name}",
                    function ($message) use ($assignment, $tournament) {
                        $message->to($assignment->email)
                            ->subject("Convocazione Torneo: {$tournament->name}")
                            ->from(config('mail.from.address'), 'Federazione Golf');
                    }
                );
                $sent++;
            } catch (\Exception $e) {
                \Log::error("Failed to send: " . $e->getMessage());
            }
        }

        // 2. INVIA AL CIRCOLO (NUOVO!)
        if ($tournament->club && $tournament->club->email) {
            try {
                $arbitriList = $assignments->map(function ($a) {
                    return "- {$a->user_name} ({$a->role})";
                })->implode("\n");

                Mail::raw(
                    "Gentile {$tournament->club->name},\n\nVi comunichiamo gli arbitri assegnati per il torneo:\n{$tournament->name}\n\nData: {$tournament->start_date->format('d/m/Y')}\n\nArbitri assegnati:\n{$arbitriList}\n\nCordiali saluti",
                    function ($message) use ($tournament) {
                        $message->to($tournament->club->email)
                            ->subject("Arbitri Assegnati - {$tournament->name}")
                            ->from(config('mail.from.address'), 'Federazione Golf');
                    }
                );
                $sent++;
                \Log::info("Email sent to club: {$tournament->club->email}");
            } catch (\Exception $e) {
                \Log::error("Failed to send to club: " . $e->getMessage());
            }
        }

        // Recupera i nomi per aggiornare referee_list
        $refereeNames = $assignments->pluck('user_name')->implode(', ');

        // Aggiorna tutto
        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
            'total_recipients' => $sent,
            'referee_list' => $refereeNames,
            'details' => json_encode([
                'sent' => $sent,
                'arbitri' => $assignments->count(),
                'club' => $tournament->club->email ? 1 : 0
            ])
        ]);

        return redirect()->route('admin.tournament-notifications.index')
            ->with('success', "Inviate {$sent} email ({$assignments->count()} arbitri + circolo)");
    }

    public function update(Request $request, TournamentNotification $notification)
    {
        $notification->update([
            'referee_list' => $request->referee_list
        ]);

        return redirect()->route('admin.tournament-notifications.index')
            ->with('success', 'Notifica aggiornata');
    }
    /**
     * 🔄 Reinvio notifiche torneo
     */
    public function resend(TournamentNotification $notification)
    {
        $tournamentNotification = $notification;
        return $this->send($notification);
    }

    /**
     * 🗑️ Eliminazione notifica torneo
     */
    public function destroy(TournamentNotification $notification)
    {
        $notification->delete();

        return redirect()->route('admin.tournament-notifications.index')
            ->with('success', 'Notifica eliminata');
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

        $callback = function () use ($notifications) {
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

    public function prepare(Tournament $tournament)
    {
        // IMPORTANTE: carica tutto prima
        $tournament->load('assignments.user');

        // Log per debug
        \Log::info('Tournament assignments count: ' . $tournament->assignments->count());

        // Recupera i nomi
        $refereeNames = $tournament->assignments->map(function ($assignment) {
            $name = $assignment->user ? $assignment->user->name : 'N/A';
            \Log::info('Assignment user: ' . $name);
            return $name;
        })->filter()->implode(', ');

        \Log::info('Final referee names: ' . $refereeNames);

        // CREA NUOVO RECORD
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'pending',
            'sent_at' => null,
            'referee_list' => $refereeNames ?: 'Nessun arbitro',
            'total_recipients' => $tournament->assignments->count() + 1,
            'sent_by' => auth()->id()
        ]);

        \Log::info('Created notification ID: ' . $notification->id . ' with referee_list: ' . $notification->referee_list);

        return redirect()->route('admin.tournament-notifications.index');
    }
}
