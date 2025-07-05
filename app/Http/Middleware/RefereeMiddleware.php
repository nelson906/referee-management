<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefereeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Check if user is a referee
        if ($user->user_type !== 'referee') {
            abort(403, 'Accesso non autorizzato. Solo gli arbitri possono accedere a questa sezione.');
        }

        // Check if referee account is active
        if (!$user->is_active) {
            abort(403, 'Il tuo account arbitro non è attivo. Contatta l\'amministratore della tua zona.');
        }

        // Check if referee has completed profile
        if (!$user->hasCompletedProfile()) {
            return redirect()->route('referee.profile.edit')
                ->with('warning', 'Devi completare il tuo profilo prima di poter accedere alle altre sezioni.');
        }

        return $next($request);
    }
}
