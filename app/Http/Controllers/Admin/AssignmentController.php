<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Carbon\Carbon;

class AssignmentController extends Controller
{
    /**
     * Display a listing of assignments.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin';

        $query = Assignment::with([
            'user:id,name,email,level',
            'tournament:id,name,start_date,end_date,club_id,tournament_category_id',
            'tournament.club:id,name',
            'tournament.tournamentCategory:id,name',
            'assignedBy:id,name'
        ]);

        // Filter by zone for non-national admins
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Apply filters
        if ($request->filled('tournament_id')) {
            $query->where('tournament_id', $request->tournament_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'confirmed') {
                $query->where('is_confirmed', true);
            } elseif ($request->status === 'unconfirmed') {
                $query->where('is_confirmed', false);
            }
        }

        $assignments = $query
            ->join('tournaments', 'assignments.tournament_id', '=', 'tournaments.id')
            ->select('assignments.*')  // IMPORTANTE: seleziona solo da assignments
            ->orderBy('tournaments.start_date', 'desc')
            ->orderBy('assignments.created_at', 'desc')
            ->paginate(20);

        // Get data for filters
        $tournaments = Tournament::with('club')
            ->when(!$isNationalAdmin && $user->user_type !== 'super_admin', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->orderBy('start_date', 'desc')
            ->get();

        $referees = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->when(!$isNationalAdmin && $user->user_type !== 'super_admin', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->orderBy('name')
            ->get();

        return view('admin.assignments.index', compact(
            'assignments',
            'tournaments',
            'referees',
            'isNationalAdmin'
        ));
    }

    /**
     * Show the form for creating a new assignment.
     */
public function create(Request $request): View
{
    $tournamentId = $request->get('tournament_id');
    $tournament = null;

    if ($tournamentId) {
        $tournament = Tournament::findOrFail($tournamentId);
        $this->checkTournamentAccess($tournament);
    }

    $user = auth()->user();
    $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

    // Get tournaments - CORRETTO SENZA max_referees
// VERSIONE ANCORA PIÙ FLESSIBILE
$tournaments = Tournament::with(['club', 'tournamentCategory'])
    ->where('start_date', '>=', Carbon::today()->subDays(30)) // Ultimi 30 giorni
    ->when(!$isNationalAdmin, function ($q) use ($user) {
        $q->where('zone_id', $user->zone_id);
    })
    ->orderBy('start_date')
    ->get();
    return view('admin.assignments.create', compact('tournament', 'tournaments'));
}

    /**
     * Store a newly created assignment.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:Arbitro,Direttore di Torneo,Osservatore',
            'notes' => 'nullable|string|max:500',
        ]);

        $tournament = Tournament::findOrFail($request->tournament_id);
        $this->checkTournamentAccess($tournament);

        $referee = User::findOrFail($request->user_id);

        // Check if referee can be assigned
        if (!$tournament->canAssignReferee($referee)) {
            return redirect()->back()
                ->with('error', 'Impossibile assegnare questo arbitro al torneo.');
        }

        // Create assignment - FIXED: usa assigned_by_id invece di assigned_by
        $assignment = Assignment::create([
            'tournament_id' => $tournament->id,
            'user_id' => $referee->id,
            'role' => $request->role,
            'notes' => $request->notes,
            'assigned_at' => now(),
            'assigned_by_id' => auth()->id(), // CORRETTO: assigned_by_id
            'is_confirmed' => false,
        ]);

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', "Arbitro {$referee->name} assegnato con successo al torneo {$tournament->name}.");
    }

    /**
     * Display the specified assignment.
     */
    public function show(Assignment $assignment): View
    {
        $this->checkAssignmentAccess($assignment);

        $assignment->load([
            'user',
            'tournament.club',
            'tournament.zone',
            'tournament.tournamentCategory',
            'assignedBy'
        ]);

        return view('admin.assignments.show', compact('assignment'));
    }

    /**
     * Update the specified assignment.
     */
    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->checkAssignmentAccess($assignment);

        $request->validate([
            'role' => 'required|in:Arbitro,Direttore di Torneo,Osservatore',
            'notes' => 'nullable|string|max:500',
        ]);

        $assignment->update([
            'role' => $request->role,
            'notes' => $request->notes,
        ]);

