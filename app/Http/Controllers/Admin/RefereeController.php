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
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        // Query base
        $query = User::where('user_type', 'referee');

        // ✅ FILTRO ZONA: Admin zonali vedono solo la loro zona
        if (!$isNationalAdmin) {
            $query->where('zone_id', $user->zone_id);
        }

        // ✅ FILTRO STATUS con default 'active'
        $status = $request->get('status', 'active');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }
        // Se status === 'all' non applica filtri

        // ✅ RICERCA MIGLIORATA con first_name e last_name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('referee_code', 'LIKE', "%{$search}%");
            });
        }

        // ✅ FILTRO LIVELLO
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        // ✅ FILTRO ZONA (solo per CRC/SuperAdmin)
        if ($request->filled('zone_id') && $isNationalAdmin) {
            $query->where('zone_id', $request->zone_id);
        }

        // ✅ ORDINAMENTO
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');

        switch ($sortField) {
            case 'zone_name':
                $query->leftJoin('zones', 'users.zone_id', '=', 'zones.id')
                    ->orderBy('zones.name', $sortDirection)
                    ->select('users.*');
                break;
            case 'last_name':
                // Usa il campo last_name se presente, altrimenti parsing
                $query->orderByRaw("
            CASE
                WHEN last_name IS NOT NULL AND last_name != ''
                THEN last_name
                ELSE SUBSTRING_INDEX(name, ' ', -1)
            END {$sortDirection}
        ");
                break;
            case 'first_name':
                // Nuovo: ordinamento per nome
                $query->orderByRaw("
            CASE
                WHEN first_name IS NOT NULL AND first_name != ''
                THEN first_name
                ELSE SUBSTRING_INDEX(name, ' ', 1)
            END {$sortDirection}
        ");
                break;
            default:
                $query->orderBy($sortField, $sortDirection);
                break;
        }

        // Ordinamento secondario per consistenza
        if ($sortField !== 'name') {
            $query->orderBy('name', 'asc');
        }

        $referees = $query->paginate(20)->withQueryString();

        // ✅ ZONE per filtro - solo per CRC/SuperAdmin
        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        return view('admin.referees.index', compact('referees', 'zones', 'isNationalAdmin'));
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

        $name = $referee->name;

        try {
            DB::beginTransaction();

            // ✅ ELIMINA PRIMA LE ASSEGNAZIONI (CASCADE DELETE)
            $assignmentsDeleted = $referee->assignments()->delete();

            // ✅ ELIMINA ANCHE LE DISPONIBILITÀ
            $availabilitiesDeleted = $referee->availabilities()->delete();

            // ✅ ELIMINA DATI ESTESI REFEREE SE ESISTONO
            $referee->referee()?->delete();

            // ✅ ELIMINA L'UTENTE ARBITRO
            $referee->delete();

            DB::commit();

            $message = "Arbitro \"{$name}\" eliminato con successo!";
            if ($assignmentsDeleted > 0) {
                $message .= " Rimosse anche {$assignmentsDeleted} assegnazioni.";
            }
            if ($availabilitiesDeleted > 0) {
                $message .= " Rimosse {$availabilitiesDeleted} disponibilità.";
            }

            return redirect()
                ->route('admin.referees.index')
                ->with('success', $message);
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

        // Controllo accesso per zona
        $user = auth()->user();
        if (!in_array($user->user_type, ['national_admin', 'super_admin'])) {
            if ($referee->zone_id != $user->zone_id) {
                abort(403, 'Non autorizzato a vedere questo curriculum');
            }
        }

        // Genera dati curriculum
        $curriculumResult = $this->generateCurriculumData($referee);
        $curriculumData = $curriculumResult['data'];
        $totalStats = $curriculumResult['stats'];

        // IMPORTANTE: Usa sempre layout admin per gli admin!
        $layout = 'layouts.admin';

        return view('referee.curriculum', compact('referee', 'curriculumData', 'totalStats', 'layout'));
    }

    public function printCurriculum($id)
    {
        $referee = User::findOrFail($id);

        // Controllo accesso
        $user = auth()->user();
        if (!in_array($user->user_type, ['national_admin', 'super_admin'])) {
            if ($referee->zone_id != $user->zone_id) {
                abort(403);
            }
        }

        // Genera dati curriculum
        $curriculumResult = $this->generateCurriculumData($referee);
        $curriculumData = $curriculumResult['data'];
        $totalStats = $curriculumResult['stats'];

        // Vista speciale per stampa
        return view('referee.curriculum-print', compact('referee', 'curriculumData', 'totalStats'));
    }
    // app/Http/Controllers/Admin/RefereeController.php

    public function curricula(Request $request)
    {
        $user = auth()->user();
        $selectedYear = $request->get('year', session('selected_year', date('Y')));
        session(['selected_year' => $selectedYear]);

        $query = User::where('user_type', 'referee');

        // Limita per zona se non è national/super admin
        if (!in_array($user->user_type, ['national_admin', 'super_admin'])) {
            $query->where('zone_id', $user->zone_id);
        }

        // Ricerca per nome
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Aggiungi conteggio tornei per l'anno selezionato
        $referees = $query->get()->map(function ($referee) use ($selectedYear) {
            // Conta tornei per l'anno selezionato
            $assignmentsTable = "assignments_{$selectedYear}";
            $tournamentsTable = "tournaments_{$selectedYear}";

            if (Schema::hasTable($assignmentsTable)) {
                $tournamentsCount = DB::table($assignmentsTable)
                    ->where('user_id', $referee->id)
                    ->count();

                // Ultimo torneo
                $lastTournament = DB::table($assignmentsTable . ' as a')
                    ->join($tournamentsTable . ' as t', 'a.tournament_id', '=', 't.id')
                    ->where('a.user_id', $referee->id)
                    ->orderBy('t.start_date', 'desc')
                    ->select('t.name', 't.start_date')
                    ->first();

                $referee->tournaments_count = $tournamentsCount;
                $referee->last_tournament = $lastTournament;
            } else {
                $referee->tournaments_count = 0;
                $referee->last_tournament = null;
            }

            return $referee;
        });

        // Pagina manualmente
        $perPage = 20;
        $page = $request->get('page', 1);
        $referees = new \Illuminate\Pagination\LengthAwarePaginator(
            $referees->forPage($page, $perPage),
            $referees->count(),
            $perPage,
            $page,
            ['path' => $request->url()]
        );

        $zones = Zone::all();
        $availableYears = $this->getAvailableYears();

        return view('admin.referees.curricula', compact(
            'referees',
            'zones',
            'selectedYear',
            'availableYears'
        ));
    }

    private function getAvailableYears()
    {
        $years = [];
        for ($year = date('Y'); $year >= 2015; $year--) {
            if (Schema::hasTable("assignments_{$year}")) {
                $years[] = $year;
            }
        }
        return $years;
    }

    public function myCurriculum()
    {
        return $this->showCurriculum(auth()->id());
    }

    /**
     * Genera i dati del curriculum per un arbitro
     */
    private function generateCurriculumData($referee)
    {
        $curriculumData = [];

        // Raccogli dati da tutti gli anni
        for ($year = date('Y'); $year >= 2015; $year--) {
            $assignmentTable = "assignments_{$year}";
            $tournamentTable = "tournaments_{$year}";

            if (!Schema::hasTable($assignmentTable) || !Schema::hasTable($tournamentTable)) {
                continue;
            }

            // Query diretta alle tabelle anno
            $assignments = DB::table($assignmentTable . ' as a')
                ->join($tournamentTable . ' as t', 'a.tournament_id', '=', 't.id')
                ->leftJoin('clubs as c', 't.club_id', '=', 'c.id')
                ->leftJoin('zones as z', 't.zone_id', '=', 'z.id')
                ->where('a.user_id', $referee->id)
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
                // Livello arbitro per quell'anno
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

        // Calcola statistiche totali
        $totalStats = [
            'total_tournaments' => 0,
            'total_td' => 0,
            'total_arbitro' => 0,
            'total_osservatore' => 0,
            'years_active' => count($curriculumData),
            'first_year' => !empty($curriculumData) ? min(array_keys($curriculumData)) : null,
            'last_year' => !empty($curriculumData) ? max(array_keys($curriculumData)) : null,
        ];

        foreach ($curriculumData as $yearData) {
            $totalStats['total_tournaments'] += $yearData['total'];
            $totalStats['total_td'] += $yearData['by_role']['td'];
            $totalStats['total_arbitro'] += $yearData['by_role']['arbitro'];
            $totalStats['total_osservatore'] += $yearData['by_role']['osservatore'];
        }

        return [
            'data' => $curriculumData,
            'stats' => $totalStats
        ];
    }
}
