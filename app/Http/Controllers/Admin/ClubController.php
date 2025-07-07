<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Http\Traits\CrudActions;

class ClubController extends Controller
{
    use CrudActions;

    /**
     * Display a listing of clubs.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $query = Club::with(['zone']);

        // Filter by zone for non-national admins
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->where('zone_id', $user->zone_id);
        }

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        // Apply zone filter
        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $clubs = $query
            ->withCount('tournaments')
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Get zones for filter
        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        return view('admin.clubs.index', compact('clubs', 'zones', 'isNationalAdmin'));
    }

    /**
     * Show the form for creating a new club.
     */
    public function create(): View
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        return view('admin.clubs.create', compact('zones'));
    }

    /**
     * Store a newly created club in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:clubs,code',
            'zone_id' => 'required|exists:zones,id',
            'city' => 'required|string|max:100',
            'province' => 'nullable|string|max:2',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Check zone access for non-national admins
        if ($user->user_type === 'admin' && $request->zone_id != $user->zone_id) {
            abort(403, 'Non puoi creare club in zone diverse dalla tua.');
        }

        $club = Club::create($request->all());

        return redirect()
            ->route('admin.clubs.index')
            ->with('success', "Club \"{$club->name}\" creato con successo!");
    }

    /**
     * Display the specified club.
     */
    public function show(Club $club): View
    {
        $this->checkClubAccess($club);

        $club->load(['zone', 'tournaments.tournamentCategory']);

        // Get statistics
        $stats = [
            'total_tournaments' => $club->tournaments()->count(),
            'upcoming_tournaments' => $club->tournaments()->upcoming()->count(),
            'active_tournaments' => $club->tournaments()->active()->count(),
            'completed_tournaments' => $club->tournaments()->where('status', 'completed')->count(),
        ];

        // Get recent tournaments
        $recentTournaments = $club->tournaments()
            ->with(['tournamentCategory', 'zone'])
            ->orderBy('start_date', 'desc')
            ->limit(10)
            ->get();

        return view('admin.clubs.show', compact('club', 'stats', 'recentTournaments'));
    }

    /**
     * Show the form for editing the specified club.
     */
    public function edit(Club $club): View
    {
        $this->checkClubAccess($club);

        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        return view('admin.clubs.edit', compact('club', 'zones'));
    }

    /**
     * Update the specified club in storage.
     */
    public function update(Request $request, Club $club): RedirectResponse
    {
        $this->checkClubAccess($club);

        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:clubs,code,' . $club->id,
            'zone_id' => 'required|exists:zones,id',
            'city' => 'required|string|max:100',
            'province' => 'nullable|string|max:2',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Check zone access for non-national admins
        if ($user->user_type === 'admin' && $request->zone_id != $user->zone_id) {
            abort(403, 'Non puoi spostare club in zone diverse dalla tua.');
        }

        $club->update($request->all());

        return redirect()
            ->route('admin.clubs.show', $club)
            ->with('success', "Club \"{$club->name}\" aggiornato con successo!");
    }

    /**
     * Remove the specified club from storage.
     */
    public function destroy(Club $club): RedirectResponse
    {
        $this->checkClubAccess($club);

        if (!$club->canBeDeleted()) {
            return redirect()
                ->route('admin.clubs.index')
                ->with('error', 'Impossibile eliminare un club con tornei associati.');
        }

        $name = $club->name;
        $club->delete();

        return redirect()
            ->route('admin.clubs.index')
            ->with('success', "Club \"{$name}\" eliminato con successo!");
    }

    /**
     * Toggle club active status.
     */
    public function toggleActive(Club $club): RedirectResponse
    {
        $this->checkClubAccess($club);

        $club->update(['is_active' => !$club->is_active]);

        $status = $club->is_active ? 'attivato' : 'disattivato';

        return redirect()->back()
            ->with('success', "Club \"{$club->name}\" {$status} con successo!");
    }

    /**
     * Get clubs for a specific zone (AJAX).
     */
    public function getClubsByZone(Request $request)
    {
        $request->validate([
            'zone_id' => 'required|exists:zones,id',
        ]);

        $user = auth()->user();

        // Check zone access for non-national admins
        if ($user->user_type === 'admin' && $request->zone_id != $user->zone_id) {
            return response()->json([], 403);
        }

        $clubs = Club::active()
            ->where('zone_id', $request->zone_id)
            ->ordered()
            ->get(['id', 'name', 'code']);

        return response()->json($clubs);
    }
    public function deactivate(Club $club)
    {
        $club->update(['is_active' => false]);

        return redirect()
            ->route('admin.clubs.index')
            ->with('success', 'Club disattivato con successo.');
    }
    /**
     * Check if user can access the club.
     */
    private function checkClubAccess(Club $club): void
    {
        $user = auth()->user();

        if ($user->user_type === 'super_admin' || $user->user_type === 'national_admin') {
            return;
        }

        if ($user->user_type === 'admin' && $club->zone_id !== $user->zone_id) {
            abort(403, 'Non sei autorizzato ad accedere a questo club.');
        }
    }
    protected function getEntityName($model): string
    {
        return 'Club';
    }

    protected function getIndexRoute(): string
    {
        return 'admin.clubs.index';
    }

    protected function getDeleteErrorMessage($model): string
    {
        return 'Impossibile eliminare un club con tornei associati.';
    }

    protected function canBeDeleted($club): bool
    {
        return !$club->tournaments()->exists();
    }

    protected function checkAccess($club): void
    {
        $this->checkClubAccess($club);
    }
}
