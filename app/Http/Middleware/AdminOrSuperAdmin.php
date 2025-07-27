<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminOrSuperAdmin
{
    /**
     * Handle an incoming request for admin or super admin access
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Authentication required',
                    'error' => 'Unauthenticated'
                ], 401);
            }

            return redirect()->guest(route('login'));
        }

        $user = Auth::user();
        $userType = $user->user_type;

        // Allowed user types for admin access
        $allowedTypes = ['admin', 'national_admin', 'super_admin'];

        if (!in_array($userType, $allowedTypes)) {
            // Log unauthorized access attempt
            Log::warning('Unauthorized admin access attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_type' => $userType,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'requested_url' => $request->fullUrl(),
                'timestamp' => now(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Access denied. Administrator privileges required.',
                    'error' => 'Forbidden'
                ], 403);
            }

            abort(403, 'Accesso negato. Sono richiesti privilegi di amministratore per accedere a questa sezione.');
        }

        // Zone-based access control for non-super admins
        if ($userType !== 'super_admin' && $request->route()) {
            // Check if the request is for a specific zone and user has access
            $this->checkZoneAccess($request, $user);
        }

        // Log successful admin access
        Log::info('Admin access granted', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_type' => $userType,
            'zone_id' => $user->zone_id,
            'requested_url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
        ]);

        return $next($request);
    }

    /**
     * Check zone-based access control
     */
    private function checkZoneAccess(Request $request, $user): void
    {
        // Skip zone check for super admins
        if ($user->user_type === 'super_admin') {
            return;
        }

        // If user has a zone restriction, check access
        if ($user->zone_id) {
            $routeParameters = $request->route()->parameters();

            // Check for zone-specific resources
            foreach ($routeParameters as $key => $value) {
                if ($this->isZoneRestrictedResource($key, $value, $user)) {
                    Log::warning('Zone access violation attempt', [
                        'user_id' => $user->id,
                        'user_zone_id' => $user->zone_id,
                        'requested_resource' => $key,
                        'resource_id' => $value,
                        'url' => $request->fullUrl(),
                    ]);

                    abort(403, 'Accesso negato. Non hai i permessi per accedere a risorse di altre zone.');
                }
            }
        }
    }

    /**
     * Check if a resource is zone-restricted
     */
    private function isZoneRestrictedResource(string $parameterName, $resourceId, $user): bool
    {
        // Define which resources require zone checking
        $zoneRestrictedResources = [
            'tournament' => \App\Models\Tournament::class,
            'referee' => \App\Models\User::class,
            'club' => \App\Models\Club::class,
            'letterhead' => \App\Models\Letterhead::class,
        ];

        if (!isset($zoneRestrictedResources[$parameterName])) {
            return false;
        }

        $modelClass = $zoneRestrictedResources[$parameterName];

        try {
            $resource = $modelClass::find($resourceId);

            if (!$resource) {
                return false; // Resource not found, let the controller handle it
            }

            // Check if resource belongs to user's zone
            if (isset($resource->zone_id) && $resource->zone_id !== $user->zone_id) {
                return true; // Access violation
            }

            // Special handling for users (referees)
            if ($parameterName === 'referee' && $resource->zone_id !== $user->zone_id) {
                return true;
            }

        } catch (\Exception $e) {
            Log::error('Error checking zone access', [
                'error' => $e->getMessage(),
                'parameter' => $parameterName,
                'resource_id' => $resourceId,
                'user_id' => $user->id,
            ]);
        }

        return false;
    }
}
