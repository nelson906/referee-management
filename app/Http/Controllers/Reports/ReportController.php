<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
}
