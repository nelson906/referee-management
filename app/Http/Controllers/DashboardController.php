<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    /**
     * Redirect users to appropriate dashboard based on their role.
     */
    public function index(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Redirect based on user type
        switch ($user->user_type) {
            case 'super_admin':
                return redirect()->route('super-admin.dashboard');

            case 'admin':
            case 'national_admin':
                return redirect()->route('admin.dashboard');

            case 'referee':
                return redirect()->route('referee.dashboard');

            default:
                // Fallback for unknown user types
                auth()->logout();
                return redirect()->route('login')
                    ->with('error', 'Tipo di utente non riconosciuto. Contatta l\'amministratore.');
        }
    }
}
