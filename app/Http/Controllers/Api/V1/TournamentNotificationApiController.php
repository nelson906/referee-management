<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\Notification;
use App\Services\TournamentNotificationService;
use App\Http\Resources\TournamentNotificationResource;
use App\Http\Resources\TournamentNotificationCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 🚀 API Controller per Sistema Notifiche Torneo
 *
 * @group Tournament Notifications API
 */
class TournamentNotificationApiController extends Controller
{
    protected $notificationService;

    public function __construct(TournamentNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;

        // Middleware API specifici
        $this->middleware(['auth:sanctum', 'tournament.notification.api']);
    }

    /**
     * 📋 Lista notifiche tornei con filtri
     *
     * @queryParam zone_id integer Filtra per zona. Example: 6
     * @queryParam status string Filtra per stato (sent,failed,partial,pending). Example: sent
     * @queryParam page integer Numero pagina. Example: 1
     * @queryParam per_page integer Elementi per pagina (max 100). Example: 20
     * @queryParam sort string Campo ordinamento. Example: sent_at
     * @queryParam direction string Direzione ordinamento (asc,desc). Example: desc
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "tournament": {
     *         "id": 123,
     *         "name": "Trofeo Regionale 2025"
     *       },
     *       "status": "sent",
     *       "total_recipients": 6,
     *       "sent_at": "2025-07-29T15:30:00.000000Z",
     *       "success_rate": 100
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "total": 150,
     *     "per_page": 20
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'sometimes|integer|exists:zones,id',
            'status' => 'sometimes|string|in:sent,failed,partial,pending',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort' => 'sometimes|string|in:id,sent_at,total_recipients,status',
            'direction' => 'sometimes|string|in:asc,desc',
            'search' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = TournamentNotification::with(['tournament.club', 'tournament.zone']);

        // Filtri di sicurezza per zona
        $user = $request->user();
        if ($user->user_type === 'admin' && $user->zone_id) {
            $query->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Applica filtri
        if ($request->filled('zone_id')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('tournament', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('club', function($subQ) use ($search) {
                      $subQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Ordinamento
        $sort = $request->get('sort', 'sent_at');
        $direction = $request->get('direction', 'desc');
        $query->orderBy($sort, $direction);

        // Paginazione
        $perPage = min($request->get('per_page', 20), 100);
        $notifications = $query->paginate($perPage);

        return response()->json(new TournamentNotificationCollection($notifications));
    }

    /**
     * 👁️ Dettagli notifica torneo
     *
     * @urlParam id integer required ID della notifica torneo. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "tournament": {
     *       "id": 123,
     *       "name": "Trofeo Regionale 2025",
     *       "club": {
     *         "name": "Golf Club Roma"
     *       },
     *       "zone": {
     *         "name": "Lazio-Abruzzo",
     *         "code": "SZR6"
     *       }
     *     },
     *     "status": "sent",
     *     "total_recipients": 6,
     *     "sent_at": "2025-07-29T15:30:00.000000Z",
     *     "sent_by": {
     *       "name": "Mario Rossi"
     *     },
     *     "details": {
     *       "club": {"sent": 1, "failed": 0},
     *       "referees": {"sent": 3, "failed": 0},
     *       "institutional": {"sent": 2, "failed": 0}
     *     },
     *     "templates_used": {
     *       "club": "club_assignment_standard",
     *       "referee": "referee_assignment_formal"
     *     },
     *     "stats": {
     *       "success_rate": 100,
     *       "total_failed": 0
     *     }
     *   }
     * }
     */
    public function show(Request $request, TournamentNotification $tournamentNotification): JsonResponse
    {
        // Verifica permessi zona
        $this->checkZonePermission($request->user(), $tournamentNotification->tournament);

        $tournamentNotification->load([
            'tournament.club',
            'tournament.zone',
            'sentBy',
            'individualNotifications'
        ]);

        return response()->json([
            'data' => new TournamentNotificationResource($tournamentNotification)
        ]);
    }

