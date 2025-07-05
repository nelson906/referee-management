<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

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
        $isNationalAdmin = $user->user_type === 'national_admin';

        // Get tournaments that need referees - now using physical columns
        $tournaments = Tournament::with(['club', 'tournamentCategory'])
            ->whereIn('status', ['open', 'closed'])
            ->when(!$isNationalAdmin && $user->user_type !== 'super_admin', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->whereRaw('(SELECT COUNT(*) FROM assignments WHERE tournament_id = tournaments.id) <
                       (SELECT max_referees FROM tournament_categories WHERE id = tournaments.tournament_category_id)')
            ->orderBy('start_date')
            ->limit(10)
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

        // Create assignment
        $assignment = Assignment::create([
            'tournament_id' => $tournament->id,
            'user_id' => $referee->id,
            'role' => $request->role,
            'notes' => $request->notes,
            'assigned_at' => now(),
            'assigned_by' => auth()->id(),
            'is_confirmed' => false,
        ]);

        // TODO: Send notification to referee

        return redirect()
            ->route('admin.assignments.index')
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
}
