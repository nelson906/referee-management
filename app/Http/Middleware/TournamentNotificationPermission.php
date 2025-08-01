<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use App\Models\Tournament;
use App\Models\TournamentNotification;

/**
 * 🛡️ Middleware per controllo permessi notifiche torneo
 */
class TournamentNotificationPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission = 'view'): mixed
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Super admin ha accesso a tutto
        if ($user->user_type === 'super_admin') {
            return $next($request);
        }

        // Verifica permessi specifici
        switch ($permission) {
            case 'view':
                return $this->checkViewPermission($request, $next, $user);
            case 'create':
                return $this->checkCreatePermission($request, $next, $user);
            case 'send':
                return $this->checkSendPermission($request, $next, $user);
            case 'manage':
                return $this->checkManagePermission($request, $next, $user);
            default:
                abort(403, 'Permesso non riconosciuto');
        }
    }

    private function checkViewPermission(Request $request, Closure $next, $user): mixed
    {
        // Admin di zona può vedere notifiche della sua zona
        if ($user->user_type === 'admin' && $user->zone_id) {
            return $next($request);
        }

        // CRC può vedere tutto
        if ($user->user_type === 'crc') {
            return $next($request);
        }

        abort(403, 'Non autorizzato a visualizzare le notifiche');
    }

    private function checkCreatePermission(Request $request, Closure $next, $user): mixed
    {
        $tournamentId = $request->route('tournament');

        if ($tournamentId) {
            $tournament = Tournament::find($tournamentId);

            if (!$tournament) {
                abort(404, 'Torneo non trovato');
            }

            // Verifica se l'utente può gestire questo torneo
            if (!$this->canManageTournament($user, $tournament)) {
                abort(403, 'Non autorizzato a gestire questo torneo');
            }

            // Verifica se il torneo può ricevere notifiche
            if (!$tournament->canSendNotifications()) {
                $blockers = $tournament->getNotificationBlockers();
                return redirect()->back()->with('error',
                    'Il torneo non può ricevere notifiche: ' . implode(', ', $blockers)
                );
            }
        }

        return $next($request);
    }

    private function checkSendPermission(Request $request, Closure $next, $user): mixed
    {
        // Solo admin e CRC possono inviare notifiche
        if (!in_array($user->user_type, ['admin', 'crc', 'super_admin'])) {
            abort(403, 'Non autorizzato a inviare notifiche');
        }

        // Verifica permessi zona se admin
        if ($user->user_type === 'admin') {
            $tournamentId = $request->route('tournament');
            if ($tournamentId) {
                $tournament = Tournament::find($tournamentId);
                if ($tournament && !$this->canManageTournament($user, $tournament)) {
                    abort(403, 'Non autorizzato a gestire questo torneo');
                }
            }
        }

        return $next($request);
    }

    private function checkManagePermission(Request $request, Closure $next, $user): mixed
    {
        // Solo admin e CRC possono gestire notifiche
        if (!in_array($user->user_type, ['admin', 'crc', 'super_admin'])) {
            abort(403, 'Non autorizzato a gestire le notifiche');
        }

        return $next($request);
    }

    private function canManageTournament($user, Tournament $tournament): bool
    {
        // Super admin può tutto
        if ($user->user_type === 'super_admin') {
            return true;
        }

        // CRC può gestire tutti i tornei
        if ($user->user_type === 'crc') {
            return true;
        }

        // Admin può gestire solo tornei della sua zona
        if ($user->user_type === 'admin') {
            return $user->zone_id === $tournament->zone_id;
        }

        return false;
    }
}

/**
 * 🚦 Middleware per rate limiting notifiche
 */
class TournamentNotificationRateLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        $key = 'tournament-notifications:' . $user->id;

        // Limite configurabile dal config
        $maxAttempts = config('tournament-notifications.security.rate_limit', 30);
        $decayMinutes = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            Log::warning('Tournament notification rate limit exceeded', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'error' => 'Troppi tentativi di invio. Riprova tra ' . ceil($seconds / 60) . ' minuti.'
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return $next($request);
    }
}

/**
 * 🛡️ Middleware per validazione sicurezza notifiche
 */
class TournamentNotificationSecurity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Verifica IP whitelist se configurata
        $this->checkIpWhitelist($request);

        // Verifica modalità sandbox
        $this->checkSandboxMode($request);

        // Valida domini email se richiesto
        $this->validateEmailDomains($request);

        // Log accesso per audit
        $this->logAccess($request);

        return $next($request);
    }

    private function checkIpWhitelist(Request $request): void
    {
        $whitelist = config('tournament-notifications.security.api_whitelist');

        if (!empty($whitelist) && !in_array($request->ip(), $whitelist)) {
            Log::warning('Unauthorized IP access to notification system', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path()
            ]);

            abort(403, 'Accesso negato da questo IP');
        }
    }

    private function checkSandboxMode(Request $request): void
    {
        if (config('tournament-notifications.security.sandbox_mode')) {
            // In modalità sandbox, aggiungi header di avviso
            $request->headers->set('X-Sandbox-Mode', 'true');

            Log::info('Notification system running in sandbox mode', [
                'user_id' => $request->user()?->id,
                'path' => $request->path()
            ]);
        }
    }

    private function validateEmailDomains(Request $request): void
    {
        $allowedDomains = config('tournament-notifications.security.allowed_email_domains');

        if (!$allowedDomains) {
            return; // Tutti i domini permessi
        }

        // Estrai email dai dati della request
        $emails = $this->extractEmailsFromRequest($request);

        foreach ($emails as $email) {
            $domain = substr(strrchr($email, '@'), 1);

            if (!in_array($domain, $allowedDomains)) {
                Log::warning('Email with unauthorized domain', [
                    'email' => $email,
                    'domain' => $domain,
                    'user_id' => $request->user()?->id
                ]);

                abort(422, "Dominio email non autorizzato: {$domain}");
            }
        }
    }

    private function extractEmailsFromRequest(Request $request): array
    {
        $emails = [];
        $data = $request->all();

        // Estrai email da vari campi possibili
        if (isset($data['recipient_email'])) {
            $emails[] = $data['recipient_email'];
        }

        if (isset($data['test_email'])) {
            $emails[] = $data['test_email'];
        }

        // Filtra email valide
        return array_filter($emails, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });
    }

    private function logAccess(Request $request): void
    {
        if (config('tournament-notifications.logging.enabled')) {
            Log::info('Tournament notification system access', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'user_agent' => $request->userAgent()
            ]);
        }
    }
}

