<?php

use Barryvdh\Debugbar\Facade;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Admin\LetterTemplateController;
use App\Http\Controllers\Admin\TemplateManagementController;
use App\Http\Controllers\Admin\LetterheadController; // ← AGGIUNTO
use App\Http\Controllers\Admin\StatisticsDashboardController; // ← AGGIUNTO
use App\Http\Controllers\Admin\MonitoringController; // ← AGGIUNTO
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Referee;
use App\Http\Controllers\Reports;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
// use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
/*
|--------------------------------------------------------------------------
| 🔧 COMPLETE WEB ROUTES - Golf Referee Management System
|--------------------------------------------------------------------------
*/

// Home redirect
Route::get('/', function () {
    return view('welcome');
});

// Authentication Routes
Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('login', [AuthenticatedSessionController::class, 'store']);
Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
});
    // Curriculum
        Route::get('/referee/my-curriculum', [Admin\RefereeController::class, 'myCurriculum'])
        ->name('referee.my-curriculum');

// Authenticated Routes
Route::middleware(['auth'])->group(function () {

    // Dashboard - redirect based on user type
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Email verification routes
    Route::get('/email/verify', function () {
        return view('auth.verify-email');
    })->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        return redirect('/dashboard');
    })->middleware(['signed'])->name('verification.verify');

    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('message', 'Verification link sent!');
    })->middleware(['throttle:6,1'])->name('verification.send');

    // =================================================================
    // ✅ UNIFIED TOURNAMENT ROUTES (tutti gli utenti autorizzati)
    // =================================================================
    Route::get('tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
    Route::get('tournaments/calendar', [TournamentController::class, 'calendar'])->name('tournaments.calendar');
    Route::get('tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');

    // =================================================================
    // 🛡️ SUPER ADMIN ROUTES
    // =================================================================
    Route::middleware(['superadmin'])->prefix('super-admin')->name('super-admin.')->group(function () {

        // Dashboard redirect
        Route::get('/', function () {
            return redirect()->route('super-admin.institutional-emails.index');
        })->name('dashboard');

        // ★ INSTITUTIONAL EMAILS MANAGEMENT ★
        Route::prefix('institutional-emails')->name('institutional-emails.')->group(function () {
            Route::get('/', [SuperAdmin\InstitutionalEmailController::class, 'index'])->name('index');
            Route::get('/create', [SuperAdmin\InstitutionalEmailController::class, 'create'])->name('create');
            Route::post('/', [SuperAdmin\InstitutionalEmailController::class, 'store'])->name('store');
            Route::get('/{institutionalEmail}', [SuperAdmin\InstitutionalEmailController::class, 'show'])->name('show');
            Route::get('/{institutionalEmail}/edit', [SuperAdmin\InstitutionalEmailController::class, 'edit'])->name('edit');
            Route::put('/{institutionalEmail}', [SuperAdmin\InstitutionalEmailController::class, 'update'])->name('update');
            Route::delete('/{institutionalEmail}', [SuperAdmin\InstitutionalEmailController::class, 'destroy'])->name('destroy');
            Route::post('/{institutionalEmail}/toggle-active', [SuperAdmin\InstitutionalEmailController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/{institutionalEmail}/test', [SuperAdmin\InstitutionalEmailController::class, 'test'])->name('test');
            Route::post('/bulk-action', [SuperAdmin\InstitutionalEmailController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/export', [SuperAdmin\InstitutionalEmailController::class, 'export'])->name('export');
        });

        // Tournament Types Management
        Route::resource('tournament-types', SuperAdmin\TournamentTypeController::class);
        Route::post('tournament-types/update-order', [SuperAdmin\TournamentTypeController::class, 'updateOrder'])->name('tournament-types.update-order');
        Route::post('tournament-types/{tournamentType}/toggle-active', [SuperAdmin\TournamentTypeController::class, 'toggleActive'])->name('tournament-types.toggle-active');
        Route::post('tournament-types/{tournamentType}/duplicate', [SuperAdmin\TournamentTypeController::class, 'duplicateCategory'])->name('tournament-types.duplicate');

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
            Route::post('/bulk-action', [SuperAdmin\UserController::class, 'bulkAction'])->name('bulk-action');
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
    // // In web.php
    // Route::get('admin/tournament-notifications/find-by-tournament/{tournament}',
    //     [TournamentNotificationController::class, 'findByTournament'])
    //     ->name('admin.tournament-notifications.find-by-tournament');

    // =================================================================
    // 🔧 ADMIN ROUTES (Zone Admin & CRC Admin) + Super Admin Access
    // =================================================================
    Route::middleware(['admin_or_superadmin'])->prefix('admin')->name('admin.')->group(function () {

        // Dashboard
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Tournament Management - CRUD completo
        Route::resource('tournaments', Admin\TournamentController::class);
        Route::post('tournaments/{tournament}/update-status', [Admin\TournamentController::class, 'updateStatus'])->name('tournaments.update-status');
        Route::get('tournaments/{tournament}/availabilities', [Admin\TournamentController::class, 'availabilities'])->name('tournaments.availabilities');
        Route::post('tournaments/{tournament}/close', [Admin\TournamentController::class, 'close'])->name('tournaments.close');
        Route::post('tournaments/{tournament}/reopen', [Admin\TournamentController::class, 'reopen'])->name('tournaments.reopen');

        // ✅ NOTIFICHE ASSEGNAZIONE - Solo queste 3 route
        Route::get('tournaments/{tournament}/send-assignment', [Admin\NotificationController::class, 'showAssignmentForm'])
            ->name('tournaments.show-assignment-form');
        Route::post('tournaments/{tournament}/send-assignment', [Admin\NotificationController::class, 'sendTournamentAssignment'])
            ->name('tournaments.send-assignment');
        Route::post('tournaments/{tournament}/send-assignment-with-convocation', [Admin\NotificationController::class, 'sendAssignmentWithConvocation'])
            ->name('tournaments.send-assignment-with-convocation');

        // Referee Management
        Route::resource('referees', Admin\RefereeController::class);
        Route::post('referees/{referee}/toggle-active', [Admin\RefereeController::class, 'toggleActive'])->name('referees.toggle-active');
        Route::post('referees/{referee}/update-level', [Admin\RefereeController::class, 'updateLevel'])->name('referees.update-level');
        Route::get('referees/{referee}/tournaments', [Admin\RefereeController::class, 'tournaments'])->name('referees.tournaments');
        Route::post('referees/import', [Admin\RefereeController::class, 'import'])->name('referees.import');
        Route::get('referees/export', [Admin\RefereeController::class, 'export'])->name('referees.export');
        Route::get('referees/{referee}/availability', [Admin\RefereeController::class, 'availability'])->name('referees.availability');

        // Club Management
        Route::resource('clubs', Admin\ClubController::class);
        Route::post('clubs/{club}/toggle-active', [Admin\ClubController::class, 'toggleActive'])->name('clubs.toggle-active');
        Route::get('clubs/{club}/tournaments', [Admin\ClubController::class, 'tournaments'])->name('clubs.tournaments');
        Route::post('clubs/{club}/deactivate', [Admin\ClubController::class, 'deactivate'])->name('clubs.deactivate');

        // Assignment Management
        Route::prefix('assignments')->name('assignments.')->group(function () {
            Route::get('/', [Admin\AssignmentController::class, 'index'])->name('index');
            Route::get('/create', [Admin\AssignmentController::class, 'create'])->name('create');
            Route::post('/', [Admin\AssignmentController::class, 'store'])->name('store');
            Route::get('/calendar', [Admin\AssignmentController::class, 'calendar'])->name('calendar');
            Route::get('/{assignment}', [Admin\AssignmentController::class, 'show'])->name('show');
            Route::get('/{tournament}/assign', [Admin\AssignmentController::class, 'assignReferees'])->name('assign-referees');
            Route::post('/bulk-assign', [Admin\AssignmentController::class, 'bulkAssign'])->name('bulk-assign');
            Route::post('/{assignment}/accept', [Admin\AssignmentController::class, 'accept'])->name('accept');
            Route::post('/{assignment}/reject', [Admin\AssignmentController::class, 'reject'])->name('reject');
            Route::delete('/{assignment}', [Admin\AssignmentController::class, 'destroy'])->name('destroy');
            Route::post('/{assignment}/confirm', [Admin\AssignmentController::class, 'confirm'])->name('confirm');
        });

        // Communication System
        Route::prefix('communications')->name('communications.')->group(function () {
            Route::get('/', [Admin\CommunicationController::class, 'index'])->name('index');
            Route::get('/create', [Admin\CommunicationController::class, 'create'])->name('create');
            Route::post('/', [Admin\CommunicationController::class, 'store'])->name('store');
            Route::get('/{communication}', [Admin\CommunicationController::class, 'show'])->name('show');
            Route::delete('/{communication}', [Admin\CommunicationController::class, 'destroy'])->name('destroy');
        });

        // Letter Templates Management
        Route::prefix('letter-templates')->name('letter-templates.')->group(function () {
            Route::get('/', [LetterTemplateController::class, 'index'])->name('index');
            Route::get('/create', [LetterTemplateController::class, 'create'])->name('create');
            Route::post('/', [LetterTemplateController::class, 'store'])->name('store');
            Route::get('/{template}', [LetterTemplateController::class, 'show'])->name('show');
            Route::get('/{template}/edit', [LetterTemplateController::class, 'edit'])->name('edit');
            Route::put('/{template}', [LetterTemplateController::class, 'update'])->name('update');
            Route::delete('/{template}', [LetterTemplateController::class, 'destroy'])->name('destroy');
            Route::post('/{template}/duplicate', [LetterTemplateController::class, 'duplicate'])->name('duplicate');
            Route::get('/{template}/preview', [LetterTemplateController::class, 'preview'])->name('preview');
            Route::post('/{template}/toggle-active', [LetterTemplateController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/{template}/set-default', [LetterTemplateController::class, 'setDefault'])->name('set-default');
        });
        Route::get('/templates/management', [TemplateManagementController::class, 'index'])->name('templates.management');
        Route::get('/templates/{template}/preview', [TemplateManagementController::class, 'preview'])->name('templates.preview');


        // Letterheads Management (aggiungi dopo letter-templates)
        Route::prefix('letterheads')->name('letterheads.')->group(function () {
            Route::get('/', [Admin\LetterheadController::class, 'index'])->name('index');
            Route::get('/create', [Admin\LetterheadController::class, 'create'])->name('create');
            Route::post('/', [Admin\LetterheadController::class, 'store'])->name('store');
            Route::get('/{letterhead}', [Admin\LetterheadController::class, 'show'])->name('show');
            Route::get('/{letterhead}/edit', [Admin\LetterheadController::class, 'edit'])->name('edit');
            Route::put('/{letterhead}', [Admin\LetterheadController::class, 'update'])->name('update');
            Route::delete('/{letterhead}', [Admin\LetterheadController::class, 'destroy'])->name('destroy');
            Route::post('/{letterhead}/duplicate', [Admin\LetterheadController::class, 'duplicate'])->name('duplicate');
            Route::get('/{letterhead}/preview', [Admin\LetterheadController::class, 'preview'])->name('preview');
            Route::post('/{letterhead}/toggle-active', [Admin\LetterheadController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/{letterhead}/set-default', [Admin\LetterheadController::class, 'setDefault'])->name('set-default');
            Route::delete('/{letterhead}/remove-logo', [Admin\LetterheadController::class, 'removeLogo'])->name('remove-logo');
        });

        // Notifications Management - PULITO
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [Admin\NotificationController::class, 'index'])->name('index');
            Route::get('/stats', [Admin\NotificationController::class, 'stats'])->name('stats');
            Route::get('/{notification}', [Admin\NotificationController::class, 'show'])->name('show');
            Route::delete('/{notification}', [Admin\NotificationController::class, 'destroy'])->name('destroy');
            Route::post('/{notification}/retry', [Admin\NotificationController::class, 'retry'])->name('retry');
            Route::post('/{notification}/resend', [Admin\NotificationController::class, 'resend'])->name('resend');
            Route::post('/{notification}/cancel', [Admin\NotificationController::class, 'cancel'])->name('cancel');
            Route::get('/export/csv', [Admin\NotificationController::class, 'exportCsv'])->name('export');
        });

        // ✅ STATISTICS DASHBOARD - ROUTES AGGIUNTE
        Route::prefix('statistics')->name('statistics.')->group(function () {
            Route::get('/', [StatisticsDashboardController::class, 'index'])->name('dashboard');
            Route::get('/disponibilita', [StatisticsDashboardController::class, 'disponibilita'])->name('disponibilita');
            Route::get('/assegnazioni', [StatisticsDashboardController::class, 'assegnazioni'])->name('assegnazioni');
            Route::get('/tornei', [StatisticsDashboardController::class, 'tornei'])->name('tornei');
            Route::get('/arbitri', [StatisticsDashboardController::class, 'arbitri'])->name('arbitri');
            Route::get('/zone', [StatisticsDashboardController::class, 'zone'])->name('zone');
            Route::get('/performance', [StatisticsDashboardController::class, 'performance'])->name('performance');
            Route::get('/export', [StatisticsDashboardController::class, 'exportCsv'])->name('export');
            Route::get('/api/{type}', [StatisticsDashboardController::class, 'apiStats'])->name('api');
        });

        // ✅ MONITORING SYSTEM - ROUTES AGGIUNTE
        Route::prefix('monitoring')->name('monitoring.')->group(function () {
            Route::get('/', [MonitoringController::class, 'dashboard'])->name('dashboard');
            Route::get('/health', [MonitoringController::class, 'healthCheck'])->name('health');
            Route::get('/metrics', [MonitoringController::class, 'realtimeMetrics'])->name('metrics');
            Route::get('/history', [MonitoringController::class, 'history'])->name('history');
            Route::get('/logs', [MonitoringController::class, 'systemLogs'])->name('logs');
            Route::get('/performance', [MonitoringController::class, 'performanceMetrics'])->name('performance');
            Route::post('/clear-cache', [MonitoringController::class, 'clearCache'])->name('clear-cache');
            Route::post('/optimize', [MonitoringController::class, 'optimize'])->name('optimize');
        });

        // Document Management
        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/', [DocumentController::class, 'index'])->name('index');
            Route::post('/upload', [DocumentController::class, 'upload'])->name('upload');
            Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
            Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
        });
        // 🏆 TOURNAMENT NOTIFICATIONS SYSTEM (Nuovo)
        Route::prefix('tournament-notifications')->name('tournament-notifications.')->group(function () {
            Route::get('/', [Admin\NotificationController::class, 'index'])->name('index');
            Route::post('/{tournament}/store', [Admin\NotificationController::class, 'store'])->name('store');
            Route::post('/{tournament}/prepare', [Admin\NotificationController::class, 'prepare'])->name('prepare');
            Route::post('/{notification}/send', [Admin\NotificationController::class, 'send'])->name('send');
            Route::post('/{notification}/resend', [Admin\NotificationController::class, 'resend'])->name('resend');
            Route::get('/{notification}/edit', [Admin\NotificationController::class, 'edit'])->name('edit');
            Route::put('/{notification}', [Admin\NotificationController::class, 'update'])->name('update');
            Route::get('/{notification}', [Admin\NotificationController::class, 'show'])->name('show');
            Route::delete('/{notification}', [Admin\NotificationController::class, 'destroy'])->name('destroy');
            Route::get('/{notification}/documents-status', [Admin\NotificationController::class, 'documentsStatus'])->name('documents-status');
            Route::get('/{notification}/download/{type}', [Admin\NotificationController::class, 'downloadDocument'])->name('download-document');
            Route::post('/{notification}/upload/{type}', [Admin\NotificationController::class, 'uploadDocument'])->name('upload-document');
            Route::post('/{notification}/generate/{type}', [Admin\NotificationController::class, 'generateDocument'])->name('generate-document');
            Route::post('/{notification}/regenerate/{type}', [Admin\NotificationController::class, 'regenerateDocument'])->name('regenerate-document');
            Route::delete('/{notification}/document/{type}', [Admin\NotificationController::class, 'deleteDocument'])->name('delete-document');
            Route::get('/find-by-tournament/{tournament}', [NotificationController::class, 'findByTournament'])->name('find-by-tournament');
        });

        // Curricula
        Route::get('/admin/referees/curricula', [Admin\RefereeController::class, 'allCurricula'])
            ->name('admin.referees.curricula');
        Route::get('/admin/referee/{id}/curriculum', [Admin\RefereeController::class, 'showCurriculum'])
            ->name('admin.referee.curriculum');

        // Settings
        Route::get('/settings', [Admin\SettingsController::class, 'index'])->name('settings');
        Route::post('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    });
});

// =================================================================
// ⚽ REFEREE ROUTES + Admin/Super Admin Access
// =================================================================
Route::middleware(['referee_or_admin'])->prefix('referee')->name('referee.')->group(function () {

    // Dashboard
    Route::get('/', [Referee\DashboardController::class, 'index'])->name('dashboard');

    // Availability Management
    Route::prefix('availability')->name('availability.')->group(function () {
        Route::get('/', [Referee\AvailabilityController::class, 'index'])->name('index');
        Route::get('/calendar', [Referee\AvailabilityController::class, 'calendar'])->name('calendar');
        Route::post('/save', [Referee\AvailabilityController::class, 'save'])->name('save');
        Route::post('/update', [Referee\AvailabilityController::class, 'update'])->name('update');
        Route::post('/bulk-update', [Referee\AvailabilityController::class, 'bulkUpdate'])->name('bulk-update');
        Route::post('/toggle', [Referee\AvailabilityController::class, 'toggle'])->name('toggle');
        Route::post('/', [Referee\AvailabilityController::class, 'store'])->name('store');
        Route::post('/bulk', [Referee\AvailabilityController::class, 'bulk'])->name('bulk');
        Route::patch('/{availability}', [Referee\AvailabilityController::class, 'update'])->name('update');
        Route::delete('/{availability}', [Referee\AvailabilityController::class, 'destroy'])->name('destroy');
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
    Route::prefix('tournament-documents')->name('tournament-documents.')->group(function () {
        Route::get('/{notification}/{type}/{filename}', [DocumentController::class, 'download'])->name('download');
        Route::post('/{notification}/upload', [DocumentController::class, 'upload'])->name('upload');
        Route::get('/{notification}/regenerate', [DocumentController::class, 'regenerate'])->name('regenerate');
    });


});

// =================================================================
// 📊 REPORTS ROUTES (All authenticated users with proper permissions)
// =================================================================
Route::middleware(['admin_or_superadmin'])->prefix('reports')->name('reports.')->group(function () {

    // Dashboard Analytics
    Route::get('/', [Reports\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/export', [Reports\DashboardController::class, 'export'])->name('dashboard.export');

    // Tournament Reports
    Route::prefix('tournaments')->name('tournament.')->group(function () {
        Route::get('/', [Reports\TournamentReportController::class, 'index'])->name('index');
        Route::get('/{tournament}', [Reports\TournamentReportController::class, 'show'])->name('show');
        Route::get('/by-category', [Reports\TournamentReportController::class, 'byCategory'])->name('by-category');
        Route::get('/by-zone', [Reports\TournamentReportController::class, 'byZone'])->name('by-zone');
        Route::get('/by-period', [Reports\TournamentReportController::class, 'byPeriod'])->name('by-period');
        Route::get('/export', [Reports\TournamentReportController::class, 'export'])->name('export');
    });

    // Referee Reports
    Route::prefix('referees')->name('referee.')->group(function () {
        Route::get('/', [Reports\RefereeReportController::class, 'index'])->name('index');
        Route::get('/{referee}', [Reports\RefereeReportController::class, 'show'])->name('show');
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
// 🔧 API ROUTES UTILI (mantenute solo quelle necessarie)
// =================================================================
Route::prefix('api')->name('api.')->group(function () {

    // API per selezioni dinamiche nel form notifiche
    Route::get('/institutional-emails/{zoneId}', function ($zoneId) {
        return \App\Models\InstitutionalEmail::where('is_active', true)
            ->where(function ($query) use ($zoneId) {
                $query->where('zone_id', $zoneId)
                    ->orWhere('receive_all_notifications', true);
            })
            ->select('id', 'name', 'email', 'category')
            ->get()
            ->groupBy('category');
    })->name('institutional-emails');

    // API per template lettere
    Route::get('/letter-templates/{type}/{zoneId?}', function ($type, $zoneId = null) {
        $query = \App\Models\LetterTemplate::where('is_active', true)
            ->where('type', $type);

        if ($zoneId) {
            $query->where(function ($q) use ($zoneId) {
                $q->where('zone_id', $zoneId)->orWhereNull('zone_id');
            });
        }

        return $query->select('id', 'name', 'subject', 'body')->get();
    })->name('letter-templates');


    // API per statistiche notifiche
    Route::get('/notifications/stats/{days?}', function ($days = 30) {
        return \App\Models\Notification::getStatistics($days);
    })->name('notification-stats');

    // API per calendario tornei
    Route::get('/tournaments/calendar', function () {
        return \App\Models\Tournament::getCalendarEvents();
    })->name('tournaments.calendar');
});



// ✅ NOTIFICATIONS - FIX per "Route [notifications.index] not defined"
Route::prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [Admin\NotificationController::class, 'index'])->name('index');
    // ... altre route
});
Route::post('/api/set-year', function (Request $request) {
    $request->validate(['year' => 'required|integer|between:2015,' . date('Y')]);
    session(['selected_year' => $request->year]);
    return response()->json(['success' => true]);
});

// =================================================================
// 🔐 AUTH ROUTES
// =================================================================
require __DIR__ . '/auth.php';

// =================================================================
// 🚀 HEALTH CHECK (no auth required)
// =================================================================
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
        'database' => DB::table('users')->count() > 0,
        'cache' => Cache::store()->getStore() instanceof \Illuminate\Cache\TaggedCache
    ]);
})->name('health.check');

// System status endpoint (requires auth)
Route::get('/status', function () {
    return response()->json([
        'environment' => config('app.env'),
        'debug' => config('app.debug'),
        'timezone' => config('app.timezone'),
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version()
    ]);
})->middleware('auth:sanctum');

// =================================================================
// 🚫 FALLBACK ROUTE
// =================================================================
Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});
