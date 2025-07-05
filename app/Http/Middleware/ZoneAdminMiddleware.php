<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ZoneAdminMiddleware
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

        // Check if user is specifically a zone admin (not national admin)
        if ($user->user_type !== 'admin') {
            abort(403, 'Accesso non autorizzato. Solo gli amministratori di zona possono accedere a questa sezione.');
        }

        // Check if zone admin has a zone assigned
        if (!$user->zone_id) {
            abort(403, 'Il tuo account non ha una zona assegnata. Contatta il supporto tecnico.');
        }

        // Check if zone is active
        if ($user->zone && !$user->zone->is_active) {
            abort(403, 'La tua zona non è attiva. Contatta il supporto tecnico.');
        }

        return $next($request);
    }
}
