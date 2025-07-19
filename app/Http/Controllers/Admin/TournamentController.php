<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentRequest;
use App\Models\Tournament;
use App\Models\TournamentType; // ✅ FIXED: Changed from TournamentCategory
use App\Models\club;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Traits\CrudActions;

class TournamentController extends Controller
{
    use CrudActions;

    /**
     * Display a listing of tournaments.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        // Base query - ✅ FIXED: tournamentType relationship
        $query = Tournament::with(['tournamentType', 'zone', 'club', 'assignments']);

        // Filter by zone for zone admins
        if (!$isNationalAdmin && !in_array($user->user_type, ['super_admin'])) {
            $query->where('zone_id', $user->zone_id);
        }

        // Apply filters
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('zone_id') && $request->zone_id !== '') {
            $query->where('zone_id', $request->zone_id);
        }

        // ✅ FIXED: tournament_type_id filter name
        if ($request->has('tournament_type_id') && $request->tournament_type_id !== '') {
            $query->where('tournament_type_id', $request->tournament_type_id);
        }

        if ($request->has('month') && $request->month !== '') {
            $startOfMonth = Carbon::parse($request->month)->startOfMonth();
            $endOfMonth = Carbon::parse($request->month)->endOfMonth();
            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth]);
            });
        }

        // Search
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('club', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Order by start date descending
        $tournaments = $query->orderBy('start_date', 'desc')->paginate(20);

        // Get data for filters
        $zones = $isNationalAdmin ? Zone::orderBy('name')->get() : collect();

        // ✅ FIXED: Variable name from $categories to $tournamentTypes
        $tournamentTypes = TournamentType::active()->ordered()->get();
        $statuses = Tournament::STATUSES;

        // ✅ FIXED: compact() uses tournamentTypes instead of categories
        return view('admin.tournaments.index', compact(
            'tournaments',
            'zones',
            'tournamentTypes', // ← FIXED: was 'categories'
            'statuses',
            'isNationalAdmin'
        ));
    }

    /**
     * Show tournaments calendar view
     */
    public function calendar(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin' || $user->user_type === 'admin';

        // ✅ FIXED: tournamentType relationship
        $tournaments = Tournament::with(['tournamentType', 'zone', 'club', 'assignments.user'])
            ->when(!$isNationalAdmin && !in_array($user->user_type, ['super_admin']), function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->get();

        // Get zones for filter
        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        // Get clubs for filter
        $clubs = \App\Models\Club::active()
            ->when(!$isNationalAdmin && !in_array($user->user_type, ['super_admin']), function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->orderBy('name')
            ->get();

        // ✅ FIXED: Variable name from $types to $tournamentTypes
        $tournamentTypes = TournamentType::active()->ordered()->get();

        // User roles for permissions
        $userRoles = ['Admin'];
        if ($user->user_type === 'super_admin') {
            $userRoles[] = 'SuperAdmin';
        } elseif ($user->user_type === 'national_admin') {
            $userRoles[] = 'NationalAdmin';
        }

        // Format tournaments for calendar
        $calendarTournaments = $tournaments->map(function ($tournament) {
            return [
                'id' => $tournament->id,
                'title' => $tournament->name,
                'start' => $tournament->start_date->format('Y-m-d'),
                'end' => $tournament->end_date->addDay()->format('Y-m-d'),
                'color' => '#3b82f6', // Default color
                'borderColor' => '#1e40af',
                'extendedProps' => [
                    'club' => $tournament->club->name ?? 'N/A',
                    'zone' => $tournament->zone->name ?? 'N/A',
                    'zone_id' => $tournament->zone_id,
                    // ✅ FIXED: tournamentType relationship
                    'category' => $tournament->tournamentType->name ?? 'N/A',
                    'status' => $tournament->status,
                    'tournament_url' => route('admin.tournaments.show', $tournament),
                    'deadline' => $tournament->availability_deadline?->format('d/m/Y') ?? 'N/A',
                    'type_id' => $tournament->tournament_type_id,
                    'availabilities_count' => $tournament->availabilities()->count(),
                    'assignments_count' => $tournament->assignments()->count(),
                    'required_referees' => $tournament->required_referees ?? 1,
                    'max_referees' => $tournament->max_referees ?? 4,
                    'management_priority' => 'open',
                ],
            ];
        });

        // Prepare data for React component
        $calendarData = [
            'tournaments' => $calendarTournaments,
            'zones' => $zones->map(function ($zone) {
                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                ];
            }),
            'clubs' => $clubs->map(function ($club) {
                return [
                    'id' => $club->id,
                    'name' => $club->name,
                    'zone_id' => $club->zone_id,
                ];
            }),
            // ✅ FIXED: tournamentTypes instead of types
            'tournamentTypes' => $tournamentTypes->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code,
                    'is_national' => $type->is_national,
                ];
            }),
            'userRoles' => $userRoles,
            'userType' => 'admin',
            'canModify' => true,
        ];

        return view('admin.tournaments.calendar', compact('calendarData'));
    }

