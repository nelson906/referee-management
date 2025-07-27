<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Letterhead;
use App\Models\Zone;
use App\Models\User; // ✅ AGGIUNGI
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate; // ✅ AGGIUNGI
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // ✅ AGGIUNGI

class LetterheadController extends Controller
{
    use AuthorizesRequests; // ✅ AGGIUNGI questo trait per $this->authorize()

    // /**
    //  * Constructor con middleware
    //  */
    // public function __construct()
    // {
    //     // Middleware di autorizzazione base
    //     $this->middleware('auth');

    //     // Middleware personalizzato per admin (definito sotto)
    //     $this->middleware(function ($request, $next) {
    //         $user = auth()->user();

    //         // Solo admin possono gestire letterheads (tranne visualizzazioni)
    //         $allowedActions = ['show', 'preview', 'getLetterheads'];
    //         $currentAction = $request->route()->getActionMethod();

    //         if (!in_array($currentAction, $allowedActions)) {
    //             if (!in_array($user->user_type, ['super_admin', 'national_admin', 'zone_admin'])) {
    //                 abort(403, 'Non autorizzato a gestire letterheads.');
    //             }
    //         }

    //         return $next($request);
    //     });
    // }

    /**
     * Display a listing of letterheads
     */
    public function index(Request $request)
    {
        $query = Letterhead::with(['zone', 'updatedBy']);

        // Filtro ricerca
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('zone', function ($zq) use ($search) {
                      $zq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filtro zona
        if ($request->filled('zone_id')) {
            if ($request->zone_id === 'global') {
                $query->whereNull('zone_id');
            } else {
                $query->where('zone_id', $request->zone_id);
            }
        }

        // Filtro stato
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Scope per zone (se l'utente non è super admin)
        if (auth()->user()->user_type !== 'super_admin' && auth()->user()->zone_id) {
            $query->where(function ($q) {
                $q->where('zone_id', auth()->user()->zone_id)
                  ->orWhereNull('zone_id'); // Letterheads globali
            });
        }

        $letterheads = $query->latest()->paginate(15)->withQueryString();
        $zones = Zone::where('is_active', true)->orderBy('name')->get(); // ✅ FIX: active() scope

        return view('admin.letterheads.index', compact('letterheads', 'zones'));
    }

    /**
     * Show the form for creating a new letterhead
     */
    public function create()
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get(); // ✅ FIX: active() scope

        // Se l'utente non è super admin, limitare alle sue zone
        if (auth()->user()->user_type !== 'super_admin' && auth()->user()->zone_id) {
            $zones = $zones->where('id', auth()->user()->zone_id);
        }

        return view('admin.letterheads.create', compact('zones'));
    }

    /**
     * Store a newly created letterhead
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'zone_id' => [
                'nullable',
                Rule::exists('zones', 'id'),
                function ($attribute, $value, $fail) {
                    // Se non è super admin, può creare solo per la sua zona
                    if (auth()->user()->user_type !== 'super_admin' &&
                        auth()->user()->zone_id &&
                        $value != auth()->user()->zone_id) {
                        $fail('Non puoi creare letterheads per altre zone.');
                    }
                },
            ],
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'header_text' => 'nullable|string|max:1000',
            'footer_text' => 'nullable|string|max:1000',
            'contact_info' => 'nullable|array',
            'contact_info.address' => 'nullable|string|max:255',
            'contact_info.phone' => 'nullable|string|max:50',
            'contact_info.email' => 'nullable|email|max:255',
            'contact_info.website' => 'nullable|url|max:255',
            'settings' => 'nullable|array',
            'settings.margins.top' => 'nullable|integer|min:0|max:100',
            'settings.margins.bottom' => 'nullable|integer|min:0|max:100',
            'settings.margins.left' => 'nullable|integer|min:0|max:100',
            'settings.margins.right' => 'nullable|integer|min:0|max:100',
            'settings.font.family' => 'nullable|string|max:50',
            'settings.font.size' => 'nullable|integer|min:8|max:24',
            'settings.font.color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('letterheads/logos', 'public');
            $validated['logo_path'] = $logoPath;
        }

        // Set defaults for settings
        $validated['settings'] = array_merge([
            'margins' => [
                'top' => 20,
                'bottom' => 20,
                'left' => 25,
                'right' => 25,
            ],
            'font' => [
                'family' => 'Arial',
                'size' => 11,
                'color' => '#000000',
            ],
        ], $validated['settings'] ?? []);

        // Set updated by
        $validated['updated_by'] = Auth::id();

        $letterhead = Letterhead::create($validated);

        // Set as default if requested (usa metodo sicuro)
        if ($validated['is_default'] ?? false) {
            $this->setLetterheadAsDefault($letterhead);
        }

        return redirect()
            ->route('admin.letterheads.index')
            ->with('success', 'Letterhead creata con successo.');
    }

    /**
     * Display the specified letterhead
     */
    public function show(Letterhead $letterhead)
    {
        // ✅ Usa try-catch per gestire errori di autorizzazione
        try {
            $this->authorize('view', $letterhead);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            abort(403, 'Non autorizzato a visualizzare questa letterhead.');
        }

        $letterhead->load(['zone', 'updatedBy']);

        return view('admin.letterheads.show', compact('letterhead'));
    }

    /**
     * Show the form for editing the specified letterhead
     */
    public function edit(Letterhead $letterhead)
    {
        try {
            $this->authorize('update', $letterhead);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            abort(403, 'Non autorizzato a modificare questa letterhead.');
        }

        $zones = Zone::where('is_active', true)->orderBy('name')->get();

        // Se l'utente non è super admin, limitare alle sue zone
        if (auth()->user()->user_type !== 'super_admin' && auth()->user()->zone_id) {
            $zones = $zones->where('id', auth()->user()->zone_id);
        }

        return view('admin.letterheads.edit', compact('letterhead', 'zones'));
    }

    /**
     * Update the specified letterhead
     */
    public function update(Request $request, Letterhead $letterhead)
    {
        try {
            $this->authorize('update', $letterhead);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            abort(403, 'Non autorizzato a modificare questa letterhead.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'zone_id' => [
                'nullable',
                Rule::exists('zones', 'id'),
                function ($attribute, $value, $fail) {
                    // Se non è super admin, può modificare solo la sua zona
                    if (auth()->user()->user_type !== 'super_admin' &&
                        auth()->user()->zone_id &&
                        $value != auth()->user()->zone_id) {
                        $fail('Non puoi modificare letterheads per altre zone.');
                    }
                },
            ],
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'header_text' => 'nullable|string|max:1000',
            'footer_text' => 'nullable|string|max:1000',
            'contact_info' => 'nullable|array',
            'contact_info.address' => 'nullable|string|max:255',
            'contact_info.phone' => 'nullable|string|max:50',
            'contact_info.email' => 'nullable|email|max:255',
            'contact_info.website' => 'nullable|url|max:255',
            'settings' => 'nullable|array',
            'settings.margins.top' => 'nullable|integer|min:0|max:100',
            'settings.margins.bottom' => 'nullable|integer|min:0|max:100',
            'settings.margins.left' => 'nullable|integer|min:0|max:100',
            'settings.margins.right' => 'nullable|integer|min:0|max:100',
            'settings.font.family' => 'nullable|string|max:50',
            'settings.font.size' => 'nullable|integer|min:8|max:24',
            'settings.font.color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($letterhead->logo_path && Storage::disk('public')->exists($letterhead->logo_path)) {
                Storage::disk('public')->delete($letterhead->logo_path);
            }

            $logoPath = $request->file('logo')->store('letterheads/logos', 'public');
            $validated['logo_path'] = $logoPath;
        }

        // Merge settings with existing ones
        if (isset($validated['settings'])) {
            $validated['settings'] = array_merge($letterhead->settings ?? [], $validated['settings']);
        }

        // Set updated by
        $validated['updated_by'] = Auth::id();

        $letterhead->update($validated);

        // Set as default if requested
        if ($validated['is_default'] ?? false) {
            $this->setLetterheadAsDefault($letterhead);
        }

        return redirect()
            ->route('admin.letterheads.index')
            ->with('success', 'Letterhead aggiornata con successo.');
    }

    /**
     * Remove the specified letterhead
     */
    public function destroy(Letterhead $letterhead)
    {
        try {
            $this->authorize('delete', $letterhead);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            abort(403, 'Non autorizzato a eliminare questa letterhead.');
        }

        // Cannot delete default letterhead
        if ($letterhead->is_default) {
            return back()->with('error', 'Non puoi eliminare la letterhead predefinita.');
        }

        // Delete logo file
        if ($letterhead->logo_path && Storage::disk('public')->exists($letterhead->logo_path)) {
            Storage::disk('public')->delete($letterhead->logo_path);
        }

        $letterhead->delete();

        return redirect()
            ->route('admin.letterheads.index')
            ->with('success', 'Letterhead eliminata con successo.');
    }

    /**
     * Toggle active status
     */
    public function toggleActive(Letterhead $letterhead)
    {
        // Controllo manuale autorizzazione (fallback se Policy non funziona)
        if (!$this->canManageLetterhead($letterhead)) {
            abort(403, 'Non autorizzato a modificare questa letterhead.');
        }

        // Cannot deactivate default letterhead
        if ($letterhead->is_default && $letterhead->is_active) {
            return back()->with('error', 'Non puoi disattivare la letterhead predefinita.');
        }

        $letterhead->update([
            'is_active' => !$letterhead->is_active,
            'updated_by' => Auth::id(),
        ]);

        $status = $letterhead->is_active ? 'attivata' : 'disattivata';

        return back()->with('success', "Letterhead {$status} con successo.");
    }

    /**
     * Set as default letterhead
     */
    public function setDefault(Letterhead $letterhead)
    {
        if (!$this->canManageLetterhead($letterhead)) {
            abort(403, 'Non autorizzato a modificare questa letterhead.');
        }

        if (!$letterhead->is_active) {
            return back()->with('error', 'La letterhead deve essere attiva per essere impostata come predefinita.');
        }

        $this->setLetterheadAsDefault($letterhead);

        return back()->with('success', 'Letterhead impostata come predefinita.');
    }

    /**
     * Duplicate letterhead
     */
    public function duplicate(Letterhead $letterhead)
    {
        if (!$this->canViewLetterhead($letterhead) || !$this->canCreateLetterheads()) {
            abort(403, 'Non autorizzato a duplicare questa letterhead.');
        }

        $clone = $this->cloneLetterhead($letterhead, [
            'title' => $letterhead->title . ' (Copia)',
            'is_default' => false,
            'is_active' => false,
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('admin.letterheads.edit', $clone)
            ->with('success', 'Letterhead duplicata con successo. Modifica i dettagli e attivala.');
    }

    /**
     * Preview letterhead
     */
    public function preview(Letterhead $letterhead)
    {
        if (!$this->canViewLetterhead($letterhead)) {
            abort(403, 'Non autorizzato a visualizzare questa letterhead.');
        }

        // Generate sample content for preview
        $sampleData = [
            'referee_name' => 'Mario Rossi',
            'tournament_name' => 'Campionato Nazionale Golf 2024',
            'tournament_date' => '15 Marzo 2024',
            'club_name' => 'Golf Club Sample',
            'zone_name' => $letterhead->zone?->name ?? 'Zona di Esempio',
            'date' => now()->format('d/m/Y'),
        ];

        return view('admin.letterheads.preview', compact('letterhead', 'sampleData'));
    }

    /**
     * Upload logo via AJAX
     */
    public function uploadLogo(Request $request)
    {
        if (!$this->canCreateLetterheads()) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $logoPath = $request->file('logo')->store('letterheads/logos', 'public');

        return response()->json([
            'success' => true,
            'path' => $logoPath,
            'url' => Storage::url($logoPath),
        ]);
    }

    /**
     * Remove logo
     */
    public function removeLogo(Letterhead $letterhead)
    {
        if (!$this->canManageLetterhead($letterhead)) {
            abort(403, 'Non autorizzato a modificare questa letterhead.');
        }

        if ($letterhead->logo_path && Storage::disk('public')->exists($letterhead->logo_path)) {
            Storage::disk('public')->delete($letterhead->logo_path);
        }

        $letterhead->update([
            'logo_path' => null,
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Logo rimosso con successo.');
    }

    /**
     * Get letterheads for AJAX calls
     */
    public function getLetterheads(Request $request)
    {
        $query = Letterhead::where('is_active', true); // ✅ FIX: active() scope

        if ($request->filled('zone_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('zone_id', $request->zone_id)
                  ->orWhereNull('zone_id'); // Include global letterheads
            });
        }

        if ($request->filled('default_only')) {
            $query->where('is_default', true);
        }

        $letterheads = $query->select('id', 'title', 'zone_id', 'is_default')
                            ->with('zone:id,name')
                            ->get()
                            ->map(function ($letterhead) {
                                return [
                                    'id' => $letterhead->id,
                                    'title' => $letterhead->title,
                                    'zone' => $letterhead->zone?->name ?? 'Globale',
                                    'is_default' => $letterhead->is_default,
                                ];
                            });

        return response()->json($letterheads);
    }

    // =====================================
    // METODI HELPER PRIVATI
    // =====================================

    /**
     * Controlla se l'utente può gestire la letterhead
     */
    private function canManageLetterhead(Letterhead $letterhead): bool
    {
        $user = auth()->user();

        if ($user->user_type === 'super_admin') {
            return true;
        }

        if ($user->user_type === 'national_admin') {
            return true;
        }

        if ($user->user_type === 'zone_admin' && $user->zone_id) {
            return $letterhead->zone_id === $user->zone_id;
        }

        return false;
    }

    /**
     * Controlla se l'utente può visualizzare la letterhead
     */
    private function canViewLetterhead(Letterhead $letterhead): bool
    {
        $user = auth()->user();

        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            return true;
        }

        if ($user->zone_id) {
            return $letterhead->zone_id === $user->zone_id || $letterhead->zone_id === null;
        }

        return false;
    }

    /**
     * Controlla se l'utente può creare letterheads
     */
    private function canCreateLetterheads(): bool
    {
        return in_array(auth()->user()->user_type, ['super_admin', 'national_admin', 'zone_admin']);
    }

    /**
     * Imposta letterhead come predefinita (metodo sicuro)
     */
    private function setLetterheadAsDefault(Letterhead $letterhead): void
    {
        // Rimuovi default da tutte le altre letterheads della stessa zona
        Letterhead::where('zone_id', $letterhead->zone_id)
                  ->where('id', '!=', $letterhead->id)
                  ->update(['is_default' => false]);

        // Imposta questa come default e attiva
        $letterhead->update([
            'is_default' => true,
            'is_active' => true,
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Clona una letterhead (metodo sicuro)
     */
    private function cloneLetterhead(Letterhead $letterhead, array $overrides = []): Letterhead
    {
        $clone = $letterhead->replicate();

        // Rimuovi campi che non devono essere clonati
        $clone->fill(array_merge([
            'is_default' => false,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        // Clona il logo se presente
        if ($letterhead->logo_path && Storage::disk('public')->exists($letterhead->logo_path)) {
            $extension = pathinfo($letterhead->logo_path, PATHINFO_EXTENSION);
            $newLogoPath = 'letterheads/logos/' . Str::uuid() . '.' . $extension;

            Storage::disk('public')->copy($letterhead->logo_path, $newLogoPath);
            $clone->logo_path = $newLogoPath;
        }

        $clone->save();

        return $clone;
    }
}
