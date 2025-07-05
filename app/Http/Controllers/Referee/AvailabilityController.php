<?php

namespace App\Http\Controllers\Referee;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Availability;
use App\Models\Zone;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AvailabilityController extends Controller
{
    /**
     * Display the availability management page.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isNationalReferee = in_array($user->level, ['nazionale', 'internazionale']);

        // Get filter parameters
        $zoneId = $request->get('zone_id');
        $categoryId = $request->get('category_id');
        $month = $request->get('month', Carbon::now()->format('Y-m'));

        // Base query for tournaments
        $query = Tournament::with(['tournamentCategory', 'zone', 'club'])
            ->where('status', 'open')
            ->where('availability_deadline', '>=', Carbon::today());

        // Apply zone filter
        if ($isNationalReferee) {
            // National referees can see all tournaments
            if ($zoneId) {
                $query->where('zone_id', $zoneId);
            }
        } else {
            // Zone referees can only see their zone tournaments
            $query->where('zone_id', $user->zone_id);
        }

        // Apply category filter
        if ($categoryId) {
            $query->where('tournament_category_id', $categoryId);
        }

        // Apply month filter
        if ($month) {
            $startOfMonth = Carbon::parse($month)->startOfMonth();
            $endOfMonth = Carbon::parse($month)->endOfMonth();
            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
                  ->orWhere(function ($q2) use ($startOfMonth, $endOfMonth) {
                      $q2->where('start_date', '<=', $startOfMonth)
                         ->where('end_date', '>=', $endOfMonth);
                  });
            });
        }

        // Order by start date
        $tournaments = $query->orderBy('start_date')->get();

        // Get user's current availabilities
        $userAvailabilities = $user->availabilities()
            ->pluck('tournament_id')
            ->toArray();

        // Get zones for filter (only for national referees)
        $zones = $isNationalReferee ? Zone::orderBy('name')->get() : collect();

        // Get categories visible to user
        $categories = \App\Models\TournamentCategory::active()
            ->when(!$isNationalReferee, function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('is_national', false)
                       ->whereJsonContains('settings->visibility_zones', $user->zone_id);
                })->orWhere('is_national', true);
            })
            ->ordered()
            ->get();

        // Group tournaments by month for display
        $tournamentsByMonth = $tournaments->groupBy(function ($tournament) {
            return $tournament->start_date->format('Y-m');
        });

        return view('referee.availability.index', compact(
            'tournamentsByMonth',
            'userAvailabilities',
            'zones',
            'categories',
            'zoneId',
            'categoryId',
            'month',
            'isNationalReferee'
        ));
    }

    /**
     * Save referee availabilities
     */
    public function save(Request $request)
    {
        $request->validate([
            'availabilities' => 'array',
            'availabilities.*' => 'exists:tournaments,id',
            'notes' => 'array',
            'notes.*' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $selectedTournaments = $request->input('availabilities', []);
        $notes = $request->input('notes', []);

        // Get tournaments user can access
        $accessibleTournaments = Tournament::where('status', 'open')
            ->where('availability_deadline', '>=', Carbon::today())
            ->when(!in_array($user->level, ['nazionale', 'internazionale']), function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->pluck('id')
            ->toArray();

        // Filter only accessible tournaments
        $selectedTournaments = array_intersect($selectedTournaments, $accessibleTournaments);

        // Start transaction
        \DB::beginTransaction();

        try {
            // Remove old availabilities for accessible tournaments
            Availability::where('user_id', $user->id)
                ->whereIn('tournament_id', $accessibleTournaments)
                ->delete();

            // Add new availabilities
            foreach ($selectedTournaments as $tournamentId) {
                Availability::create([
                    'user_id' => $user->id,
                    'tournament_id' => $tournamentId,
                    'notes' => $notes[$tournamentId] ?? null,
                    'submitted_at' => Carbon::now(),
                ]);
            }

            \DB::commit();

            return redirect()->route('referee.availability.index')
                ->with('success', 'Disponibilità aggiornate con successo!');

        } catch (\Exception $e) {
            \DB::rollback();

            return redirect()->route('referee.availability.index')
                ->with('error', 'Errore durante il salvataggio delle disponibilità. Riprova.');
        }
    }

    /**
     * Toggle single availability via AJAX
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'available' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $tournament = Tournament::findOrFail($request->tournament_id);

        // Check if user can access this tournament
        if (!in_array($user->level, ['nazionale', 'internazionale']) && $tournament->zone_id != $user->zone_id) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        // Check if tournament is open for availability
        if (!$tournament->isOpenForAvailability()) {
            return response()->json(['error' => 'Torneo non aperto per disponibilità'], 400);
        }

        if ($request->available) {
            // Add availability
            Availability::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'tournament_id' => $tournament->id,
                ],
                [
                    'notes' => $request->notes,
                    'submitted_at' => Carbon::now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Disponibilità confermata',
            ]);
        } else {
            // Remove availability
            Availability::where('user_id', $user->id)
                ->where('tournament_id', $tournament->id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Disponibilità rimossa',
            ]);
        }
    }

    /**
     * Show availability calendar view
     */
    public function calendar(Request $request)
    {
        $user = auth()->user();
        $isNationalReferee = in_array($user->level, ['nazionale', 'internazionale']);

        // Get tournaments for calendar
        $tournaments = Tournament::with(['tournamentCategory', 'zone', 'club'])
            ->where('status', '!=', 'draft')
            ->when(!$isNationalReferee, function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->get();

        // Get user's availabilities
        $userAvailabilities = $user->availabilities()
            ->pluck('tournament_id')
            ->toArray();

        // Get user's assignments
        $userAssignments = $user->assignments()
            ->pluck('tournament_id')
            ->toArray();

        // Format for calendar
        $calendarData = [
            'tournaments' => $tournaments->map(function ($tournament) use ($userAvailabilities, $userAssignments) {
                return [
                    'id' => $tournament->id,
                    'title' => $tournament->name,
                    'start' => $tournament->start_date->format('Y-m-d'),
                    'end' => $tournament->end_date->addDay()->format('Y-m-d'), // FullCalendar needs exclusive end
                    'color' => $this->getEventColor($tournament, $userAvailabilities, $userAssignments),
                    'extendedProps' => [
                        'club' => $tournament->club->name,
                        'zone' => $tournament->zone->name,
                        'category' => $tournament->tournamentCategory->name,
                        'status' => $tournament->status,
                        'available' => in_array($tournament->id, $userAvailabilities),
                        'assigned' => in_array($tournament->id, $userAssignments),
                    ],
                ];
            }),
            'zones' => $isNationalReferee ? Zone::orderBy('name')->get() : collect(),
            'userRoles' => [$user->user_type],
        ];

        return view('referee.availability.calendar', compact('calendarData'));
    }

    /**
     * Get event color based on status
     */
    private function getEventColor($tournament, $userAvailabilities, $userAssignments)
    {
        if (in_array($tournament->id, $userAssignments)) {
            return '#10B981'; // Green - Assigned
        }

        if (in_array($tournament->id, $userAvailabilities)) {
            return '#3B82F6'; // Blue - Available
        }

        if ($tournament->isOpenForAvailability()) {
            return '#F59E0B'; // Amber - Open
        }

        return '#6B7280'; // Gray - Closed/Other
    }
}
