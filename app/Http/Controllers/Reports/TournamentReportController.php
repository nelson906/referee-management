<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\View\View;

class TournamentReportController extends Controller
{
    /**
     * Display tournament report.
     */
    public function show(Tournament $tournament): View
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Load relationships
        $tournament->load([
            'club',
            'zone',
            'tournamentCategory',
            'availabilities.user',
            'assignments.user',
            'assignments.assignedBy'
        ]);

        // Get statistics
        $stats = [
            'total_availabilities' => $tournament->availabilities()->count(),
            'total_assignments' => $tournament->assignments()->count(),
            'confirmed_assignments' => $tournament->assignments()->where('is_confirmed', true)->count(),
            'required_referees' => $tournament->required_referees,
            'max_referees' => $tournament->max_referees,
            'days_until_start' => $tournament->start_date ? now()->diffInDays($tournament->start_date, false) : null,
        ];

        return view('reports.tournament.show', compact('tournament', 'stats'));
    }

    /**
     * Export tournament report.
     */
    public function export(Tournament $tournament)
    {
        $this->checkTournamentAccess($tournament);

        // TODO: Implement export functionality
        return response()->json([
            'message' => 'Export functionality coming soon'
        ]);
    }

    /**
     * Check if user can access tournament reports.
     */
    private function checkTournamentAccess(Tournament $tournament): void
    {
        $user = auth()->user();

        if ($user->user_type === 'super_admin' || $user->user_type === 'national_admin') {
            return;
        }

        if ($user->user_type === 'admin' && $user->zone_id !== $tournament->zone_id) {
            abort(403, 'Non sei autorizzato ad accedere ai report di questo torneo.');
        }
    }
}