        return redirect()
            ->route('admin.assignments.show', $assignment)
            ->with('success', 'Assegnazione aggiornata con successo.');
    }

    /**
     * Confirm assignment.
     */
    public function confirm(Assignment $assignment): RedirectResponse
    {
        $this->checkAssignmentAccess($assignment);

        $assignment->update(['is_confirmed' => true]);

        return redirect()->back()
            ->with('success', 'Assegnazione confermata con successo.');
    }

    /**
     * Remove the specified assignment.
     */
    public function destroy(Assignment $assignment): RedirectResponse
    {
        $this->checkAssignmentAccess($assignment);

        $tournamentName = $assignment->tournament->name;
        $refereeName = $assignment->user->name;

        $assignment->delete();

        return redirect()
            ->route('admin.assignments.index')
            ->with('success', "Assegnazione di {$refereeName} al torneo {$tournamentName} rimossa con successo.");
    }

    /**
     * Check if user can access the tournament.
     */
    private function checkTournamentAccess(Tournament $tournament): void
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
     * Check if user can access the assignment.
     */
    private function checkAssignmentAccess(Assignment $assignment): void
    {
        $this->checkTournamentAccess($assignment->tournament);
    }

    /**
     * Show assignment interface for a specific tournament.
     */
    public function assignReferees(Tournament $tournament): View
    {
        $this->checkTournamentAccess($tournament);

        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        // Load tournament with relations
        $tournament->load(['club', 'zone', 'tournamentCategory', 'assignments.user']);

        // Get available referees (have declared availability)
        $availableReferees = $this->getAvailableReferees($tournament);

        // Get possible referees (zone referees who haven't declared availability)
        $possibleReferees = $this->getPossibleReferees($tournament, $availableReferees->pluck('id'));

        // Get national referees (for national tournaments)
        $nationalReferees = collect();
        if ($tournament->tournamentCategory->is_national) {
            $nationalReferees = $this->getNationalReferees(
                $tournament,
                $availableReferees->pluck('id')->merge($possibleReferees->pluck('id'))
            );
        }

        // Get currently assigned referees
        $assignedReferees = $tournament->assignments()->with('user.referee')->get();

        // Check conflicts for all referees
        $this->checkDateConflicts($availableReferees, $tournament);
        $this->checkDateConflicts($possibleReferees, $tournament);
        $this->checkDateConflicts($nationalReferees, $tournament);

        return view('admin.assignments.assign-referees', compact(
            'tournament',
            'availableReferees',
            'possibleReferees',
            'nationalReferees',
            'assignedReferees',
            'isNationalAdmin'
        ));
    }

    /**
     * Assign multiple referees to tournament.
     */
    public function bulkAssign(Request $request): RedirectResponse
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'referees' => 'required|array|min:1',
            'referees.*.user_id' => 'required|exists:users,id',
            'referees.*.role' => 'required|in:Arbitro,Direttore di Torneo,Osservatore',
            'referees.*.notes' => 'nullable|string|max:500',
        ]);

        $tournament = Tournament::findOrFail($request->tournament_id);
        $this->checkTournamentAccess($tournament);

        $assignedCount = 0;

        \DB::beginTransaction();
        try {
            foreach ($request->referees as $refereeData) {
                $referee = User::findOrFail($refereeData['user_id']);

                // Check if already assigned
                if ($tournament->assignments()->where('user_id', $referee->id)->exists()) {
                    continue;
                }

                // Create assignment
                Assignment::create([
                    'tournament_id' => $tournament->id,
                    'user_id' => $referee->id,
                    'role' => $refereeData['role'],
                    'notes' => $refereeData['notes'] ?? null,
                    'assigned_at' => now(),
                    'assigned_by' => auth()->id(),
                    'is_confirmed' => true, // Nessuna conferma richiesta
                ]);

                $assignedCount++;
            }

            \DB::commit();

            return redirect()
                ->route('admin.assignments.assign-referees', $tournament)
                ->with('success', "{$assignedCount} arbitri assegnati con successo al torneo {$tournament->name}.");
        } catch (\Exception $e) {
            \DB::rollback();

            return redirect()->back()
                ->with('error', 'Errore durante l\'assegnazione degli arbitri. Riprova.');
        }
    }

    /**
     * Get referees who declared availability for this tournament.
     */
    private function getAvailableReferees(Tournament $tournament)
    {
        return User::with(['referee', 'zone'])
            ->whereHas('availabilities', function ($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            })
            ->where('user_type', 'referee')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get zone referees who haven't declared availability.
     */
    private function getPossibleReferees(Tournament $tournament, $excludeIds)
    {
        return User::with(['referee', 'zone'])
            ->where('user_type', 'referee')
            ->where('is_active', true)
            ->where('zone_id', $tournament->zone_id)
            ->whereNotIn('id', $excludeIds)
            ->whereDoesntHave('availabilities', function ($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get national/international referees (for national tournaments).
     */
    private function getNationalReferees(Tournament $tournament, $excludeIds)
    {
        return User::with(['referee', 'zone'])
            ->where('user_type', 'referee')
            ->where('is_active', true)
            ->whereHas('referee', function ($q) {
                $q->whereIn('level', ['nazionale', 'internazionale']);
            })
            ->whereNotIn('id', $excludeIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Check date conflicts for referees.
     */
    private function checkDateConflicts($referees, Tournament $tournament)
    {
        foreach ($referees as $referee) {
            $conflicts = Assignment::where('user_id', $referee->id)
                ->whereHas('tournament', function ($q) use ($tournament) {
                    $q->where('id', '!=', $tournament->id)
                        ->where(function ($q2) use ($tournament) {
                            // Tournament dates overlap
                            $q2->whereBetween('start_date', [$tournament->start_date, $tournament->end_date])
                                ->orWhereBetween('end_date', [$tournament->start_date, $tournament->end_date])
                                ->orWhere(function ($q3) use ($tournament) {
                                    $q3->where('start_date', '<=', $tournament->start_date)
                                        ->where('end_date', '>=', $tournament->end_date);
                                });
                        });
                })
                ->with('tournament:id,name,start_date,end_date')
                ->get();

            $referee->conflicts = $conflicts;
            $referee->has_conflicts = $conflicts->count() > 0;
        }
    }
}
