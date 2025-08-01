<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Se l'utente non ha completato il profilo, reindirizza al profilo
        if (!$user->hasCompletedProfile()) {
            return redirect()->route('referee.profile.edit')
                ->with('info', 'Completa il tuo profilo per continuare.');
        }

        // Reindirizza basato sul tipo di utente
        switch ($user->user_type) {
            case 'super_admin':
                return redirect()->route('super-admin.dashboard');

            case 'national_admin':
            case 'admin':
                return redirect()->route('admin.dashboard');

            case 'referee':
                return redirect()->route('referee.dashboard');

            default:
                // Fallback per tipi di utente non riconosciuti
                auth()->logout();
                return redirect()->route('login')
                    ->with('error', 'Tipo di utente non valido. Contatta l\'amministratore.');
        }
    }
}
