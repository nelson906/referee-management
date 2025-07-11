<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Club;
use App\Models\TournamentAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the reports dashboard.
     */
    public function index(Request $request)
    {
        $period = $request->get('period', '30'); // days
        $startDate = Carbon::now()->subDays($period);

        // Basic statistics
        $stats = $this->getBasicStats();

        // Growth statistics
        $growth = $this->getGrowthStats($startDate);

        // Activity trends
        $trends = $this->getActivityTrends($startDate);

        // Zone performance
        $zonePerformance = $this->getZonePerformance();

        // Category usage
        $categoryUsage = $this->getCategoryUsage();

        // Recent activity
        $recentActivity = $this->getRecentActivity();

        // System health indicators
        $systemHealth = $this->getSystemHealth();

        return view('reports.dashboard.index', compact(
            'stats',
            'growth',
            'trends',
            'zonePerformance',
            'categoryUsage',
            'recentActivity',
            'systemHealth',
            'period'
        ));
    }

    /**
     * Get basic system statistics.
     */
    private function getBasicStats()
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'total_referees' => User::where('user_type', 'referee')->count(),
            'active_referees' => User::where('user_type', 'referee')->where('is_active', true)->count(),
            'total_zones' => Zone::count(),
            'active_zones' => Zone::where('is_active', true)->count(),
            'total_clubs' => Club::count(),
            'active_clubs' => Club::where('is_active', true)->count(),
            'total_tournaments' => Tournament::count(),
            'active_tournaments' => Tournament::whereIn('status', ['open', 'closed', 'assigned'])->count(),
            'completed_tournaments' => Tournament::where('status', 'completed')->count(),
            'total_categories' => TournamentType::count(),
            'active_categories' => TournamentType::where('is_active', true)->count(),
            'total_assignments' => TournamentAssignment::count(),
            'pending_assignments' => TournamentAssignment::where('status', 'pending')->count(),
            'accepted_assignments' => TournamentAssignment::where('status', 'accepted')->count(),
        ];
    }

    /**
     * Get growth statistics over a period.
     */
    private function getGrowthStats($startDate)
    {
        $previousPeriodStart = $startDate->copy()->subDays($startDate->diffInDays(Carbon::now()));

        // Current period counts
        $currentUsers = User::where('created_at', '>=', $startDate)->count();
        $currentTournaments = Tournament::where('created_at', '>=', $startDate)->count();
        $currentClubs = Club::where('created_at', '>=', $startDate)->count();
        $currentAssignments = TournamentAssignment::where('created_at', '>=', $startDate)->count();

        // Previous period counts
        $previousUsers = User::whereBetween('created_at', [$previousPeriodStart, $startDate])->count();
        $previousTournaments = Tournament::whereBetween('created_at', [$previousPeriodStart, $startDate])->count();
        $previousClubs = Club::whereBetween('created_at', [$previousPeriodStart, $startDate])->count();
        $previousAssignments = TournamentAssignment::whereBetween('created_at', [$previousPeriodStart, $startDate])->count();

        return [
            'users' => [
                'current' => $currentUsers,
                'previous' => $previousUsers,
                'growth' => $previousUsers > 0 ? (($currentUsers - $previousUsers) / $previousUsers) * 100 : 0
            ],
            'tournaments' => [
                'current' => $currentTournaments,
                'previous' => $previousTournaments,
                'growth' => $previousTournaments > 0 ? (($currentTournaments - $previousTournaments) / $previousTournaments) * 100 : 0
            ],
            'clubs' => [
                'current' => $currentClubs,
                'previous' => $previousClubs,
                'growth' => $previousClubs > 0 ? (($currentClubs - $previousClubs) / $previousClubs) * 100 : 0
            ],
            'assignments' => [
                'current' => $currentAssignments,
                'previous' => $previousAssignments,
                'growth' => $previousAssignments > 0 ? (($currentAssignments - $previousAssignments) / $previousAssignments) * 100 : 0
            ],
        ];
    }

    /**
     * Get activity trends over time.
     */
    private function getActivityTrends($startDate)
    {
        $userTrends = User::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->pluck('count');

        $tournamentTrends = Tournament::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->pluck('count');

        $assignmentTrends = TournamentAssignment::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->pluck('count');

        // Fill missing dates with 0
        $dates = [];
        $current = $startDate->copy();
        while ($current <= Carbon::now()) {
            $dateStr = $current->format('Y-m-d');
            $dates[] = [
                'date' => $dateStr,
                'users' => $userTrends->get($dateStr, 0),
                'tournaments' => $tournamentTrends->get($dateStr, 0),
                'assignments' => $assignmentTrends->get($dateStr, 0),
            ];
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Get zone performance statistics.
     */
    private function getZonePerformance()
    {
        return Zone::withCount(['users', 'tournaments', 'clubs'])
            ->orderBy('tournaments_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($zone) {
                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'region' => $zone->region,
                    'users_count' => $zone->users_count,
                    'tournaments_count' => $zone->tournaments_count,
                    'clubs_count' => $zone->clubs_count,
                    'is_active' => $zone->is_active,
                ];
            });
    }

    /**
     * Get tournament type usage statistics.
     */
    private function getCategoryUsage()
    {
        return TournamentType::withCount('tournaments')
            ->orderBy('tournaments_count', 'desc')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'code' => $category->code,
                    'tournaments_count' => $category->tournaments_count,
                    'is_national' => $category->is_national,
                    'is_active' => $category->is_active,
                ];
            });
    }

    /**
     * Get recent system activity.
     */
    private function getRecentActivity()
    {
        $activities = [];

        // Recent users
        $recentUsers = User::latest()->limit(5)->get();
        foreach ($recentUsers as $user) {
            $activities[] = [
                'type' => 'user_registered',
                'title' => 'Nuovo utente registrato',
                'description' => "{$user->name} si è registrato come {$user->user_type}",
                'created_at' => $user->created_at,
                'icon' => 'user-plus',
                'color' => 'blue',
            ];
        }

        // Recent tournaments
        $recentTournaments = Tournament::with(['category', 'zone'])->latest()->limit(5)->get();
        foreach ($recentTournaments as $tournament) {
            $activities[] = [
                'type' => 'tournament_created',
                'title' => 'Nuovo torneo creato',
                'description' => $tournament->name . ' (' . ($tournament->tournamentCategory->name ?? 'N/A') . ') - ' . ($tournament->zone->name ?? 'N/A'),
                'created_at' => $tournament->created_at,
                'icon' => 'calendar',
                'color' => 'green',
            ];
        }

        // Recent assignments
        $recentAssignments = TournamentAssignment::with(['tournament', 'referee'])
            ->where('status', 'accepted')
            ->latest()
            ->limit(5)
            ->get();
        foreach ($recentAssignments as $assignment) {
            $activities[] = [
                'type' => 'assignment_accepted',
                'title' => 'Assegnazione accettata',
                'description' => "{$assignment->referee->name} ha accettato {$assignment->tournament->name}",
                'created_at' => $assignment->updated_at,
                'icon' => 'check-circle',
                'color' => 'green',
            ];
        }

        // Sort by creation date
        return collect($activities)->sortByDesc('created_at')->take(15)->values();
    }

    /**
     * Get system health indicators.
     */
    private function getSystemHealth()
    {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'user_activity' => $this->checkUserActivity(),
            'tournament_activity' => $this->checkTournamentActivity(),
            'assignment_rate' => $this->checkAssignmentRate(),
            'system_errors' => $this->checkSystemErrors(),
        ];

        // Calculate overall health score
        $scores = array_column($health, 'score');
        $overallScore = count($scores) > 0 ? array_sum($scores) / count($scores) : 0;

        $health['overall'] = [
            'score' => $overallScore,
            'status' => $this->getHealthStatus($overallScore),
            'message' => $this->getHealthMessage($overallScore),
        ];

        return $health;
    }

    /**
     * Check database health.
     */
    private function checkDatabaseHealth()
    {
        try {
            DB::connection()->getPdo();
            $tableCount = count(DB::select('SHOW TABLES'));

            return [
                'score' => 100,
                'status' => 'healthy',
                'message' => "Database connesso ({$tableCount} tabelle)",
                'details' => ['tables' => $tableCount]
            ];
        } catch (\Exception $e) {
            return [
                'score' => 0,
                'status' => 'error',
                'message' => 'Errore connessione database',
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Check user activity.
     */
    private function checkUserActivity()
    {
        $totalUsers = User::count();
        $activeUsers = User::where('last_login_at', '>=', Carbon::now()->subDays(30))->count();

        if ($totalUsers == 0) {
            $activityRate = 0;
        } else {
            $activityRate = ($activeUsers / $totalUsers) * 100;
        }

        return [
            'score' => min($activityRate, 100),
            'status' => $activityRate >= 70 ? 'healthy' : ($activityRate >= 40 ? 'warning' : 'error'),
            'message' => "{$activeUsers}/{$totalUsers} utenti attivi (ultimi 30 giorni)",
            'details' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'rate' => round($activityRate, 1)
            ]
        ];
    }

    /**
     * Check tournament activity.
     */
    private function checkTournamentActivity()
    {
        $recentTournaments = Tournament::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $activeTournaments = Tournament::whereIn('status', ['open', 'closed', 'assigned'])->count();

        $score = min(($recentTournaments * 10) + ($activeTournaments * 5), 100);

        return [
            'score' => $score,
            'status' => $score >= 70 ? 'healthy' : ($score >= 40 ? 'warning' : 'error'),
            'message' => "{$recentTournaments} tornei creati, {$activeTournaments} attivi",
            'details' => [
                'recent' => $recentTournaments,
                'active' => $activeTournaments
            ]
        ];
    }

    /**
     * Check assignment rate.
     */
    private function checkAssignmentRate()
    {
        $totalAssignments = TournamentAssignment::count();
        $acceptedAssignments = TournamentAssignment::where('status', 'accepted')->count();

        if ($totalAssignments == 0) {
            $acceptanceRate = 100; // No assignments yet, assume healthy
        } else {
            $acceptanceRate = ($acceptedAssignments / $totalAssignments) * 100;
        }

        return [
            'score' => $acceptanceRate,
            'status' => $acceptanceRate >= 80 ? 'healthy' : ($acceptanceRate >= 60 ? 'warning' : 'error'),
            'message' => "{$acceptedAssignments}/{$totalAssignments} assegnazioni accettate",
            'details' => [
                'total' => $totalAssignments,
                'accepted' => $acceptedAssignments,
                'rate' => round($acceptanceRate, 1)
            ]
        ];
    }

    /**
     * Check for system errors.
     */
    private function checkSystemErrors()
    {
        // This is a simplified check - in a real app you'd check log files
        $inactiveZones = Zone::where('is_active', false)->count();
        $inactiveUsers = User::where('is_active', false)->count();

        $issues = $inactiveZones + $inactiveUsers;
        $score = max(100 - ($issues * 5), 0);

        return [
            'score' => $score,
            'status' => $score >= 90 ? 'healthy' : ($score >= 70 ? 'warning' : 'error'),
            'message' => $issues == 0 ? 'Nessun problema rilevato' : "{$issues} elementi disattivati",
            'details' => [
                'inactive_zones' => $inactiveZones,
                'inactive_users' => $inactiveUsers
            ]
        ];
    }

    /**
     * Get health status based on score.
     */
    private function getHealthStatus($score)
    {
        if ($score >= 80) return 'healthy';
        if ($score >= 60) return 'warning';
        return 'error';
    }

    /**
     * Get health message based on score.
     */
    private function getHealthMessage($score)
    {
        if ($score >= 90) return 'Sistema in ottima salute';
        if ($score >= 80) return 'Sistema in buona salute';
        if ($score >= 60) return 'Sistema con alcuni problemi';
        if ($score >= 40) return 'Sistema con problemi significativi';
        return 'Sistema con gravi problemi';
    }

    /**
     * Export dashboard data.
     */
    public function export(Request $request)
    {
        $period = $request->get('period', '30');
        $format = $request->get('format', 'csv');

        $stats = $this->getBasicStats();
        $growth = $this->getGrowthStats(Carbon::now()->subDays($period));

        $data = array_merge($stats, [
            'export_date' => Carbon::now()->format('Y-m-d H:i:s'),
            'period_days' => $period,
        ]);

        if ($format === 'json') {
            return response()->json($data);
        }

        // CSV Export
        $filename = 'dashboard_report_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, ['Metrica', 'Valore']);

            foreach ($data as $key => $value) {
                if (is_numeric($value)) {
                    fputcsv($file, [ucfirst(str_replace('_', ' ', $key)), $value]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
