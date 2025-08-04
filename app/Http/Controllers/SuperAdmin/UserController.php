<?php

namespace App\Http\Controllers\SuperAdmin;

use Illuminate\Support\Str;

use Illuminate\Support\Facades\Storage;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::with(['zone', 'tournaments', 'assignments']);

        // Search filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('codice_tessera', 'like', "%{$search}%");
            });
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);
        $zones = Zone::orderBy('name')->get();

        return view('super-admin.users.index', compact('users', 'zones'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get();
        $userTypes = [
            'super_admin' => 'Super Admin',
            'national_admin' => 'Admin Nazionale (CRC)',
            'zone_admin' => 'Admin Zona',
            'referee' => 'Arbitro'
        ];
        $refereeLevels = config('referee.referee_levels');

        return view('super-admin.users.create', compact('zones', 'userTypes', 'refereeLevels'));
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'user_type' => 'required|string|in:super_admin,national_admin,zone_admin,referee',
            'zone_id' => 'nullable|exists:zones,id',
            'codice_tessera' => 'nullable|string|max:50|unique:users',
            'telefono' => 'nullable|string|max:20',
            'data_nascita' => 'nullable|date',
            'indirizzo' => 'nullable|string|max:255',
            'citta' => 'nullable|string|max:100',
            'cap' => 'nullable|string|max:10',
            'livello_arbitro' => 'nullable|string|in:aspirante,1_livello,regionale,nazionale,internazionale',
            'is_active' => 'boolean',
            'profile_photo' => 'nullable|image|max:2048',
        ]);

        $userData = $request->except(['password', 'password_confirmation', 'profile_photo']);
        $userData['password'] = Hash::make($request->password);
        $userData['email_verified_at'] = now();

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $userData['profile_photo_path'] = $path;
        }

        $user = User::create($userData);

        return redirect()->route('super-admin.users.index')
            ->with('success', 'Utente creato con successo.');
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load(['zone', 'tournaments', 'assignments.tournament']);

        $stats = [
            'tournaments_count' => $user->tournaments()->count(),
            'assignments_count' => $user->assignments()->count(),
            'pending_assignments' => $user->assignments()->where('status', 'pending')->count(),
            'completed_assignments' => $user->assignments()->where('status', 'accepted')->count(),
        ];

        return view('super-admin.users.show', compact('user', 'stats'));
    }

    /**
     * Show the form for editing the user.
     */
    public function edit(User $user)
    {
        $zones = Zone::where('is_active', true)->orderBy('name')->get();
        $userTypes = [
            'super_admin' => 'Super Admin',
            'national_admin' => 'Admin Nazionale (CRC)',
            'zone_admin' => 'Admin Zona',
            'referee' => 'Arbitro'
        ];
        $refereeLevels = config('referee.referee_levels');

        return view('super-admin.users.edit', compact('user', 'zones', 'userTypes', 'refereeLevels'));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'user_type' => 'required|string|in:super_admin,national_admin,zone_admin,referee',
            'zone_id' => 'nullable|exists:zones,id',
            'codice_tessera' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($user->id)],
            'telefono' => 'nullable|string|max:20',
            'data_nascita' => 'nullable|date',
            'indirizzo' => 'nullable|string|max:255',
            'citta' => 'nullable|string|max:100',
            'cap' => 'nullable|string|max:10',
            'livello_arbitro' => 'nullable|string|in:aspirante,1_livello,regionale,nazionale,internazionale',
            'is_active' => 'boolean',
            'profile_photo' => 'nullable|image|max:2048',
        ]);

        $userData = $request->except(['password', 'password_confirmation', 'profile_photo']);

        // Update password only if provided
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($user->profile_photo_path) {
                \Storage::disk('public')->delete($user->profile_photo_path);
            }

            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $userData['profile_photo_path'] = $path;
        }

        $user->update($userData);

        return redirect()->route('super-admin.users.show', $user)
            ->with('success', 'Utente aggiornato con successo.');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        // Prevent deleting current user
        if ($user->id === auth()->id()) {
            return redirect()->route('super-admin.users.index')
                ->with('error', 'Non puoi eliminare il tuo account.');
        }

        // Check if user has active assignments
        if ($user->assignments()->where('status', 'accepted')->exists()) {
            return redirect()->route('super-admin.users.index')
                ->with('error', 'Impossibile eliminare utente con assegnazioni attive.');
        }

        // Delete profile photo if exists
        if ($user->profile_photo_path) {
            \Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->delete();

        return redirect()->route('super-admin.users.index')
            ->with('success', 'Utente eliminato con successo.');
    }

    /**
     * Toggle user active status.
     */
    public function toggleActive(User $user)
    {
        // Prevent deactivating current user
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Non puoi disattivare il tuo account.'
            ]);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'Utente attivato.' : 'Utente disattivato.',
            'is_active' => $user->is_active
        ]);
    }

    /**
     * Reset user password.
     */
    public function resetPassword(User $user)
    {
        // Generate temporary password
        $tempPassword = \Str::random(12);

        $user->update([
            'password' => Hash::make($tempPassword),
            'email_verified_at' => null, // Force email verification
        ]);

        // Send password reset email
        try {
            // Here you would send an email with the temporary password
            // or a password reset link

            return response()->json([
                'success' => true,
                'message' => 'Password reimpostata. L\'utente riceverà le istruzioni via email.',
                'temp_password' => $tempPassword // Remove this in production
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore durante l\'invio dell\'email: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Bulk actions on users.
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,delete',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id'
        ]);

        $userIds = array_filter($request->user_ids, function($id) {
            return $id != auth()->id(); // Exclude current user
        });

        $users = User::whereIn('id', $userIds);

        switch ($request->action) {
            case 'activate':
                $users->update(['is_active' => true]);
                $message = 'Utenti attivati con successo.';
                break;

            case 'deactivate':
                $users->update(['is_active' => false]);
                $message = 'Utenti disattivati con successo.';
                break;

            case 'delete':
                // Check for active assignments
                $hasActiveAssignments = $users->whereHas('assignments', function($q) {
                    $q->where('status', 'accepted');
                })->exists();

                if ($hasActiveAssignments) {
                    return redirect()->route('super-admin.users.index')
                        ->with('error', 'Impossibile eliminare utenti con assegnazioni attive.');
                }

                $users->delete();
                $message = 'Utenti eliminati con successo.';
                break;
        }

        return redirect()->route('super-admin.users.index')
            ->with('success', $message);
    }
}
