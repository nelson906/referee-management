<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RefereeController extends Controller
{
    /**
     * Display a listing of referees.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin';

        $query = User::where('user_type', 'referee')
            ->with(['zone'])
            ->withCount(['assignments', 'availabilities']);

        // Filter by zone for non-national admins
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
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

        // Apply level filter
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $referees = $query
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Get zones and levels for filters
        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        $levels = [
            'aspirante' => 'Aspirante',
            'primo_livello' => 'Primo Livello',
            'regionale' => 'Regionale',
            'nazionale' => 'Nazionale',
            'internazionale' => 'Internazionale',
        ];

        return view('admin.referees.index', compact('referees', 'zones', 'levels', 'isNationalAdmin'));
    }

    /**
     * Display the specified referee.
     */
    public function show(User $referee): View
    {
        if (!$referee->isReferee()) {
            abort(404, 'Arbitro non trovato.');
        }

        $this->checkRefereeAccess($referee);

        $referee->load([
            'zone',
            'assignments.tournament.club',
            'availabilities.tournament.club'
        ]);

        // Get statistics
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
     * Show the form for editing the specified referee.
     */
    public function edit(User $referee): View
    {
        if (!$referee->isReferee()) {
            abort(404, 'Arbitro non trovato.');
        }

        $this->checkRefereeAccess($referee);

        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin';

        $zones = $isNationalAdmin
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        $levels = [
            'aspirante' => 'Aspirante',
            'primo_livello' => 'Primo Livello',
            'regionale' => 'Regionale',
            'nazionale' => 'Nazionale',
            'internazionale' => 'Internazionale',
        ];

        return view('admin.referees.edit', compact('referee', 'zones', 'levels'));
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

        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $referee->id,
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'zone_id' => 'required|exists:zones,id',
            'level' => 'required|in:aspirante,primo_livello,regionale,nazionale,internazionale',
            'referee_code' => 'required|string|max:20|unique:users,referee_code,' . $referee->id,
            'is_active' => 'boolean',
        ]);

        // Check zone access for non-national admins
        if ($user->user_type === 'admin' && $request->zone_id != $user->zone_id) {
            abort(403, 'Non puoi spostare arbitri in zone diverse dalla tua.');
        }

        $referee->update($request->all());

        return redirect()
            ->route('admin.referees.show', $referee)
            ->with('success', "Arbitro \"{$referee->name}\" aggiornato con successo!");
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
        if ($referee->assignments()->whereHas('tournament', function($q) {
            $q->whereIn('status', ['open', 'closed', 'assigned']);
        })->exists()) {
            return redirect()
                ->route('admin.referees.index')
                ->with('error', 'Impossibile eliminare un arbitro con assegnazioni attive.');
        }

        $name = $referee->name;
        $referee->delete();

        return redirect()
            ->route('admin.referees.index')
            ->with('success', "Arbitro \"{$name}\" eliminato con successo!");
    }

    /**
     * Check if user can access the referee.
     */
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
}
