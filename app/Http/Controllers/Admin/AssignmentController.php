<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Carbon\Carbon;
use App\Services\DocumentGenerationService;
use App\Models\Availability;

class AssignmentController extends Controller
{

    protected $documentService;

    public function __construct(DocumentGenerationService $documentService)
    {
        $this->documentService = $documentService;
    }
    /**
     * Display assignments for a tournament
     */
    public function index(Request $request, $tournamentId = null)
    {
        // Se c'è un torneo specifico, imposta l'anno
        if ($tournamentId) {
            $tournament = Tournament::findOrFail($tournamentId);
            $year = Carbon::parse($tournament->start_date)->year;
            session(['selected_year' => $year]);
        }

        $year = session('selected_year', date('Y'));

        // Query diretta alla tabella corretta
        $assignments = DB::table("assignments_{$year} as a")
            ->join("tournaments_{$year} as t", 'a.tournament_id', '=', 't.id')
            ->join('users as u', 'a.user_id', '=', 'u.id')
            ->select(
                'a.*',
                't.name as tournament_name',
                't.start_date',
                'u.name as referee_name'
            )
            ->when($tournamentId, function($q) use ($tournamentId) {
                $q->where('a.tournament_id', $tournamentId);
            })
            ->orderBy('t.start_date', 'desc')
            ->paginate(20);

        return view('admin.assignments.index', compact('assignments', 'year'));
    }

    /**
     * Store a new assignment
     */
    public function store(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:Direttore di Torneo,Arbitro,Osservatore'
        ]);

        // Trova l'anno del torneo
        $tournament = Tournament::findOrFail($request->tournament_id);
        $year = Carbon::parse($tournament->start_date)->year;

        // Inserisci nella tabella corretta
        DB::table("assignments_{$year}")->insert([
            'tournament_id' => $request->tournament_id,
            'user_id' => $request->user_id,
            'assigned_by_id' => auth()->id(),
            'role' => $request->role,
            'assigned_at' => now(),
            'is_confirmed' => false,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return back()->with('success', 'Assegnazione creata con successo');
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
     * Display the specified assignment.
     */
    public function show(Assignment $assignment): View
    {
        $this->checkAssignmentAccess($assignment);

        $assignment->load([
            'user',
            'tournament.club',
            'tournament.zone',
            'tournament.tournamentType',
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
     * Delete assignment
     */
    public function destroy($tournamentId, $userId)
    {
        $tournament = Tournament::findOrFail($tournamentId);
        $year = Carbon::parse($tournament->start_date)->year;

        DB::table("assignments_{$year}")
            ->where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->delete();

        return back()->with('success', 'Assegnazione rimossa');


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
            // abort(403, 'Non sei autorizzato ad accedere a questo torneo.');
            return;
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
     * Get referees who declared availability for this tournament.
     */
public function getAvailableReferees(Request $request)
{
    $tournamentId = $request->tournament_id;
    $tournament = Tournament::findOrFail($tournamentId);

    // SOLO arbitri con disponibilità per QUESTO torneo specifico
    $availabilities = Availability::where('tournament_id', $tournamentId)
        ->with(['user' => function($query) {
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
    ->map(function($assignment) {
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
     * Assign multiple referees to tournament.
     */
    public function bulkAssign(Request $request): RedirectResponse
    {
        $request->validate([
            'referees' => 'required|array|min:1',
            'referees.*' => 'array',
            'referees.*.selected' => 'nullable|in:1',
            'referees.*.user_id' => 'required_with:referees.*.selected|exists:users,id',
            'referees.*.role' => 'required_with:referees.*.selected|in:Arbitro,Direttore di Torneo,Osservatore',
        ]);

        $tournament = Tournament::findOrFail($request->tournament_id);
        $this->checkTournamentAccess($tournament);

        $assignedCount = 0;
        $errors = [];

        \DB::beginTransaction();
        try {
            // Process the referees array
            foreach ($request->referees as $key => $refereeData) {
                // Controlla se il referee è stato selezionato
                if (!isset($refereeData['selected']) || $refereeData['selected'] !== '1') {
                    continue;
                }

                // Verifica che abbia i dati necessari
                if (!isset($refereeData['user_id']) || !isset($refereeData['role'])) {
                    continue;
                }
                $referee = User::findOrFail($refereeData['user_id']);

                // Check if already assigned
                if ($tournament->assignments()->where('user_id', $referee->id)->exists()) {
                    $errors[] = "{$referee->name} è già assegnato a questo torneo";
                    continue;
                }

                // Create assignment
                Assignment::create([
                    'tournament_id' => $tournament->id,
                    'user_id' => $referee->id,
                    'role' => $refereeData['role'],
                    'notes' => $refereeData['notes'] ?? null,
                    'assigned_at' => now(),
                    'assigned_by_id' => auth()->id(),
                    'is_confirmed' => true, // Always confirmed
                ]);

                $assignedCount++;
            }

            \DB::commit();

            $message = "{$assignedCount} arbitri assegnati con successo al torneo {$tournament->name}.";
            if (!empty($errors)) {
                $message .= " Errori: " . implode(', ', $errors);
            }

            return redirect()
                ->route('admin.assignments.assign-referees', $tournament)
                ->with('success', $message);
        } catch (\Exception $e) {
            \DB::rollback();

            return redirect()->back()
                ->with('error', 'Errore durante l\'assegnazione degli arbitri. Riprova.');
        }
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
