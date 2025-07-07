<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ZoneController extends Controller
{
    /**
     * Display a listing of zones.
     */
    public function index(Request $request)
    {
        $query = Zone::withCount(['users', 'tournaments', 'clubs']);

        // Search filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $zones = $query->orderBy('sort_order')->orderBy('name')->paginate(15);

        // Statistics
        $stats = [
            'total_zones' => Zone::count(),
            'active_zones' => Zone::where('is_active', true)->count(),
            'total_users' => User::whereIn('zone_id', Zone::pluck('id'))->count(),
            'total_tournaments' => \DB::table('tournaments')->whereIn('zone_id', Zone::pluck('id'))->count(),
        ];

        return view('super-admin.zones.index', compact('zones', 'stats'));
    }

    /**
     * Show the form for creating a new zone.
     */
    public function create()
    {
        $admins = User::where('user_type', 'zone_admin')
                     ->whereNull('zone_id')
                     ->orWhere('zone_id', '')
                     ->orderBy('name')
                     ->get();

        return view('super-admin.zones.create', compact('admins'));
    }

    /**
     * Store a newly created zone.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:zones',
            'code' => 'required|string|max:10|unique:zones',
            'description' => 'nullable|string',
            'region' => 'required|string|max:100',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'website' => 'nullable|url|max:255',
            'admin_id' => 'nullable|exists:users,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
            'coordinates' => 'nullable|string',
        ]);

        $zoneData = $request->all();
        $zoneData['settings'] = $request->settings ?? [];

        // Set sort order if not provided
        if (!$request->filled('sort_order')) {
            $zoneData['sort_order'] = Zone::max('sort_order') + 10;
        }

        $zone = Zone::create($zoneData);

        // Assign admin to zone if provided
        if ($request->filled('admin_id')) {
            User::where('id', $request->admin_id)->update(['zone_id' => $zone->id]);
        }

        return redirect()->route('super-admin.zones.index')
            ->with('success', 'Zona creata con successo.');
    }

    /**
     * Display the specified zone.
     */
    public function show(Zone $zone)
    {
        $zone->load(['users', 'tournaments.category', 'clubs']);

        $stats = [
            'total_users' => $zone->users()->count(),
            'active_users' => $zone->users()->where('is_active', true)->count(),
            'referees_count' => $zone->users()->where('user_type', 'referee')->count(),
            'admins_count' => $zone->users()->where('user_type', 'zone_admin')->count(),
            'tournaments_count' => $zone->tournaments()->count(),
            'active_tournaments' => $zone->tournaments()->whereIn('status', ['open', 'closed', 'assigned'])->count(),
            'clubs_count' => $zone->clubs()->count(),
            'active_clubs' => $zone->clubs()->where('is_active', true)->count(),
        ];

        // Recent activity
        $recentTournaments = $zone->tournaments()
                                  ->with('category')
                                  ->orderBy('created_at', 'desc')
                                  ->limit(10)
                                  ->get();

        $recentUsers = $zone->users()
                            ->orderBy('created_at', 'desc')
                            ->limit(10)
                            ->get();

        return view('super-admin.zones.show', compact('zone', 'stats', 'recentTournaments', 'recentUsers'));
    }

    /**
     * Show the form for editing the zone.
     */
    public function edit(Zone $zone)
    {
        $admins = User::where('user_type', 'zone_admin')
                     ->where(function($q) use ($zone) {
                         $q->whereNull('zone_id')
                           ->orWhere('zone_id', '')
                           ->orWhere('zone_id', $zone->id);
                     })
                     ->orderBy('name')
                     ->get();

        $currentAdmin = $zone->users()->where('user_type', 'zone_admin')->first();

        return view('super-admin.zones.edit', compact('zone', 'admins', 'currentAdmin'));
    }

    /**
     * Update the specified zone.
     */
    public function update(Request $request, Zone $zone)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('zones')->ignore($zone->id)],
            'code' => ['required', 'string', 'max:10', Rule::unique('zones')->ignore($zone->id)],
            'description' => 'nullable|string',
            'region' => 'required|string|max:100',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'website' => 'nullable|url|max:255',
            'admin_id' => 'nullable|exists:users,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
            'coordinates' => 'nullable|string',
        ]);

        $zoneData = $request->all();
        $zoneData['settings'] = $request->settings ?? [];

        $zone->update($zoneData);

        // Update admin assignment
        $currentAdmin = $zone->users()->where('user_type', 'zone_admin')->first();

        if ($currentAdmin && $currentAdmin->id != $request->admin_id) {
            // Remove current admin
            $currentAdmin->update(['zone_id' => null]);
        }

        if ($request->filled('admin_id') && (!$currentAdmin || $currentAdmin->id != $request->admin_id)) {
            // Assign new admin
            User::where('id', $request->admin_id)->update(['zone_id' => $zone->id]);
        }

        return redirect()->route('super-admin.zones.show', $zone)
            ->with('success', 'Zona aggiornata con successo.');
    }

    /**
     * Remove the specified zone.
     */
    public function destroy(Zone $zone)
    {
        // Check if zone has users
        if ($zone->users()->exists()) {
            return redirect()->route('super-admin.zones.index')
                ->with('error', 'Impossibile eliminare una zona con utenti associati.');
        }

        // Check if zone has tournaments
        if ($zone->tournaments()->exists()) {
            return redirect()->route('super-admin.zones.index')
                ->with('error', 'Impossibile eliminare una zona con tornei associati.');
        }

        // Check if zone has clubs
        if ($zone->clubs()->exists()) {
            return redirect()->route('super-admin.zones.index')
                ->with('error', 'Impossibile eliminare una zona con circoli associati.');
        }

        $zone->delete();

        return redirect()->route('super-admin.zones.index')
            ->with('success', 'Zona eliminata con successo.');
    }

    /**
     * Toggle zone active status.
     */
    public function toggleActive(Zone $zone)
    {
        $zone->update(['is_active' => !$zone->is_active]);

        return response()->json([
            'success' => true,
            'message' => $zone->is_active ? 'Zona attivata.' : 'Zona disattivata.',
            'is_active' => $zone->is_active
        ]);
    }

    /**
     * Update zones sort order.
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'zones' => 'required|array',
            'zones.*.id' => 'required|exists:zones,id',
            'zones.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->zones as $zoneData) {
            Zone::where('id', $zoneData['id'])
                ->update(['sort_order' => $zoneData['sort_order']]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Duplicate a zone.
     */
    public function duplicate(Zone $zone)
    {
        $newZone = $zone->replicate();
        $newZone->name = $zone->name . ' (Copia)';
        $newZone->code = $zone->code . '_COPY';
        $newZone->sort_order = Zone::max('sort_order') + 10;
        $newZone->save();

        return redirect()->route('super-admin.zones.edit', $newZone)
            ->with('success', 'Zona duplicata con successo. Modifica i dettagli necessari.');
    }

    /**
     * Export zones data.
     */
    public function export(Request $request)
    {
        $zones = Zone::withCount(['users', 'tournaments', 'clubs'])->get();

        $filename = 'zones_export_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($zones) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'ID',
                'Nome',
                'Codice',
                'Regione',
                'Città',
                'Email Contatto',
                'Telefono',
                'Utenti',
                'Tornei',
                'Circoli',
                'Stato',
                'Data Creazione'
            ]);

            foreach ($zones as $zone) {
                fputcsv($file, [
                    $zone->id,
                    $zone->name,
                    $zone->code,
                    $zone->region,
                    $zone->city,
                    $zone->contact_email,
                    $zone->contact_phone,
                    $zone->users_count,
                    $zone->tournaments_count,
                    $zone->clubs_count,
                    $zone->is_active ? 'Attiva' : 'Non Attiva',
                    $zone->created_at->format('d/m/Y H:i')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get zone statistics for dashboard.
     */
    public function getStats()
    {
        $stats = [
            'zones_by_region' => Zone::selectRaw('region, count(*) as count')
                                    ->groupBy('region')
                                    ->pluck('count', 'region'),

            'users_by_zone' => Zone::withCount('users')
                                  ->orderBy('users_count', 'desc')
                                  ->limit(10)
                                  ->pluck('users_count', 'name'),

            'tournaments_by_zone' => Zone::withCount('tournaments')
                                        ->orderBy('tournaments_count', 'desc')
                                        ->limit(10)
                                        ->pluck('tournaments_count', 'name'),

            'active_vs_inactive' => [
                'active' => Zone::where('is_active', true)->count(),
                'inactive' => Zone::where('is_active', false)->count(),
            ]
        ];

        return response()->json($stats);
    }
}