    /**
     * 📧 Invia notifiche per torneo
     *
     * @urlParam tournament_id integer required ID del torneo. Example: 123
     *
     * @bodyParam club_template string required Template per circolo. Example: club_assignment_standard
     * @bodyParam referee_template string required Template per arbitri. Example: referee_assignment_formal
     * @bodyParam institutional_template string required Template istituzionale. Example: institutional_report_standard
     * @bodyParam include_attachments boolean Includi allegati. Example: true
     * @bodyParam send_to_club boolean Invia al circolo. Example: true
     * @bodyParam send_to_referees boolean Invia agli arbitri. Example: true
     * @bodyParam send_to_institutional boolean Invia agli istituzionali. Example: true
     * @bodyParam custom_message string Messaggio personalizzato. Example: Messaggio aggiuntivo per tutte le email
     *
     * @response 201 {
     *   "data": {
     *     "tournament_notification_id": 1,
     *     "tournament_id": 123,
     *     "total_sent": 6,
     *     "details": {
     *       "club": {"sent": 1, "failed": 0},
     *       "referees": {"sent": 3, "failed": 0},
     *       "institutional": {"sent": 2, "failed": 0}
     *     }
     *   },
     *   "message": "Notifiche inviate con successo"
     * }
     */
    public function store(Request $request, Tournament $tournament): JsonResponse
    {
        // Rate limiting specifico per API
        $key = 'tournament-notification-api:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 10)) { // Max 10 al minuto
            return response()->json([
                'message' => 'Rate limit exceeded. Try again later.',
                'retry_after' => RateLimiter::availableIn($key)
            ], 429);
        }

        RateLimiter::hit($key, 60);

        // Verifica permessi
        $this->checkZonePermission($request->user(), $tournament);

