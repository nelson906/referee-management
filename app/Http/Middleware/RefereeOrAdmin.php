<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefereeOrAdmin
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

        // Allow all user types (referee, zone_admin, national_admin, super_admin)
        $allowedTypes = ['referee', 'zone_admin', 'national_admin', 'super_admin'];

        if (!in_array($user->user_type, $allowedTypes)) {
            abort(403, 'Accesso negato.');
        }

        // Store user context for views
        view()->share('isReferee', $user->user_type === 'referee');
        view()->share('isAdmin', in_array($user->user_type, ['zone_admin', 'national_admin', 'super_admin']));
        view()->share('isSuperAdmin', $user->user_type === 'super_admin');
        view()->share('currentUserType', $user->user_type);

        return $next($request);
    }
}
