<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Display reports dashboard.
     */
    public function index(): View
    {
        $user = auth()->user();

        // Get user's accessible zones
        $zones = $this->getAccessibleZones($user);

        // Get recent tournaments
        $recentTournaments = $this->getAccessibleTournaments($user)
            ->with(['club', 'zone', 'tournamentType'])
            ->orderBy('start_date', 'desc')
            ->limit(10)
            ->get();

        // Get statistics based on user access
        $stats = $this->getAccessibleStats($user);

        return view('reports.index', compact('zones', 'recentTournaments', 'stats'));
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
     * Get tournaments accessible to the user.
     */
    private function getAccessibleTournaments($user)
    {
        $query = Tournament::query();

        if ($user->user_type === 'admin' && $user->user_type !== 'super_admin' && $user->user_type !== 'national_admin') {
            $query->where('zone_id', $user->zone_id);
        }

        return $query;
    }

    /**
     * Get statistics based on user access.
     */
    private function getAccessibleStats($user): array
    {
        $tournamentsQuery = $this->getAccessibleTournaments($user);
        $assignmentsQuery = Assignment::query();

        if ($user->user_type === 'admin' && $user->user_type !== 'super_admin' && $user->user_type !== 'national_admin') {
            $assignmentsQuery->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        return [
            'total_tournaments' => $tournamentsQuery->count(),
            'upcoming_tournaments' => $tournamentsQuery->upcoming()->count(),
            'active_tournaments' => $tournamentsQuery->active()->count(),
            'total_assignments' => $assignmentsQuery->count(),
            'confirmed_assignments' => $assignmentsQuery->where('is_confirmed', true)->count(),
            'current_year_assignments' => $assignmentsQuery->whereYear('created_at', now()->year)->count(),
        ];
    }
        /**
     * Generate assignment report
     */
    public function assignments(Request $request)
    {
        $year = $request->get('year', session('selected_year', date('Y')));
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = DB::table("assignments_{$year} as a")
            ->join("tournaments_{$year} as t", 'a.tournament_id', '=', 't.id')
            ->join('users as u', 'a.user_id', '=', 'u.id')
            ->leftJoin('clubs as c', 't.club_id', '=', 'c.id')
            ->leftJoin('zones as z', 't.zone_id', '=', 'z.id');

        if ($startDate) {
            $query->where('t.start_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('t.start_date', '<=', $endDate);
        }

        $assignments = $query->select(
            't.name as tournament_name',
            't.start_date',
            'c.name as club_name',
            'z.name as zone_name',
            'u.name as referee_name',
            'a.role',
            'a.is_confirmed'
        )
        ->orderBy('t.start_date')
        ->get();

        return view('admin.reports.assignments', compact('assignments', 'year'));
    }

    /**
     * Referee statistics
     */
    public function refereeStats(Request $request)
    {
        $year = $request->get('year', session('selected_year', date('Y')));

        $stats = DB::table('users as u')
            ->where('u.user_type', 'referee')
            ->leftJoin(
                DB::raw("(SELECT user_id, COUNT(*) as assignments_count,
                         SUM(CASE WHEN role = 'Direttore di Torneo' THEN 1 ELSE 0 END) as td_count,
                         SUM(CASE WHEN role = 'Arbitro' THEN 1 ELSE 0 END) as arbitro_count,
                         SUM(CASE WHEN role = 'Osservatore' THEN 1 ELSE 0 END) as osservatore_count
                         FROM assignments_{$year}
                         GROUP BY user_id) as a"),
                'u.id', '=', 'a.user_id'
            )
            ->select(
                'u.name',
                'u.email',
                'u.level',
                DB::raw('COALESCE(a.assignments_count, 0) as total_assignments'),
                DB::raw('COALESCE(a.td_count, 0) as td_count'),
                DB::raw('COALESCE(a.arbitro_count, 0) as arbitro_count'),
                DB::raw('COALESCE(a.osservatore_count, 0) as osservatore_count')
            )
            ->orderBy('total_assignments', 'desc')
            ->get();

        return view('admin.reports.referee-stats', compact('stats', 'year'));
    }

}