/**
 * 🔐 Middleware per validazione CSRF specifico per API notifiche
 */
class TournamentNotificationCSRF
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Per API calls, verifica token custom invece di CSRF standard
        if ($request->is('api/*')) {
            $apiToken = $request->header('X-API-Token');
            $expectedToken = config('tournament-notifications.api_token');

            if (!$apiToken || !hash_equals($expectedToken, $apiToken)) {
                Log::warning('Invalid API token for notification system', [
                    'provided_token' => $apiToken ? substr($apiToken, 0, 8) . '...' : null,
                    'ip' => $request->ip()
                ]);

                return response()->json(['error' => 'Token API non valido'], 401);
            }
        }

        return $next($request);
    }
}

/**
 * 📊 Middleware per monitoring performance
 */
class TournamentNotificationMonitoring
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $startTime = microtime(true);

        $response = $next($request);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // in millisecondi

        // Log performance solo se supera soglia
        $threshold = config('tournament-notifications.monitoring.slow_request_threshold', 1000);

        if ($executionTime > $threshold) {
            Log::warning('Slow notification request detected', [
                'path' => $request->path(),
                'method' => $request->method(),
                'execution_time_ms' => round($executionTime, 2),
                'user_id' => $request->user()?->id,
                'memory_usage' => $this->formatBytes(memory_get_peak_usage(true))
            ]);
        }

        // Aggiungi header performance
        $response->headers->set('X-Execution-Time', round($executionTime, 2) . 'ms');
        $response->headers->set('X-Memory-Usage', $this->formatBytes(memory_get_peak_usage(true)));

        return $response;
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

/**
 * 🔄 Middleware per gestione feature flags
 */
class TournamentNotificationFeatureFlag
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Verifica se il nuovo sistema è abilitato
        if (!config('tournament-notifications.features.new_system_enabled')) {

            // Fallback al sistema legacy se abilitato
            if (config('tournament-notifications.features.legacy_fallback')) {
                return redirect()->route('notifications.index')
                    ->with('info', 'Utilizzando sistema notifiche legacy');
            }

            abort(503, 'Sistema notifiche tornei temporaneamente non disponibile');
        }

        // Verifica feature specifiche
        $this->checkFeatureFlags($request);

        return $next($request);
    }

    private function checkFeatureFlags(Request $request): void
    {
        // Verifica feature push notifications
        if ($request->has('enable_push') && !config('tournament-notifications.features.push_notifications')) {
            $request->merge(['enable_push' => false]);
        }

        // Verifica feature SMS
        if ($request->has('enable_sms') && !config('tournament-notifications.features.sms_notifications')) {
            $request->merge(['enable_sms' => false]);
        }

        // Verifica feature calendar integration
        if ($request->has('calendar_integration') && !config('tournament-notifications.features.calendar_integration')) {
            $request->merge(['calendar_integration' => false]);
        }
    }
}

// ===========================
// REGISTRAZIONE MIDDLEWARE
// ===========================

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's route middleware.
     */
    protected $routeMiddleware = [
        // ... middleware esistenti ...

        // Middleware sistema notifiche torneo
        'tournament.notification.permission' => \App\Http\Middleware\TournamentNotificationPermission::class,
        'tournament.notification.rate_limit' => \App\Http\Middleware\TournamentNotificationRateLimit::class,
        'tournament.notification.security' => \App\Http\Middleware\TournamentNotificationSecurity::class,
        'tournament.notification.csrf' => \App\Http\Middleware\TournamentNotificationCSRF::class,
        'tournament.notification.monitoring' => \App\Http\Middleware\TournamentNotificationMonitoring::class,
        'tournament.notification.feature_flag' => \App\Http\Middleware\TournamentNotificationFeatureFlag::class,
    ];

    /**
     * The application's middleware groups.
     */
    protected $middlewareGroups = [
        'web' => [
            // ... middleware web esistenti ...
        ],

        'api' => [
            // ... middleware api esistenti ...
        ],

        // Gruppo middleware per notifiche torneo
        'tournament-notifications' => [
            'tournament.notification.feature_flag',
            'tournament.notification.security',
            'tournament.notification.monitoring',
            'tournament.notification.rate_limit',
            'tournament.notification.permission',
        ],

        // Gruppo middleware per API notifiche
        'tournament-notifications-api' => [
            'tournament.notification.feature_flag',
            'tournament.notification.csrf',
            'tournament.notification.security',
            'tournament.notification.monitoring',
            'tournament.notification.rate_limit',
        ],
    ];
}
