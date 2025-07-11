<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Zone;
use App\Models\TournamentType; // ✅ FIXED: Changed from TournamentCategory
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

class AssignmentReportController extends Controller
{
    /**
     * Display assignments report.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        $query = Assignment::with([
            'user:id,name,referee_code,level',
            // ✅ FIXED: Changed tournament.tournamentCategory to tournament.tournamentType
            'tournament:id,name,start_date,end_date,zone_id,club_id,tournament_type_id',
            'tournament.club:id,name',
            'tournament.zone:id,name',
            'tournament.tournamentType:id,name', // ← FIXED: was tournamentCategory
            'assignedBy:id,name'
        ]);

        // Apply access restrictions
        if ($user->user_type === 'admin' && !in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $query->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Apply filters
        if ($request->filled('zone_id')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'confirmed') {
                $query->where('is_confirmed', true);
            } elseif ($request->status === 'unconfirmed') {
                $query->where('is_confirmed', false);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('start_date', '>=', $request->date_from);
            });
        }

        if ($request->filled('date_to')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('start_date', '<=', $request->date_to);
            });
        }

        // ✅ FIXED: Changed from category_id to tournament_type_id
        if ($request->filled('tournament_type_id')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('tournament_type_id', $request->tournament_type_id);
            });
        }

        $assignments = $query
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Get filter options
        $zones = $this->getAccessibleZones($user);

        // ✅ FIXED: Variable name from $categories to $tournamentTypes
        $tournamentTypes = TournamentType::active()->ordered()->get();

        // Get statistics
        $stats = $this->getAssignmentStats($user, $request);

        // ✅ FIXED: compact() uses tournamentTypes instead of categories
        return view('reports.assignments.index', compact(
            'assignments',
            'zones',
            'tournamentTypes', // ← FIXED: was 'categories'
            'stats'
        ));
    }

    /**
     * Get assignment statistics.
     */
    private function getAssignmentStats($user, $request): array
    {
        $query = Assignment::query();

        // Apply same access restrictions as main query
        if ($user->user_type === 'admin' && !in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $query->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Apply same filters as main query
        if ($request->filled('zone_id')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->whereHas('tournament', function($q) use ($request) {
                if ($request->filled('date_from')) {
                    $q->where('start_date', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $q->where('start_date', '<=', $request->date_to);
                }
            });
        }

        // ✅ FIXED: Changed tournament_category_id to tournament_type_id
        if ($request->filled('tournament_type_id')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('tournament_type_id', $request->tournament_type_id);
            });
        }

        return [
            'total' => $query->count(),
            'confirmed' => $query->where('is_confirmed', true)->count(),
            'unconfirmed' => $query->where('is_confirmed', false)->count(),
            'current_year' => $query->whereYear('created_at', now()->year)->count(),
            'this_month' => $query->whereMonth('created_at', now()->month)
                                 ->whereYear('created_at', now()->year)->count(),
        ];
    }

    /**
     * Get zones accessible to the user.
     */
    private function getAccessibleZones($user)
    {
        if ($user->user_type === 'super_admin' || $user->user_type === 'national_admin') {
            return Zone::orderBy('name')->get();
        }

        return Zone::where('id', $user->zone_id)->get();
    }

    /**
     * Export assignments report.
     */
    public function export(Request $request)
    {
        $user = auth()->user();

        $query = Assignment::with([
            'user:id,name,referee_code,level',
            // ✅ FIXED: tournamentType relationship
            'tournament:id,name,start_date,end_date,zone_id,club_id,tournament_type_id',
            'tournament.club:id,name',
            'tournament.zone:id,name',
            'tournament.tournamentType:id,name', // ← FIXED
            'assignedBy:id,name'
        ]);

        // Apply same access restrictions and filters as index method
        if ($user->user_type === 'admin' && !in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $query->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Apply filters (same logic as index)
        if ($request->filled('zone_id')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'confirmed') {
                $query->where('is_confirmed', true);
            } elseif ($request->status === 'unconfirmed') {
                $query->where('is_confirmed', false);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('start_date', '>=', $request->date_from);
            });
        }

