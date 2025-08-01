<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Services\FileStorageService;
use App\Services\DocumentGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Mail\RefereeAssignmentMail;
use App\Mail\ClubNotificationMail;

class TournamentNotificationController extends Controller
{
    protected $fileStorage;
    protected $documentService;

    public function __construct(
        FileStorageService $fileStorage,
        DocumentGenerationService $documentService
    ) {
        $this->fileStorage = $fileStorage;
        $this->documentService = $documentService;
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
    public function show($notification)
    {
        // Gestisce sia model binding che ID
        if (!$notification instanceof TournamentNotification) {
            $notification = TournamentNotification::findOrFail($notification);
        }

        $tournamentNotification = $notification;

        // Carica tutte le relazioni necessarie
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

    /**
     * SEND - Invia email con allegati
     */
    public function send(TournamentNotification $notification)
    {
        $tournament = Tournament::with(['club.zone', 'assignments.user'])->find($notification->tournament_id);
        $sent = 0;

        // 1. Invia agli arbitri (con PDF se disponibile)
        foreach ($tournament->assignments as $assignment) {
            Mail::to($assignment->user->email)
                ->send(new RefereeAssignmentMail($assignment, $tournament));
            $sent++;
        }
        // 2. Invia al circolo (con DOCX facsimile)
        if ($tournament->club->email) {
            $attachments = [];

            // Allega facsimile Word
            $clubDocPath = $this->fileStorage->getClubLetterPath($tournament);
            if (Storage::exists($clubDocPath)) {
                $attachments[] = storage_path('app/public/' . $clubDocPath);
            }

            Mail::to($tournament->club->email)->send(new ClubNotificationMail($tournament, $attachments));
            $sent++;
        }

        // 3. Aggiorna stato
        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
            'total_recipients' => $sent,
            'templates_used' => [
                'club' => 'facsimile_convocazione_v1',
                'referee' => 'convocazione_arbitro_v1',
                'institutional' => null // Non ancora usato
            ]
        ]);


        return redirect()->route('admin.tournament-notifications.index')
            ->with('success', "Inviate {$sent} notifiche");
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

    /**
     * PREPARE - Crea record e genera documenti
     */
    public function prepare(Tournament $tournament)
    {
        // 1. Carica relazioni necessarie
        $tournament->load(['assignments.user', 'club.zone']);

        // 2. Recupera nomi arbitri
        $refereeNames = $tournament->assignments
            ->map(fn($a) => $a->user->name)
            ->filter()
            ->implode(', ');

        // 3. Genera documenti Word per circolo
        $clubDoc = $this->documentService->generateClubDocument($tournament);
        $this->fileStorage->storeInZone($clubDoc, $tournament, 'docx');

        // 4. Crea record notifica
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'pending',
            'referee_list' => $refereeNames,
            'total_recipients' => $tournament->assignments->count() + 1,
            'sent_by' => auth()->id(),
            'attachments' => json_encode([
                'club' => $clubDoc['filename'],
                'szr' => null // Verrà popolato quando SZR genera PDF
            ])
        ]);

        return redirect()->route('admin.tournament-notifications.index');
    }
}
