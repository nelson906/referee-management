<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOrSuperAdmin
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

        // Allow super_admin, national_admin, and zone_admin
        $allowedTypes = ['super_admin', 'national_admin', 'zone_admin'];

        if (!in_array($user->user_type, $allowedTypes)) {
            abort(403, 'Accesso negato. Solo gli amministratori possono accedere a questa sezione.');
        }

        // Store user context for views
        view()->share('isSuperAdmin', $user->user_type === 'super_admin');
        view()->share('isNationalAdmin', $user->user_type === 'national_admin');
        view()->share('currentUserType', $user->user_type);

        return $next($request);
    }
}
