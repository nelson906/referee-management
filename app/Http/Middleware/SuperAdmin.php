<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SuperAdmin
{
    /**
     * Handle an incoming request for super admin access only
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

        // Only super admins are allowed
        if ($userType !== 'super_admin') {
            // Log unauthorized super admin access attempt
            Log::warning('Unauthorized super admin access attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_type' => $userType,
                'user_zone_id' => $user->zone_id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'requested_url' => $request->fullUrl(),
                'timestamp' => now(),
                'severity' => 'HIGH', // High severity for super admin access attempts
            ]);

            // Send alert for security monitoring
            $this->sendSecurityAlert($user, $request);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Access denied. Super Administrator privileges required.',
                    'error' => 'Forbidden'
                ], 403);
            }

            abort(403, 'Accesso negato. Sono richiesti privilegi di Super Amministratore per accedere a questa sezione.');
        }

        // Additional security checks for super admin
        $this->performSecurityChecks($request, $user);

        // Log successful super admin access
        Log::info('Super Admin access granted', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'requested_url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now(),
        ]);

        return $next($request);
    }

    /**
     * Perform additional security checks for super admin access
     */
    private function performSecurityChecks(Request $request, $user): void
    {
        // Check for suspicious activity patterns
        $this->checkSuspiciousActivity($request, $user);

        // Check for IP whitelist if configured
        $this->checkIpWhitelist($request, $user);

        // Check for time-based restrictions if configured
        $this->checkTimeRestrictions($request, $user);
    }

    /**
     * Check for suspicious activity patterns
     */
    private function checkSuspiciousActivity(Request $request, $user): void
    {
        $currentIp = $request->ip();
        $userAgent = $request->userAgent();
        $cacheKey = "super_admin_activity_{$user->id}";

        // Get recent activity
        $recentActivity = cache()->get($cacheKey, []);

        // Check for multiple IP addresses in short time
        $recentIps = collect($recentActivity)->pluck('ip')->unique();
        if ($recentIps->count() > 3 && !$recentIps->contains($currentIp)) {
            Log::warning('Super Admin suspicious activity: Multiple IPs', [
                'user_id' => $user->id,
                'current_ip' => $currentIp,
                'recent_ips' => $recentIps->toArray(),
                'url' => $request->fullUrl(),
            ]);
        }

        // Check for unusual user agent changes
        $recentUserAgents = collect($recentActivity)->pluck('user_agent')->unique();
        if ($recentUserAgents->count() > 2 && !$recentUserAgents->contains($userAgent)) {
            Log::warning('Super Admin suspicious activity: User agent change', [
                'user_id' => $user->id,
                'current_user_agent' => $userAgent,
                'recent_user_agents' => $recentUserAgents->toArray(),
                'ip' => $currentIp,
            ]);
        }

        // Store current activity
        $recentActivity[] = [
            'timestamp' => now(),
            'ip' => $currentIp,
            'user_agent' => $userAgent,
            'url' => $request->fullUrl(),
        ];

        // Keep only last 10 activities
        $recentActivity = array_slice($recentActivity, -10);

        // Cache for 24 hours
        cache()->put($cacheKey, $recentActivity, 60 * 24);
    }

    /**
     * Check IP whitelist if configured
     */
    private function checkIpWhitelist(Request $request, $user): void
    {
        $allowedIps = config('golf.security.super_admin_ip_whitelist', []);

        if (!empty($allowedIps)) {
            $currentIp = $request->ip();

            if (!in_array($currentIp, $allowedIps)) {
                Log::warning('Super Admin access denied: IP not whitelisted', [
                    'user_id' => $user->id,
                    'ip_address' => $currentIp,
                    'allowed_ips' => $allowedIps,
                    'url' => $request->fullUrl(),
                ]);

                abort(403, 'Accesso negato. Il tuo indirizzo IP non è autorizzato per l\'accesso Super Admin.');
            }
        }
    }

    /**
     * Check time-based restrictions
     */
    private function checkTimeRestrictions(Request $request, $user): void
    {
        $timeRestrictions = config('golf.security.super_admin_time_restrictions');

        if ($timeRestrictions['enabled'] ?? false) {
            $currentHour = now()->hour;
            $allowedStart = $timeRestrictions['start_hour'] ?? 0;
            $allowedEnd = $timeRestrictions['end_hour'] ?? 23;

            if ($currentHour < $allowedStart || $currentHour > $allowedEnd) {
                Log::warning('Super Admin access denied: Outside allowed hours', [
                    'user_id' => $user->id,
                    'current_hour' => $currentHour,
                    'allowed_start' => $allowedStart,
                    'allowed_end' => $allowedEnd,
                    'ip' => $request->ip(),
                ]);

                abort(403, "Accesso Super Admin consentito solo dalle {$allowedStart}:00 alle {$allowedEnd}:00.");
            }
        }
    }

    /**
     * Send security alert for unauthorized access attempts
     */
    private function sendSecurityAlert($user, Request $request): void
    {
        // In a production environment, this could send notifications via:
        // - Email to security team
        // - Slack/Teams notification
        // - SMS alert
        // - Security monitoring system webhook

        $alertData = [
            'type' => 'super_admin_access_attempt',
            'severity' => 'HIGH',
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_type' => $user->user_type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'requested_url' => $request->fullUrl(),
            'timestamp' => now(),
        ];

        Log::critical('SECURITY ALERT: Unauthorized Super Admin access attempt', $alertData);

        // Example: Send to security monitoring endpoint
        if (config('golf.security.alerts.webhook_url')) {
            try {
                // This would be an actual HTTP call to your security monitoring system
                // Http::post(config('golf.security.alerts.webhook_url'), $alertData);
            } catch (\Exception $e) {
                Log::error('Failed to send security alert', ['error' => $e->getMessage()]);
            }
        }
    }
}
