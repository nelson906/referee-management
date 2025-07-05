<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Assignment;
use App\Models\Club;
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
        // Base queries for tournaments and assignments
        $tournamentsQuery = Tournament::query();
        $assignmentsQuery = Assignment::query();
        $refereesQuery = User::where('user_type', 'referee');
        $clubsQuery = Club::query();

        // Apply zone filtering for non-national admins
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $tournamentsQuery->where('zone_id', $user->zone_id);
            $assignmentsQuery->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
            $refereesQuery->where('zone_id', $user->zone_id);
            $clubsQuery->where('zone_id', $user->zone_id);
        }

        return [
            'total_tournaments' => $tournamentsQuery->count(),
            'active_tournaments' => $tournamentsQuery->active()->count(),
            'upcoming_tournaments' => $tournamentsQuery->upcoming()->count(),
            'draft_tournaments' => $tournamentsQuery->where('status', 'draft')->count(),

            'total_referees' => $refereesQuery->count(),
            'active_referees' => $refereesQuery->where('is_active', true)->count(),
            'inactive_referees' => $refereesQuery->where('is_active', false)->count(),

            'total_assignments' => $assignmentsQuery->count(),
            'unconfirmed_assignments' => $assignmentsQuery->where('is_confirmed', false)->count(),
            'confirmed_assignments' => $assignmentsQuery->where('is_confirmed', true)->count(),
            'current_year_assignments' => $assignmentsQuery->whereYear('created_at', now()->year)->count(),

            'total_clubs' => $clubsQuery->count(),
            'active_clubs' => $clubsQuery->where('is_active', true)->count(),
        ];
    }

    /**
     * Get alerts for the dashboard.
     */
    private function getAlerts($user, $isNationalAdmin): array
    {
        $alerts = [];

        // Check for unconfirmed assignments
        $unconfirmedQuery = Assignment::where('is_confirmed', false);
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $unconfirmedQuery->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }
        $unconfirmedCount = $unconfirmedQuery->count();

        if ($unconfirmedCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Ci sono {$unconfirmedCount} assegnazioni non ancora confermate dagli arbitri.",
                'action_url' => route('admin.assignments.index', ['status' => 'unconfirmed']),
                'action_text' => 'Visualizza'
            ];
        }

        // Check for tournaments needing referees
        $tournamentsNeedingReferees = $this->getTournamentsNeedingReferees($user, $isNationalAdmin);
        if ($tournamentsNeedingReferees->count() > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "Ci sono {$tournamentsNeedingReferees->count()} tornei che necessitano ancora di arbitri.",
                'action_url' => route('admin.tournaments.index', ['status' => 'open']),
                'action_text' => 'Visualizza'
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

        // Apply zone filtering for non-national admins
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->where('zone_id', $user->zone_id);
        }

        return $query->get()->filter(function ($tournament) {
            $assignedCount = $tournament->assignments()->count();
            $minRequired = $tournament->tournamentCategory->min_referees ?? 1;
            return $assignedCount < $minRequired;
        })->take(5);
    }

    /**
     * Get recent assignments.
     */
    private function getRecentAssignments($user, $isNationalAdmin)
    {
        $query = Assignment::with([
            'user:id,name,referee_code',
            'tournament:id,name,start_date,club_id',
            'tournament.club:id,name'
        ])->orderBy('created_at', 'desc');

        // Apply zone filtering for non-national admins
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        return $query->limit(5)->get();
    }
}
