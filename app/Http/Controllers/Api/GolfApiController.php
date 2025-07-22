<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Zone;
use App\Models\User;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Availability;
use App\Models\Assignment;
use Carbon\Carbon;

/**
 * API Controller per dati del sistema Golf seeded
 * Fornisce endpoints per accedere ai dati generati dai seeder
 */
class GolfApiController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/golf/zones",
     *     summary="Lista zone geografiche",
     *     tags={"Golf Zones"},
     *     @OA\Response(response=200, description="Lista zone")
     * )
     */
    public function getZones(Request $request): JsonResponse
    {
        $query = Zone::query();

        if ($request->has('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->has('with_stats')) {
            $query->withCount(['clubs', 'users' => function($q) {
                $q->where('user_type', 'referee');
            }]);
        }

        $zones = $query->get();

        return response()->json([
            'success' => true,
            'data' => $zones,
            'meta' => [
                'total' => $zones->count(),
                'generated_by' => 'Golf Seeder System',
                'last_updated' => now()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/golf/zones/{zoneCode}",
     *     summary="Dettaglio zona specifica",
     *     tags={"Golf Zones"},
     *     @OA\Parameter(name="zoneCode", in="path", required=true)
     * )
     */
    public function getZoneDetails(string $zoneCode): JsonResponse
    {
        $zone = Zone::where('code', $zoneCode)
            ->with(['clubs', 'users' => function($query) {
                $query->where('user_type', 'referee');
            }])
            ->withCount([
                'clubs',
                'users' => function($q) { $q->where('user_type', 'referee'); }
            ])
            ->first();

        if (!$zone) {
            return response()->json([
                'success' => false,
                'message' => "Zona {$zoneCode} non trovata"
            ], 404);
        }

        // Statistiche aggiuntive
        $stats = [
            'tournaments_total' => Tournament::where('zone_id', $zone->id)->count(),
            'tournaments_active' => Tournament::where('zone_id', $zone->id)
                ->whereIn('status', ['open', 'closed', 'assigned'])->count(),
            'referees_by_level' => User::where('zone_id', $zone->id)
                ->where('user_type', 'referee')
                ->selectRaw('level, count(*) as count')
                ->groupBy('level')
                ->pluck('count', 'level'),
            'recent_tournaments' => Tournament::where('zone_id', $zone->id)
                ->with('tournamentType', 'club')
                ->latest()
                ->limit(5)
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $zone,
            'statistics' => $stats,
            'meta' => [
                'zone_info' => 'Dati generati dal Golf Seeder System',
                'last_seeded' => $zone->updated_at
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/golf/referees",
     *     summary="Lista arbitri con filtri",
     *     tags={"Golf Referees"}
     * )
     */
    public function getReferees(Request $request): JsonResponse
    {
        $query = User::where('user_type', 'referee')
            ->with(['zone']);

        // Filtri
        if ($request->has('zone')) {
            $zone = Zone::where('code', $request->zone)->first();
            if ($zone) {
                $query->where('zone_id', $zone->id);
            }
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->has('available_for_tournament')) {
            $tournamentId = $request->available_for_tournament;
            $query->whereHas('availabilities', function($q) use ($tournamentId) {
                $q->where('tournament_id', $tournamentId)
                  ->where('is_available', true);
            });
        }

        // Paginazione
        $perPage = min($request->get('per_page', 25), 100);
        $referees = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $referees->items(),
            'pagination' => [
                'current_page' => $referees->currentPage(),
                'total_pages' => $referees->lastPage(),
                'total_items' => $referees->total(),
                'per_page' => $referees->perPage()
            ],
            'filters_applied' => $request->only(['zone', 'level', 'active_only', 'available_for_tournament']),
            'meta' => [
                'seeder_generated' => true,
                'data_quality' => 'Clean and consistent'
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/golf/tournaments",
     *     summary="Lista tornei con filtri avanzati",
     *     tags={"Golf Tournaments"}
     * )
     */
    public function getTournaments(Request $request): JsonResponse
    {
        $query = Tournament::with(['zone', 'club', 'tournamentType'])
            ->withCount(['availabilities', 'assignments']);

        // Filtri temporali
        if ($request->has('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('end_date', '<=', $request->date_to);
        }

        // Filtro status
        if ($request->has('status')) {
            if (is_array($request->status)) {
                $query->whereIn('status', $request->status);
            } else {
                $query->where('status', $request->status);
            }
        }

        // Filtro zona
        if ($request->has('zone')) {
            $zone = Zone::where('code', $request->zone)->first();
            if ($zone) {
                $query->where('zone_id', $zone->id);
            }
        }

        // Filtro tipo torneo
        if ($request->has('tournament_type')) {
            $query->whereHas('tournamentType', function($q) use ($request) {
                $q->where('code', $request->tournament_type);
            });
        }

        // Ordinamento
        $sortBy = $request->get('sort_by', 'start_date');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = min($request->get('per_page', 25), 100);
        $tournaments = $query->paginate($perPage);

        // Statistiche aggiuntive per la pagina corrente
        $currentPageTournaments = collect($tournaments->items());
        $stats = [
            'status_distribution' => $currentPageTournaments->countBy('status'),
            'zones_represented' => $currentPageTournaments->pluck('zone.code')->filter()->unique()->count(),
            'avg_availabilities' => round($currentPageTournaments->avg('availabilities_count'), 1),
            'avg_assignments' => round($currentPageTournaments->avg('assignments_count'), 1)
        ];

        return response()->json([
            'success' => true,
            'data' => $tournaments->items(),
            'pagination' => [
                'current_page' => $tournaments->currentPage(),
                'total_pages' => $tournaments->lastPage(),
                'total_items' => $tournaments->total(),
                'per_page' => $tournaments->perPage()
            ],
            'statistics' => $stats,
            'filters_applied' => $request->only(['date_from', 'date_to', 'status', 'zone', 'tournament_type']),
            'meta' => [
                'seeder_info' => 'Tornei generati con logica business realistica',
                'workflow_states' => ['draft', 'scheduled', 'open', 'closed', 'assigned', 'completed'],
                'generated_at' => $tournaments->first()?->created_at
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/golf/tournaments/{id}/workflow",
     *     summary="Workflow completo di un torneo",
     *     tags={"Golf Tournaments"}
     * )
     */
    public function getTournamentWorkflow(int $id): JsonResponse
    {
        $tournament = Tournament::with([
            'zone',
            'club',
            'tournamentType',
            'availabilities.referee',
            'assignments.referee'
        ])->find($id);

        if (!$tournament) {
            return response()->json([
                'success' => false,
                'message' => 'Torneo non trovato'
            ], 404);
        }

        // Workflow analysis
        $workflow = [
            'tournament_info' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'status' => $tournament->status,
                'zone' => $tournament->zone?->code,
                'club' => $tournament->club?->name,
                'type' => $tournament->tournamentType?->name,
                'dates' => [
                    'start' => $tournament->start_date,
                    'end' => $tournament->end_date,
                    'availability_deadline' => $tournament->availability_deadline
                ]
            ],
            'requirements' => [
                'min_referees' => $tournament->tournamentType?->min_referees,
                'max_referees' => $tournament->tournamentType?->max_referees,
                'requires_approval' => $tournament->tournamentType?->requires_approval
            ],
            'availabilities' => [
                'total_responses' => $tournament->availabilities->count(),
                'available_referees' => $tournament->availabilities->where('is_available', true)->count(),
                'unavailable_referees' => $tournament->availabilities->where('is_available', false)->count(),
                'response_rate' => $this->calculateResponseRate($tournament),
                'by_level' => $this->groupAvailabilitiesByLevel($tournament)
            ],
            'assignments' => [
                'total_assigned' => $tournament->assignments->count(),
                'confirmed' => $tournament->assignments->where('is_confirmed', true)->count(),
                'pending_confirmation' => $tournament->assignments->where('is_confirmed', false)->count(),
                'by_role' => $tournament->assignments->groupBy('role')->map->count(),
                'meets_requirements' => $this->checkAssignmentRequirements($tournament)
            ],
            'workflow_status' => $this->getWorkflowStatus($tournament)
        ];

        return response()->json([
            'success' => true,
            'data' => $workflow,
            'detailed_availabilities' => $tournament->availabilities->map(function($avail) {
                return [
                    'referee' => [
                        'name' => $avail->referee->name,
                        'level' => $avail->referee->level,
                        'zone' => $avail->referee->zone?->code
                    ],
                    'is_available' => $avail->is_available,
                    'submitted_at' => $avail->submitted_at,
                    'notes' => $avail->notes
                ];
            }),
            'detailed_assignments' => $tournament->assignments->map(function($assign) {
                return [
                    'referee' => [
                        'name' => $assign->referee->name,
                        'level' => $assign->referee->level,
                        'code' => $assign->referee->referee_code
                    ],
                    'role' => $assign->role,
                    'is_confirmed' => $assign->is_confirmed,
                    'assigned_at' => $assign->assigned_at,
                    'fee_amount' => $assign->fee_amount
                ];
            }),
            'meta' => [
                'seeder_generated' => true,
                'workflow_integrity' => 'Validated by seeder logic'
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/golf/dashboard/stats",
     *     summary="Statistiche dashboard aggregate",
     *     tags={"Golf Dashboard"}
     * )
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        $zoneFilter = null;
        if ($request->has('zone')) {
            $zone = Zone::where('code', $request->zone)->first();
            $zoneFilter = $zone?->id;
        }

        $stats = [
            'overview' => [
                'total_zones' => Zone::count(),
                'total_referees' => User::where('user_type', 'referee')->when($zoneFilter, fn($q) => $q->where('zone_id', $zoneFilter))->count(),
                'active_referees' => User::where('user_type', 'referee')->where('is_active', true)->when($zoneFilter, fn($q) => $q->where('zone_id', $zoneFilter))->count(),
                'total_clubs' => Club::when($zoneFilter, fn($q) => $q->where('zone_id', $zoneFilter))->count(),
                'total_tournaments' => Tournament::when($zoneFilter, fn($q) => $q->where('zone_id', $zoneFilter))->count()
            ],
            'tournaments_by_status' => Tournament::when($zoneFilter, fn($q) => $q->where('zone_id', $zoneFilter))
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'referees_by_level' => User::where('user_type', 'referee')
                ->when($zoneFilter, fn($q) => $q->where('zone_id', $zoneFilter))
                ->selectRaw('level, count(*) as count')
                ->groupBy('level')
                ->pluck('count', 'level'),
            'recent_activity' => [
                'new_tournaments_this_month' => Tournament::when($zoneFilter, fn($q) => $q->where('zone_id', $zoneFilter))
                    ->where('created_at', '>=', now()->startOfMonth())->count(),
                'pending_availabilities' => Tournament::when($zoneFilter, fn($q) => $q->where('zone_id', $zoneFilter))
                    ->where('status', 'open')
                    ->where('availability_deadline', '>', now())->count(),
                'unconfirmed_assignments' => Assignment::whereHas('tournament', function($q) use ($zoneFilter) {
                        if ($zoneFilter) $q->where('zone_id', $zoneFilter);
                    })->where('is_confirmed', false)->count()
            ],
            'performance_metrics' => [
                'avg_response_rate' => $this->calculateAvgResponseRate($zoneFilter),
                'avg_confirmation_rate' => $this->calculateAvgConfirmationRate($zoneFilter),
                'tournaments_meeting_requirements' => $this->calculateRequirementsMet($zoneFilter)
            ]
        ];

        // Zone breakdown se non filtrato per zona specifica
        if (!$zoneFilter) {
            $stats['zones_breakdown'] = Zone::withCount([
                'clubs',
                'users' => function($q) { $q->where('user_type', 'referee'); }
            ])->get()->map(function($zone) {
                return [
                    'code' => $zone->code,
                    'name' => $zone->name,
                    'clubs_count' => $zone->clubs_count,
                    'referees_count' => $zone->users_count,
                    'tournaments_count' => Tournament::where('zone_id', $zone->id)->count()
                ];
            });
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
            'filters' => [
                'zone' => $request->zone ?? 'all',
                'generated_at' => now()
            ],
            'meta' => [
                'data_source' => 'Golf Seeder System',
                'data_quality' => 'Production-ready clean data',
                'last_seeding' => $this->getLastSeedingDate()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/golf/export/data",
     *     summary="Export dati per analisi esterna",
     *     tags={"Golf Export"}
     * )
     */
    public function exportData(Request $request): JsonResponse
    {
        $exportType = $request->get('type', 'summary');
        $zoneCode = $request->get('zone');

        $zone = $zoneCode ? Zone::where('code', $zoneCode)->first() : null;

        $data = match($exportType) {
            'full' => $this->getFullExportData($zone),
            'tournaments' => $this->getTournamentsExportData($zone),
            'referees' => $this->getRefereesExportData($zone),
            'workflow' => $this->getWorkflowExportData($zone),
            default => $this->getSummaryExportData($zone)
        };

        return response()->json([
            'success' => true,
            'export_type' => $exportType,
            'zone_filter' => $zoneCode,
            'data' => $data,
            'meta' => [
                'exported_at' => now(),
                'record_counts' => $this->getExportCounts($data),
                'data_integrity' => 'Seeder-validated',
                'suggested_analysis' => $this->getSuggestedAnalysis($exportType)
            ]
        ]);
    }

    // Helper methods
    private function calculateResponseRate(Tournament $tournament): float
    {
        $eligibleReferees = $this->getEligibleRefereesCount($tournament);
        $responses = $tournament->availabilities->count();

        return $eligibleReferees > 0 ? round(($responses / $eligibleReferees) * 100, 1) : 0;
    }

    private function groupAvailabilitiesByLevel(Tournament $tournament): array
    {
        return $tournament->availabilities->groupBy('referee.level')
            ->map(function($group) {
                return [
                    'total' => $group->count(),
                    'available' => $group->where('is_available', true)->count(),
                    'unavailable' => $group->where('is_available', false)->count()
                ];
            })->toArray();
    }

    private function checkAssignmentRequirements(Tournament $tournament): array
    {
        $assignedCount = $tournament->assignments->count();
        $minRequired = $tournament->tournamentType?->min_referees ?? 0;
        $maxAllowed = $tournament->tournamentType?->max_referees ?? 999;

        return [
            'meets_minimum' => $assignedCount >= $minRequired,
            'within_maximum' => $assignedCount <= $maxAllowed,
            'assigned_count' => $assignedCount,
            'min_required' => $minRequired,
            'max_allowed' => $maxAllowed,
            'status' => $assignedCount >= $minRequired && $assignedCount <= $maxAllowed ? 'OK' : 'ISSUE'
        ];
    }

    private function getWorkflowStatus(Tournament $tournament): array
    {
        $status = [
            'current_phase' => $tournament->status,
            'can_declare_availability' => $tournament->status === 'open' && $tournament->availability_deadline > now(),
            'can_assign_referees' => $tournament->status === 'closed' && $tournament->availabilities->where('is_available', true)->count() > 0,
            'all_confirmed' => $tournament->assignments->where('is_confirmed', false)->count() === 0,
            'ready_for_tournament' => $tournament->status === 'assigned' && $tournament->assignments->where('is_confirmed', false)->count() === 0
        ];

        $status['next_action'] = $this->getNextAction($tournament, $status);

        return $status;
    }

    private function getNextAction(Tournament $tournament, array $status): string
    {
        if ($status['can_declare_availability']) {
            return 'Raccolta disponibilità in corso';
        }

        if ($status['can_assign_referees']) {
            return 'Pronto per assegnazioni arbitri';
        }

        if (!$status['all_confirmed']) {
            return 'In attesa conferme arbitri';
        }

        if ($status['ready_for_tournament']) {
            return 'Torneo pronto per svolgimento';
        }

        return 'Revisione stato necessaria';
    }

    private function getEligibleRefereesCount(Tournament $tournament): int
    {
        // Logica per contare arbitri eleggibili (stessa del seeder)
        if ($tournament->zone_id) {
            return User::where('user_type', 'referee')
                ->where('is_active', true)
                ->where(function($query) use ($tournament) {
                    $query->where('zone_id', $tournament->zone_id)
                          ->orWhereIn('level', ['nazionale', 'internazionale']);
                })->count();
        } else {
            return User::where('user_type', 'referee')
                ->where('is_active', true)
                ->whereIn('level', ['nazionale', 'internazionale'])
                ->count();
        }
    }

    private function calculateAvgResponseRate(?int $zoneFilter): float
    {
        $tournaments = Tournament::where('status', 'open')
            ->when($zoneFilter, fn($q) => $q->where('zone_id', $zoneFilter))
            ->withCount('availabilities')
            ->get();

        if ($tournaments->isEmpty()) return 0;

        $totalRate = $tournaments->sum(function($tournament) {
            return $this->calculateResponseRate($tournament);
        });

        return round($totalRate / $tournaments->count(), 1);
    }

    private function calculateAvgConfirmationRate(?int $zoneFilter): float
    {
        $assignments = Assignment::whereHas('tournament', function($q) use ($zoneFilter) {
            if ($zoneFilter) $q->where('zone_id', $zoneFilter);
        });

        $total = $assignments->count();
        $confirmed = $assignments->where('is_confirmed', true)->count();

        return $total > 0 ? round(($confirmed / $total) * 100, 1) : 0;
    }

    private function calculateRequirementsMet(?int $zoneFilter): float
    {
        $tournaments = Tournament::whereIn('status', ['assigned', 'completed'])
            ->when($zoneFilter, fn($q) => $q->where('zone_id', $zoneFilter))
            ->with('tournamentType')
            ->withCount('assignments')
            ->get();

        if ($tournaments->isEmpty()) return 100;

        $meetingRequirements = $tournaments->filter(function($tournament) {
            $assigned = $tournament->assignments_count;
            $min = $tournament->tournamentType?->min_referees ?? 0;
            $max = $tournament->tournamentType?->max_referees ?? 999;

            return $assigned >= $min && $assigned <= $max;
        })->count();

        return round(($meetingRequirements / $tournaments->count()) * 100, 1);
    }

    private function getLastSeedingDate(): ?string
    {
        $lastSeeding = Zone::latest('updated_at')->first()?->updated_at;
        return $lastSeeding?->format('Y-m-d H:i:s');
    }

    private function getSummaryExportData(?Zone $zone): array
    {
        return [
            'zones' => $zone ? [$zone] : Zone::all(),
            'summary_stats' => [
                'total_referees' => User::where('user_type', 'referee')->when($zone, fn($q) => $q->where('zone_id', $zone->id))->count(),
                'total_tournaments' => Tournament::when($zone, fn($q) => $q->where('zone_id', $zone->id))->count(),
                'total_assignments' => Assignment::when($zone, fn($q) => $q->whereHas('tournament', fn($qq) => $qq->where('zone_id', $zone->id)))->count()
            ]
        ];
    }

    private function getFullExportData(?Zone $zone): array
    {
        return [
            'zones' => $zone ? [$zone] : Zone::with(['clubs', 'users'])->get(),
            'tournaments' => Tournament::when($zone, fn($q) => $q->where('zone_id', $zone->id))->with(['club', 'tournamentType', 'availabilities', 'assignments'])->get(),
            'referees' => User::where('user_type', 'referee')->when($zone, fn($q) => $q->where('zone_id', $zone->id))->with('zone')->get()
        ];
    }

    private function getTournamentsExportData(?Zone $zone): array
    {
        return Tournament::when($zone, fn($q) => $q->where('zone_id', $zone->id))
            ->with(['zone', 'club', 'tournamentType', 'availabilities', 'assignments'])
            ->get()->toArray();
    }

    private function getRefereesExportData(?Zone $zone): array
    {
        return User::where('user_type', 'referee')
            ->when($zone, fn($q) => $q->where('zone_id', $zone->id))
            ->with(['zone', 'availabilities', 'assignments'])
            ->get()->toArray();
    }

    private function getWorkflowExportData(?Zone $zone): array
    {
        return [
            'availabilities' => Availability::when($zone, fn($q) => $q->whereHas('tournament', fn($qq) => $qq->where('zone_id', $zone->id)))->with(['tournament', 'referee'])->get(),
            'assignments' => Assignment::when($zone, fn($q) => $q->whereHas('tournament', fn($qq) => $qq->where('zone_id', $zone->id)))->with(['tournament', 'referee'])->get()
        ];
    }

    private function getExportCounts(array $data): array
    {
        $counts = [];

        foreach ($data as $key => $items) {
            if (is_array($items) || is_countable($items)) {
                $counts[$key] = count($items);
            }
        }

        return $counts;
    }

    private function getSuggestedAnalysis(string $exportType): array
    {
        return match($exportType) {
            'full' => ['Zone comparison', 'Performance benchmarking', 'Resource allocation'],
            'tournaments' => ['Success rate analysis', 'Timeline optimization', 'Resource planning'],
            'referees' => ['Workload distribution', 'Skill gap analysis', 'Training needs'],
            'workflow' => ['Process efficiency', 'Bottleneck identification', 'Automation opportunities'],
            default => ['General system health', 'Usage patterns', 'Growth trends']
        };
    }
}
