<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

class ZoneReportController extends Controller
{
    /**
     * Display zone overview report.
     */
    public function show(Zone $zone): View
    {
        // Check access
        $this->checkZoneAccess($zone);

        // Get statistics
        $stats = [
            'total_referees' => $zone->referees()->count(),
            'active_referees' => $zone->referees()->where('is_active', true)->count(),
            'total_clubs' => $zone->clubs()->count(),
            'active_clubs' => $zone->clubs()->where('is_active', true)->count(),
            'total_tournaments' => $zone->tournaments()->count(),
            'upcoming_tournaments' => $zone->tournaments()->upcoming()->count(),
            'active_tournaments' => $zone->tournaments()->active()->count(),
            'completed_tournaments' => $zone->tournaments()->where('status', 'completed')->count(),
        ];

        // Get referees by level
        $refereesByLevel = $zone->referees()
            ->where('is_active', true)
            ->selectRaw('level, COUNT(*) as total')
            ->groupBy('level')
            ->get()
            ->pluck('total', 'level')
            ->toArray();

        // Get recent tournaments
        $recentTournaments = $zone->tournaments()
            ->with(['club', 'tournamentType'])
            ->orderBy('start_date', 'desc')
            ->limit(10)
            ->get();

        // Get assignment statistics
        $assignmentStats = [
            'total_assignments' => Assignment::whereHas('tournament', function($q) use ($zone) {
                $q->where('zone_id', $zone->id);
            })->count(),
            'confirmed_assignments' => Assignment::whereHas('tournament', function($q) use ($zone) {
                $q->where('zone_id', $zone->id);
            })->where('is_confirmed', true)->count(),
            'current_year' => Assignment::whereHas('tournament', function($q) use ($zone) {
                $q->where('zone_id', $zone->id);
            })->whereYear('created_at', now()->year)->count(),
        ];

        return view('reports.zone.show', compact(
            'zone',
            'stats',
            'refereesByLevel',
            'recentTournaments',
            'assignmentStats'
        ));
    }

    /**
     * Display zone referees report.
     */
    public function referees(Zone $zone): View
    {
        $this->checkZoneAccess($zone);

        $referees = $zone->referees()
            ->with(['assignments' => function($q) {
                $q->whereYear('created_at', now()->year);
            }])
            ->withCount([
                'assignments',
                'assignments as current_year_assignments' => function($q) {
                    $q->whereYear('created_at', now()->year);
                },
                'availabilities'
            ])
            ->orderBy('is_active', 'desc')
            ->orderBy('level')
            ->orderBy('name')
            ->paginate(20);

        return view('reports.zone.referees', compact('zone', 'referees'));
    }

    /**
     * Display zone tournaments report.
     */
    public function tournaments(Zone $zone): View
    {
        $this->checkZoneAccess($zone);

        $tournaments = $zone->tournaments()
            ->with(['club', 'tournamentType'])
            ->withCount(['assignments', 'availabilities'])
            ->orderBy('start_date', 'desc')
            ->paginate(20);

        return view('reports.zone.tournaments', compact('zone', 'tournaments'));
    }

    /**
     * Export zone report.
     */
    public function export(Zone $zone)
    {
        $this->checkZoneAccess($zone);

        // TODO: Implement export functionality
        return response()->json([
            'message' => 'Export functionality coming soon'
        ]);
    }

    /**
     * Check if user can access zone reports.
     */
    private function checkZoneAccess(Zone $zone): void
    {
        $user = auth()->user();

        if ($user->user_type === 'super_admin' || $user->user_type === 'national_admin') {
            return;
        }

        if ($user->user_type === 'admin' && $user->zone_id !== $zone->id) {
            abort(403, 'Non sei autorizzato ad accedere ai report di questa zona.');
        }
    }
}
