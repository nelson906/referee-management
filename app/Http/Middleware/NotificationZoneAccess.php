<?php
// File: app/Http/Middleware/NotificationZoneAccess.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Notification;
use App\Models\LetterTemplate;
use App\Models\InstitutionalEmail;

class NotificationZoneAccess
{
    /**
     * Handle an incoming request.
     *
     * This middleware ensures that zone-restricted admins can only access
     * notifications, templates, and institutional emails within their zone.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user || !$user->hasRole('Admin')) {
            return $next($request);
        }

        // Get the resource being accessed
        $resource = $this->getResourceFromRoute($request);

        if ($resource && !$this->canAccessResource($user, $resource)) {
            abort(403, 'Non hai accesso a questa risorsa di altra zona.');
        }

        return $next($request);
    }

    /**
     * Get the resource from the current route
     */
    private function getResourceFromRoute(Request $request)
    {
        $route = $request->route();

        if (!$route) {
            return null;
        }

        // Check for notification resource
        if ($notification = $route->parameter('notification')) {
            return $notification instanceof Notification ? $notification : Notification::find($notification);
        }

        // Check for letter template resource
        if ($letterTemplate = $route->parameter('letterTemplate')) {
            return $letterTemplate instanceof LetterTemplate ? $letterTemplate : LetterTemplate::find($letterTemplate);
        }

        // Check for institutional email resource
        if ($institutionalEmail = $route->parameter('institutionalEmail')) {
            return $institutionalEmail instanceof InstitutionalEmail ? $institutionalEmail : InstitutionalEmail::find($institutionalEmail);
        }

        // Check for tournament resource (for sending notifications)
        if ($tournament = $route->parameter('tournament')) {
            $tournament = $tournament instanceof \App\Models\Tournament ? $tournament : \App\Models\Tournament::find($tournament);
            return $tournament;
        }

        return null;
    }

    /**
     * Check if user can access the resource
     */
    private function canAccessResource($user, $resource): bool
    {
        $userZoneId = $user->referee->zone_id ?? null;

        if (!$userZoneId) {
            return false;
        }

        if ($resource instanceof Notification) {
            // Check if notification is related to user's zone
            if ($resource->assignment && $resource->assignment->tournament) {
                return $resource->assignment->tournament->club->zone_id === $userZoneId;
            }
            return true; // Allow access to standalone notifications
        }

        if ($resource instanceof LetterTemplate) {
            // Allow access to global templates or templates for user's zone
            return $resource->zone_id === null || $resource->zone_id === $userZoneId;
        }

        if ($resource instanceof InstitutionalEmail) {
            // Allow access to global emails or emails for user's zone
            return $resource->zone_id === null || $resource->zone_id === $userZoneId;
        }

        if ($resource instanceof \App\Models\Tournament) {
            // Check if tournament is in user's zone
            return $resource->club->zone_id === $userZoneId;
        }

        return true;
    }
}
