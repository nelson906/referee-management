<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
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

        // Check if user is admin (zone admin or national admin)
    if (!in_array($user->user_type, ['admin', 'national_admin', 'super_admin'])) {
        abort(403, 'Accesso non autorizzato. Solo gli amministratori possono accedere a questa sezione.');
    }

        // Additional check: ensure admin has access to their zone
        if ($user->user_type === 'admin' && !$user->zone_id) {
            abort(403, 'Il tuo account amministratore non ha una zona assegnata. Contatta il supporto tecnico.');
        }

        return $next($request);
    }
}
