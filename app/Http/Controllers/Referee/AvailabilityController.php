<?php

namespace App\Http\Controllers\Referee;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Availability;
use App\Models\Zone;
use App\Helpers\RefereeLevelsHelper;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class AvailabilityController extends Controller
{
    /**
     * Display the availability summary page
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // FIXED: Use RefereeLevelsHelper instead of hardcoded array
        $isNationalReferee = RefereeLevelsHelper::canAccessNationalTournaments($user->level);

        // Get filter parameters
        $zoneId = $request->get('zone_id');
        $typeId = $request->get('type_id'); // FIXED: was category_id
        $month = $request->get('month');

        // FIXED: Base query - Add status filter for tests
        $query = Tournament::with(['tournamentType', 'zone', 'club'])
            ->where('status', Tournament::STATUS_OPEN) // FIXED: Only open tournaments
            ->where(function ($q) use ($user) {
                $q->where(function ($q2) {
                    // Future tournaments
                    $q2->where('start_date', '>=', Carbon::today());
                })->orWhere(function ($q2) use ($user) {
                    // Or tournaments where referee already declared availability
                    $q2->whereHas('availabilities', function ($q3) use ($user) {
                        $q3->where('user_id', $user->id);
                    });
                });
            })
            // FIXED: Add deadline filter for tests "tournaments past deadline are not shown"
            ->where(function ($q) {
                $q->whereNull('availability_deadline')
                  ->orWhere('availability_deadline', '>=', Carbon::today());
            });

        // FIXED: Apply zone filter logic - National referees see ALL tournaments
        if ($isNationalReferee) {
            // National referees: ALL tournaments everywhere
            if ($zoneId) {
                // If zone filter specified, show only that zone
                $query->where('zone_id', $zoneId);
            }
            // No additional filtering - show all zones
        } else {
            // Zone/Regional referees: ONLY their own zone
            $query->where('zone_id', $user->zone_id);
        }

        // Apply type filter if specified
        if ($typeId) {
            $query->where('tournament_type_id', $typeId);
        }

        // Apply month filter if specified
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

        // FIXED: Get user's availabilities with notes for display
        $availabilitiesWithNotes = $user->availabilities()
            ->get()
            ->keyBy('tournament_id');

        // Get zones for filter
        $zones = Zone::orderBy('name')->get();

        // FIXED: Get tournament types for filter
        $types = \App\Models\TournamentType::active()->ordered()->get();

        // Group tournaments by month for display
        $tournamentsByMonth = $tournaments->groupBy(function ($tournament) {
            return $tournament->start_date->format('Y-m');
        });

        return view('referee.availability.index', compact(
            'tournamentsByMonth',
            'userAvailabilities',
            'availabilitiesWithNotes', // FIXED: Added for tests
            'zones',
            'types',
            'zoneId',
            'typeId', // FIXED: was categoryId
            'month',
            'isNationalReferee'
        ));
    }

    /**
     * ADDED: Store a single availability - Required for tests
     */
    public function store(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $tournament = Tournament::findOrFail($request->tournament_id);

        // Check if user can access this tournament
        if (!$this->canAccessTournament($user, $tournament)) {
            return redirect()->back()->withErrors(['tournament_id' => 'Non puoi accedere a questo torneo.']);
        }

        // Check deadline
        if ($tournament->availability_deadline && $tournament->availability_deadline < Carbon::today()) {
            return redirect()->back()->withErrors(['tournament_id' => 'La scadenza per dichiarare disponibilità è passata.']);
        }

        // Check for duplicates
        $existing = Availability::where('user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->first();

        if ($existing) {
            return redirect()->back()->withErrors(['tournament_id' => 'Hai già dichiarato disponibilità per questo torneo.']);
        }

        // Create availability
        Availability::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'notes' => $request->notes,
            'submitted_at' => Carbon::now(),
        ]);

        return redirect()->route('referee.availability.index')
            ->with('success', 'Disponibilità dichiarata con successo!');
    }

    /**
     * ADDED: Store bulk availabilities - Required for tests
     */
    public function bulk(Request $request)
    {
        $request->validate([
            'tournament_ids' => 'required|array',
            'tournament_ids.*' => 'exists:tournaments,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $tournamentIds = $request->tournament_ids;
        $notes = $request->notes;

        DB::beginTransaction();

        try {
            foreach ($tournamentIds as $tournamentId) {
                $tournament = Tournament::findOrFail($tournamentId);

                // Check access and deadline for each tournament
                if (!$this->canAccessTournament($user, $tournament)) {
                    continue;
                }

                if ($tournament->availability_deadline && $tournament->availability_deadline < Carbon::today()) {
                    continue;
                }

                // Skip if already exists
                $existing = Availability::where('user_id', $user->id)
                    ->where('tournament_id', $tournamentId)
                    ->first();

                if ($existing) {
                    continue;
                }

                // Create availability
                Availability::create([
                    'user_id' => $user->id,
                    'tournament_id' => $tournamentId,
                    'notes' => $notes,
                    'submitted_at' => Carbon::now(),
                ]);
            }

            DB::commit();

            return redirect()->route('referee.availability.index')
                ->with('success', 'Disponibilità dichiarate con successo!');
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->route('referee.availability.index')
                ->with('error', 'Errore durante il salvataggio delle disponibilità.');
        }
    }

    /**
     * ADDED: Update availability notes - Required for tests
     */
    public function update(Request $request, Availability $availability)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        // Check ownership
        if ($availability->user_id !== $user->id) {
            abort(403);
        }

        // Check deadline
        $tournament = $availability->tournament;
        if ($tournament->availability_deadline && $tournament->availability_deadline < Carbon::today()) {
            abort(403, 'La scadenza per modificare la disponibilità è passata.');
        }

        $availability->update([
            'notes' => $request->notes,
        ]);

        return redirect()->route('referee.availability.index')
            ->with('success', 'Note aggiornate con successo!');
    }

    /**
     * ADDED: Remove availability - Required for tests
     */
    public function destroy(Availability $availability)
    {
        $user = auth()->user();

        // Check ownership
        if ($availability->user_id !== $user->id) {
            abort(403);
        }

        // Check deadline
        $tournament = $availability->tournament;
        if ($tournament->availability_deadline && $tournament->availability_deadline < Carbon::today()) {
            abort(403, 'La scadenza per rimuovere la disponibilità è passata.');
        }

        $availability->delete();

        return redirect()->route('referee.availability.index')
            ->with('success', 'Disponibilità rimossa con successo!');
    }

    /**
     * Save referee availabilities - EXISTING METHOD KEPT
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
        $isNationalReferee = RefereeLevelsHelper::canAccessNationalTournaments($user->level);
        $selectedTournaments = $request->input('availabilities', []);
        $notes = $request->input('notes', []);

        // Get tournaments user can access - ONLY FUTURE TOURNAMENTS
        $accessibleQuery = Tournament::where('start_date', '>=', Carbon::today());

        if ($isNationalReferee) {
            // National referees: all tournaments
        } else {
            // Zone referees: only their zone
            $accessibleQuery->where('zone_id', $user->zone_id);
        }

        $accessibleTournaments = $accessibleQuery->pluck('id')->toArray();

        // Filter only accessible tournaments
        $selectedTournaments = array_intersect($selectedTournaments, $accessibleTournaments);

        // Start transaction
        DB::beginTransaction();

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

            DB::commit();

            return redirect()->route('referee.availability.index')
                ->with('success', 'Disponibilità aggiornate con successo!');
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->route('referee.availability.index')
                ->with('error', 'Errore durante il salvataggio delle disponibilità. Riprova.');
        }
    }

    /**
     * Toggle single availability via AJAX - EXISTING METHOD KEPT
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
        if (!$this->canAccessTournament($user, $tournament)) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        // Check if tournament allows availability changes
        if ($tournament->start_date <= Carbon::today()) {
            return response()->json(['error' => 'Torneo già iniziato'], 400);
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
     * Referee Calendar - EXISTING METHOD KEPT
     */
    public function calendar(Request $request)
    {
        $user = auth()->user();
        $isNationalReferee = RefereeLevelsHelper::canAccessNationalTournaments($user->level);

        // Get tournaments for calendar
        $tournamentsQuery = Tournament::with(['tournamentType', 'zone', 'club'])
            ->whereIn('status', ['draft', 'open', 'closed', 'assigned']);

        if ($isNationalReferee) {
            // National referees: all tournaments everywhere
        } else {
            // Zone referees: only their zone
            $tournamentsQuery->where('zone_id', $user->zone_id);
        }

        $tournaments = $tournamentsQuery->get();

        // Get user's availabilities and assignments
        $userAvailabilities = $user->availabilities()->pluck('tournament_id')->toArray();
        $userAssignments = $user->assignments()->pluck('tournament_id')->toArray();

        // Format tournaments for calendar
        $calendarTournaments = $tournaments->map(function ($tournament) use ($userAvailabilities, $userAssignments) {
            $isAvailable = in_array($tournament->id, $userAvailabilities);
            $isAssigned = in_array($tournament->id, $userAssignments);

            return [
                'id' => $tournament->id,
                'title' => $tournament->name . ($tournament->club->code ? ' (' . $tournament->club->code . ')' : ''),
                'start' => $tournament->start_date->format('Y-m-d'),
                'end' => $tournament->end_date->addDay()->format('Y-m-d'),
                'color' => $isAssigned ? '#10B981' : ($isAvailable ? '#3B82F6' : '#E5E7EB'),
                'borderColor' => $isAssigned ? '#10B981' : ($isAvailable ? '#F59E0B' : '#9CA3AF'),
                'textColor' => $isAssigned ? '#FFFFFF' : ($isAvailable ? '#FFFFFF' : '#374151'),
                'extendedProps' => [
                    'club' => $tournament->club->name ?? 'N/A',
                    'club_code' => $tournament->club->code ?? '',
                    'zone' => $tournament->zone->name ?? 'N/A',
                    'category' => $tournament->tournamentType->name ?? 'N/A',
                    'status' => $tournament->status,
                    'deadline' => $tournament->availability_deadline?->format('d/m/Y') ?? 'N/A',
                    'days_until_deadline' => $tournament->availability_deadline
                        ? Carbon::today()->diffInDays($tournament->availability_deadline, false)
                        : null,
                    'is_available' => $isAvailable,
                    'is_assigned' => $isAssigned,
                    'can_apply' => $tournament->start_date > Carbon::today() &&
                        ($tournament->availability_deadline ? $tournament->availability_deadline >= Carbon::today() : true),
                    'personal_status' => $isAssigned ? 'assigned' : ($isAvailable ? 'available' : 'can_apply'),
                    'tournament_url' => route('tournaments.show', $tournament),
                    'is_national' => $tournament->tournamentType->is_national ?? false,
                ],
            ];
        });

        $calendarData = [
            'tournaments' => $calendarTournaments,
            'userType' => 'referee',
            'userRoles' => ['referee'],
            'canModify' => true,
            'zones' => collect(),
            'types' => collect(),
            'clubs' => collect(),
            'availabilities' => $userAvailabilities,
            'assignments' => $userAssignments,
            'totalTournaments' => $tournaments->count(),
            'lastUpdated' => now()->toISOString(),
            'userLevel' => $user->level,
            'isNationalReferee' => $isNationalReferee,
        ];

        return view('referee.availability.calendar', compact('calendarData'));
    }

    /**
     * ADDED: Check if user can access tournament - Private helper
     */
    private function canAccessTournament($user, $tournament)
    {
        $isNationalReferee = RefereeLevelsHelper::canAccessNationalTournaments($user->level);

        if ($isNationalReferee) {
            // National referees can access all tournaments
            return true;
        } else {
            // Zone referees can only access tournaments in their zone
            return $tournament->zone_id == $user->zone_id;
        }
    }
}
