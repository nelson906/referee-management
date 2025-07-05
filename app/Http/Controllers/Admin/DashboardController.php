<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Assignment;
use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index()
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin';

        // Get zones accessible by user
        $zones = $isNationalAdmin
            ? Zone::with('clubs', 'referees')->get()
            : Zone::where('id', $user->zone_id)->with('clubs', 'referees')->get();

        // Base queries
        $tournamentsQuery = Tournament::query();
        $assignmentsQuery = Assignment::query();
        $refereesQuery = User::where('user_type', 'referee');

        // Filter by zone for zone admins
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $tournamentsQuery->where('zone_id', $user->zone_id);
            $assignmentsQuery->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
            $refereesQuery->where('zone_id', $user->zone_id);
        }

        // General Statistics
        $stats = [
            'total_tournaments' => (clone $tournamentsQuery)->count(),
            'active_tournaments' => (clone $tournamentsQuery)->active()->count(),
            'upcoming_tournaments' => (clone $tournamentsQuery)->upcoming()->count(),
            'total_referees' => (clone $refereesQuery)->count(),
            'active_referees' => (clone $refereesQuery)->where('is_active', true)->count(),
            'total_assignments' => (clone $assignmentsQuery)->count(),
            'pending_confirmations' => (clone $assignmentsQuery)->where('is_confirmed', false)->count(),
            'zones_count' => $zones->count(),
        ];

        // Tournaments by status
        $tournamentsByStatus = (clone $tournamentsQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Upcoming tournaments needing referees
        $tournamentsNeedingReferees = (clone $tournamentsQuery)
            ->with(['club', 'tournamentCategory'])
            ->whereIn('status', ['open', 'closed'])
            ->whereRaw('(SELECT COUNT(*) FROM assignments WHERE tournament_id = tournaments.id) <
                       (SELECT min_referees FROM tournament_categories WHERE id = tournaments.tournament_category_id)')
            ->orderBy('start_date')
            ->limit(10)
            ->get();

        // Recent assignments
        $recentAssignments = (clone $assignmentsQuery)
            ->with(['user', 'tournament.club'])
            ->orderBy('assigned_at', 'desc')
            ->limit(10)
            ->get();

        // Availability deadlines approaching
        $deadlinesApproaching = (clone $tournamentsQuery)
            ->with(['club', 'tournamentCategory'])
            ->where('status', 'open')
            ->whereBetween('availability_deadline', [Carbon::today(), Carbon::today()->addDays(7)])
            ->orderBy('availability_deadline')
            ->get();

        // Referees by level (for current zone(s))
        $refereesByLevel = (clone $refereesQuery)
            ->where('is_active', true)
            ->select('level', DB::raw('count(*) as total'))
            ->groupBy('level')
            ->pluck('total', 'level')
            ->toArray();

        // Monthly tournament trend (last 6 months)
        $monthlyTrend = (clone $tournamentsQuery)
            ->select(
                DB::raw('DATE_FORMAT(start_date, "%Y-%m") as month'),
                DB::raw('count(*) as total')
            )
            ->where('start_date', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Zone statistics (for national admin)
        $zoneStats = [];
        if ($isNationalAdmin) {
            foreach ($zones as $zone) {
                $zoneStats[$zone->name] = [
                    'tournaments' => $zone->tournaments()->count(),
                    'active_tournaments' => $zone->tournaments()->active()->count(),
                    'referees' => $zone->referees()->where('is_active', true)->count(),
                    'clubs' => $zone->clubs()->where('is_active', true)->count(),
                ];
            }
        }

        // Top referees by assignments (current year)
        $topReferees = (clone $refereesQuery)
            ->withCount(['assignments' => function ($query) {
                $query->whereYear('assigned_at', Carbon::now()->year);
            }])
            ->where('is_active', true)
            ->orderBy('assignments_count', 'desc')
            ->limit(5)
            ->get();

        // Alerts and notifications
        $alerts = [];

        // Check for tournaments without enough referees starting soon
        $tournamentsStartingSoon = (clone $tournamentsQuery)
            ->whereIn('status', ['open', 'closed'])
            ->whereBetween('start_date', [Carbon::today(), Carbon::today()->addDays(14)])
            ->get();

        foreach ($tournamentsStartingSoon as $tournament) {
            if ($tournament->assignments()->count() < $tournament->required_referees) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Il torneo '{$tournament->name}' inizia il {$tournament->start_date->format('d/m/Y')} ma ha solo {$tournament->assignments()->count()} arbitri su {$tournament->required_referees} richiesti.",
                    'link' => route('admin.tournaments.show', $tournament),
                ];
            }
        }

        // Check for unconfirmed assignments
        $unconfirmedCount = (clone $assignmentsQuery)
            ->where('is_confirmed', false)
            ->whereHas('tournament', function ($q) {
                $q->where('start_date', '>=', Carbon::today());
            })
            ->count();

        if ($unconfirmedCount > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "Ci sono {$unconfirmedCount} assegnazioni non ancora confermate dagli arbitri.",
                'link' => route('admin.assignments.index', ['status' => 'pending']),
            ];
        }

        return view('admin.dashboard', compact(
            'stats',
            'tournamentsByStatus',
            'tournamentsNeedingReferees',
            'recentAssignments',
            'deadlinesApproaching',
            'refereesByLevel',
            'monthlyTrend',
            'zoneStats',
            'topReferees',
            'alerts',
            'isNationalAdmin'
        ));
    }
}
