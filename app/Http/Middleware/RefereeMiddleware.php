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

        // Check if user is active
        if (!$user->is_active) {
            auth()->logout();
            return redirect()->route('login')
                ->with('error', 'Il tuo account è stato disattivato. Contatta l\'amministratore.');
        }

        // Check if user has referee role or is admin
        if (!in_array($user->user_type, ['referee', 'admin', 'national_admin', 'super_admin'])) {
            abort(403, 'Accesso non autorizzato.');
        }

        return $next($request);
    }
}
