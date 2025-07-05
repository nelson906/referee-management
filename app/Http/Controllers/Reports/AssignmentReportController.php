<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Zone;
use App\Models\TournamentCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

class AssignmentReportController extends Controller
{
    /**
     * Display assignments report.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        $query = Assignment::with([
            'user:id,name,referee_code,level',
            'tournament:id,name,start_date,end_date,zone_id,club_id,tournament_category_id',
            'tournament.club:id,name',
            'tournament.zone:id,name',
            'tournament.tournamentCategory:id,name',
            'assignedBy:id,name'
        ]);

        // Apply access restrictions
        if ($user->user_type === 'admin' && !in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $query->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Apply filters
        if ($request->filled('zone_id')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'confirmed') {
                $query->where('is_confirmed', true);
            } elseif ($request->status === 'unconfirmed') {
                $query->where('is_confirmed', false);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('start_date', '>=', $request->date_from);
            });
        }

        if ($request->filled('date_to')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('start_date', '<=', $request->date_to);
            });
        }

        if ($request->filled('category_id')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('tournament_category_id', $request->category_id);
            });
        }

        $assignments = $query
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Get filter options
        $zones = $this->getAccessibleZones($user);
        $categories = TournamentCategory::active()->ordered()->get();

        // Get statistics
        $stats = $this->getAssignmentStats($user, $request);

        return view('reports.assignments.index', compact(
            'assignments',
            'zones',
            'categories',
            'stats'
        ));
    }

    /**
     * Get assignment statistics.
     */
    private function getAssignmentStats($user, $request): array
    {
        $query = Assignment::query();

        // Apply same access restrictions as main query
        if ($user->user_type === 'admin' && !in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $query->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Apply same filters as main query
        if ($request->filled('zone_id')) {
            $query->whereHas('tournament', function($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->whereHas('tournament', function($q) use ($request) {
                if ($request->filled('date_from')) {
                    $q->where('start_date', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $q->where('start_date', '<=', $request->date_to);
                }
            });
        }

        return [
            'total' => $query->count(),
            'confirmed' => $query->where('is_confirmed', true)->count(),
            'unconfirmed' => $query->where('is_confirmed', false)->count(),
            'current_year' => $query->whereYear('created_at', now()->year)->count(),
            'this_month' => $query->whereMonth('created_at', now()->month)
                                 ->whereYear('created_at', now()->year)->count(),
        ];
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
}
