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
use App\Services\DocumentGenerationService;

class AssignmentController extends Controller
{

    protected $documentService;

    public function __construct(DocumentGenerationService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Display a listing of assignments.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin';

        $query = Assignment::with([
            'user:id,name,email,level,referee_code,zone_id',
            'tournament:id,name,start_date,end_date,club_id,tournament_type_id',
            'tournament.club:id,name',
            'tournament.tournamentType:id,name',
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

        // NUOVO: Controlla se arbitro già assegnato
        if ($tournament->assignments()->where('user_id', $referee->id)->exists()) {
            return redirect()->back()
                ->with('error', "L'arbitro {$referee->name} è già assegnato a questo torneo con un altro ruolo.");
        }

        // Create assignment
        $assignment = Assignment::create([
            'tournament_id' => $tournament->id,
            'user_id' => $referee->id,
            'role' => $request->role,
            'notes' => $request->notes,
            'assigned_at' => now(),
            'assigned_by_id' => auth()->id(),
            'is_confirmed' => true, // SEMPRE confermato
        ]);

        return redirect()
            ->route('admin.assignments.create', ['tournament_id' => $tournament->id])
            ->with('success', "Arbitro {$referee->name} assegnato con successo come {$request->role}!");
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
     * Update destroy method to redirect back to tournament assignment if coming from there.
     */
    public function destroy(Assignment $assignment): RedirectResponse
    {
        $this->checkAssignmentAccess($assignment);

        $tournamentId = $assignment->tournament_id;
        $tournamentName = $assignment->tournament->name;
        $refereeName = $assignment->user->name;

        $assignment->delete();

        // Check if we came from tournament assignment page
        $referer = request()->headers->get('referer');
        if ($referer && str_contains($referer, '/assign')) {
            return redirect()
                ->route('admin.assignments.assign-referees', $tournamentId)
                ->with('success', "{$refereeName} rimosso dal comitato di gara di {$tournamentName}.");
        }

        // Default redirect to assignments list
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
        $assignedReferees = $tournament->assignedReferees;
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
