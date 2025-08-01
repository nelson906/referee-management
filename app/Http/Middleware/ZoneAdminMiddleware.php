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

        // Allow access for zone admins and higher roles
        if (!in_array($user->user_type, ['admin', 'national_admin', 'super_admin'])) {
            abort(403, 'Accesso non autorizzato. Solo gli amministratori di zona possono accedere a questa sezione.');
        }

        return $next($request);
    }
}
