<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Assignment;
use Illuminate\View\View;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(Request $request)
    {
        $year = session('selected_year', date('Y'));
        $user = auth()->user();

        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        // STATISTICHE COMPLETE
        $stats = [
            'total_tournaments' => Tournament::count(),
            'open_tournaments' => Tournament::where('status', 'open')->count(),
            'completed_tournaments' => Tournament::where('status', 'completed')->count(),
            'total_assignments' => Assignment::count(),
            'pending_assignments' => Assignment::where('is_confirmed', false)->count(),
            'active_referees' => User::where('user_type', 'referee')
                ->where('is_active', true)
                ->when(!$isNationalAdmin, fn($q) => $q->where('zone_id', $user->zone_id))
                ->count(),
        ];

        // Prossimi tornei
        $upcomingTournaments = Tournament::with(['club', 'zone'])
            ->where('start_date', '>=', now())
            ->when($user->user_type === 'admin', fn($q) => $q->where('zone_id', $user->zone_id))
            ->orderBy('start_date')
            ->limit(10)
            ->get();

        // Tornei che necessitano arbitri
        $tournamentsNeedingReferees = Tournament::with(['club', 'assignments', 'tournamentType'])
            ->where('status', 'open')
            ->get()
            ->filter(function ($tournament) {
                $assigned = $tournament->assignments->count();
                $required = $tournament->tournamentType->min_referees ?? 2;
                return $assigned < $required;
            })
            ->take(10);


        // Alerts
        $alerts = [];

        // Alert tornei urgenti
        $urgentTournaments = DB::table("tournaments_{$year}")
            ->whereNotExists(function ($q) use ($year) {
                $q->from("assignments_{$year}")
                    ->whereRaw("assignments_{$year}.tournament_id = tournaments_{$year}.id");
            })
            ->where('status', 'open')
            ->where('start_date', '<=', now()->addDays(7))
            ->count();

        if ($urgentTournaments > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Tornei Urgenti',
                'message' => "Ci sono {$urgentTournaments} tornei che iniziano entro 7 giorni senza arbitri assegnati",
                'icon' => 'exclamation'
            ];
        }

        // Alert assegnazioni non confermate
        if ($stats['pending_assignments'] > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Conferme in Attesa',
                'message' => "Ci sono {$stats['pending_assignments']} assegnazioni in attesa di conferma",
                'icon' => 'clock'
            ];
        }

        // Alert deadline disponibilità
        $deadlineToday = DB::table("tournaments_{$year}")
            ->where('availability_deadline', '=', now()->toDateString())
            ->where('status', 'open')
            ->count();

        if ($deadlineToday > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Deadline Oggi',
                'message' => "{$deadlineToday} tornei hanno la deadline per le disponibilità oggi",
                'icon' => 'calendar'
            ];
        }

        // Attività recenti
        $recentActivities = [];

        // Ultime assegnazioni
        $recentAssignments = DB::table("assignments_{$year} as a")
            ->join('users as u', 'a.user_id', '=', 'u.id')
            ->join("tournaments_{$year} as t", 'a.tournament_id', '=', 't.id')
            ->select(
                'u.name as referee_name',
                't.name as tournament_name',
                'a.role',
                'a.created_at'
            )
            ->orderBy('a.created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentAssignments as $assignment) {
            $recentActivities[] = [
                'type' => 'assignment',
                'message' => "{$assignment->referee_name} assegnato come {$assignment->role} a {$assignment->tournament_name}",
                'time' => $assignment->created_at
            ];
        }

        return view('admin.dashboard', compact(
            'stats',
            'upcomingTournaments',
            'tournamentsNeedingReferees',
            'year',
            'isNationalAdmin',
            'alerts',
            'recentActivities',
            'recentAssignments',
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
            $assignmentQuery->whereHas('tournament', function ($q) use ($user) {
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
            $assignmentQuery->whereHas('tournament', function ($q) use ($user) {
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
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        return $query->limit(10)->get();
    }
}
