<?php

namespace App\Http\Controllers\Referee;

use App\Http\Controllers\Controller;
use App\Models\Referee;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();
        $referee = $user->referee;

        // Se non esiste il referee, crealo
        if (!$referee) {
            $referee = Referee::create([
                'user_id' => $user->id,
                'zone_id' => $user->zone_id,
                'referee_code' => 'REF' . str_pad($user->id, 4, '0', STR_PAD_LEFT),
                'level' => 'primo_livello',
                'category' => 'misto',
                'certified_date' => now(),
            ]);
        }

        $zones = Zone::orderBy('name')->get();

        $levels = [
            'primo_livello' => 'Primo Livello',
            'secondo_livello' => 'Secondo Livello',
            'terzo_livello' => 'Terzo Livello',
            'nazionale' => 'Nazionale',
            'internazionale' => 'Internazionale'
        ];

        return view('referee.profile.edit', compact('referee', 'zones', 'levels'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . auth()->id(),
            'phone' => 'nullable|string|max:20',
            'level' => 'required|string',
            'zone_id' => 'required|exists:zones,id',
            'category' => 'required|string',
            'bio' => 'nullable|string|max:1000',
            'experience_years' => 'nullable|integer|min:0',
            'specializations' => 'nullable|array',
            'languages' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $user = auth()->user();
            $referee = $user->referee;

            // Aggiorna User
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'zone_id' => $request->zone_id,
            ]);

            // Aggiorna Referee
            $referee->update([
                'level' => $request->level,
                'zone_id' => $request->zone_id,
                'category' => $request->category,
                'bio' => $request->bio ?: 'Profilo arbitro completato',
                'experience_years' => $request->experience_years ?: 0,
                'specializations' => json_encode($request->specializations ?: ['stroke_play']),
                'languages' => json_encode($request->languages ?: ['it']),
                'profile_completed_at' => now(), // ✅ IMPORTANTE: Marca come completato
            ]);

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

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Password attuale non corretta']);
        }

        auth()->user()->update([
            'password' => Hash::make($request->password)
        ]);

        return back()->with('success', 'Password aggiornata con successo!');
    }
}
