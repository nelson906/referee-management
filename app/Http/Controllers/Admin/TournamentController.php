<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentRequest;
use App\Models\Tournament;
use App\Models\TournamentCategory;
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

        // Base query
        $query = Tournament::with(['tournamentCategory', 'zone', 'club', 'assignments']);

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

        if ($request->has('category_id') && $request->category_id !== '') {
            $query->where('tournament_category_id', $request->category_id);
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
        $categories = TournamentCategory::active()->ordered()->get();
        $statuses = Tournament::STATUSES;

        return view('admin.tournaments.index', compact(
            'tournaments',
            'zones',
            'categories',
            'statuses',
            'isNationalAdmin'
        ));
    }

    /**
     * Show the form for creating a new tournament.
     */
    public function create()
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        // Get categories available for user's zone
        $categories = TournamentCategory::active()
            ->when(!$isNationalAdmin, function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('is_national', false)
                       ->whereJsonContains('settings->visibility_zones', $user->zone_id);
                })->orWhere('is_national', true);
            })
            ->ordered()
            ->get();

        // Get zones
        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        // Get clubs for the selected zone
        $clubs = club::active()
            ->when(!$isNationalAdmin, function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->ordered()
            ->get();

        return view('admin.tournaments.create', compact('categories', 'zones', 'clubs'));
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
            ->route('admin.tournaments.show', $tournament)
            ->with('success', 'Torneo creato con successo!');
    }

    /**
     * Display the specified tournament.
     */
    public function show(Tournament $tournament)
    {
        // Check access
        $this->checkTournamentAccess($tournament);

        // Load relationships
        $tournament->load([
            'tournamentCategory',
            'zone',
            'club',
            'availabilities.user',
            'assignments.user',
            'assignments.assignedBy'
        ]);

        // Get availability and assignment stats
        $stats = [
            'total_availabilities' => $tournament->availabilities()->count(),
            'total_assignments' => $tournament->assignments()->count(),
            'required_referees' => $tournament->required_referees,
            'max_referees' => $tournament->max_referees,
            'days_until_deadline' => $tournament->days_until_deadline,
        ];

        // Get referees by status
        $availableReferees = $tournament->availableReferees()
            ->whereNotIn('users.id', $tournament->assignments()->pluck('user_id'))
            ->get();

        $assignedReferees = $tournament->assignedReferees()
            ->withPivot('is_confirmed')
            ->get();

        return view('admin.tournaments.show', compact(
            'tournament',
            'stats',
            'availableReferees',
            'assignedReferees'
        ));
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
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        // Get categories
        $categories = TournamentCategory::active()
            ->when(!$isNationalAdmin, function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('is_national', false)
                       ->whereJsonContains('settings->visibility_zones', $user->zone_id);
                })->orWhere('is_national', true);
            })
            ->ordered()
            ->get();

        // Get zones
        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        // Get clubs
        $clubs = club::active()
            ->where('zone_id', $tournament->zone_id)
            ->ordered()
            ->get();

        return view('admin.tournaments.edit', compact('tournament', 'categories', 'zones', 'clubs'));
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
                ->route('admin.tournaments.show', $tournament)
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
            ->route('admin.tournaments.show', $tournament)
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
            ->when($tournament->tournamentCategory->is_national, function ($q) {
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
}


