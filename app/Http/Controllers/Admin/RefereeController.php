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
     * Display a listing of referees.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $query = User::where('user_type', 'referee')
            ->with(['zone', 'referee'])
            ->withCount(['assignments', 'availabilities']);

        // Filter by zone for non-national admins
        if (!$isNationalAdmin) {
            $query->where('zone_id', $user->zone_id);
        }

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('referee_code', 'like', "%{$search}%");
            });
        }

        // Apply zone filter
        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        // Apply level filter with helper normalization
        if ($request->filled('level')) {
            $normalizedLevel = RefereeLevelsHelper::normalize($request->level);
            $query->where('level', $normalizedLevel);
        }

        // ✅ APPLY STATUS FILTER - DEFAULT ATTIVI
        if ($request->filled('status')) {
            // Se l'utente ha specificato uno status, usalo
            $query->where('is_active', $request->status === 'active');
        } elseif (!$request->has('status')) {
            // ✅ DEFAULT: mostra solo arbitri attivi se non specificato
            $query->where('is_active', true);
        }
        // Se status = 'all' o '', mostra tutti (non aggiunge filtro)

        // GESTIONE ORDINAMENTO
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');

        // Validazione campi ordinamento
        $allowedSortFields = ['name', 'email', 'level', 'zone_name', 'is_active', 'created_at', 'last_name'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'name';
        }

        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        // Applica ordinamento
        switch ($sortField) {
            case 'zone_name':
                $query->join('zones', 'users.zone_id', '=', 'zones.id')
                    ->orderBy('zones.name', $sortDirection)
                    ->select('users.*'); // Evita conflitti di campo
                break;

            case 'last_name':
                // Ordina per cognome (ultima parola del nome)
                $query->orderByRaw("SUBSTRING_INDEX(name, ' ', -1) {$sortDirection}");
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

        // Get zones for filters
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
    // Per ADMIN - vede tutti
public function showCurriculum($id)
{
    $referee = User::findOrFail($id);
    $curriculumData = [];

    for ($year = date('Y'); $year >= 2015; $year--) {
        $tableName = "gare_{$year}";
        if (!Schema::hasTable($tableName)) continue;

        // LEGGI DIRETTAMENTE DAI CAMPI CSV!
        $tornei = DB::table($tableName)
            ->where(function($q) use ($referee) {
                $q->where('TD', 'LIKE', "%{$referee->name}%")
                  ->orWhere('Arbitri', 'LIKE', "%{$referee->name}%")
                  ->orWhere('Osservatori', 'LIKE', "%{$referee->name}%");
            })
            ->get();

        foreach ($tornei as $torneo) {
            // Determina il ruolo dal CSV
            if (str_contains($torneo->TD, $referee->name)) {
                $torneo->role = 'Direttore di Torneo';
            } elseif (str_contains($torneo->Arbitri, $referee->name)) {
                $torneo->role = 'Arbitro';
            } elseif (str_contains($torneo->Osservatori, $referee->name)) {
                $torneo->role = 'Osservatore';
            }
        }

        if ($tornei->count() > 0) {
            $curriculumData[$year] = [
                'level' => $referee->{"level_{$year}"} ?? $referee->level,
                'assignments' => $tornei
            ];
        }
    }

    return view('referee.curriculum', compact('referee', 'curriculumData'));
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
