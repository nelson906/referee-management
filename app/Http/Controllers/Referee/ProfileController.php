<?php

namespace App\Http\Controllers\Referee;
use App\Http\Controllers\Controller;
use App\Models\Referee;
use App\Models\Zone;
use App\Models\User;
use App\Helpers\RefereeLevelsHelper; // ← ADD
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Show the form for editing referee profile - USER-CENTRIC APPROACH ✅
     */
    public function edit()
    {
        $user = auth()->user();

        if (!$user->isReferee()) {
            abort(403, 'Accesso negato.');
        }

        $user->load('referee');

        $zones = Zone::orderBy('name')->get();

        // ✅ USE HELPER for levels

        return view('referee.profile.edit', compact('user', 'zones'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        if (!$user->isReferee()) {
            abort(403, 'Accesso negato.');
        }

        // ✅ NORMALIZE level during validation
        $request->merge([
            'level' => RefereeLevelsHelper::normalize($request->level)
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'level' => ['required', function ($attribute, $value, $fail) {
                if (!RefereeLevelsHelper::isValid($value)) {
                    $fail("Il livello selezionato non è valido.");
                }
            }],
            'zone_id' => 'required|exists:zones,id',

            // Referee extension fields
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'tax_code' => 'nullable|string|max:16',
            'bio' => 'nullable|string|max:1000',
            'experience_years' => 'nullable|integer|min:0',
            'specializations' => 'nullable|array',
            'languages' => 'nullable|array',
            'available_for_international' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // ✅ UPDATE USER with normalized level
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'city' => $request->city,
                'zone_id' => $request->zone_id,
                'level' => $request->level, // Already normalized
            ]);

            // Handle referee extension data
            $hasExtendedData = $request->filled(['address', 'postal_code', 'tax_code', 'bio']) ||
                              $request->filled('experience_years') ||
                              !empty($request->specializations) ||
                              !empty($request->languages) ||
                              $request->has('available_for_international');

            if ($hasExtendedData) {
                $user->referee()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'address' => $request->address,
                        'postal_code' => $request->postal_code,
                        'tax_code' => $request->tax_code,
                        'bio' => $request->bio ?: 'Profilo arbitro completato',
                        'experience_years' => $request->experience_years ?: 0,
                        'specializations' => $request->specializations ?? ['stroke_play'],
                        'languages' => $request->languages ?? ['it'],
                        'available_for_international' => $request->boolean('available_for_international', false),
                        'profile_completed_at' => now(),
                    ]
                );
            }

            DB::commit();

            return redirect()
                ->route('referee.dashboard')
                ->with('success', 'Profilo aggiornato con successo!');

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Errore durante l\'aggiornamento: ' . $e->getMessage());
        }
    }


    /**
     * Update referee password.
     */
    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Password attuale non corretta']);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return back()->with('success', 'Password aggiornata con successo!');
    }

    /**
     * Show referee profile (read-only view).
     */
    public function show()
    {
        $user = auth()->user();

        if (!$user->isReferee()) {
            abort(403, 'Accesso negato.');
        }

        // Load all relationships for complete profile view
        $user->load([
            'zone',
            'referee',
            'assignments.tournament.club',
            'availabilities.tournament.club'
        ]);

        // Get profile completion status
        $profileComplete = $user->hasCompletedProfile();
        $extendedProfileComplete = $user->referee ? $user->referee->isProfileComplete() : false;

        // Get recent activity
        $recentAssignments = $user->assignments()
            ->with('tournament.club')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentAvailabilities = $user->availabilities()
            ->with('tournament.club')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Get statistics
        $stats = [
            'total_assignments' => $user->assignments()->count(),
            'current_year_assignments' => $user->assignments()
                ->whereHas('tournament', function($q) {
                    $q->whereYear('start_date', now()->year);
                })
                ->count(),
            'confirmed_assignments' => $user->assignments()->where('is_confirmed', true)->count(),
            'total_availabilities' => $user->availabilities()->count(),
            'upcoming_assignments' => $user->assignments()
                ->whereHas('tournament', function($q) {
                    $q->where('start_date', '>=', now());
                })
                ->count(),
        ];

        return view('referee.profile.show', compact(
            'user',
            'profileComplete',
            'extendedProfileComplete',
            'recentAssignments',
            'recentAvailabilities',
            'stats'
        ));
    }
}
