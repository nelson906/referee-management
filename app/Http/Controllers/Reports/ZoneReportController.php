<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoneReportController extends Controller
{
    /**
     * Display zones reports listing.
     */
    public function index(): View
    {
        $user = auth()->user();

        // Get accessible zones with statistics
        $query = Zone::withCount([
            'users',
            'tournaments',
            'clubs',
            'users as referees_count' => function($q) {
                $q->where('user_type', 'referee');
            }
        ]);

        // Apply access restrictions
        if (!in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $query->where('id', $user->zone_id);
        }

        $zones = $query->orderBy('name')->get();

        return view('reports.zones.index', compact('zones'));
    }

    /**
     * Display zone overview report.
     */
    public function show(Zone $zone): View
    {
        // Check access
        $this->checkZoneAccess($zone);

        // Get statistics
        $stats = [
            'total_referees' => $zone->users()->where('user_type', 'referee')->count(),
            'active_referees' => $zone->users()->where('user_type', 'referee')->where('is_active', true)->count(),
            'total_clubs' => $zone->clubs()->count(),
            'active_clubs' => $zone->clubs()->where('is_active', true)->count(),
            'total_tournaments' => $zone->tournaments()->count(),
            'upcoming_tournaments' => $zone->tournaments()->where('start_date', '>', now())->count(),
            'active_tournaments' => $zone->tournaments()->whereIn('status', ['open', 'closed', 'assigned'])->count(),
            'completed_tournaments' => $zone->tournaments()->where('status', 'completed')->count(),
        ];

        // Get referees by level
        $refereesByLevel = $zone->users()
            ->where('user_type', 'referee')
            ->where('is_active', true)
            ->selectRaw('level, COUNT(*) as total')
            ->groupBy('level')
            ->get()
            ->pluck('total', 'level')
            ->toArray();

        // Get recent tournaments
        $recentTournaments = $zone->tournaments()
            ->with(['club', 'tournamentType'])
            ->orderBy('start_date', 'desc')
            ->limit(10)
            ->get();

        return view('reports.zones.show', compact(
            'zone',
            'stats',
            'refereesByLevel',
            'recentTournaments'
        ));
    }

    /**
     * Check zone access permissions.
     */
    private function checkZoneAccess(Zone $zone): void
    {
        $user = auth()->user();

        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            return;
        }

        if ($user->user_type === 'admin' && $user->zone_id !== $zone->id) {
            abort(403, 'Non sei autorizzato ad accedere ai report di questa zona.');
        }
    }
}
