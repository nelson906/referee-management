<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Referee;
use App\Http\Controllers\Reports;
use App\Http\Controllers\Api;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Auth\LoginController;
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
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

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

        // Tournaments Management
        Route::resource('tournaments', Admin\TournamentController::class);
        Route::post('tournaments/{tournament}/update-status', [Admin\TournamentController::class, 'updateStatus'])
            ->name('tournaments.update-status');
        Route::get('tournaments/{tournament}/availabilities', [Admin\TournamentController::class, 'availabilities'])
            ->name('tournaments.availabilities');

        // Referees Management
        Route::resource('referees', Admin\RefereeController::class);
        Route::post('referees/{referee}/toggle-active', [Admin\RefereeController::class, 'toggleActive'])
            ->name('referees.toggle-active');
        Route::get('referees/{referee}/history', [Admin\RefereeController::class, 'history'])
            ->name('referees.history');

        // Circles Management
        Route::resource('circles', Admin\CircleController::class);
        Route::post('circles/{circle}/toggle-active', [Admin\CircleController::class, 'toggleActive'])
            ->name('circles.toggle-active');

        // Assignments Management
        Route::resource('assignments', Admin\AssignmentController::class);
        Route::post('assignments/bulk', [Admin\AssignmentController::class, 'bulkAssign'])
            ->name('assignments.bulk');
        Route::delete('assignments/{assignment}/remove', [Admin\AssignmentController::class, 'remove'])
            ->name('assignments.remove');
        Route::post('assignments/{assignment}/confirm', [Admin\AssignmentController::class, 'confirm'])
            ->name('assignments.confirm');

        // Notifications
        Route::get('notifications', [Admin\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/send', [Admin\NotificationController::class, 'send'])->name('notifications.send');
        Route::get('notifications/{notification}', [Admin\NotificationController::class, 'show'])->name('notifications.show');
        Route::post('notifications/{notification}/resend', [Admin\NotificationController::class, 'resend'])->name('notifications.resend');

        // Zone Management (only for zone admins)
        Route::middleware(['zone.admin'])->group(function () {
            Route::resource('zones', Admin\ZoneController::class)->only(['show', 'edit', 'update']);
            Route::post('zones/{zone}/upload-header', [Admin\ZoneController::class, 'uploadHeader'])
                ->name('zones.upload-header');
        });
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

        // Category Reports (super admin only)
        Route::middleware(['superadmin'])->group(function () {
            Route::get('category/{category}', [Reports\CategoryReportController::class, 'show'])
                ->name('category.show');
            Route::get('category/{category}/export', [Reports\CategoryReportController::class, 'export'])
                ->name('category.export');
        });
    });

    // =================================================================
    // DOCUMENT GENERATION ROUTES
    // =================================================================
    Route::middleware(['admin'])->prefix('documents')->name('documents.')->group(function () {

        // Convocation Letters
        Route::post('convocation/{assignment}', [DocumentController::class, 'generateConvocation'])
            ->name('convocation.generate');
        Route::get('convocation/{assignment}/preview', [DocumentController::class, 'previewConvocation'])
            ->name('convocation.preview');
        Route::get('convocation/{assignment}/download', [DocumentController::class, 'downloadConvocation'])
            ->name('convocation.download');

        // Club Letters
        Route::post('club-letter/{tournament}', [DocumentController::class, 'generateClubLetter'])
            ->name('club-letter.generate');
        Route::get('club-letter/{tournament}/preview', [DocumentController::class, 'previewClubLetter'])
            ->name('club-letter.preview');
        Route::get('club-letter/{tournament}/download', [DocumentController::class, 'downloadClubLetter'])
            ->name('club-letter.download');

        // Batch Generation
        Route::post('batch/tournament/{tournament}', [DocumentController::class, 'generateBatchForTournament'])
            ->name('batch.tournament');

        // Templates
        Route::get('templates', [DocumentController::class, 'templates'])
            ->name('templates.index');
        Route::get('templates/{template}/edit', [DocumentController::class, 'editTemplate'])
            ->name('templates.edit');
        Route::put('templates/{template}', [DocumentController::class, 'updateTemplate'])
            ->name('templates.update');
    });

    // =================================================================
    // API ROUTES (for calendar and AJAX)
    // =================================================================
    Route::prefix('api')->name('api.')->group(function () {

        // Calendar Data
        Route::get('calendar/tournaments', [Api\CalendarController::class, 'tournaments'])
            ->name('calendar.tournaments');
        Route::get('calendar/assignments', [Api\CalendarController::class, 'assignments'])
            ->name('calendar.assignments');
        Route::get('calendar/availabilities', [Api\CalendarController::class, 'availabilities'])
            ->name('calendar.availabilities');

        // Availability Toggle
        Route::post('availability/toggle', [Api\AvailabilityApiController::class, 'toggle'])
            ->name('availability.toggle');
        Route::post('availability/bulk-update', [Api\AvailabilityApiController::class, 'bulkUpdate'])
            ->name('availability.bulk-update');

        // Search APIs
        Route::get('search/referees', [Api\SearchController::class, 'referees'])
            ->name('search.referees');
        Route::get('search/circles', [Api\SearchController::class, 'circles'])
            ->name('search.circles');
        Route::get('search/tournaments', [Api\SearchController::class, 'tournaments'])
            ->name('search.tournaments');

        // Data for Select2/Autocomplete
        Route::get('data/zones', [Api\DataController::class, 'zones'])
            ->name('data.zones');
        Route::get('data/categories', [Api\DataController::class, 'categories'])
            ->name('data.categories');
        Route::get('data/referee-levels', [Api\DataController::class, 'refereeLevels'])
            ->name('data.referee-levels');
    });
});

// =================================================================
// PUBLIC CALENDAR ROUTE (if needed)
// =================================================================
Route::get('/public/calendar', [PublicController::class, 'calendar'])
    ->name('public.calendar');

// Fallback route
Route::fallback(function () {
    return redirect()->route('dashboard');
});

require __DIR__.'/auth.php';
