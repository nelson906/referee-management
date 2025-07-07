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
use App\Http\Controllers\Reports\TournamentReportController;

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
    });

    // =================================================================
    // ADMIN ROUTES (Zone Admin & CRC Admin)
    // =================================================================
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {

        // Dashboard
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Tournament Management
        Route::resource('tournaments', Admin\TournamentController::class);
        Route::post('tournaments/{tournament}/close', [Admin\TournamentController::class, 'close'])
            ->name('tournaments.close');
        Route::post('tournaments/{tournament}/reopen', [Admin\TournamentController::class, 'reopen'])
            ->name('tournaments.reopen');
        Route::get('tournaments/{tournament}/assignments', [Admin\TournamentController::class, 'assignments'])
            ->name('tournaments.assignments');
        Route::post('tournaments/{tournament}/assignments', [Admin\TournamentController::class, 'storeAssignments'])
            ->name('tournaments.assignments.store');
        Route::get('tournaments/{tournament}/availabilities', [Admin\TournamentController::class, 'availabilities'])
            ->name('tournaments.availabilities');
Route::post('tournaments/{tournament}/update-status', [Admin\TournamentController::class, 'updateStatus'])
    ->name('tournaments.update-status');

        // Referee Management
        Route::resource('referees', Admin\RefereeController::class);
        Route::post('referees/{referee}/toggle-active', [Admin\RefereeController::class, 'toggleActive'])
            ->name('referees.toggle-active');
        Route::get('referees/{referee}/availabilities', [Admin\RefereeController::class, 'availabilities'])
            ->name('referees.availabilities');
// Tournament Management
Route::resource('tournaments', Admin\TournamentController::class);
Route::post('tournaments/{tournament}/toggle-active', [Admin\TournamentController::class, 'toggleActive'])
    ->name('tournaments.toggle-active');
Route::get('reports/tournament/{tournament}', [TournamentReportController::class, 'show'])->name('reports.tournament.show');

        // club Management (for zone admins)
        Route::resource('clubs', Admin\ClubController::class);
        Route::post('clubs/{club}/toggle-active', [Admin\ClubController::class, 'toggleActive'])
            ->name('clubs.toggle-active');
Route::patch('clubs/{club}/activate', [Admin\ClubController::class, 'activate'])->name('clubs.activate');
Route::patch('clubs/{club}/deactivate', [Admin\ClubController::class, 'deactivate'])->name('clubs.deactivate');

        // Assignment Management
        Route::resource('assignments', Admin\AssignmentController::class);
        Route::post('assignments/{assignment}/confirm', [Admin\AssignmentController::class, 'confirm'])
            ->name('assignments.confirm');
    });

    // =================================================================
    // REFEREE ROUTES
    // =================================================================
    Route::middleware(['referee'])->prefix('referee')->name('referee.')->group(function () {

        // Dashboard
        Route::get('/', [Referee\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [Referee\DashboardController::class, 'index']);

        // Availability Management
        Route::get('availability', [Referee\AvailabilityController::class, 'index'])->name('availability.index');
        Route::post('availability/save', [Referee\AvailabilityController::class, 'save'])->name('availability.save');
        Route::post('availability/toggle', [Referee\AvailabilityController::class, 'toggle'])->name('availability.toggle');
        Route::get('availability/calendar', [Referee\AvailabilityController::class, 'calendar'])->name('availability.calendar');

        // Profile Management
        Route::get('profile', [Referee\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [Referee\ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [Referee\ProfileController::class, 'updatePassword'])->name('profile.update-password');

        // My Assignments
        Route::get('assignments', [Referee\AssignmentController::class, 'index'])->name('assignments.index');
        Route::get('assignments/{assignment}', [Referee\AssignmentController::class, 'show'])->name('assignments.show');
        Route::post('assignments/{assignment}/confirm', [Referee\AssignmentController::class, 'confirm'])->name('assignments.confirm');
    });

    // =================================================================
    // REPORTS ROUTES (accessible by appropriate roles)
    // =================================================================
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [Reports\ReportController::class, 'index'])->name('index');

        // Referee Reports
        Route::get('referee/{referee}', [Reports\RefereeReportController::class, 'show'])
            ->name('referee.show')
            ->middleware('can:view,referee');
        Route::get('referee/{referee}/export', [Reports\RefereeReportController::class, 'export'])
            ->name('referee.export')
            ->middleware('can:view,referee');

        // Zone Reports (admin only)
        Route::middleware(['admin'])->group(function () {
            Route::get('zone/{zone}', [Reports\ZoneReportController::class, 'show'])
                ->name('zone.show');
            Route::get('zone/{zone}/export', [Reports\ZoneReportController::class, 'export'])
                ->name('zone.export');
            Route::get('zone/{zone}/referees', [Reports\ZoneReportController::class, 'referees'])
                ->name('zone.referees');
            Route::get('zone/{zone}/tournaments', [Reports\ZoneReportController::class, 'tournaments'])
                ->name('zone.tournaments');
        });

        // Tournament Reports
        Route::get('tournament/{tournament}', [Reports\TournamentReportController::class, 'show'])
            ->name('tournament.show')
            ->middleware('can:view,tournament');
        Route::get('tournament/{tournament}/export', [Reports\TournamentReportController::class, 'export'])
            ->name('tournament.export')
            ->middleware('can:view,tournament');

        // Assignment Reports
        Route::get('assignments', [Reports\AssignmentReportController::class, 'index'])->name('assignments.index');

        // Category Reports (super admin only)
        Route::middleware(['superadmin'])->group(function () {
            Route::get('categories', [Reports\CategoryReportController::class, 'index'])
                ->name('category.index');
            Route::get('category/{category}', [Reports\CategoryReportController::class, 'show'])
                ->name('category.show');
            Route::get('category/{category}/export', [Reports\CategoryReportController::class, 'export'])
                ->name('category.export');
        });
    });

    // =================================================================
    // DOCUMENT ROUTES
    // =================================================================
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('download/{type}', [DocumentController::class, 'download'])->name('download');
        Route::post('generate', [DocumentController::class, 'generate'])->name('generate');
    });
});

// =================================================================
// API ROUTES (for AJAX calls)
// =================================================================
Route::middleware(['auth', 'api'])->prefix('api')->name('api.')->group(function () {
    Route::get('calendar/events', [Api\CalendarController::class, 'index'])->name('calendar.events');
    Route::get('tournaments/search', [Api\TournamentController::class, 'search'])->name('tournaments.search');
    Route::get('referees/search', [Api\RefereeController::class, 'search'])->name('referees.search');
});

// Include auth routes
require __DIR__ . '/auth.php';
