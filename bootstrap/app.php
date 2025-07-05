<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Global HTTP middleware stack
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // API middleware group
        $middleware->api(append: [
            // Add API-specific middleware here if needed
        ]);

        // Middleware aliases for the golf referee system
        $middleware->alias([
            'superadmin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'referee' => \App\Http\Middleware\RefereeMiddleware::class,
            'zone.access' => \App\Http\Middleware\ZoneAccessMiddleware::class,
            'zone.admin' => \App\Http\Middleware\ZoneAdminMiddleware::class,
        ]);

        // Middleware groups for different user types
        $middleware->group('superadmin-group', [
            'auth',
            'verified',
            'superadmin',
        ]);

        $middleware->group('admin-group', [
            'auth',
            'verified',
            'admin',
        ]);

        $middleware->group('referee-group', [
            'auth',
            'verified',
            'referee',
        ]);

        // Priority middleware - executed first
        $middleware->priority([
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Custom exception handling for the golf referee system
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Non sei autorizzato ad accedere a questa risorsa.',
                    'error' => 'Unauthorized'
                ], 403);
            }

            return redirect()->route('dashboard')->with('error', 'Non sei autorizzato ad accedere a questa sezione.');
        });
    })->create();
