<?php
// File: app/Http/Middleware/NotificationAccessMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class NotificationAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission = 'view'): Response
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'Authentication required');
        }

        // Check based on permission level
        switch ($permission) {
            case 'view':
                if (!$this->canViewNotifications($user)) {
                    abort(403, 'Non hai il permesso di visualizzare le notifiche.');
                }
                break;

            case 'send':
                if (!$this->canSendNotifications($user)) {
                    abort(403, 'Non hai il permesso di inviare notifiche.');
                }
                break;

            case 'manage_templates':
                if (!$this->canManageTemplates($user)) {
                    abort(403, 'Non hai il permesso di gestire i template.');
                }
                break;

            case 'manage_institutional':
                if (!$this->canManageInstitutionalEmails($user)) {
                    abort(403, 'Non hai il permesso di gestire le email istituzionali.');
                }
                break;

            case 'view_stats':
                if (!$this->canViewStats($user)) {
                    abort(403, 'Non hai il permesso di visualizzare le statistiche.');
                }
                break;

            default:
                abort(403, 'Permesso non riconosciuto.');
        }

        // Add user context to request for controllers
        $request->merge(['notification_user_context' => $this->getUserContext($user)]);

        return $next($request);
    }

    /**
     * Check if user can view notifications
     */
    private function canViewNotifications($user): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin', 'NationalAdmin']);
    }

    /**
     * Check if user can send notifications
     */
    private function canSendNotifications($user): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin', 'NationalAdmin']);
    }

    /**
     * Check if user can manage letter templates
     */
    private function canManageTemplates($user): bool
    {
        // All admin types can create/edit templates for their scope
        return $user->hasAnyRole(['Admin', 'SuperAdmin', 'NationalAdmin']);
    }

    /**
     * Check if user can manage institutional emails
     */
    private function canManageInstitutionalEmails($user): bool
    {
        // Only SuperAdmin can manage institutional emails globally
        // Admin can manage emails for their zone
        return $user->hasAnyRole(['Admin', 'SuperAdmin']);
    }

    /**
     * Check if user can view notification statistics
     */
    private function canViewStats($user): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin', 'NationalAdmin']);
    }

    /**
     * Get user context for authorization in controllers
     */
    private function getUserContext($user): array
    {
        $context = [
            'user_id' => $user->id,
            'roles' => $user->roles->pluck('name')->toArray(),
            'can_view_all_zones' => $user->hasRole('SuperAdmin'),
            'can_manage_national' => $user->hasAnyRole(['SuperAdmin', 'NationalAdmin']),
            'zone_id' => null,
            'accessible_zones' => [],
        ];

        // Set zone context for zone-restricted users
        if ($user->hasRole('Admin') && $user->referee && $user->referee->zone_id) {
            $context['zone_id'] = $user->referee->zone_id;
            $context['accessible_zones'] = [$user->referee->zone_id];
        } elseif ($user->hasRole('NationalAdmin')) {
            // NationalAdmin can see national tournaments from all zones
            $context['accessible_zones'] = \App\Models\Zone::pluck('id')->toArray();
        } elseif ($user->hasRole('SuperAdmin')) {
            // SuperAdmin can see everything
            $context['accessible_zones'] = \App\Models\Zone::pluck('id')->toArray();
        }

        return $context;
    }
}

