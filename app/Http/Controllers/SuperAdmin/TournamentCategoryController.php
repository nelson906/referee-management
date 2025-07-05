<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentCategoryRequest;
use App\Models\TournamentCategory;
use App\Models\Zone;
use Illuminate\Http\Request;

class TournamentCategoryController extends Controller
{
    /**
     * Display a listing of the tournament categories.
     */
    public function index()
    {
        $categories = TournamentCategory::withCount('tournaments')
            ->ordered()
            ->get();

        return view('super-admin.tournament-categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new tournament category.
     */
    public function create()
    {
        $zones = Zone::where('is_national', false)->orderBy('name')->get();
        $refereeLevels = TournamentCategory::REFEREE_LEVELS;
        $categoryLevels = TournamentCategory::CATEGORY_LEVELS;

        return view('super-admin.tournament-categories.create', compact(
            'zones',
            'refereeLevels',
            'categoryLevels'
        ));
    }

    /**
     * Store a newly created tournament category in storage.
     */
    public function store(TournamentCategoryRequest $request)
    {
        $data = $request->validated();

        // Prepara le impostazioni
        $settings = [
            'required_referee_level' => $data['required_referee_level'] ?? 'aspirante',
            'min_referees' => $data['min_referees'] ?? 1,
            'max_referees' => $data['max_referees'] ?? $data['min_referees'] ?? 1,
            'special_requirements' => $data['special_requirements'] ?? null,
            'notification_templates' => $data['notification_templates'] ?? [],
        ];

        // Gestione visibility_zones
        if ($data['is_national'] ?? false) {
            $settings['visibility_zones'] = 'all';
        } else {
            $settings['visibility_zones'] = $data['visibility_zones'] ?? [];
        }

        // Crea la categoria
        $category = TournamentCategory::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'is_national' => $data['is_national'] ?? false,
            'level' => $data['level'] ?? 'zonale',
            'required_level' => $data['required_referee_level'] ?? 'aspirante',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'settings' => $settings,
        ]);

        return redirect()
            ->route('super-admin.tournament-categories.index')
            ->with('success', 'Categoria torneo creata con successo!');
    }

    /**
     * Display the specified tournament category.
     */
    public function show(TournamentCategory $tournamentCategory)
    {
        $tournamentCategory->loadCount('tournaments');
        $recentTournaments = $tournamentCategory->tournaments()
            ->with(['club', 'zone'])
            ->latest()
            ->limit(10)
            ->get();

        return view('super-admin.tournament-categories.show', compact(
            'tournamentCategory',
            'recentTournaments'
        ));
    }

    /**
     * Show the form for editing the specified tournament category.
     */
    public function edit(TournamentCategory $tournamentCategory)
    {
        $zones = Zone::where('is_national', false)->orderBy('name')->get();
        $refereeLevels = TournamentCategory::REFEREE_LEVELS;
        $categoryLevels = TournamentCategory::CATEGORY_LEVELS;

        return view('super-admin.tournament-categories.edit', compact(
            'tournamentCategory',
            'zones',
            'refereeLevels',
            'categoryLevels'
        ));
    }

    /**
     * Update the specified tournament category in storage.
     */
    public function update(TournamentCategoryRequest $request, TournamentCategory $tournamentCategory)
    {
        $data = $request->validated();

        // Prepara le impostazioni
        $settings = [
            'required_referee_level' => $data['required_referee_level'] ?? 'aspirante',
            'min_referees' => $data['min_referees'] ?? 1,
            'max_referees' => $data['max_referees'] ?? $data['min_referees'] ?? 1,
            'special_requirements' => $data['special_requirements'] ?? null,
            'notification_templates' => $data['notification_templates'] ?? [],
        ];

        // Gestione visibility_zones
        if ($data['is_national'] ?? false) {
            $settings['visibility_zones'] = 'all';
        } else {
            $settings['visibility_zones'] = $data['visibility_zones'] ?? [];
        }

        // Aggiorna la categoria
        $tournamentCategory->update([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'is_national' => $data['is_national'] ?? false,
            'level' => $data['level'] ?? 'zonale',
            'required_level' => $data['required_referee_level'] ?? 'aspirante',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'settings' => $settings,
        ]);

        return redirect()
            ->route('super-admin.tournament-categories.index')
            ->with('success', 'Categoria torneo aggiornata con successo!');
    }

    /**
     * Remove the specified tournament category from storage.
     */
    public function destroy(TournamentCategory $tournamentCategory)
    {
        // Verifica se ci sono tornei associati
        if ($tournamentCategory->tournaments()->exists()) {
            return redirect()
                ->route('super-admin.tournament-categories.index')
                ->with('error', 'Impossibile eliminare la categoria: ci sono tornei associati.');
        }

        $tournamentCategory->delete();

        return redirect()
            ->route('super-admin.tournament-categories.index')
            ->with('success', 'Categoria torneo eliminata con successo!');
    }

    /**
     * Update categories order via AJAX
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:tournament_categories,id',
            'categories.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->categories as $categoryData) {
            TournamentCategory::where('id', $categoryData['id'])
                ->update(['sort_order' => $categoryData['sort_order']]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Toggle category active status
     */
    public function toggleActive(TournamentCategory $tournamentCategory)
    {
        $tournamentCategory->update([
            'is_active' => !$tournamentCategory->is_active
        ]);

        return response()->json([
            'success' => true,
            'is_active' => $tournamentCategory->is_active
        ]);
    }

    /**
     * Duplicate a tournament category
     */
    public function duplicate(TournamentCategory $tournamentCategory)
    {
        $newCategory = $tournamentCategory->replicate();
        $newCategory->name = $tournamentCategory->name . ' (Copia)';
        $newCategory->code = $tournamentCategory->code . '_COPY';
        $newCategory->is_active = false;
        $newCategory->save();

        return redirect()
            ->route('super-admin.tournament-categories.edit', $newCategory)
            ->with('success', 'Categoria duplicata con successo! Modifica i dettagli e attivala quando pronta.');
    }
}
