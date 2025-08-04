<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Assignment;
use App\Models\LetterTemplate;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\InstitutionalEmail;
use App\Models\Zone;
use App\Models\User;
use App\Services\FileStorageService;
use App\Services\NotificationService;
use App\Services\DocumentGenerationService;
use App\Mail\RefereeAssignmentMail;
use App\Mail\ClubNotificationMail;
use App\Mail\InstitutionalNotificationMail;
use App\Mail\AssignmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;



class NotificationController extends Controller
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
        $validated = $request->validate([
            'email_template' => 'required|string',
            'send_to_club' => 'boolean',
            'send_to_referees' => 'boolean',
            'generate_documents' => 'boolean',
            'institutional_emails' => 'array',
            'additional_emails' => 'array'
        ]);

        DB::beginTransaction();
        try {
            // Crea la notifica
            $notification = TournamentNotification::create([
                'tournament_id' => $tournament->id,
                'status' => 'pending',
                'referee_list' => $tournament->assignments->pluck('user.name')->implode(', '),
                'total_recipients' => $tournament->assignments->count() + 1,
                'sent_by' => auth()->id(),
                'templates_used' => ['email' => $request->email_template]
            ]);

            // Genera documenti se richiesto
            if ($request->boolean('generate_documents', true)) {
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

            DB::commit();

            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', 'Notifica preparata. Ora puoi inviarla.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
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
    public function edit(TournamentNotification $notification)
    {
        $tournamentNotification = $notification;
        return view('admin.tournament-notifications.edit', compact('tournamentNotification'));
    }

    /**
     * Send notifications with correct recipients
     */
    public function send(TournamentNotification $notification)
    {
        $tournament = Tournament::with(['club.zone', 'assignments.user', 'tournamentType'])->find($notification->tournament_id);
        $sent = 0;

        // RECUPERA GLI ATTACHMENTS ESISTENTI
        $existingAttachments = is_string($notification->attachments) ?
            json_decode($notification->attachments, true) : $notification->attachments;
        $existingAttachments = $existingAttachments ?? [];

        $zone = $this->getZoneFolder($tournament);

        // 1. PDF - USA ESISTENTE O GENERA
        if (
            isset($existingAttachments['convocation']) &&
            Storage::disk('public')->exists("convocazioni/{$zone}/generated/{$existingAttachments['convocation']}")
        ) {
            $pdfPath = "convocazioni/{$zone}/generated/{$existingAttachments['convocation']}";
            Log::info('Using existing PDF: ' . $pdfPath);
        } else {
            $pdfPath = $this->documentService->generateConvocationPDF($tournament);
            Log::info('Generated new PDF: ' . $pdfPath);
        }

        // 2. DOCX - USA ESISTENTE O GENERA
        if (
            isset($existingAttachments['club_letter']) &&
            Storage::disk('public')->exists("convocazioni/{$zone}/generated/{$existingAttachments['club_letter']}")
        ) {
            $docxPath = "convocazioni/{$zone}/generated/{$existingAttachments['club_letter']}";
            Log::info('Using existing DOCX: ' . $docxPath);
        } elseif (
            isset($existingAttachments['club']) &&
            Storage::disk('public')->exists("convocazioni/{$zone}/generated/{$existingAttachments['club']}")
        ) {
            $docxPath = "convocazioni/{$zone}/generated/{$existingAttachments['club']}";
            Log::info('Using existing club DOCX: ' . $docxPath);
        } else {
            $docxData = $this->documentService->generateClubDocument($tournament);
            $docxPath = $this->fileStorage->storeInZone($docxData, $tournament, 'docx');
            Log::info('Generated new DOCX: ' . $docxPath);
        }

        // Prepara array attachments per le email
        $attachmentsPaths = [
            storage_path('app/public/' . $pdfPath),
            storage_path('app/public/' . $docxPath)
        ];

        Log::info('Final PDF Path: ' . $pdfPath);
        Log::info('Final DOCX Path: ' . $docxPath);
        Log::info('Attachments array:', $attachmentsPaths);

        // 2. INVIA AGLI ARBITRI (con PDF)
        foreach ($tournament->assignments as $assignment) {
            Log::info('Sending to referee: ' . $assignment->user->email);
            Log::info('With attachment: ' . $attachmentsPaths[0]);

            Mail::to($assignment->user->email)
                ->send(new RefereeAssignmentMail($assignment, $tournament, [$attachmentsPaths[0]]));

            Notification::create([
                'assignment_id' => $assignment->id,
                'recipient_type' => 'referee',
                'recipient_email' => $assignment->user->email,
                'recipient_name' => $assignment->user->name,
                'subject' => "Convocazione {$assignment->role} - {$tournament->name}",
                'body' => 'Convocazione ufficiale in allegato',
                'status' => 'sent',
                'sent_at' => now(),
                'tournament_id' => $tournament->id
            ]);

            $sent++;
        }

        // 3. INVIA AL CIRCOLO (con PDF + DOCX)
        if ($tournament->club->email) {
            Mail::to($tournament->club->email)
                ->send(new ClubNotificationMail($tournament, $attachmentsPaths));

            Notification::create([
                'recipient_type' => 'club',
                'recipient_email' => $tournament->club->email,
                'recipient_name' => $tournament->club->name,
                'subject' => "Arbitri Assegnati - {$tournament->name}",
                'body' => 'Comunicazione arbitri assegnati',
                'status' => 'sent',
                'sent_at' => now(),
                'tournament_id' => $tournament->id
            ]);

            $sent++;
        }

        // 4. DESTINATARI ISTITUZIONALI
        $institutionalRecipients = $this->getInstitutionalRecipients($tournament);

        foreach ($institutionalRecipients as $recipient) {
            Mail::to($recipient['email'])
                ->send(new InstitutionalNotificationMail($tournament, $recipient['type']));

            Notification::create([
                'recipient_type' => 'institutional',
                'recipient_email' => $recipient['email'],
                'recipient_name' => $recipient['name'],
                'subject' => "[{$recipient['type']}] {$tournament->name}",
                'body' => 'Notifica istituzionale',
                'status' => 'sent',
                'sent_at' => now(),
                'tournament_id' => $tournament->id
            ]);

            $sent++;
        }

        // 5. AGGIORNA STATO
        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
            'total_recipients' => $sent,
            'details' => [
                'sent' => $sent,
                'arbitri' => $tournament->assignments->count(),
                'club' => 1,
                'institutional' => count($institutionalRecipients)
            ],
            'templates_used' => [
                'club' => 'tournament_assignment_generic',
                'referee' => 'tournament_assignment_generic',
                'institutional' => 'institutional_notification'
            ],
            'attachments' => [
                'convocation' => basename($pdfPath),
                'club_letter' => basename($docxPath)
            ]
        ]);

        return redirect()->route('admin.tournament-notifications.index')
            ->with('success', "Inviate {$sent} notifiche con allegati");
    }

    /**
     * Get institutional recipients based on tournament type
     */
    private function getInstitutionalRecipients(Tournament $tournament): array
    {
        $recipients = [];

        // Ufficio Campionati sempre
        $recipients[] = [
            'email' => config('golf.emails.ufficio_campionati', 'campionati@federgolf.it'),
            'name' => 'Ufficio Campionati FIG',
            'type' => 'COORDINAMENTO'
        ];

        if ($tournament->is_national || $tournament->tournamentType->is_national) {
            // Per tornei nazionali

            // SZR competente per conoscenza
            $recipients[] = [
                'email' => "szr{$tournament->zone_id}@federgolf.it",
                'name' => "SZR {$tournament->zone->name}",
                'type' => 'CONOSCENZA'
            ];
        } else {
            // Per tornei zonali

            // CRC Regionale per conoscenza
            $recipients[] = [
                'email' => 'crc@federgolf.it',
                'name' => 'CRC - Comitato Regionale',
                'type' => 'CONOSCENZA'
            ];
        }

        return $recipients;
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
    $tournament->load(['assignments.user', 'club.zone']);

    $refereeNames = $tournament->assignments
        ->map(fn($a) => $a->user->name)
        ->filter()
        ->implode(', ');

    // GENERA ENTRAMBI I DOCUMENTI
    // 1. Convocazione PDF
    $pdfPath = $this->documentService->generateConvocationPDF($tournament);

    // 2. Facsimile DOCX per circolo
    $clubDoc = $this->documentService->generateClubDocument($tournament);
    $docxPath = $this->fileStorage->storeInZone($clubDoc, $tournament, 'docx');


    // SEMPRE UNA SOLA NOTIFICA PER TORNEO
    $notification = TournamentNotification::updateOrCreate(
        [
            'tournament_id' => $tournament->id  // Solo questa chiave
        ],
        [
            'status' => 'pending',  // Resetta sempre a pending
            'referee_list' => $refereeNames,
            'total_recipients' => $tournament->assignments->count() + 1,
            'sent_by' => auth()->id(),
            'sent_at' => null,  // Resetta data invio
            'attachments' => json_encode([
                'club' => $clubDoc['filename'],
                'szr' => null
            ])
        ]
    );

    return redirect()->route('admin.tournament-notifications.index')
        ->with('success', $notification->wasRecentlyCreated ?
            'Notifica preparata' :
            'Notifica aggiornata (la precedente è stata sovrascritta)');
}
    public function documentsStatus(TournamentNotification $notification)
    {
        $tournament = $notification->tournament;
        $attachments = is_string($notification->attachments) ?
            json_decode($notification->attachments, true) : $notification->attachments;

        $convocation = null;
        $clubLetter = null;

        $zone = $this->getZoneFolder($tournament);

        // Controlla convocazione
        if (isset($attachments['convocation'])) {
            $path = "convocazioni/{$zone}/generated/{$attachments['convocation']}";

            if (Storage::disk('public')->exists($path)) {
                $convocation = [
                    'filename' => $attachments['convocation'],
                    'generated_at' => Storage::disk('public')->lastModified($path) ?
                        Carbon::createFromTimestamp(Storage::disk('public')->lastModified($path))->format('d/m/Y H:i') : 'N/A',
                    'size' => $this->formatBytes(Storage::disk('public')->size($path))
                ];
            }
        }

        // Controlla lettera circolo - PRIMA club_letter POI club
        if (isset($attachments['club_letter'])) {
            $path = "convocazioni/{$zone}/generated/{$attachments['club_letter']}";

            if (Storage::disk('public')->exists($path)) {
                $clubLetter = [
                    'filename' => $attachments['club_letter'],
                    'generated_at' => Storage::disk('public')->lastModified($path) ?
                        Carbon::createFromTimestamp(Storage::disk('public')->lastModified($path))->format('d/m/Y H:i') : 'N/A',
                    'size' => $this->formatBytes(Storage::disk('public')->size($path))
                ];
            }
        } elseif (isset($attachments['club'])) {
            $path = "convocazioni/{$zone}/generated/{$attachments['club']}";

            if (Storage::disk('public')->exists($path)) {
                $clubLetter = [
                    'filename' => $attachments['club'],
                    'generated_at' => Storage::disk('public')->lastModified($path) ?
                        Carbon::createFromTimestamp(Storage::disk('public')->lastModified($path))->format('d/m/Y H:i') : 'N/A',
                    'size' => $this->formatBytes(Storage::disk('public')->size($path))
                ];
            }
        }

        return response()->json([
            'notification_id' => $notification->id,
            'tournament_id' => $tournament->id,
            'convocation' => $convocation,
            'club_letter' => $clubLetter
        ]);
    }

    // Aggiungi questo metodo helper
    private function getZoneFolder($tournament): string
    {
        // Usa il metodo del FileStorageService per consistenza
        return $this->fileStorage->getZoneFolder($tournament);
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
    /**
     * Genera un documento
     */
public function generateDocument(Request $request, TournamentNotification $notification, $type)
{
    $tournament = $notification->tournament;

    try {
        // Recupera attachments esistenti
        $attachments = is_string($notification->attachments) ?
            json_decode($notification->attachments, true) : $notification->attachments;
        $attachments = $attachments ?? [];

        if ($type === 'convocation') {
            $path = $this->documentService->generateConvocationForTournament($tournament);
            $attachments['convocation'] = basename($path);
        }

        if ($type === 'club_letter') {
            $docData = $this->documentService->generateClubDocument($tournament);
            $path = $this->fileStorage->storeInZone($docData, $tournament, 'docx');
            $attachments['club_letter'] = basename($path);
        }

        // Aggiorna con TUTTI gli attachments
        $notification->update(['attachments' => json_encode($attachments)]);

        return redirect()->back()->with('success', 'Documento generato con successo');

    } catch (\Exception $e) {
        return redirect()->back()->with('error', 'Errore nella generazione: ' . $e->getMessage());
    }
}

    /**
     * Rigenera un documento
     */
    public function regenerateDocument(Request $request, TournamentNotification $notification, $type)
    {
        // Usa lo stesso metodo di generate
        return $this->generateDocument($request, $notification, $type);
    }

    /**
     * Scarica un documento
     */
    // Per scaricare
    public function downloadDocument(TournamentNotification $notification, $type)
    {
        $tournament = $notification->tournament;
        $attachments = is_string($notification->attachments) ?
            json_decode($notification->attachments, true) : $notification->attachments;

        $filename = null;

        // Per club_letter, cerca in entrambe le chiavi
        if ($type === 'club_letter') {
            if (isset($attachments['club_letter'])) {
                $filename = $attachments['club_letter'];
            } elseif (isset($attachments['club'])) {
                $filename = $attachments['club'];
            }
        } else {
            $filename = $attachments[$type] ?? null;
        }

        if (!$filename) {
            return redirect()->back()->with('error', 'Documento non trovato negli attachments');
        }

        $zone = $this->getZoneFolder($tournament);
        $path = "convocazioni/{$zone}/generated/{$filename}";
        $fullPath = storage_path('app/public/' . $path);

        if (file_exists($fullPath)) {
            return response()->download($fullPath);
        }

        return redirect()->back()->with('error', 'File non trovato sul server: ' . $path);
    }

    // Per caricare file modificato
    public function uploadDocument(Request $request, TournamentNotification $notification, $type)
    {
        $request->validate([
            'document' => 'required|file|mimes:doc,docx|max:10240'
        ]);

        $file = $request->file('document');
        $tournament = $notification->tournament;

        // Gestisci attachments come stringa o array (NON attachmentPaths!)
        $attachments = is_string($notification->attachments) ?
            json_decode($notification->attachments, true) : $notification->attachments;
        $attachments = $attachments ?? [];

        // Usa il DocumentGenerationService che già hai
        $filename = isset($attachments[$type]) ?
            $attachments[$type] :
            $this->documentService->generateFilename($type, $tournament);

        // Prepara dati per storage
        $fileData = [
            'path' => $file->getRealPath(),
            'filename' => $filename,
            'type' => $type
        ];

        Log::info('Upload document - BEFORE', [
            'type' => $type,
            'current_attachments' => $notification->attachments,
            'filename_to_use' => $filename
        ]);

        $path = $this->fileStorage->storeInZone($fileData, $tournament, 'docx');

        Log::info('Upload document - AFTER storage', [
            'stored_path' => $path,
            'basename' => basename($path)
        ]);

        // Aggiorna attachments nel database
        $attachments[$type] = basename($path);
        $notification->update(['attachments' => json_encode($attachments)]);

        // Ricarica per verificare
        $notification->refresh();

        Log::info('Upload document - AFTER update', [
            'updated_attachments' => $notification->attachments
        ]);

        return redirect()->back()->with('success', 'Documento caricato con successo');
    }


    /**
     * Elimina un documento
     */
    public function deleteDocument(TournamentNotification $notification, $type)
    {
        $tournament = $notification->tournament;

        try {
            if ($type === 'convocation') {
                $this->fileStorage->deleteConvocation($tournament);

                $attachments = $notification->attachments ?? [];
                unset($attachments['convocation']);
                $notification->update(['attachments' => $attachments]);
            }

            if ($type === 'club_letter') {
                $this->fileStorage->deleteClubLetter($tournament);

                $attachments = $notification->attachments ?? [];
                unset($attachments['club_letter']);
                $notification->update(['attachments' => $attachments]);
            }

            return redirect()->back()->with('success', 'Documento eliminato');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Errore nell\'eliminazione: ' . $e->getMessage());
        }
    }

    public function findByTournament(Tournament $tournament)
    {
        $notification = TournamentNotification::where('tournament_id', $tournament->id)
            ->latest()
            ->first();

        return response()->json([
            'notification_id' => $notification ? $notification->id : null
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


}
