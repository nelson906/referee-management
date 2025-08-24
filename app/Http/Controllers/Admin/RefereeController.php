<?php

// ===================================================================
// 1. AGGIORNAMENTO Admin/RefereeController.php
// ===================================================================

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Log;
use App\Helpers\RefereeLevelsHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Referee;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\Assignment;

class RefereeController extends Controller
{


    /**
     * List referees with filters
     */
    public function index(Request $request)
    {
        $year = session('selected_year', date('Y'));

        $referees = User::where('user_type', 'referee')
            ->when($request->zone_id, function($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            })
            ->when($request->level, function($q) use ($request) {
                $q->where('level', $request->level);
            })
            ->withCount([
                'assignments' => function($q) use ($year) {
                    // Count personalizzato per anno
                    $q->from("assignments_{$year}");
                }
            ])
            ->paginate(20);

        return view('admin.referees.index', compact('referees', 'year'));
    }


    /**
     * Show the form for creating a new referee.
     */
    public function create(): View
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        // ✅ USE HELPER for levels

        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        return view('admin.referees.create', compact('zones'));
    }

    /**
     * Store a newly created referee.
     */
    public function store(Request $request): RedirectResponse
    {
        // ✅ NORMALIZE level during validation
        $request->merge([
            'level' => RefereeLevelsHelper::normalize($request->level)
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'level' => ['required', function ($attribute, $value, $fail) {
                if (!RefereeLevelsHelper::isValid($value)) {
                    $fail("Il livello selezionato non è valido.");
                }
            }],
            'zone_id' => 'required|exists:zones,id',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',

            // Optional referee extension fields
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'tax_code' => 'nullable|string|max:16',
            'qualifications' => 'nullable|array',
            'languages' => 'nullable|array',
            'available_for_international' => 'boolean',
        ]);

        $user = auth()->user();

        if ($user->user_type === 'admin' && $request->zone_id != $user->zone_id) {
            abort(403, 'Non puoi creare arbitri in zone diverse dalla tua.');
        }

        try {
            DB::beginTransaction();

            $refereeCode = $this->generateRefereeCode();

            // ✅ CREATE USER senza 'notes'
            $newUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make('arbitro123'),
                'user_type' => 'referee',
                'zone_id' => $request->zone_id,
                'phone' => $request->phone,
                'city' => $request->city,
                'level' => $request->level,
                'referee_code' => $refereeCode,
                // ❌ RIMUOVI: 'notes' => $request->notes,
                'is_active' => $request->boolean('is_active', true),
                'email_verified_at' => now(),
                'certified_date' => now(),
            ]);

            // ✅ Create referee extension se ci sono dati estesi O notes
            $hasExtendedData = $request->filled(['address', 'postal_code', 'tax_code', 'notes']) ||
                !empty($request->qualifications) ||
                !empty($request->languages) ||
                $request->boolean('available_for_international');

            if ($hasExtendedData) {
                Referee::create([
                    'user_id' => $newUser->id,
                    'address' => $request->address,
                    'postal_code' => $request->postal_code,
                    'tax_code' => $request->tax_code,
                    'notes' => $request->notes, // ✅ Notes vanno qui
                    'qualifications' => $request->qualifications ?? [],
                    'languages' => $request->languages ?? ['it'],
                    'available_for_international' => $request->boolean('available_for_international', false),
                    'profile_completed_at' => now(),
                ]);
            }

            DB::commit();

            return redirect()
                ->route('admin.referees.index')
                ->with('success', "Arbitro {$newUser->name} creato con successo! Codice: {$refereeCode}");
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Errore durante la creazione dell\'arbitro: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified referee - USER-CENTRIC APPROACH ✅
     */
    public function show(User $referee): View
    {
        if (!$referee->isReferee()) {
            abort(404, 'Arbitro non trovato.');
        }

        $this->checkRefereeAccess($referee);

        // ✅ Load relationships from User model
        $referee->load([
            'zone',
            'referee', // Optional extension data
            'assignments.tournament.club',
            'availabilities.tournament.club'
        ]);

        // Get statistics from User model
        $stats = [
            'total_assignments' => $referee->assignments()->count(),
            'confirmed_assignments' => $referee->assignments()->where('is_confirmed', true)->count(),
            'current_year_assignments' => $referee->assignments()->whereYear('created_at', now()->year)->count(),
            'total_availabilities' => $referee->availabilities()->count(),
            'upcoming_assignments' => $referee->assignments()->upcoming()->count(),
        ];

        // Get recent assignments and availabilities
        $recentAssignments = $referee->assignments()
            ->with(['tournament.club'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentAvailabilities = $referee->availabilities()
            ->with(['tournament.club'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.referees.show', compact('referee', 'stats', 'recentAssignments', 'recentAvailabilities'));
    }

    /**
     * Show the form for editing the specified referee - USER-CENTRIC APPROACH ✅
     */
    public function edit(User $referee): View
    {
        if (!$referee->isReferee()) {
            abort(404, 'Arbitro non trovato.');
        }

        $this->checkRefereeAccess($referee);
        $referee->load('referee');

        // ✅ USE HELPER for levels

        // ✅ DEBUG INFO (remove after fix)
        if (config('app.debug')) {
            \Log::info(
                'RefereeLevelsHelper Debug',
                RefereeLevelsHelper::debugLevel($referee->level ?? 'null')
            );
        }

        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        return view('admin.referees.edit', compact('referee', 'zones'));
    }

    /**
     * Update the specified referee.
     */
    public function update(Request $request, User $referee): RedirectResponse
    {
        if (!$referee->isReferee()) {
            abort(404, 'Arbitro non trovato.');
        }

        $this->checkRefereeAccess($referee);

        // ✅ NORMALIZE level during validation
        $request->merge([
            'level' => RefereeLevelsHelper::normalize($request->level)
        ]);

        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $referee->id,
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'zone_id' => 'required|exists:zones,id',
            'level' => ['required', function ($attribute, $value, $fail) {
                if (!RefereeLevelsHelper::isValid($value)) {
                    $fail("Il livello selezionato non è valido.");
                }
            }],
            'referee_code' => 'nullable|string|max:20|unique:users,referee_code,' . $referee->id,
            'notes' => 'nullable|string',
            'is_active' => 'boolean',

            // Optional referee extension fields
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'tax_code' => 'nullable|string|max:16',
            'qualifications' => 'nullable|array',
            'languages' => 'nullable|array',
            'available_for_international' => 'boolean',
        ]);

        if ($user->user_type === 'admin' && $request->zone_id != $user->zone_id) {
            abort(403, 'Non puoi spostare arbitri in zone diverse dalla tua.');
        }

        try {
            DB::beginTransaction();

            // ✅ UPDATE USER senza 'notes'
            $referee->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'city' => $request->city,
                'zone_id' => $request->zone_id,
                'level' => $request->level,
                'referee_code' => $request->referee_code ?: $referee->referee_code,
                // ❌ RIMUOVI: 'notes' => $request->notes,
                'is_active' => $request->boolean('is_active', true),
            ]);

            // ✅ Handle referee extension data incluse le notes
            $hasExtendedData = $request->filled(['address', 'postal_code', 'tax_code', 'notes']) ||
                !empty($request->qualifications) ||
                !empty($request->languages) ||
                $request->has('available_for_international');

            if ($hasExtendedData) {
                $referee->referee()->updateOrCreate(
                    ['user_id' => $referee->id],
                    [
                        'address' => $request->address,
                        'postal_code' => $request->postal_code,
                        'tax_code' => $request->tax_code,
                        'notes' => $request->notes, // ✅ Notes vanno qui
                        'qualifications' => $request->qualifications ?? [],
                        'languages' => $request->languages ?? ['it'],
                        'available_for_international' => $request->boolean('available_for_international', false),
                        'profile_completed_at' => $referee->referee->profile_completed_at ?? now(),
                    ]
                );
            }

            DB::commit();

            return redirect()
                ->route('admin.referees.show', $referee)
                ->with('success', "Arbitro \"{$referee->name}\" aggiornato con successo!");
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Errore durante l\'aggiornamento: ' . $e->getMessage());
        }
    }

    /**
     * Toggle referee active status.
     */
    public function toggleActive(User $referee): RedirectResponse
    {
        if (!$referee->isReferee()) {
            abort(404, 'Arbitro non trovato.');
        }

        $this->checkRefereeAccess($referee);

        $referee->update(['is_active' => !$referee->is_active]);

        $status = $referee->is_active ? 'attivato' : 'disattivato';

        return redirect()->back()
            ->with('success', "Arbitro \"{$referee->name}\" {$status} con successo!");
    }

    /**
     * Remove the specified referee.
     */
    public function destroy(User $referee): RedirectResponse
    {
        if (!$referee->isReferee()) {
            abort(404, 'Arbitro non trovato.');
        }

        $this->checkRefereeAccess($referee);

        // Check if referee has active assignments
        if ($referee->assignments()->whereHas('tournament', function ($q) {
            $q->whereIn('status', ['open', 'closed', 'assigned']);
        })->exists()) {
            return redirect()
                ->route('admin.referees.index')
                ->with('error', 'Impossibile eliminare un arbitro con assegnazioni attive.');
        }

        $name = $referee->name;

        try {
            DB::beginTransaction();

            // ✅ Delete referee extension if exists (cascade will handle this, but be explicit)
            $referee->referee()?->delete();

            // ✅ Delete user (main record)
            $referee->delete();

            DB::commit();

            return redirect()
                ->route('admin.referees.index')
                ->with('success', "Arbitro \"{$name}\" eliminato con successo!");
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()
                ->route('admin.referees.index')
                ->with('error', 'Errore durante l\'eliminazione: ' . $e->getMessage());
        }
    }

    /**
     * Generate unique referee code.
     */
    private function generateRefereeCode(): string
    {
        do {
            $code = 'ARB' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::where('referee_code', $code)->exists());

        return $code;
    }

    private function checkRefereeAccess(User $referee): void
    {
        $user = auth()->user();

        if ($user->user_type === 'super_admin' || $user->user_type === 'national_admin') {
            return;
        }

        if ($user->user_type === 'admin' && $referee->zone_id !== $user->zone_id) {
            abort(403, 'Non sei autorizzato ad accedere a questo arbitro.');
        }
    }

    /**
     * Show referee curriculum
     */
    public function showCurriculum($id)
    {
        $referee = User::findOrFail($id);
        $curriculumData = [];

        for ($year = date('Y'); $year >= 2015; $year--) {
            $assignmentTable = "assignments_{$year}";
            $tournamentTable = "tournaments_{$year}";

            if (!Schema::hasTable($assignmentTable) || !Schema::hasTable($tournamentTable)) {
                continue;
            }

            $assignments = DB::table($assignmentTable . ' as a')
                ->join($tournamentTable . ' as t', 'a.tournament_id', '=', 't.id')
                ->leftJoin('clubs as c', 't.club_id', '=', 'c.id')
                ->leftJoin('zones as z', 't.zone_id', '=', 'z.id')
                ->where('a.user_id', $id)
                ->select(
                    't.id',
                    't.name',
                    't.start_date',
                    't.end_date',
                    'c.name as club_name',
                    'z.name as zone_name',
                    'a.role',
                    'a.is_confirmed'
                )
                ->orderBy('t.start_date', 'desc')
                ->get();

            if ($assignments->count() > 0) {
                $levelColumn = "level_{$year}";
                $level = $referee->$levelColumn ?? $referee->level ?? 'N/D';

                $curriculumData[$year] = [
                    'year' => $year,
                    'level' => $level,
                    'assignments' => $assignments,
                    'total' => $assignments->count(),
                    'by_role' => [
                        'td' => $assignments->where('role', 'Direttore di Torneo')->count(),
                        'arbitro' => $assignments->where('role', 'Arbitro')->count(),
                        'osservatore' => $assignments->where('role', 'Osservatore')->count(),
                    ]
                ];
            }
        }

        return view('admin.referees.curriculum', compact('referee', 'curriculumData'));
    }
    public function allCurricula()
    {
        $referees = User::where('user_type', 'referee')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.referees.curricula', compact('referees'));
    }

    public function myCurriculum()
    {
        return $this->showCurriculum(auth()->id());
    }
}
