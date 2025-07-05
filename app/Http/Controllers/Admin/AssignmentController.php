<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssignmentController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of assignments.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin';

        // Base query
        $query = Assignment::with(['user', 'tournament.club', 'tournament.zone', 'assignedBy'])
            ->join('tournaments', 'assignments.tournament_id', '=', 'tournaments.id');

        // Filter by zone for zone admins
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->where('tournaments.zone_id', $user->zone_id);
        }

        // Apply filters
        if ($request->has('tournament_id') && $request->tournament_id !== '') {
            $query->where('assignments.tournament_id', $request->tournament_id);
        }

        if ($request->has('referee_id') && $request->referee_id !== '') {
            $query->where('assignments.user_id', $request->referee_id);
        }

        if ($request->has('status') && $request->status !== '') {
            if ($request->status === 'confirmed') {
                $query->where('assignments.is_confirmed', true);
            } elseif ($request->status === 'pending') {
                $query->where('assignments.is_confirmed', false);
            }
        }

        if ($request->has('month') && $request->month !== '') {
            $startOfMonth = Carbon::parse($request->month)->startOfMonth();
            $endOfMonth = Carbon::parse($request->month)->endOfMonth();
            $query->whereBetween('tournaments.start_date', [$startOfMonth, $endOfMonth]);
        }

        // Order by tournament date
        $assignments = $query->select('assignments.*')
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
    public function create(Request $request)
    {
        $tournamentId = $request->get('tournament_id');
        $tournament = null;

        if ($tournamentId) {
            $tournament = Tournament::findOrFail($tournamentId);
            $this->checkTournamentAccess($tournament);
        }

        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin';

        // Get tournaments that need referees
        $tournaments = Tournament::with(['club', 'tournamentCategory'])
            ->whereIn('status', ['open', 'closed'])
            ->when(!$isNationalAdmin && $user->user_type !== 'super_admin', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            })
            ->whereRaw('(SELECT COUNT(*) FROM assignments WHERE tournament_id = tournaments.id) <
                       (SELECT max_referees FROM tournament_categories WHERE id = tournaments.tournament_category_id)')
            ->orderBy('start_date')
            ->get();

        return view('admin.assignments.create', compact('tournament', 'tournaments'));
    }

    /**
     * Store a newly created assignment.
     */
    public function store(Request $request)
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

        DB::beginTransaction();

        try {
            // Create assignment
            $assignment = Assignment::create([
                'user_id' => $referee->id,
                'tournament_id' => $tournament->id,
                'role' => $request->role,
                'is_confirmed' => false,
                'assigned_at' => Carbon::now(),
                'assigned_by' => auth()->id(),
                'notes' => $request->notes,
            ]);

            // Send notifications
            $this->notificationService->sendAssignmentNotification($assignment);

            // Update tournament status if needed
            $tournament->updateStatus();

            DB::commit();

            return redirect()
                ->route('admin.tournaments.show', $tournament)
                ->with('success', 'Arbitro assegnato con successo!');

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->with('error', 'Errore durante l\'assegnazione: ' . $e->getMessage());
        }
    }

    /**
     * Bulk assign referees from availability list.
     */
    public function bulkAssign(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'referee_ids' => 'required|array',
            'referee_ids.*' => 'exists:users,id',
            'role' => 'required|in:Arbitro,Direttore di Torneo,Osservatore',
        ]);

        $tournament = Tournament::findOrFail($request->tournament_id);
        $this->checkTournamentAccess($tournament);

        $assigned = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($request->referee_ids as $refereeId) {
                $referee = User::find($refereeId);

                if (!$tournament->canAssignReferee($referee)) {
                    $errors[] = "Impossibile assegnare {$referee->name}";
                    continue;
                }

                $assignment = Assignment::create([
                    'user_id' => $referee->id,
                    'tournament_id' => $tournament->id,
                    'role' => $request->role,
                    'is_confirmed' => false,
                    'assigned_at' => Carbon::now(),
                    'assigned_by' => auth()->id(),
                ]);

                $this->notificationService->sendAssignmentNotification($assignment);
                $assigned++;
            }

            $tournament->updateStatus();

            DB::commit();

            $message = "Assegnati {$assigned} arbitri con successo.";
            if (!empty($errors)) {
                $message .= " Errori: " . implode(', ', $errors);
            }

            return redirect()
                ->route('admin.tournaments.show', $tournament)
                ->with($assigned > 0 ? 'success' : 'error', $message);

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->with('error', 'Errore durante l\'assegnazione: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified assignment.
     */
    public function show(Assignment $assignment)
    {
        $this->checkAssignmentAccess($assignment);

        $assignment->load([
            'user',
            'tournament.club',
            'tournament.zone',
            'tournament.tournamentCategory',
            'assignedBy',
            'notifications'
        ]);

        return view('admin.assignments.show', compact('assignment'));
    }

    /**
     * Remove the specified assignment.
     */
    public function remove(Assignment $assignment)
    {
        $this->checkAssignmentAccess($assignment);

        $tournament = $assignment->tournament;

        // Check if tournament is in a state that allows removal
        if (in_array($tournament->status, ['assigned', 'completed'])) {
            return redirect()->back()
                ->with('error', 'Non è possibile rimuovere assegnazioni da tornei assegnati o completati.');
        }

        DB::beginTransaction();

        try {
            // Delete related notifications
            $assignment->notifications()->delete();

            // Delete assignment
            $assignment->delete();

            // Update tournament status
            $tournament->updateStatus();

            DB::commit();

            return redirect()
                ->route('admin.tournaments.show', $tournament)
                ->with('success', 'Assegnazione rimossa con successo.');

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->with('error', 'Errore durante la rimozione: ' . $e->getMessage());
        }
    }

    /**
     * Confirm assignment (mark as confirmed by admin).
     */
    public function confirm(Assignment $assignment)
    {
        $this->checkAssignmentAccess($assignment);

        $assignment->update(['is_confirmed' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Assegnazione confermata.'
        ]);
    }

    /**
     * Get available referees for a tournament (AJAX).
     */
    public function getAvailableReferees(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
        ]);

        $tournament = Tournament::findOrFail($request->tournament_id);
        $this->checkTournamentAccess($tournament);

        // Get referees who declared availability
        $availableReferees = $tournament->availableReferees()
            ->whereNotIn('users.id', $tournament->assignments()->pluck('user_id'))
            ->select('users.id', 'users.name', 'users.level', 'users.referee_code', 'availabilities.notes')
            ->get();

        // Get all eligible referees
        $eligibleReferees = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->whereNotIn('id', $tournament->assignments()->pluck('user_id'))
            ->when(!$tournament->tournamentCategory->is_national, function ($q) use ($tournament) {
                $q->where('zone_id', $tournament->zone_id);
            })
            ->where(function ($q) use ($tournament) {
                $requiredLevel = $tournament->tournamentCategory->required_referee_level;
                $levels = array_keys(\App\Models\TournamentCategory::REFEREE_LEVELS);
                $requiredIndex = array_search($requiredLevel, $levels);
                $eligibleLevels = array_slice($levels, $requiredIndex);
                $q->whereIn('level', $eligibleLevels);
            })
            ->select('id', 'name', 'level', 'referee_code')
            ->orderBy('name')
            ->get();

        return response()->json([
            'available' => $availableReferees,
            'eligible' => $eligibleReferees,
        ]);
    }

    /**
     * Check tournament access.
     */
    private function checkTournamentAccess(Tournament $tournament)
    {
        $user = auth()->user();

        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            return;
        }

        if ($user->user_type === 'admin' && $tournament->zone_id !== $user->zone_id) {
            abort(403, 'Non sei autorizzato ad accedere a questo torneo.');
        }
    }

    /**
     * Check assignment access.
     */
    private function checkAssignmentAccess(Assignment $assignment)
    {
        $this->checkTournamentAccess($assignment->tournament);
    }
}