        if ($request->filled('date_to')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('start_date', '<=', $request->date_to);
            });
        }

        // ✅ FIXED: tournament_type_id filter
        if ($request->filled('tournament_type_id')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('tournament_type_id', $request->tournament_type_id);
            });
        }

        $assignments = $query->orderBy('created_at', 'desc')->get();

        $filename = 'assignments_report_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($assignments) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'ID',
                'Arbitro',
                'Codice Arbitro',
                'Livello',
                'Torneo',
                'Categoria Torneo', // ✅ Column name clarified
                'Club',
                'Zona',
                'Ruolo',
                'Data Assegnazione',
                'Confermato',
                'Assegnato da',
                'Note'
            ]);

            foreach ($assignments as $assignment) {
                fputcsv($file, [
                    $assignment->id,
                    $assignment->user->name,
                    $assignment->user->referee_code ?? 'N/A',
                    $assignment->user->level ?? 'N/A',
                    $assignment->tournament->name,
                    // ✅ FIXED: tournamentType relationship
                    $assignment->tournament->tournamentType->name ?? 'N/A',
                    $assignment->tournament->club->name ?? 'N/A',
                    $assignment->tournament->zone->name ?? 'N/A',
                    $assignment->role,
                    $assignment->assigned_at?->format('d/m/Y H:i') ?? 'N/A',
                    $assignment->is_confirmed ? 'Sì' : 'No',
                    $assignment->assignedBy->name ?? 'N/A',
                    $assignment->notes ?? ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show detailed assignment analytics.
     */
    public function analytics(Request $request): View
    {
        $user = auth()->user();
        $startDate = $request->get('start_date', now()->subMonths(6)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        // Base query with proper access control
        $baseQuery = Assignment::query()
            ->when($user->user_type === 'admin' && !in_array($user->user_type, ['super_admin', 'national_admin']), function($q) use ($user) {
                $q->whereHas('tournament', function($subQ) use ($user) {
                    $subQ->where('zone_id', $user->zone_id);
                });
            });

        // Assignments by tournament type
        $assignmentsByType = $baseQuery->clone()
            ->with(['tournament.tournamentType']) // ✅ FIXED relationship
            ->whereHas('tournament', function($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate]);
            })
            ->get()
            ->groupBy(function($assignment) {
                // ✅ FIXED: tournamentType relationship
                return $assignment->tournament->tournamentType->name ?? 'Sconosciuta';
            })
            ->map(function($assignments) {
                return $assignments->count();
            });

        // Assignments by month
        $assignmentsByMonth = $baseQuery->clone()
            ->whereHas('tournament', function($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate]);
            })
            ->get()
            ->groupBy(function($assignment) {
                return $assignment->tournament->start_date->format('Y-m');
            })
            ->map(function($assignments) {
                return $assignments->count();
            })
            ->sortKeys();

        // Top referees by assignments
        $topReferees = $baseQuery->clone()
            ->with(['user'])
            ->whereHas('tournament', function($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate]);
            })
            ->get()
            ->groupBy('user_id')
            ->map(function($assignments) {
                return [
                    'referee' => $assignments->first()->user,
                    'count' => $assignments->count(),
                    'confirmed' => $assignments->where('is_confirmed', true)->count()
                ];
            })
            ->sortByDesc('count')
            ->take(10);

        // Zone distribution (only for national admins)
        $zoneDistribution = collect();
        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $zoneDistribution = $baseQuery->clone()
                ->with(['tournament.zone'])
                ->whereHas('tournament', function($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate]);
                })
                ->get()
                ->groupBy(function($assignment) {
                    return $assignment->tournament->zone->name ?? 'Sconosciuta';
                })
                ->map(function($assignments) {
                    return $assignments->count();
                });
        }

        return view('reports.assignments.analytics', compact(
            'assignmentsByType',
            'assignmentsByMonth',
            'topReferees',
            'zoneDistribution',
            'startDate',
            'endDate'
        ));
    }
}
