<?php

namespace App\Http\Controllers\Referee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Show the form for editing the referee profile.
     */
public function edit(Request $request): View
{
    $user = $request->user();
    $user->load('referee', 'zone'); // Ora funziona

    return view('referee.profile.edit', compact('user'));
}


    /**
     * Update the referee profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'referee_code' => ['required', 'string', 'max:50', 'unique:users,referee_code,' . $user->id],
            'phone' => ['required', 'string', 'max:20'],
            'level' => ['required', 'in:aspirante,primo_livello,regionale,nazionale,internazionale'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->fill($request->only([
            'name',
            'email',
            'referee_code',
            'phone',
            'level',
            'notes'
        ]));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return redirect()->route('referee.dashboard')
            ->with('status', 'Profilo aggiornato con successo!');
    }

    /**
     * Update the referee password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('referee.dashboard')
            ->with('status', 'Password aggiornata con successo!');
    }
}