        // Validazione
        $validator = Validator::make($request->all(), [
            'club_template' => 'required|string|max:100',
            'referee_template' => 'required|string|max:100',
            'institutional_template' => 'required|string|max:100',
            'include_attachments' => 'sometimes|boolean',
            'send_to_club' => 'sometimes|boolean',
            'send_to_referees' => 'sometimes|boolean',
            'send_to_institutional' => 'sometimes|boolean',
            'custom_message' => 'sometimes|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verifica se il torneo può ricevere notifiche
        if (!$tournament->canSendNotifications()) {
            return response()->json([
                'message' => 'Tournament cannot receive notifications',
                'blockers' => $tournament->getNotificationBlockers()
            ], 422);
        }

        // Verifica se già notificato
        if ($tournament->hasNotifications()) {
            return response()->json([
                'message' => 'Tournament already has notifications sent',
                'existing_notification_id' => $tournament->lastNotification()->id
            ], 409);
        }

        try {
            $options = array_merge($request->validated(), [
                'sent_by' => $request->user()->id
            ]);

            $result = $this->notificationService->sendTournamentNotifications($tournament, $options);

            return response()->json([
                'data' => [
                    'tournament_notification_id' => $result['tournament_notification_id'],
                    'tournament_id' => $tournament->id,
                    'total_sent' => $result['total_sent'],
                    'details' => $result['details']
                ],
                'message' => 'Notifications sent successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔄 Reinvia notifiche fallite
     *
     * @urlParam id integer required ID della notifica torneo. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "tournament_notification_id": 2,
     *     "resent": 3,
     *     "failed": 0
     *   },
     *   "message": "Notifications resent successfully"
     * }
     */
    public function resend(Request $request, TournamentNotification $tournamentNotification): JsonResponse
    {
        // Verifica permessi
        $this->checkZonePermission($request->user(), $tournamentNotification->tournament);

        if (!$tournamentNotification->canBeResent()) {
            return response()->json([
                'message' => 'Notification cannot be resent',
                'reason' => 'Status does not allow resending or too recent'
            ], 422);
        }

        try {
            $result = $this->notificationService->resendTournamentNotifications($tournamentNotification);

            return response()->json([
                'data' => $result,
                'message' => 'Notifications resent successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to resend notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🗑️ Elimina notifiche torneo
     *
     * @urlParam id integer required ID della notifica torneo. Example: 1
     *
     * @response 200 {
     *   "message": "Tournament notifications deleted successfully"
     * }
     */
    public function destroy(Request $request, TournamentNotification $tournamentNotification): JsonResponse
    {
        // Verifica permessi
        $this->checkZonePermission($request->user(), $tournamentNotification->tournament);

        try {
            // Elimina notifiche individuali correlate
            $tournamentNotification->individualNotifications()->delete();
            $tournamentNotification->delete();

            return response()->json([
                'message' => 'Tournament notifications deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📊 Statistiche notifiche
     *
     * @queryParam zone_id integer Filtra per zona. Example: 6
     * @queryParam period string Periodo (today,week,month,year). Example: month
     *
     * @response 200 {
     *   "data": {
     *     "total_notifications": 150,
     *     "total_recipients": 900,
     *     "success_rate": 95.5,
     *     "by_status": {
     *       "sent": 143,
     *       "failed": 5,
     *       "partial": 2
     *     },
     *     "by_type": {
     *       "club": 150,
     *       "referee": 450,
     *       "institutional": 300
     *     },
     *     "trends": [
     *       {"date": "2025-07-01", "count": 5},
     *       {"date": "2025-07-02", "count": 8}
     *     ]
     *   }
     * }
     */
    public function stats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'sometimes|integer|exists:zones,id',
            'period' => 'sometimes|string|in:today,week,month,year'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $zoneId = $user->user_type === 'admin' ? $user->zone_id : $request->zone_id;
        $period = $request->get('period', 'month');

        $stats = $this->calculateStats($zoneId, $period);

        return response()->json([
            'data' => $stats,
            'meta' => [
                'zone_id' => $zoneId,
                'period' => $period,
                'generated_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * 📄 Lista template disponibili
     *
     * @queryParam type string Tipo template (club,referee,institutional). Example: club
     *
     * @response 200 {
     *   "data": {
     *     "club": [
     *       {
     *         "name": "club_assignment_standard",
     *         "label": "Standard - Assegnazione Arbitri",
     *         "description": "Template standard per comunicazione arbitri al circolo"
     *       }
     *     ],
     *     "referee": [
     *       {
     *         "name": "referee_assignment_formal",
     *         "label": "Formale - Convocazione ufficiale"
     *       }
     *     ]
     *   }
     * }
     */
    public function templates(Request $request): JsonResponse
    {
        $templates = config('tournament-notifications.templates');

        if ($request->filled('type')) {
            $type = $request->type;
            if (!isset($templates[$type])) {
                return response()->json([
                    'message' => 'Invalid template type',
                    'available_types' => array_keys($templates)
                ], 422);
            }

            $templates = [$type => $templates[$type]];
        }

        // Arricchisci con informazioni aggiuntive
        foreach ($templates as $type => &$typeTemplates) {
            foreach ($typeTemplates['available'] as $name => &$label) {
                $template = \App\Models\LetterTemplate::where('name', $name)->first();
                $label = [
                    'name' => $name,
                    'label' => $label,
                    'description' => $template?->description ?? '',
                    'variables' => $template?->variables ?? [],
                    'is_active' => $template?->is_active ?? true
                ];
            }
        }

        return response()->json(['data' => $templates]);
    }

    /**
     * 🔍 Anteprima template
     *
     * @urlParam template_name string required Nome del template. Example: club_assignment_standard
     * @urlParam tournament_id integer required ID del torneo per dati esempio. Example: 123
     *
     * @response 200 {
     *   "data": {
     *     "subject": "Assegnazione Arbitri - Trofeo Regionale 2025",
     *     "body": "Gentile Golf Club Roma...",
     *     "variables_used": ["club_name", "tournament_name", "referees"]
     *   }
     * }
     */
    public function previewTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'template_name' => 'required|string|exists:letter_templates,name',
            'tournament_id' => 'required|integer|exists:tournaments,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tournament = Tournament::with(['club', 'zone', 'assignments.user'])->find($request->tournament_id);

        // Verifica permessi
        $this->checkZonePermission($request->user(), $tournament);

        try {
            $template = \App\Models\LetterTemplate::where('name', $request->template_name)->firstOrFail();

            // Genera dati esempio per preview
            $sampleData = $this->generateSampleData($tournament, $template->type);

            // Processa template
            $subject = $this->processTemplateString($template->subject, $sampleData);
            $body = $this->processTemplateString($template->body, $sampleData);

            return response()->json([
                'data' => [
                    'subject' => $subject,
                    'body' => $body,
                    'variables_used' => $template->variables ?? [],
                    'sample_data' => $sampleData
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate template preview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔒 Verifica permessi zona
     */
    private function checkZonePermission($user, Tournament $tournament): void
    {
        if ($user->user_type === 'super_admin' || $user->user_type === 'crc') {
            return; // Accesso completo
        }

        if ($user->user_type === 'admin' && $user->zone_id === $tournament->zone_id) {
            return; // Admin della zona corretta
        }

        abort(403, 'Unauthorized access to tournament from different zone');
    }

    /**
     * 📊 Calcola statistiche per periodo
     */
    private function calculateStats(?int $zoneId, string $period): array
    {
        $dateRange = $this->getDateRangeForPeriod($period);

        $query = TournamentNotification::whereBetween('sent_at', $dateRange);

        if ($zoneId) {
            $query->whereHas('tournament', function($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        }

        $notifications = $query->get();

        return [
            'total_notifications' => $notifications->count(),
            'total_recipients' => $notifications->sum('total_recipients'),
            'success_rate' => $this->calculateSuccessRate($notifications),
            'by_status' => $notifications->groupBy('status')->map->count(),
            'by_type' => $this->getRecipientTypeStats($zoneId, $dateRange),
            'trends' => $this->getDailyTrends($zoneId, $dateRange)
        ];
    }

    /**
     * 📅 Range date per periodo
     */
    private function getDateRangeForPeriod(string $period): array
    {
        return match($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()]
        };
    }

    /**
     * 📊 Calcola tasso successo
     */
    private function calculateSuccessRate($notifications): float
    {
        if ($notifications->isEmpty()) return 0;

        $successful = $notifications->where('status', 'sent')->count();
        return round(($successful / $notifications->count()) * 100, 1);
    }

    /**
     * 📧 Statistiche per tipo destinatario
     */
    private function getRecipientTypeStats(?int $zoneId, array $dateRange): array
    {
        $query = Notification::whereBetween('sent_at', $dateRange);

        if ($zoneId) {
            $query->whereHas('tournament', function($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        }

        return $query->groupBy('recipient_type')->selectRaw('recipient_type, count(*) as count')
                    ->pluck('count', 'recipient_type')
                    ->toArray();
    }

    /**
     * 📈 Trend giornalieri
     */
    private function getDailyTrends(?int $zoneId, array $dateRange): array
    {
        $query = TournamentNotification::selectRaw('DATE(sent_at) as date, COUNT(*) as count')
                                     ->whereBetween('sent_at', $dateRange)
                                     ->groupBy('date')
                                     ->orderBy('date');

        if ($zoneId) {
            $query->whereHas('tournament', function($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        }

        return $query->get()->map(function($item) {
            return [
                'date' => $item->date,
                'count' => $item->count
            ];
        })->toArray();
    }

    /**
     * 📄 Genera dati esempio per template
     */
    private function generateSampleData(Tournament $tournament, string $templateType): array
    {
        $baseData = [
            'tournament_name' => $tournament->name,
            'tournament_dates' => $tournament->formatted_dates,
            'club_name' => $tournament->club->name,
            'club_address' => $tournament->club->address ?? 'Via del Golf 1, Roma',
            'zone_name' => $tournament->zone->name,
            'zone_email' => $tournament->zone->email ?? 'szr@federgolf.it'
        ];

        if ($templateType === 'club') {
            $baseData['referees'] = $tournament->assignments->map(function($assignment) {
                return [
                    'name' => $assignment->user->name,
                    'role' => $assignment->role,
                    'email' => $assignment->user->email,
                    'phone' => $assignment->user->phone ?? '+39 339 1234567'
                ];
            })->toArray();
        }

        if ($templateType === 'referee') {
            $assignment = $tournament->assignments->first();
            if ($assignment) {
                $baseData = array_merge($baseData, [
                    'referee_name' => $assignment->user->name,
                    'assignment_role' => $assignment->role,
                    'assignment_notes' => $assignment->notes ?? 'Nessuna nota particolare'
                ]);
            }
        }

        if ($templateType === 'institutional') {
            $baseData = array_merge($baseData, [
                'assignments_list' => $tournament->assignments_list,
                'assigned_date' => now()->format('d/m/Y H:i'),
                'tournament_category' => $tournament->tournamentType->name ?? 'Regionale'
            ]);
        }

        return $baseData;
    }

    /**
     * 🔧 Processa string template
     */
    private function processTemplateString(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Gestione array (come lista arbitri)
                $value = collect($value)->map(function($item) {
                    if (is_array($item)) {
                        return implode(' - ', array_values($item));
                    }
                    return $item;
                })->join("\n");
            }

            $template = str_replace('{{'.$key.'}}', $value, $template);
        }

        return $template;
    }
}
