<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\YearlyAssignment;
use App\Models\YearlyTournament;
use App\Models\Tournament;
use App\Models\Assignment;
use App\Models\User;
use App\Services\YearService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function index(Request $request, $tournamentId = null): View
    {
        // Gestione anno
        if ($tournamentId) {
            $tournament = Tournament::findOrFail($tournamentId);
            $year = Carbon::parse($tournament->start_date)->year;
            YearService::setYear($year);
        }

        $year = YearService::getCurrentYear();

        // Verifica che la tabella esista
        if (!YearService::tableExists('assignments', $year)) {
            return view('admin.assignments.index', [
                'assignments' => collect(),
                'year' => $year,
                'tournaments' => collect(),
                'referees' => User::where('user_type', 'referee')->where('is_active', true)->get()
            ]);
        }

        // Carica arbitri
        $referees = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Carica tornei per l'anno selezionato
        $tournaments = collect();
        if (YearService::tableExists('tournaments', $year)) {
            $tournaments = YearlyTournament::forYear($year)
                ->with(['club'])
                ->orderBy('start_date', 'desc')
                ->get();
        }

        // Query assegnazioni
        $assignmentsQuery = YearlyAssignment::forYear($year)
            ->with([
                'user',
                'assignedBy'
            ]);

        // Applica filtri
        if ($tournamentId) {
            $assignmentsQuery->where('tournament_id', $tournamentId);
        }

        if ($request->filled('tournament_id') && !$tournamentId) {
            $assignmentsQuery->where('tournament_id', $request->tournament_id);
        }

        if ($request->filled('user_id')) {
            $assignmentsQuery->where('user_id', $request->user_id);
        }

        // Ottieni assegnazioni
        $assignments = $assignmentsQuery
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Carica i tornei separatamente per evitare problemi di relazione
        $tournamentIds = $assignments->pluck('tournament_id')->unique();
        $tournamentsData = [];

        if ($tournamentIds->isNotEmpty() && YearService::tableExists('tournaments', $year)) {
            $tournamentsData = YearlyTournament::forYear($year)
                ->with('club')
                ->whereIn('id', $tournamentIds)
                ->get()
                ->keyBy('id');
        }

        // Trasforma i dati per la view
        $assignments->getCollection()->transform(function ($assignment) use ($tournamentsData) {
            $assignment->referee_name = $assignment->user->name ?? 'N/A';

            $tournament = $tournamentsData[$assignment->tournament_id] ?? null;
            $assignment->tournament_name = $tournament->name ?? 'N/A';
            $assignment->club_name = $tournament->club->name ?? 'N/A';
            $assignment->tournament_start_date = $tournament->start_date ?? null;

            return $assignment;
        });

        return view('admin.assignments.index', compact(
            'assignments',
            'year',
            'tournaments',
            'referees'
        ));
    }
    /**
     * Store a new assignment
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:Direttore di Torneo,Arbitro,Osservatore'
        ]);

        // Trova l'anno del torneo
        $tournament = Tournament::findOrFail($request->tournament_id);
        $year = Carbon::parse($tournament->start_date)->year;

        // Crea l'assegnazione usando YearlyAssignment
        YearlyAssignment::forYear($year)->create([
            'tournament_id' => $request->tournament_id,
            'user_id' => $request->user_id,
            'assigned_by_id' => auth()->id(),
            'role' => $request->role,
            'assigned_at' => now(),
            'is_confirmed' => false,
        ]);

        return back()->with('success', 'Assegnazione creata con successo');
    }

    /**
     * Delete assignment
     */
    public function destroy($tournamentId, $userId): RedirectResponse
    {
        // Trova il torneo per determinare l'anno
        $tournament = Tournament::findOrFail($tournamentId);
        $year = Carbon::parse($tournament->start_date)->year;

        // Elimina usando YearlyAssignment
        YearlyAssignment::forYear($year)
            ->where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->delete();

        return back()->with('success', 'Assegnazione rimossa');
    }

    /**
     * Show assignment details
     */
    public function show($assignmentId): View
    {
        // Trova l'assegnazione in tutti gli anni disponibili
        $assignment = null;

        foreach (YearService::getAvailableYears() as $year) {
            if (YearService::tableExists('assignments', $year)) {
                $found = YearlyAssignment::forYear($year)
                    ->with([
                        'user',
                        'tournament.club',
                        'tournament.zone',
                        'tournament.tournamentType',
                        'assignedBy'
                    ])
                    ->find($assignmentId);

                if ($found) {
                    $assignment = $found;
                    break;
                }
            }
        }

        if (!$assignment) {
            abort(404, 'Assegnazione non trovata');
        }

        $this->checkAssignmentAccess($assignment);

        return view('admin.assignments.show', compact('assignment'));
    }

    /**
     * Bulk assign referees to tournament
     */
    public function bulkAssign(Request $request): RedirectResponse
    {
        $request->validate([
            'tournament_id' => 'required',
            'referees' => 'required|array|min:1',
            'referees.*' => 'array',
            'referees.*.selected' => 'nullable|in:1',
            'referees.*.user_id' => 'required_with:referees.*.selected|exists:users,id',
            'referees.*.role' => 'required_with:referees.*.selected|in:Arbitro,Direttore di Torneo,Osservatore',
        ]);

        // Trova torneo e anno
        $tournament = Tournament::findOrFail($request->tournament_id);
        $year = Carbon::parse($tournament->start_date)->year;

        $this->checkTournamentAccess($tournament);

        $assignedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($request->referees as $refereeData) {
                if (!isset($refereeData['selected']) || $refereeData['selected'] !== '1') {
                    continue;
                }

                if (!isset($refereeData['user_id']) || !isset($refereeData['role'])) {
                    continue;
                }

                $referee = User::findOrFail($refereeData['user_id']);

                // Verifica se già assegnato
                $existingAssignment = YearlyAssignment::forYear($year)
                    ->where('tournament_id', $tournament->id)
                    ->where('user_id', $referee->id)
                    ->exists();

                if ($existingAssignment) {
                    $errors[] = "{$referee->name} è già assegnato a questo torneo";
                    continue;
                }

                // Crea assegnazione
                YearlyAssignment::forYear($year)->create([
                    'tournament_id' => $tournament->id,
                    'user_id' => $referee->id,
                    'role' => $refereeData['role'],
                    'notes' => $refereeData['notes'] ?? null,
                    'assigned_at' => now(),
                    'assigned_by_id' => auth()->id(),
                    'is_confirmed' => true,
                ]);

                $assignedCount++;
            }

            DB::commit();

            $message = "{$assignedCount} arbitri assegnati con successo.";
            if (!empty($errors)) {
                $message .= " Errori: " . implode(', ', $errors);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Errore durante l\'assegnazione degli arbitri. Riprova.');
        }
    }

    /**
     * Check if user can access the tournament.
     */
    private function checkTournamentAccess($tournament): void
    {
        $user = auth()->user();

        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            return;
        }

        if ($user->user_type === 'admin' && $tournament->zone_id !== $user->zone_id) {
            // Per ora permetti l'accesso, in futuro potresti fare abort(403)
            return;
        }
    }

    /**
     * Check if user can access the assignment.
     */
    private function checkAssignmentAccess($assignment): void
    {
        $this->checkTournamentAccess($assignment->tournament);
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


        $query = Tournament::with(['club', 'tournamentType'])
            ->whereIn('status', ['open', 'closed', 'draft'])
            ->where('start_date', '>=', Carbon::today()->subDays(30));

        // FILTRO ZONA - versione corretta
        if (!$isNationalAdmin) {
            $query->where('zone_id', $user->zone_id);
        }

        $tournaments = $query->orderBy('start_date')->get();

        // ARBITRI SEPARATI PER DISPONIBILITÀ
        $tournamentId = $request->get('tournament_id');

        // Se un torneo è selezionato, separa arbitri per disponibilità
        // Nel metodo create(), modifica le query degli arbitri:
        if ($tournamentId) {
            $tournament = Tournament::with(['assignments.user'])->findOrFail($tournamentId);
            $this->checkTournamentAccess($tournament);

            $assignedRefereeIds = $tournament->assignments->pluck('user_id')->toArray();

            // CORRETTO ✅ - use only necessary relations
            $availableReferees = User::with(['zone'])
                ->whereHas('availabilities', function ($q) use ($tournamentId) {
                    $q->where('tournament_id', $tournamentId);
                })
                ->whereNotIn('id', $assignedRefereeIds)
                ->where('user_type', 'referee')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            // Altri arbitri della zona NON già assegnati
            $otherReferees = User::with(['zone'])
                ->where('user_type', 'referee')
                ->where('is_active', true)
                ->where('zone_id', $user->zone_id)
                ->whereDoesntHave('availabilities', function ($q) use ($tournamentId) {
                    $q->where('tournament_id', $tournamentId);
                })
                ->whereNotIn('id', $assignedRefereeIds) // ESCLUDI già assegnati
                ->orderBy('name')
                ->get();
        } else {
            // Se nessun torneo selezionato, tutti gli arbitri della zona
            $availableReferees = collect();
            $otherReferees = User::with(['zone'])
                ->where('user_type', 'referee')
                ->where('is_active', true)
                ->where('zone_id', $user->zone_id)
                ->orderBy('name')
                ->get();
        }

        return view('admin.assignments.create', compact(
            'tournament',
            'tournaments',
            'availableReferees',
            'otherReferees'
        ));
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
     * Get referees who declared availability for this tournament.
     */
    public function getAvailableReferees(Request $request)
    {
        $tournamentId = $request->tournament_id;
        $tournament = Tournament::findOrFail($tournamentId);

        // SOLO arbitri con disponibilità per QUESTO torneo specifico
        $availabilities = Availability::where('tournament_id', $tournamentId)
            ->with(['user' => function ($query) {
                $query->where('user_type', 'referee')
                    ->where('is_active', true);
            }])
            ->get();

        // NON cercare in altri anni!
        return response()->json($availabilities);
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
            ->whereIn('level', ['nazionale', 'internazionale'])
            ->whereNotIn('id', $excludeIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Show assignment interface for a specific tournament.
     */
    public function assignReferees(Tournament $tournament): View
    {
        $this->checkTournamentAccess($tournament);

        $user = auth()->user();
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        // Load tournament with relations
        // CORRETTO ✅
        $tournament->load(['club', 'zone', 'tournamentType']);

        // Get currently assigned referees - CORRETTO ✅
        $assignedReferees = $tournament->assignments()
            ->with('user')
            ->get()
            ->map(function ($assignment) {
                // Aggiungi i dati user all'assignment per retrocompatibilità
                $assignment->name = $assignment->user->name;
                $assignment->referee_code = $assignment->user->referee_code;
                $assignment->level = $assignment->user->level;
                return $assignment;
            });
        $assignedRefereeIds = $assignedReferees->pluck('user_id')->toArray();

        // Get available referees - CORRETTO ✅
        $availableReferees = User::with('zone')
            ->whereHas('availabilities', function ($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            })
            ->where('user_type', 'referee')
            ->where('is_active', true)
            ->whereNotIn('id', $assignedRefereeIds)
            ->orderBy('name')
            ->get();

        // Get possible referees (zone referees who haven't declared availability) - EXCLUDE already assigned
        $possibleReferees = User::with(['referee', 'zone'])
            ->where('user_type', 'referee')
            ->where('is_active', true)
            ->where('zone_id', $tournament->zone_id)
            ->whereDoesntHave('availabilities', function ($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            })
            ->whereNotIn('id', $assignedRefereeIds)
            ->orderBy('name')
            ->get();


        // Get national referees (for national tournaments) - EXCLUDE already assigned
        $nationalReferees = collect();
        if ($tournament->tournamentType->is_national) {
            $nationalReferees = User::with(['referee', 'zone'])
                ->where('user_type', 'referee')
                ->where('is_active', true)
                ->whereHas('referee', function ($q) {
                    $q->whereIn('level', ['nazionale', 'internazionale']);
                })
                ->whereNotIn('id', $assignedRefereeIds)
                ->whereNotIn('id', $availableReferees->pluck('id')->merge($possibleReferees->pluck('id')))
                ->orderBy('name')
                ->get();
        }

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
