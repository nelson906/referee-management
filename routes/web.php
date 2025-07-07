<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Referee;
use App\Http\Controllers\Reports;
use App\Http\Controllers\Api;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Home redirect
Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication Routes
Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('login', [AuthenticatedSessionController::class, 'store']);
Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Authenticated Routes
Route::middleware(['auth'])->group(function () {

    // Dashboard - redirect based on user type
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // =================================================================
    // SUPER ADMIN ROUTES
    // =================================================================
    Route::middleware(['superadmin'])->prefix('super-admin')->name('super-admin.')->group(function () {

        // Dashboard
        Route::get('/', function () {
            return redirect()->route('super-admin.tournament-categories.index');
        })->name('dashboard');

        // Tournament Categories Management
        Route::resource('tournament-categories', SuperAdmin\TournamentCategoryController::class);
        Route::post('tournament-categories/update-order', [SuperAdmin\TournamentCategoryController::class, 'updateOrder'])
            ->name('tournament-categories.update-order');
        Route::post('tournament-categories/{tournamentCategory}/toggle-active', [SuperAdmin\TournamentCategoryController::class, 'toggleActive'])
            ->name('tournament-categories.toggle-active');
        Route::post('tournament-categories/{tournamentCategory}/duplicate', [SuperAdmin\TournamentCategoryController::class, 'duplicateCategory'])
            ->name('tournament-categories.duplicate');

        // System Settings
        Route::get('settings', [SuperAdmin\SystemSettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [SuperAdmin\SystemSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/clear-cache', [SuperAdmin\SystemSettingsController::class, 'clearCache'])->name('settings.clear-cache');
        Route::post('settings/optimize', [SuperAdmin\SystemSettingsController::class, 'optimize'])->name('settings.optimize');

        // User Management (Super Admin exclusive)
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [SuperAdmin\UserController::class, 'index'])->name('index');
            Route::get('/create', [SuperAdmin\UserController::class, 'create'])->name('create');
            Route::post('/', [SuperAdmin\UserController::class, 'store'])->name('store');
            Route::get('/{user}', [SuperAdmin\UserController::class, 'show'])->name('show');
            Route::get('/{user}/edit', [SuperAdmin\UserController::class, 'edit'])->name('edit');
            Route::put('/{user}', [SuperAdmin\UserController::class, 'update'])->name('update');
            Route::delete('/{user}', [SuperAdmin\UserController::class, 'destroy'])->name('destroy');
            Route::post('/{user}/toggle-active', [SuperAdmin\UserController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/{user}/reset-password', [SuperAdmin\UserController::class, 'resetPassword'])->name('reset-password');
        });

        // Zone Management (Super Admin exclusive)
        Route::resource('zones', SuperAdmin\ZoneController::class);
        Route::post('zones/{zone}/toggle-active', [SuperAdmin\ZoneController::class, 'toggleActive'])->name('zones.toggle-active');

        // System Logs and Monitoring
        Route::prefix('system')->name('system.')->group(function () {
            Route::get('logs', [SuperAdmin\SystemController::class, 'logs'])->name('logs');
            Route::get('activity', [SuperAdmin\SystemController::class, 'activity'])->name('activity');
            Route::get('performance', [SuperAdmin\SystemController::class, 'performance'])->name('performance');
            Route::post('maintenance', [SuperAdmin\SystemController::class, 'toggleMaintenance'])->name('maintenance');
        });
    });

    // =================================================================
    // ADMIN ROUTES (Zone Admin & CRC Admin) + Super Admin Access
    // =================================================================
    Route::middleware(['admin_or_superadmin'])->prefix('admin')->name('admin.')->group(function () {

        // Dashboard
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Tournament Management
        Route::resource('tournaments', Admin\TournamentController::class);
        Route::post('tournaments/{tournament}/update-status', [Admin\TournamentController::class, 'updateStatus'])
            ->name('tournaments.update-status');
        Route::post('tournaments/{tournament}/duplicate', [Admin\TournamentController::class, 'duplicate'])
            ->name('tournaments.duplicate');
        Route::get('tournaments/{tournament}/assignments', [Admin\TournamentController::class, 'assignments'])
            ->name('tournaments.assignments');
        Route::post('tournaments/{tournament}/assign-referee', [Admin\TournamentController::class, 'assignReferee'])
            ->name('tournaments.assign-referee');
        Route::delete('tournaments/{tournament}/remove-referee/{referee}', [Admin\TournamentController::class, 'removeReferee'])
            ->name('tournaments.remove-referee');
        Route::get('tournaments/{tournament}/availabilities', [Admin\TournamentController::class, 'availabilities'])
            ->name('tournaments.availabilities');
Route::get('tournaments/calendar', [Admin\TournamentController::class, 'calendar'])
    ->name('tournaments.calendar');

        // Referee Management
        Route::resource('referees', Admin\RefereeController::class);
        Route::post('referees/{referee}/toggle-active', [Admin\RefereeController::class, 'toggleActive'])
            ->name('referees.toggle-active');
        Route::post('referees/{referee}/update-level', [Admin\RefereeController::class, 'updateLevel'])
            ->name('referees.update-level');
        Route::get('referees/{referee}/tournaments', [Admin\RefereeController::class, 'tournaments'])
            ->name('referees.tournaments');
        Route::post('referees/import', [Admin\RefereeController::class, 'import'])
            ->name('referees.import');
        Route::get('referees/export', [Admin\RefereeController::class, 'export'])
            ->name('referees.export');

        // Club Management
        Route::post('clubs/{club}/toggle-active', [Admin\ClubController::class, 'toggleActive'])
            ->name('clubs.toggle-active');
        Route::get('clubs/{club}/tournaments', [Admin\ClubController::class, 'tournaments'])
            ->name('clubs.tournaments');
        Route::resource('clubs', Admin\ClubController::class);
        Route::post('clubs/{club}/deactivate', [Admin\ClubController::class, 'deactivate'])
            ->name('clubs.deactivate');

        // Assignment Management
        Route::prefix('assignments')->name('assignments.')->group(function () {
            Route::get('/', [Admin\AssignmentController::class, 'index'])->name('index');
            Route::get('/create', [Admin\AssignmentController::class, 'create'])->name('create');
            Route::post('/', [Admin\AssignmentController::class, 'store'])->name('store');  // ← AGGIUNGI
            Route::get('/calendar', [Admin\AssignmentController::class, 'calendar'])->name('calendar');
            Route::post('/bulk-assign', [Admin\AssignmentController::class, 'bulkAssign'])->name('bulk-assign');
            Route::post('/{assignment}/accept', [Admin\AssignmentController::class, 'accept'])->name('accept');
            Route::post('/{assignment}/reject', [Admin\AssignmentController::class, 'reject'])->name('reject');
            Route::delete('/{assignment}', [Admin\AssignmentController::class, 'destroy'])->name('destroy');
            Route::post('/{assignment}/confirm', [Admin\AssignmentController::class, 'confirm'])->name('assignments.confirm');
        });

        // Communication System
        Route::prefix('communications')->name('communications.')->group(function () {
            Route::get('/', [Admin\CommunicationController::class, 'index'])->name('index');
            Route::get('/create', [Admin\CommunicationController::class, 'create'])->name('create');
            Route::post('/', [Admin\CommunicationController::class, 'store'])->name('store');
            Route::get('/{communication}', [Admin\CommunicationController::class, 'show'])->name('show');
            Route::delete('/{communication}', [Admin\CommunicationController::class, 'destroy'])->name('destroy');
        });

        // Document Management
        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/', [DocumentController::class, 'index'])->name('index');
            Route::post('/upload', [DocumentController::class, 'upload'])->name('upload');
            Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
            Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
        });
    });

    // =================================================================
    // REFEREE ROUTES + Admin/Super Admin Access
    // =================================================================
    Route::middleware(['referee_or_admin'])->prefix('referee')->name('referee.')->group(function () {

        // Dashboard
        Route::get('/', [Referee\DashboardController::class, 'index'])->name('dashboard');

        // Profile Management
        Route::get('/profile', [Referee\ProfileController::class, 'show'])->name('profile.show');
        Route::get('/profile/edit', [Referee\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [Referee\ProfileController::class, 'update'])->name('profile.update');

        // Availability Management
        Route::prefix('availability')->name('availability.')->group(function () {
            Route::get('/', [Referee\AvailabilityController::class, 'index'])->name('index');
            Route::get('/calendar', [Referee\AvailabilityController::class, 'calendar'])->name('calendar');
            Route::post('/update', [Referee\AvailabilityController::class, 'update'])->name('update');
            Route::post('/bulk-update', [Referee\AvailabilityController::class, 'bulkUpdate'])->name('bulk-update');
        });

        // Tournament Applications
        Route::prefix('applications')->name('applications.')->group(function () {
            Route::get('/', [Referee\ApplicationController::class, 'index'])->name('index');
            Route::post('/{tournament}/apply', [Referee\ApplicationController::class, 'apply'])->name('apply');
            Route::delete('/{tournament}/withdraw', [Referee\ApplicationController::class, 'withdraw'])->name('withdraw');
        });

        // Assignment History
        Route::get('/assignments', [Referee\AssignmentController::class, 'index'])->name('assignments.index');
        Route::get('/assignments/{assignment}', [Referee\AssignmentController::class, 'show'])->name('assignments.show');

        // Documents and Certifications
        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/', [Referee\DocumentController::class, 'index'])->name('index');
            Route::post('/upload', [Referee\DocumentController::class, 'upload'])->name('upload');
            Route::delete('/{document}', [Referee\DocumentController::class, 'destroy'])->name('destroy');
        });
    });

    // =================================================================
    // REPORTS ROUTES (All authenticated users with proper permissions)
    // =================================================================
    Route::middleware(['admin_or_superadmin'])->prefix('reports')->name('reports.')->group(function () {

        // Dashboard Analytics
        Route::get('/', [Reports\DashboardController::class, 'index'])->name('dashboard');

        // Tournament Reports
        Route::prefix('tournaments')->name('tournament.')->group(function () {
            Route::get('/', [Reports\TournamentReportController::class, 'index'])->name('index');
            Route::get('/{tournament}', [Reports\TournamentReportController::class, 'show'])->name('show');  // ← AGGIUNGI
            Route::get('/by-category', [Reports\TournamentReportController::class, 'byCategory'])->name('by-category');
            Route::get('/by-zone', [Reports\TournamentReportController::class, 'byZone'])->name('by-zone');
            Route::get('/by-period', [Reports\TournamentReportController::class, 'byPeriod'])->name('by-period');
            Route::get('/export', [Reports\TournamentReportController::class, 'export'])->name('export');
        });

        // Referee Reports
        Route::prefix('referees')->name('referee.')->group(function () {
            Route::get('/', [Reports\RefereeReportController::class, 'index'])->name('index');
            Route::get('/{referee}', [Reports\RefereeReportController::class, 'show'])->name('show');  // ← AGGIUNGI
            Route::get('/performance', [Reports\RefereeReportController::class, 'performance'])->name('performance');
            Route::get('/availability', [Reports\RefereeReportController::class, 'availability'])->name('availability');
            Route::get('/workload', [Reports\RefereeReportController::class, 'workload'])->name('workload');
            Route::get('/export', [Reports\RefereeReportController::class, 'export'])->name('export');
        });

        // Category Reports
        Route::prefix('categories')->name('category.')->group(function () {
            Route::get('/', [Reports\CategoryReportController::class, 'index'])->name('index');
            Route::get('/{category}', [Reports\CategoryReportController::class, 'show'])->name('show');
            Route::get('/{category}/tournaments', [Reports\CategoryReportController::class, 'tournaments'])->name('tournaments');
            Route::get('/export', [Reports\CategoryReportController::class, 'export'])->name('export');
        });

        // Zone Reports
        Route::prefix('zones')->name('zone.')->group(function () {
            Route::get('/', [Reports\ZoneReportController::class, 'index'])->name('index');
            Route::get('/{zone}', [Reports\ZoneReportController::class, 'show'])->name('show');
            Route::get('/{zone}/referees', [Reports\ZoneReportController::class, 'referees'])->name('referees');
            Route::get('/{zone}/tournaments', [Reports\ZoneReportController::class, 'tournaments'])->name('tournaments');
            Route::get('/export', [Reports\ZoneReportController::class, 'export'])->name('export');
        });

        // Advanced Analytics
        Route::prefix('analytics')->name('analytics.')->group(function () {
            Route::get('/trends', [Reports\AnalyticsController::class, 'trends'])->name('trends');
            Route::get('/forecasting', [Reports\AnalyticsController::class, 'forecasting'])->name('forecasting');
            Route::get('/efficiency', [Reports\AnalyticsController::class, 'efficiency'])->name('efficiency');
            Route::get('/custom', [Reports\AnalyticsController::class, 'custom'])->name('custom');
        });
    });

    // =================================================================
    // API ROUTES (Internal usage with proper authentication)
    // =================================================================
    Route::prefix('api')->name('api.')->group(function () {

        // Tournament API
        Route::prefix('tournaments')->name('tournaments.')->group(function () {
            Route::get('/', [Api\TournamentController::class, 'index'])->name('index');
            Route::get('/{tournament}', [Api\TournamentController::class, 'show'])->name('show');
            Route::get('/{tournament}/referees', [Api\TournamentController::class, 'referees'])->name('referees');
        });

        // Referee API
        Route::prefix('referees')->name('referees.')->group(function () {
            Route::get('/', [Api\RefereeController::class, 'index'])->name('index');
            Route::get('/{referee}', [Api\RefereeController::class, 'show'])->name('show');
            Route::get('/{referee}/availability', [Api\RefereeController::class, 'availability'])->name('availability');
        });

        // Calendar API
        Route::prefix('calendar')->name('calendar.')->group(function () {
            Route::get('/events', [Api\CalendarController::class, 'events'])->name('events');
            Route::get('/referee/{referee}/events', [Api\CalendarController::class, 'refereeEvents'])->name('referee-events');
        });

        // Statistics API
        Route::prefix('stats')->name('stats.')->group(function () {
            Route::get('/dashboard', [Api\StatsController::class, 'dashboard'])->name('dashboard');
            Route::get('/tournaments', [Api\StatsController::class, 'tournaments'])->name('tournaments');
            Route::get('/referees', [Api\StatsController::class, 'referees'])->name('referees');
        });
    });

    // =================================================================
    // NOTIFICATION ROUTES
    // =================================================================
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{notification}/mark-read', [NotificationController::class, 'markAsRead'])->name('mark-read');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
        Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
    });

    // =================================================================
    // SEARCH ROUTES
    // =================================================================
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::get('/search/tournaments', [SearchController::class, 'tournaments'])->name('search.tournaments');
    Route::get('/search/referees', [SearchController::class, 'referees'])->name('search.referees');
    Route::get('/search/clubs', [SearchController::class, 'clubs'])->name('search.clubs');
});

// =================================================================
// PUBLIC ROUTES (No authentication required)
// =================================================================

// Public Tournament Calendar
Route::get('/calendar', [PublicController::class, 'calendar'])->name('public.calendar');

// Public Tournament List
Route::get('/tournaments', [PublicController::class, 'tournaments'])->name('public.tournaments');

// Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0')
    ]);
})->name('health');

// =================================================================
// FALLBACK ROUTE
// =================================================================
Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});