/**
 * Show the form for creating a new tournament.
 */
public function create()
{
    $user = auth()->user();
    $isNationalAdmin = $user->user_type === 'national_admin';

    // Get all active types (NON categories!)
    $allTypes = TournamentType::active()->ordered()->get();

    // Filter types based on user access
    $types = $allTypes->filter(function ($type) use ($user, $isNationalAdmin) {
        // National admins see all types
        if ($isNationalAdmin) {
            return true;
        }

        // National types are always visible
        if ($type->is_national) {
            return true;
        }

        // Check if zone user can see this zonal type
        return $type->isAvailableForZone($user->zone_id);
    });

    // Get zones
    $zones = $isNationalAdmin
        ? Zone::orderBy('name')->get()
        : Zone::where('id', $user->zone_id)->get();

    // Get clubs
    $clubs = Club::active()
        ->when(!$isNationalAdmin, function ($q) use ($user) {
            $q->where('zone_id', $user->zone_id);
        })
        ->ordered()
        ->get();

    return view('admin.tournaments.create', compact('types', 'zones', 'clubs'));
}

/**
 * Show the form for editing the specified tournament.
 */
public function edit(Tournament $tournament)
{
    // Check access
    $this->checkTournamentAccess($tournament);

    // Check if editable
    if (!$tournament->isEditable()) {
        return redirect()
            ->route('admin.tournaments.show', $tournament)
            ->with('error', 'Questo torneo non può essere modificato nel suo stato attuale.');
    }

    $user = auth()->user();
    $isNationalAdmin = $user->user_type === 'national_admin';

    // Get all active types (NON categories!)
    $allTournamentTypes = TournamentType::active()->get();

    // Filter types based on user access
    $tournamentTypes = $allTournamentTypes->filter(function ($type) use ($user, $isNationalAdmin) {
        // National admins see all types
        if ($isNationalAdmin) {
            return true;
        }

        // National types are always visible
        if ($type->is_national) {
            return true;
        }

        // Check if zone user can see this zonal type
        return $type->isAvailableForZone($user->zone_id);
    });

    // Get zones
    $zones = $isNationalAdmin
        ? Zone::orderBy('name')->get()
        : Zone::where('id', $user->zone_id)->get();

    // Get clubs
    $clubs = Club::active()
        ->where('zone_id', $tournament->zone_id)
        ->ordered()
        ->get();

    return view('admin.tournaments.edit', compact('tournament', 'tournamentTypes', 'zones', 'clubs'));
}


    /**
     * Store a newly created tournament in storage.
     */
    public function store(TournamentRequest $request)
    {
        $data = $request->validated();

        // Set zone_id from club if not national admin
        if (auth()->user()->user_type !== 'national_admin') {
            $club = club::findOrFail($data['club_id']);
            $data['zone_id'] = $club->zone_id;
        }

        // Create tournament
        $tournament = Tournament::create($data);

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Torneo creato con successo!');
    }

    /**
     * Display the specified tournament for admin view
     */
    public function show(Tournament $tournament)
    {
        $user = auth()->user();

        // Check permissions (zone admin can only see their zone tournaments)
        if ($user->user_type === 'admin' && $user->zone_id !== $tournament->zone_id) {
            abort(403, 'Non hai i permessi per visualizzare questo torneo.');
        }

        // ✅ FIXED: Load tournamentType relationship
        $tournament->load([
            'tournamentType',
            'zone',
            'club',
            'assignments.referee',
            'availabilities.referee'
        ]);

        // Get statistics
        $stats = [
            'total_assignments' => $tournament->assignments()->count(),
            'total_availabilities' => $tournament->availabilities()->count(),
            'total_referees' => $tournament->availabilities()->count(),
            'assigned_referees' => $tournament->assignments()->count(),
            // ✅ FIXED: Use tournamentType relationship
            'required_referees' => $tournament->required_referees ?? $tournament->tournamentType->min_referees ?? 1,
            'max_referees' => $tournament->max_referees ?? $tournament->tournamentType->max_referees ?? 4,
            'days_until_deadline' => $tournament->days_until_deadline,
            'is_editable' => method_exists($tournament, 'isEditable') ? $tournament->isEditable() : true,
        ];

        $assignedReferees = $tournament->assignments()->with('user')->get();
        $availableReferees = $tournament->availabilities()->with('user')->get();

        return view('admin.tournaments.show', compact(
            'tournament',
            'assignedReferees', // ← AGGIUNGI QUESTO
            'availableReferees',  // ← AGGIUNGI
            'stats'               // ← AGGIUNGI
        ));
    }


    /**
     * Update the specified tournament in storage.
     */
    public function update(TournamentRequest $request, Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Check if editable
        if (!$tournament->isEditable()) {
            return redirect()
                ->route('tournaments.show', $tournament)
                ->with('error', 'Questo torneo non può essere modificato nel suo stato attuale.');
        }

        $data = $request->validated();

        // Update zone_id from club if changed
        if (isset($data['club_id']) && $data['club_id'] != $tournament->club_id) {
            $club = club::findOrFail($data['club_id']);
            $data['zone_id'] = $club->zone_id;
        }

        $tournament->update($data);

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Torneo aggiornato con successo!');
    }

    /**
     * Remove the specified tournament from storage.
     */
    public function destroy(Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Check if can be deleted
        if ($tournament->assignments()->exists()) {
            return redirect()
                ->route('admin.tournaments.index')
                ->with('error', 'Impossibile eliminare un torneo con assegnazioni.');
        }

        if ($tournament->status !== 'draft') {
            return redirect()
                ->route('admin.tournaments.index')
                ->with('error', 'Solo i tornei in bozza possono essere eliminati.');
        }

        $tournament->delete();

        return redirect()
            ->route('admin.tournaments.index')
            ->with('success', 'Torneo eliminato con successo!');
    }

    /**
     * Update tournament status.
     */
    public function updateStatus(Request $request, Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(Tournament::STATUSES))],
        ]);

        $newStatus = $request->status;
        $currentStatus = $tournament->status;

        // Validate status transition
        $validTransitions = [
            'draft' => ['open'],
            'open' => ['closed'],
            'closed' => ['open', 'assigned'],
            'assigned' => ['completed'],
            'completed' => [],
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => 'Transizione di stato non valida.'
            ], 400);
        }

        // Additional checks
        if ($newStatus === 'assigned' && $tournament->assignments()->count() < $tournament->required_referees) {
            return response()->json([
                'success' => false,
                'message' => 'Non ci sono abbastanza arbitri assegnati.'
            ], 400);
        }

        $tournament->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Stato aggiornato con successo.',
            'new_status' => $newStatus,
            'new_status_label' => Tournament::STATUSES[$newStatus],
            'new_status_color' => $tournament->status_color,
        ]);
    }

    /**
     * Show availabilities for a tournament.
     */
    public function availabilities(Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Get available referees with their level and zone
        $availabilities = $tournament->availabilities()
            ->with(['user' => function ($query) {
                $query->with('zone');
            }])
            ->get()
            ->sortBy('user.name');

        // Get all eligible referees who haven't declared availability
        $eligibleReferees = \App\Models\User::where('user_type', 'referee')
            ->where('is_active', true)
            // ✅ FIXED: Use tournamentType relationship
            ->when($tournament->tournamentType->is_national, function ($q) {
                $q->whereIn('level', ['nazionale', 'internazionale']);
            }, function ($q) use ($tournament) {
                $q->where('zone_id', $tournament->zone_id);
            })
            ->whereNotIn('id', $tournament->availabilities()->pluck('user_id'))
            ->whereNotIn('id', $tournament->assignments()->pluck('user_id'))
            ->orderBy('name')
            ->get();

        return view('admin.tournaments.availabilities', compact(
            'tournament',
            'availabilities',
            'eligibleReferees'
        ));
    }

    /**
     * Check if user can access tournament.
     */
    private function checkTournamentAccess(Tournament $tournament)
    {
        $user = auth()->user();

        if ($user->user_type === 'super_admin' || $user->user_type === 'national_admin') {
            return;
        }

        if ($user->user_type === 'admin' && $tournament->zone_id !== $user->zone_id) {
            abort(403, 'Non sei autorizzato ad accedere a questo torneo.');
        }
    }

    /**
     * Get clubs for a specific zone (AJAX).
     */
    public function getclubsByZone(Request $request)
    {
        $request->validate([
            'zone_id' => 'required|exists:zones,id',
        ]);

        $clubs = club::active()
            ->where('zone_id', $request->zone_id)
            ->ordered()
            ->get(['id', 'name', 'code']);

        return response()->json($clubs);
    }

    /**
     * Configurazione per il trait
     */
    protected function getEntityName($model): string
    {
        return 'Torneo';
    }

    protected function getIndexRoute(): string
    {
        return 'admin.tournaments.index';
    }

    protected function getDeleteErrorMessage($model): string
    {
        return 'Impossibile eliminare un torneo con assegnazioni.';
    }

    protected function canBeDeleted($tournament): bool
    {
        return !$tournament->assignments()->exists() && $tournament->status === 'draft';
    }

    protected function checkAccess($tournament): void
    {
        $this->checkTournamentAccess($tournament);
    }

    /**
     * Display a public listing of tournaments (for all authenticated users including referees)
     */
    public function publicIndex(Request $request)
    {
        $user = auth()->user();
        $isNationalReferee = in_array($user->level ?? '', ['nazionale', 'internazionale']);

        // ✅ FIXED: tournamentType relationship
        $query = Tournament::with(['tournamentType', 'zone', 'club'])
            ->where('status', '!=', 'draft'); // Hide drafts from public view

        // Zone filtering logic
        if (!$isNationalReferee && $user->zone_id) {
            $query->where('zone_id', $user->zone_id);
        }

        // Simple search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('club', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $tournaments = $query->orderBy('start_date', 'desc')->paginate(20);

        return view('tournaments.index', compact('tournaments'));
    }

    /**
     * Display public calendar view
     */
    public function publicCalendar(Request $request)
    {
        $user = auth()->user();
        $isNationalReferee = in_array($user->level ?? '', ['nazionale', 'internazionale']);

        // ✅ FIXED: tournamentType relationship
        $tournaments = Tournament::with(['tournamentType', 'zone', 'club'])
            ->where('status', '!=', 'draft')
            ->when(!$isNationalReferee, function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->get();

        // Get user's availabilities and assignments if referee
        $userAvailabilities = [];
        $userAssignments = [];

        if ($user->user_type === 'referee') {
            $userAvailabilities = $user->availabilities()->pluck('tournament_id')->toArray();
            $userAssignments = $user->assignments()->pluck('tournament_id')->toArray();
        }

        // Format for calendar
        $calendarData = [
            'tournaments' => $tournaments->map(function ($tournament) use ($userAvailabilities, $userAssignments) {
                return [
                    'id' => $tournament->id,
                    'title' => $tournament->name,
                    'start' => $tournament->start_date->format('Y-m-d'),
                    'end' => $tournament->end_date->addDay()->format('Y-m-d'),
                    // ✅ FIXED: tournamentType relationship
                    'color' => $tournament->tournamentType->calendar_color ?? '#3b82f6',
                    'extendedProps' => [
                        'club' => $tournament->club->name,
                        'zone' => $tournament->zone->name,
                        // ✅ FIXED: tournamentType relationship
                        'category' => $tournament->tournamentType->name,
                        'status' => $tournament->status,
                        'available' => in_array($tournament->id, $userAvailabilities),
                        'assigned' => in_array($tournament->id, $userAssignments),
                    ],
                ];
            }),
        ];

        return view('tournaments.calendar', compact('calendarData'));
    }

    /**
     * Display public tournament details
     */
    public function publicShow(Tournament $tournament)
    {
        $user = auth()->user();

        // Check access - hide drafts from public
        if ($tournament->status === 'draft') {
            abort(404);
        }

        // Check zone access for zone-specific users
        if (
            !in_array($user->level ?? '', ['nazionale', 'internazionale']) &&
            $user->zone_id && $tournament->zone_id !== $user->zone_id
        ) {
            abort(403, 'Non hai accesso a questo torneo.');
        }

        // ✅ FIXED: tournamentType relationship
        $tournament->load([
            'tournamentType',
            'zone',
            'club',
            'assignments.user',
            'availabilities.user'
        ]);

        // Check if user has applied/is assigned (for referees)
        $userAvailability = null;
        $userAssignment = null;

        if ($user->user_type === 'referee') {
            $userAvailability = $tournament->availabilities()->where('user_id', $user->id)->first();
            $userAssignment = $tournament->assignments()->where('user_id', $user->id)->first();
        }

        return view('tournaments.show', compact('tournament', 'userAvailability', 'userAssignment'));
    }

    /**
     * ADMIN METHODS - Solo per admin/super admin
     */

    /**
     * Display admin listing of tournaments with full management features
     */
    public function adminIndex(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin';

        // ✅ FIXED: tournamentType relationship
        $query = Tournament::with(['tournamentType', 'zone', 'club', 'assignments']);

        // Filter by zone for zone admins
        if (!$isNationalAdmin && !in_array($user->user_type, ['super_admin'])) {
            $query->where('zone_id', $user->zone_id);
        }

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        // ✅ FIXED: tournament_type_id filter
        if ($request->filled('tournament_type_id')) {
            $query->where('tournament_type_id', $request->tournament_type_id);
        }

        if ($request->filled('month')) {
            $startOfMonth = \Carbon\Carbon::parse($request->month)->startOfMonth();
            $endOfMonth = \Carbon\Carbon::parse($request->month)->endOfMonth();
            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth]);
            });
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('club', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $tournaments = $query->orderBy('start_date', 'desc')->paginate(15);

        // Get data for filters
        $zones = $isNationalAdmin ? \App\Models\Zone::orderBy('name')->get() : collect();

        // ✅ FIXED: Variable name from $categories to $tournamentTypes
        $tournamentTypes = \App\Models\TournamentType::where('is_active', true)->orderBy('name')->get();

        // Define statuses
        $statuses = [
            'draft' => 'Bozza',
            'open' => 'Aperto',
            'closed' => 'Chiuso',
            'assigned' => 'Assegnato',
            'completed' => 'Completato'
        ];

        // ✅ FIXED: compact() uses tournamentTypes
        return view('admin.tournaments.index', compact(
            'tournaments',
            'zones',
            'tournamentTypes', // ← FIXED: was 'types'
            'statuses',
            'isNationalAdmin'
        ));
    }

    /**
     * Display admin calendar with management features
     */
    public function adminCalendar(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin';

        // ✅ FIXED: tournamentType relationship
        $tournaments = Tournament::with(['tournamentType', 'zone', 'club', 'assignments', 'availabilities'])
            ->when(!$isNationalAdmin && !in_array($user->user_type, ['super_admin']), function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->get();

        // Format for calendar with admin data
        $calendarData = [
            'tournaments' => $tournaments->map(function ($tournament) {
                return [
                    'id' => $tournament->id,
                    'title' => $tournament->name,
                    'start' => $tournament->start_date->format('Y-m-d'),
                    'end' => $tournament->end_date->addDay()->format('Y-m-d'),
                    // ✅ FIXED: tournamentType relationship
                    'color' => $tournament->tournamentType->calendar_color ?? '#3b82f6',
                    'extendedProps' => [
                        'club' => $tournament->club->name,
                        'zone' => $tournament->zone->name,
                        // ✅ FIXED: tournamentType relationship
                        'category' => $tournament->tournamentType->name,
                        'status' => $tournament->status,
                        'assignments_count' => $tournament->assignments()->count(),
                        'availabilities_count' => $tournament->availabilities()->count(),
                        'required_referees' => $tournament->required_referees ?? 1,
                        'can_edit' => true, // Admin can always edit
                    ],
                ];
            }),
        ];

        return view('admin.tournaments.calendar', compact('calendarData'));
    }
}
