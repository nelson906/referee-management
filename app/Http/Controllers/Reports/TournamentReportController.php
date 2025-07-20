<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Zone;
use App\Models\TournamentType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TournamentReportController extends Controller
{
    /**
     * Display tournaments reports listing.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        // Base query
        $query = Tournament::with(['club', 'zone', 'tournamentType'])
            ->withCount(['assignments', 'availabilities']);

        // Apply zone restrictions
        if (!in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $query->where('zone_id', $user->zone_id);
        }

        // Apply filters
        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        if ($request->filled('tournament_type_id')) {
            $query->where('tournament_type_id', $request->tournament_type_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('start_date', '<=', $request->date_to);
        }

        $tournaments = $query->orderBy('start_date', 'desc')->paginate(20);

        // Get filter options
        $zones = $this->getAccessibleZones($user);
        $types = TournamentType::orderBy('name')->get();
        $statuses = [
            'draft' => 'Bozza',
            'open' => 'Aperto',
            'closed' => 'Chiuso',
            'assigned' => 'Assegnato',
            'completed' => 'Completato'
        ];

        return view('reports.tournaments.index', compact('tournaments', 'zones', 'types', 'statuses'));
    }

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
            'tournamentType',
            'availabilities.user',
            'assignments.user',
            'assignments.assignedBy'
        ]);

        // Get statistics
        $stats = [
            'total_availabilities' => $tournament->availabilities()->count(),
            'total_assignments' => $tournament->assignments()->count(),
            'confirmed_assignments' => $tournament->assignments()->where('is_confirmed', true)->count(),
            'days_until_start' => $tournament->start_date ? now()->diffInDays($tournament->start_date, false) : null,
        ];

        return view('reports.tournaments.show', compact('tournament', 'stats'));
    }

    /**
     * Get accessible zones.
     */
    private function getAccessibleZones($user)
    {
        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            return Zone::orderBy('name')->get();
        }

        return Zone::where('id', $user->zone_id)->get();
    }

    /**
     * Check tournament access permissions.
     */
    private function checkTournamentAccess(Tournament $tournament): void
    {
        $user = auth()->user();

        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            return;
        }

        if ($user->user_type === 'admin' && $user->zone_id !== $tournament->zone_id) {
            abort(403, 'Non sei autorizzato ad accedere ai report di questo torneo.');
        }
    }
}
