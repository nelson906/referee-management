<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentTypeRequest; // ✅ FIXED: Request name aligned
use App\Models\TournamentType; // ✅ FIXED: Model aligned with database convention
use App\Models\Zone;
use Illuminate\Http\Request;

class TournamentTypeController extends Controller
{
    /**
     * Display a listing of the tournament types.
     */
    public function index()
    {
        // ✅ FIXED: Variable name from $categories to $tournamentTypes
        $tournamentTypes = TournamentType::withCount('tournaments')
            ->ordered()
            ->get();

        // ✅ FIXED: compact() uses tournamentTypes
        return view('super-admin.tournament-types.index', compact('tournamentTypes'));
    }

    /**
     * Show the form for creating a new tournament type.
     */
    public function create()
    {
        $zones = Zone::where('is_national', false)->orderBy('name')->get();
        $refereeLevels = TournamentType::REFEREE_LEVELS;
        $categoryLevels = TournamentType::CATEGORY_LEVELS;

        return view('super-admin.tournament-types.create', compact(
            'zones',
            'refereeLevels',
            'categoryLevels'
        ));
    }

    /**
     * Store a newly created tournament type in storage.
     */
    public function store(TournamentTypeRequest $request)
    {
        $data = $request->validated();

        // Prepara le impostazioni per il campo JSON
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

        // ✅ FIXED: Create using TournamentType model
        $tournamentType = TournamentType::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'is_national' => $data['is_national'] ?? false,
            'level' => $data['level'] ?? 'zonale',
            'required_level' => $data['required_referee_level'] ?? 'aspirante',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,

            // Colonne fisiche
            'min_referees' => $data['min_referees'] ?? 1,
            'max_referees' => $data['max_referees'] ?? $data['min_referees'] ?? 1,

            // Campo JSON (sincronizzato automaticamente dal modello)
            'settings' => $settings,
        ]);

        return redirect()
            ->route('super-admin.tournament-types.index')
            ->with('success', 'Tipo torneo creato con successo!');
    }

    /**
     * Display the specified tournament type.
     */
    public function show(TournamentType $tournamentType)
    {
        $tournamentType->loadCount('tournaments');
        $recentTournaments = $tournamentType->tournaments()
            ->with(['club', 'zone'])
            ->latest()
            ->limit(10)
            ->get();

        return view('super-admin.tournament-types.show', compact(
            'tournamentType',
            'recentTournaments'
        ));
    }

    /**
     * Show the form for editing the specified tournament type.
     */
    public function edit(TournamentType $tournamentType)
    {
        $zones = Zone::where('is_national', false)->orderBy('name')->get();
        $refereeLevels = TournamentType::REFEREE_LEVELS;
        $categoryLevels = TournamentType::CATEGORY_LEVELS;

        return view('super-admin.tournament-types.edit', compact(
            'tournamentType',
            'zones',
            'refereeLevels',
            'categoryLevels'
        ));
    }

    /**
     * Update the specified tournament type in storage.
     */
    public function update(TournamentTypeRequest $request, TournamentType $tournamentType)
    {
        $data = $request->validated();

        // Prepara le impostazioni per il campo JSON
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

        // ✅ FIXED: Update TournamentType model
        $tournamentType->update([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'is_national' => $data['is_national'] ?? false,
            'level' => $data['level'] ?? 'zonale',
            'required_level' => $data['required_referee_level'] ?? 'aspirante',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,

            // Colonne fisiche
            'min_referees' => $data['min_referees'] ?? 1,
            'max_referees' => $data['max_referees'] ?? $data['min_referees'] ?? 1,

            // Campo JSON (sincronizzato automaticamente dal modello)
            'settings' => $settings,
        ]);

        return redirect()
            ->route('super-admin.tournament-types.show', $tournamentType)
            ->with('success', 'Tipo torneo aggiornato con successo!');
    }

    /**
     * Remove the specified tournament type from storage.
     */
    public function destroy(TournamentType $tournamentType)
    {
        if (!$tournamentType->canBeDeleted()) {
            return redirect()
                ->route('super-admin.tournament-types.index')
                ->with('error', 'Impossibile eliminare un tipo con tornei associati.');
        }

        $name = $tournamentType->name;
        $tournamentType->delete();

        return redirect()
            ->route('super-admin.tournament-types.index')
            ->with('success', "Tipo torneo \"{$name}\" eliminato con successo!");
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(TournamentType $tournamentType)
    {
        $tournamentType->update([
            'is_active' => !$tournamentType->is_active
        ]);

        $status = $tournamentType->is_active ? 'attivato' : 'disattivato';

        return redirect()->back()
            ->with('success', "Tipo \"{$tournamentType->name}\" {$status} con successo!");
    }

    /**
     * Update display order.
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            // ✅ FIXED: tournament_types instead of categories
            'tournament_types' => 'required|array',
            'tournament_types.*.id' => 'required|exists:tournament_types,id',
            'tournament_types.*.sort_order' => 'required|integer|min:0',
        ]);

        // ✅ FIXED: tournament_types instead of categories
        foreach ($request->tournament_types as $typeData) {
            TournamentType::where('id', $typeData['id'])
                ->update(['sort_order' => $typeData['sort_order']]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Duplicate a tournament type.
     */
    public function duplicate(TournamentType $tournamentType)
    {
        $newType = $tournamentType->replicate();
        $newType->name = $tournamentType->name . ' (Copia)';
        $newType->code = $tournamentType->code . '_COPY';
        $newType->sort_order = TournamentType::max('sort_order') + 10;
        $newType->is_active = false;
        $newType->save();

        return redirect()
            ->route('super-admin.tournament-types.edit', $newType)
            ->with('success', "Tipo duplicato con successo! Modifica i dettagli e attivalo quando pronto.");
    }

    /**
     * Get tournament types statistics.
     */
    public function statistics()
    {
        $stats = [
            'total_types' => TournamentType::count(),
            'active_types' => TournamentType::where('is_active', true)->count(),
            'national_types' => TournamentType::where('is_national', true)->count(),
            'zonal_types' => TournamentType::where('is_national', false)->count(),
            'types_with_tournaments' => TournamentType::has('tournaments')->count(),
        ];

        // Types by usage
        $typesByUsage = TournamentType::withCount('tournaments')
            ->orderBy('tournaments_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code,
                    'tournaments_count' => $type->tournaments_count,
                    'is_national' => $type->is_national,
                    'is_active' => $type->is_active,
                ];
            });

        return response()->json([
            'stats' => $stats,
            'types_by_usage' => $typesByUsage,
        ]);
    }

    /**
     * Export tournament types data.
     */
    public function export(Request $request)
    {
        $tournamentTypes = TournamentType::withCount('tournaments')->get();

        $filename = 'tournament_types_export_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($tournamentTypes) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'ID',
                'Nome',
                'Codice',
                'Descrizione',
                'Tipo',
                'Livello Richiesto',
                'Min Arbitri',
                'Max Arbitri',
                'Ordine',
                'Attivo',
                'Tornei Associati',
                'Data Creazione'
            ]);

            foreach ($tournamentTypes as $type) {
                fputcsv($file, [
                    $type->id,
                    $type->name,
                    $type->code,
                    $type->description ?? '',
                    $type->is_national ? 'Nazionale' : 'Zonale',
                    $type->required_level,
                    $type->min_referees,
                    $type->max_referees,
                    $type->sort_order,
                    $type->is_active ? 'Sì' : 'No',
                    $type->tournaments_count ?? 0,
                    $type->created_at->format('d/m/Y H:i')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk operations on tournament types.
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,delete',
            'tournament_type_ids' => 'required|array|min:1',
            'tournament_type_ids.*' => 'exists:tournament_types,id'
        ]);

        $tournamentTypes = TournamentType::whereIn('id', $request->tournament_type_ids);

        switch ($request->action) {
            case 'activate':
                $tournamentTypes->update(['is_active' => true]);
                $message = 'Tipi torneo attivati con successo.';
                break;

            case 'deactivate':
                $tournamentTypes->update(['is_active' => false]);
                $message = 'Tipi torneo disattivati con successo.';
                break;

            case 'delete':
                // Check if any type has tournaments
                $hasToournaments = $tournamentTypes->has('tournaments')->exists();

                if ($hasToournaments) {
                    return redirect()->route('super-admin.tournament-types.index')
                        ->with('error', 'Impossibile eliminare tipi con tornei associati.');
                }

                $tournamentTypes->delete();
                $message = 'Tipi torneo eliminati con successo.';
                break;
        }

        return redirect()->route('super-admin.tournament-types.index')
            ->with('success', $message);
    }

    /**
     * Preview tournament type settings.
     */
    public function preview(TournamentType $tournamentType)
    {
        $preview = [
            'basic_info' => [
                'name' => $tournamentType->name,
                'code' => $tournamentType->code,
                'description' => $tournamentType->description,
                'type' => $tournamentType->is_national ? 'Nazionale' : 'Zonale',
            ],
            'referee_requirements' => [
                'required_level' => $tournamentType->required_level,
                'min_referees' => $tournamentType->min_referees,
                'max_referees' => $tournamentType->max_referees,
            ],
            'settings' => $tournamentType->settings ?? [],
            'visibility' => [
                'zones' => $tournamentType->visibility_zones,
                'is_national' => $tournamentType->is_national,
            ],
            'statistics' => [
                'tournaments_count' => $tournamentType->tournaments()->count(),
                'active_tournaments' => $tournamentType->tournaments()->active()->count(),
                'upcoming_tournaments' => $tournamentType->tournaments()->upcoming()->count(),
            ]
        ];

        return response()->json($preview);
    }
}
