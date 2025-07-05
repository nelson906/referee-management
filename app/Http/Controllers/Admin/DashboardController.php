<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Assignment;
use Illuminate\View\View;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(): View
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin';

        // Get statistics based on user access
        $stats = $this->getStatistics($user, $isNationalAdmin);

        // Get alerts (unconfirmed assignments, tournaments needing referees, etc.)
        $alerts = $this->getAlerts($user, $isNationalAdmin);

        // Get tournaments that need referees
        $tournamentsNeedingReferees = $this->getTournamentsNeedingReferees($user, $isNationalAdmin);

        // Get recent assignments
        $recentAssignments = $this->getRecentAssignments($user, $isNationalAdmin);

        return view('admin.dashboard', compact(
            'isNationalAdmin',
            'stats',
            'alerts',
            'tournamentsNeedingReferees',
            'recentAssignments'
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
        $query = Tournament::with(['club', 'tournamentCategory'])
            ->whereIn('status', ['open', 'closed'])
            ->where('start_date', '>=', Carbon::today());

        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->where('zone_id', $user->zone_id);
        }

        // Filter tournaments that need more referees
        return $query->get()->filter(function ($tournament) {
            $assignedReferees = $tournament->assignments()->count();
            $requiredReferees = $tournament->tournamentCategory->max_referees ?? 1;
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
