<?php

namespace App\Http\Controllers\Referee;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the referee dashboard.
     */
    public function index()
    {
        $user = auth()->user();
        $isNationalReferee = in_array($user->level, ['nazionale', 'internazionale']);

        // Get referee statistics
        $stats = $user->referee_statistics;

        // Upcoming assignments
        $upcomingAssignments = $user->assignments()
            ->with(['tournament.club', 'tournament.zone', 'tournament.tournamentCategory'])
            ->whereHas('tournament', function ($q) {
                $q->where('start_date', '>=', Carbon::today());
            })
            ->orderBy(Tournament::select('start_date')
                ->whereColumn('tournaments.id', 'assignments.tournament_id')
            )
            ->get();

        // Recent assignments (last 3 months)
        $recentAssignments = $user->assignments()
            ->with(['tournament.club', 'tournament.zone'])
            ->whereHas('tournament', function ($q) {
                $q->where('end_date', '>=', Carbon::now()->subMonths(3))
                  ->where('end_date', '<', Carbon::today());
            })
            ->orderBy(Tournament::select('end_date')
                ->whereColumn('tournaments.id', 'assignments.tournament_id'),
                'desc'
            )
            ->limit(5)
            ->get();

        // Tournaments open for availability
        $openTournamentsQuery = Tournament::with(['club', 'zone', 'tournamentCategory'])
            ->where('status', 'open')
            ->where('availability_deadline', '>=', Carbon::today());

        // Filter by zone for non-national referees
        if (!$isNationalReferee) {
            $openTournamentsQuery->where('zone_id', $user->zone_id);
        } else {
            // National referees see national tournaments from all zones
            $openTournamentsQuery->where(function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id)
                  ->orWhereHas('tournamentCategory', function ($q2) {
                      $q2->where('is_national', true);
                  });
            });
        }

        $openTournaments = $openTournamentsQuery
            ->orderBy('availability_deadline')
            ->limit(10)
            ->get();

        // Get availabilities that haven't been assigned yet
        $pendingAvailabilities = $user->availabilities()
            ->with(['tournament.club', 'tournament.zone'])
            ->whereDoesntHave('tournament.assignments', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereHas('tournament', function ($q) {
                $q->whereIn('status', ['open', 'closed'])
                  ->where('start_date', '>=', Carbon::today());
            })
            ->orderBy(Tournament::select('start_date')
                ->whereColumn('tournaments.id', 'availabilities.tournament_id')
            )
            ->get();

        // Assignments needing confirmation
        $assignmentsToConfirm = $user->assignments()
            ->with(['tournament.club'])
            ->where('is_confirmed', false)
            ->whereHas('tournament', function ($q) {
                $q->where('start_date', '>=', Carbon::today());
            })
            ->get();

        // Monthly statistics (last 12 months)
        $monthlyStats = $user->assignments()
            ->select(
                DB::raw('DATE_FORMAT(tournaments.start_date, "%Y-%m") as month'),
                DB::raw('count(*) as total')
            )
            ->join('tournaments', 'assignments.tournament_id', '=', 'tournaments.id')
            ->where('tournaments.start_date', '>=', Carbon::now()->subMonths(12)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Fill missing months with zeros
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i)->format('Y-m');
            $months[$month] = $monthlyStats[$month] ?? 0;
        }
        $monthlyStats = $months;

        // Assignments by tournament category
        $assignmentsByCategory = $user->assignments()
            ->select('tournament_categories.name', DB::raw('count(*) as total'))
            ->join('tournaments', 'assignments.tournament_id', '=', 'tournaments.id')
            ->join('tournament_categories', 'tournaments.tournament_category_id', '=', 'tournament_categories.id')
            ->whereYear('assignments.assigned_at', Carbon::now()->year)
            ->groupBy('tournament_categories.name')
            ->pluck('total', 'name')
            ->toArray();

        // Calendar events for the next 3 months
        $calendarEvents = [];

        // Add assignments
        $calendarAssignments = $user->assignments()
            ->with(['tournament.club', 'tournament.tournamentCategory'])
            ->whereHas('tournament', function ($q) {
                $q->whereBetween('start_date', [Carbon::today(), Carbon::today()->addMonths(3)]);
            })
            ->get();

        foreach ($calendarAssignments as $assignment) {
            $calendarEvents[] = [
                'id' => 'assignment-' . $assignment->id,
                'title' => $assignment->tournament->name,
                'start' => $assignment->tournament->start_date->format('Y-m-d'),
                'end' => $assignment->tournament->end_date->addDay()->format('Y-m-d'),
                'color' => $assignment->is_confirmed ? '#10B981' : '#F59E0B',
                'type' => 'assignment',
                'details' => [
                    'club' => $assignment->tournament->club->name,
                    'role' => $assignment->role,
                    'confirmed' => $assignment->is_confirmed,
                ],
            ];
        }

        // Add availability deadlines
        $upcomingDeadlines = Tournament::where('status', 'open')
            ->whereBetween('availability_deadline', [Carbon::today(), Carbon::today()->addMonths(1)])
            ->when(!$isNationalReferee, function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->get();

        foreach ($upcomingDeadlines as $tournament) {
            $calendarEvents[] = [
                'id' => 'deadline-' . $tournament->id,
                'title' => 'Scadenza: ' . $tournament->name,
                'start' => $tournament->availability_deadline->format('Y-m-d'),
                'color' => '#EF4444',
                'type' => 'deadline',
            ];
        }

        // Alerts and reminders
        $alerts = [];

        // Unconfirmed assignments
        if ($assignmentsToConfirm->count() > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Hai {$assignmentsToConfirm->count()} assegnazioni da confermare.",
                'link' => route('referee.assignments.index', ['status' => 'unconfirmed']),
            ];
        }

        // Upcoming deadlines
        $deadlinesIn3Days = Tournament::where('status', 'open')
            ->whereBetween('availability_deadline', [Carbon::today(), Carbon::today()->addDays(3)])
            ->when(!$isNationalReferee, function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->count();

        if ($deadlinesIn3Days > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "Ci sono {$deadlinesIn3Days} tornei con scadenza disponibilità nei prossimi 3 giorni.",
                'link' => route('referee.availability.index'),
            ];
        }

        return view('referee.dashboard', compact(
            'stats',
            'upcomingAssignments',
            'recentAssignments',
            'openTournaments',
            'pendingAvailabilities',
            'assignmentsToConfirm',
            'monthlyStats',
            'assignmentsByCategory',
            'calendarEvents',
            'alerts',
            'isNationalReferee'
        ));
    }
}
