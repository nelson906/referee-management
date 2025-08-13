<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Assignment;
use Illuminate\View\View;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DB;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(Request $request)
    {
        $year = session('selected_year', date('Y'));
        $user = auth()->user();

        // Statistiche per anno selezionato
        $stats = [
            'total_tournaments' => DB::table("tournaments_{$year}")->count(),
            'open_tournaments' => DB::table("tournaments_{$year}")
                ->where('status', 'open')
                ->count(),
            'total_assignments' => DB::table("assignments_{$year}")->count(),
            'pending_assignments' => DB::table("assignments_{$year}")
                ->where('is_confirmed', false)
                ->count(),
        ];

        // Prossimi tornei
        $upcomingTournaments = DB::table("tournaments_{$year}")
            ->where('start_date', '>=', now())
            ->when($user->user_type === 'admin', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->orderBy('start_date')
            ->limit(10)
            ->get();

        // Tornei che necessitano arbitri
        $tournamentsNeedingReferees = DB::table("tournaments_{$year} as t")
            ->leftJoin(
                DB::raw("(SELECT tournament_id, COUNT(*) as count FROM assignments_{$year} GROUP BY tournament_id) as a"),
                't.id', '=', 'a.tournament_id'
            )
            ->where('t.status', 'open')
            ->whereRaw('COALESCE(a.count, 0) < t.required_referees')
            ->select('t.*', DB::raw('COALESCE(a.count, 0) as assigned_count'))
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'upcomingTournaments',
            'tournamentsNeedingReferees',
            'year'
        ));
    }


    /**
     * Get statistics for the dashboard.
     */
    private function getStatistics($user, $isNationalAdmin): array
    {
        $query = Tournament::query();

        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->where('zone_id', $user->zone_id);
        }

        $totalTournaments = $query->count();
        $activeTournaments = (clone $query)->whereIn('status', ['open', 'closed', 'assigned'])->count();
        $completedTournaments = (clone $query)->where('status', 'completed')->count();

        $assignmentQuery = Assignment::query();
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $assignmentQuery->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        $totalAssignments = $assignmentQuery->count();
        $pendingConfirmations = (clone $assignmentQuery)->where('is_confirmed', false)->count();

        $refereeQuery = User::where('user_type', 'referee')->where('is_active', true);
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $refereeQuery->where('zone_id', $user->zone_id);
        }

        $activeReferees = $refereeQuery->count();

        return [
            'total_tournaments' => $totalTournaments,
            'active_tournaments' => $activeTournaments,
            'completed_tournaments' => $completedTournaments,
            'total_assignments' => $totalAssignments,
            'pending_confirmations' => $pendingConfirmations,
            'active_referees' => $activeReferees,
            'zones_count' => $isNationalAdmin ? \App\Models\Zone::count() : 1,
            'upcoming_tournaments' => (clone $query)->where('start_date', '>=', Carbon::today())->count(),
        ];
    }

    /**
     * Get alerts for the dashboard.
     */
    private function getAlerts($user, $isNationalAdmin): array
    {
        $alerts = [];

        // Check for tournaments needing referees
        $tournamentsNeedingReferees = $this->getTournamentsNeedingReferees($user, $isNationalAdmin)->count();
        if ($tournamentsNeedingReferees > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Ci sono {$tournamentsNeedingReferees} tornei che necessitano di arbitri."
            ];
        }

        // Check for unconfirmed assignments
        $assignmentQuery = Assignment::where('is_confirmed', false);
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $assignmentQuery->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }
        $unconfirmedAssignments = $assignmentQuery->count();

        if ($unconfirmedAssignments > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "Ci sono {$unconfirmedAssignments} assegnazioni in attesa di conferma."
            ];
        }

        return $alerts;
    }

    /**
     * Get tournaments that need referees.
     */
    private function getTournamentsNeedingReferees($user, $isNationalAdmin)
    {
        $query = Tournament::with(['club', 'tournamentType'])
            ->whereIn('status', ['open', 'closed'])
            ->where('start_date', '>=', Carbon::today());

        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->where('zone_id', $user->zone_id);
        }

        // Filter tournaments that need more referees
        return $query->get()->filter(function ($tournament) {
            $assignedReferees = $tournament->assignments()->count();
            $requiredReferees = $tournament->tournamentType->max_referees ?? 1;
            return $assignedReferees < $requiredReferees;
        })->take(10);
    }

    /**
     * Get recent assignments.
     */
    private function getRecentAssignments($user, $isNationalAdmin)
    {
        $query = Assignment::with(['user', 'tournament.club', 'assignedBy'])
            ->orderBy('created_at', 'desc');

        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        return $query->limit(10)->get();
    }
}
