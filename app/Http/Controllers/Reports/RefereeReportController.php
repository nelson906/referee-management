<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class RefereeReportController extends Controller
{
    /**
     * Display referee report.
     */
    public function show(User $referee): View
    {
        // Check that user is actually a referee
        if (!$referee->isReferee()) {
            abort(404, 'Utente non trovato.');
        }

        // Check access
        $this->checkRefereeAccess($referee);

        // Load relationships and statistics
        $referee->load([
            'zone',
            'assignments.tournament.club',
            'availabilities.tournament.club'
        ]);

        // Get statistics
        $stats = [
            'total_assignments' => $referee->assignments()->count(),
            'confirmed_assignments' => $referee->assignments()->where('is_confirmed', true)->count(),
            'current_year_assignments' => $referee->assignments()->whereYear('created_at', now()->year)->count(),
            'total_availabilities' => $referee->availabilities()->count(),
            'current_year_availabilities' => $referee->availabilities()->whereYear('created_at', now()->year)->count(),
            'upcoming_assignments' => $referee->assignments()->upcoming()->count(),
        ];

        // Get recent assignments
        $recentAssignments = $referee->assignments()
            ->with(['tournament.club', 'tournament.zone'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('reports.referee.show', compact('referee', 'stats', 'recentAssignments'));
    }

    /**
     * Export referee report.
     */
    public function export(User $referee)
    {
        $this->checkRefereeAccess($referee);

        // TODO: Implement export functionality
        return response()->json([
            'message' => 'Export functionality coming soon'
        ]);
    }

    /**
     * Check if user can access referee reports.
     */
    private function checkRefereeAccess(User $referee): void
    {
        $user = auth()->user();

        // Super admin and national admin can access all
        if ($user->user_type === 'super_admin' || $user->user_type === 'national_admin') {
            return;
        }

        // Zone admin can access referees in their zone
        if ($user->user_type === 'admin' && $user->zone_id === $referee->zone_id) {
            return;
        }

        // Referees can only access their own reports
        if ($user->isReferee() && $user->id === $referee->id) {
            return;
        }

        abort(403, 'Non sei autorizzato ad accedere a questo report.');
    }
}
