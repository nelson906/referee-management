<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RefereeReportController extends Controller
{
    /**
     * Display referee reports listing.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        // Base query for referees
        $query = User::where('user_type', 'referee')
            ->with(['zone', 'assignments'])
            ->withCount([
                'assignments',
                'assignments as current_year_assignments' => function($q) {
                    $q->whereYear('created_at', now()->year);
                },
                'availabilities'
            ]);

        // Apply zone restrictions for non-super admins
        if (!in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $query->where('zone_id', $user->zone_id);
        }

        // Apply filters
        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $referees = $query->orderBy('name')->paginate(20);

        // Get filter options
        $zones = $this->getAccessibleZones($user);
        $levels = ['Aspirante', '1_livello', 'Regionale', 'Nazionale', 'Internazionale'];

        return view('reports.referees.index', compact('referees', 'zones', 'levels'));
    }

    /**
     * Show specific referee report.
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

        return view('reports.referees.show', compact('referee', 'stats', 'recentAssignments'));
    }

    /**
     * Get zones accessible to user.
     */
    private function getAccessibleZones($user)
    {
        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            return Zone::orderBy('name')->get();
        }

        return Zone::where('id', $user->zone_id)->get();
    }

    /**
     * Check referee access permissions.
     */
    private function checkRefereeAccess(User $referee): void
    {
        $user = auth()->user();

        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            return;
        }

        if ($user->user_type === 'admin' && $user->zone_id === $referee->zone_id) {
            return;
        }

        if ($user->isReferee() && $user->id === $referee->id) {
            return;
        }

        abort(403, 'Non sei autorizzato ad accedere a questo report.');
    }
}
