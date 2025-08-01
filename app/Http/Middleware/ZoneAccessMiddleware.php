<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ZoneAccessMiddleware
{
    /**
     * Handle an incoming request.
     * Verifies that the user has access to resources of a specific zone.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $parameterName  The name of the route parameter containing the zone ID (default: 'zone')
     */
    public function handle(Request $request, Closure $next, ?string $parameterName = 'zone'): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Super admins and national admins have access to all zones
        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            return $next($request);
        }

        // Get zone ID from route parameter
        $zoneId = $request->route($parameterName);

        // If zone parameter is a model instance, get its ID
        if (is_object($zoneId) && method_exists($zoneId, 'getKey')) {
            $zoneId = $zoneId->getKey();
        }

        // Check if zone ID is provided
        if (!$zoneId) {
            abort(404, 'Zona non specificata.');
        }

        // Zone admins can only access their own zone
        if ($user->user_type === 'admin') {
            if ($user->zone_id != $zoneId) {
                abort(403, 'Non sei autorizzato ad accedere alle risorse di questa zona.');
            }
        }

        // Referees can only access resources from their zone (for zone-specific content)
        if ($user->user_type === 'referee') {
            if ($user->zone_id != $zoneId) {
                abort(403, 'Non puoi accedere alle risorse di altre zone.');
            }
        }

        return $next($request);
    }
}
