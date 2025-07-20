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
use App\Http\Admin\LetterTemplateController;
use App\Http\Controllers\TournamentController;

/*
|--------------------------------------------------------------------------
| Web Routes
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

// Authenticated Routes
Route::middleware(['auth'])->group(function () {

    // Dashboard - redirect based on user type
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // =================================================================
    // PUBLIC TOURNAMENT ROUTES (visible to all authenticated users)
    // =================================================================
    Route::get('tournaments', [Admin\TournamentController::class, 'publicIndex'])->name('tournaments.index');
    Route::get('tournaments/calendar', [Admin\TournamentController::class, 'publicCalendar'])->name('tournaments.calendar');
    Route::get('tournaments/{tournament}', [Admin\TournamentController::class, 'publicShow'])->name('tournaments.show');

    // =================================================================
    // SUPER ADMIN ROUTES
    // =================================================================
    Route::middleware(['superadmin'])->prefix('super-admin')->name('super-admin.')->group(function () {

        // Dashboard
        Route::get('/', function () {
            return redirect()->route('super-admin.tournament-types.index');
        })->name('dashboard');

        // Tournament Types Management
        Route::resource('tournament-types', SuperAdmin\TournamentTypeController::class);
        Route::post('tournament-types/update-order', [SuperAdmin\TournamentTypeController::class, 'updateOrder'])
            ->name('tournament-types.update-order');
        Route::post('tournament-types/{tournamentType}/toggle-active', [SuperAdmin\TournamentTypeController::class, 'toggleActive'])
            ->name('tournament-types.toggle-active');
        Route::post('tournament-types/{tournamentType}/duplicate', [SuperAdmin\TournamentTypeController::class, 'duplicateCategory'])
            ->name('tournament-types.duplicate');

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
// EMAIL ISTITUZIONALI - Gestione (SuperAdmin only)
Route::middleware(['superadmin'])->prefix('institutional-emails')->name('institutional-emails.')->group(function () {
    Route::get('/', [Admin\InstitutionalEmailController::class, 'index'])->name('index');
    Route::get('/create', [Admin\InstitutionalEmailController::class, 'create'])->name('create');
    Route::post('/', [Admin\InstitutionalEmailController::class, 'store'])->name('store');
    Route::get('/{email}/edit', [Admin\InstitutionalEmailController::class, 'edit'])->name('edit');
    Route::put('/{email}', [Admin\InstitutionalEmailController::class, 'update'])->name('update');
    Route::delete('/{email}', [Admin\InstitutionalEmailController::class, 'destroy'])->name('destroy');
    Route::post('/{email}/toggle', [Admin\InstitutionalEmailController::class, 'toggle'])->name('toggle');
});
// TEMPLATE LETTERE - Gestione
Route::prefix('letter-templates')->name('letter-templates.')->group(function () {
    Route::get('/', [Admin\LetterTemplateController::class, 'index'])->name('index');
    Route::get('/create', [Admin\LetterTemplateController::class, 'create'])->name('create');
    Route::post('/', [Admin\LetterTemplateController::class, 'store'])->name('store');
    Route::get('/{template}', [Admin\LetterTemplateController::class, 'show'])->name('show');
    Route::get('/{template}/edit', [Admin\LetterTemplateController::class, 'edit'])->name('edit');
    Route::put('/{template}', [Admin\LetterTemplateController::class, 'update'])->name('update');
    Route::delete('/{template}', [Admin\LetterTemplateController::class, 'destroy'])->name('destroy');

    // Azioni speciali
    Route::post('/{template}/duplicate', [Admin\LetterTemplateController::class, 'duplicate'])->name('duplicate');
    Route::post('/{template}/toggle', [Admin\LetterTemplateController::class, 'toggle'])->name('toggle');
    Route::get('/{template}/preview', [Admin\LetterTemplateController::class, 'preview'])->name('preview');
});

    // =================================================================
    // ADMIN ROUTES (Zone Admin & CRC Admin) + Super Admin Access
    // =================================================================
    Route::middleware(['admin_or_superadmin'])->prefix('admin')->name('admin.')->group(function () {

        // Dashboard
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Tournament Management - NOMI CORRETTI per convenzione Laravel
        Route::get('tournaments', [Admin\TournamentController::class, 'adminIndex'])->name('tournaments.index'); // ← FIX
        Route::get('tournaments/calendar', [Admin\TournamentController::class, 'calendar'])->name('tournaments.calendar'); // ← FIX
        Route::get('tournaments/create', [Admin\TournamentController::class, 'create'])->name('tournaments.create');
        Route::post('tournaments', [Admin\TournamentController::class, 'store'])->name('tournaments.store');
        Route::get('tournaments/{tournament}', [Admin\TournamentController::class, 'show'])->name('tournaments.show'); // ← AGGIUNGI
        Route::get('tournaments/{tournament}/edit', [Admin\TournamentController::class, 'edit'])->name('tournaments.edit');
        Route::put('tournaments/{tournament}', [Admin\TournamentController::class, 'update'])->name('tournaments.update');
        Route::delete('tournaments/{tournament}', [Admin\TournamentController::class, 'destroy'])->name('tournaments.destroy');
        Route::post('tournaments/{tournament}/close', [Admin\TournamentController::class, 'close'])->name('tournaments.close');
        Route::post('tournaments/{tournament}/reopen', [Admin\TournamentController::class, 'reopen'])->name('tournaments.reopen');
        // AGGIUNGI QUESTE ROUTE MANCANTI ✅
        Route::get('tournaments/{tournament}/availabilities', [Admin\TournamentController::class, 'availabilities'])
            ->name('tournaments.availabilities');

        // Potresti aver bisogno anche di:
        Route::post('tournaments/{tournament}/update-status', [Admin\TournamentController::class, 'updateStatus'])
            ->name('tournaments.update-status');


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
        Route::resource('clubs', Admin\ClubController::class);
        Route::post('clubs/{club}/toggle-active', [Admin\ClubController::class, 'toggleActive'])
            ->name('clubs.toggle-active');
        Route::get('clubs/{club}/tournaments', [Admin\ClubController::class, 'tournaments'])
            ->name('clubs.tournaments');
        Route::post('clubs/{club}/deactivate', [Admin\ClubController::class, 'deactivate'])
            ->name('clubs.deactivate');

        // Assignment Management
        Route::prefix('assignments')->name('assignments.')->group(function () {
            Route::get('/', [Admin\AssignmentController::class, 'index'])->name('index');
            Route::get('/create', [Admin\AssignmentController::class, 'create'])->name('create');
            Route::post('/', [Admin\AssignmentController::class, 'store'])->name('store');
            Route::get('/calendar', [Admin\AssignmentController::class, 'calendar'])->name('calendar');

            // AGGIUNGI QUESTA ROUTE MANCANTE
            Route::get('/{assignment}', [Admin\AssignmentController::class, 'show'])->name('show');

            // Route per assegnazione flessibile
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

        // Document Management
        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/', [DocumentController::class, 'index'])->name('index');
            Route::post('/upload', [DocumentController::class, 'upload'])->name('upload');
            Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
            Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
        });

        // Admin Calendar - Management focus
        Route::get('calendar', [Admin\CalendarController::class, 'index'])->name('calendar.index');
    });

 // =================================================================
// NOTIFICATIONS SYSTEM ROUTES
// =================================================================
Route::middleware(['admin_or_superadmin'])->group(function () {

    // Notifications Management
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [Admin\NotificationController::class, 'index'])->name('index');
        Route::get('/stats', [Admin\NotificationController::class, 'stats'])->name('stats');
        Route::get('/send-assignment', [Admin\NotificationController::class, 'sendAssignmentForm'])->name('send-assignment');
        Route::post('/send-assignment', [Admin\NotificationController::class, 'sendAssignment'])->name('send-assignment.post');
        Route::get('/{notification}', [Admin\NotificationController::class, 'show'])->name('show');
        Route::delete('/{notification}', [Admin\NotificationController::class, 'destroy'])->name('destroy');
        Route::post('/{notification}/retry', [Admin\NotificationController::class, 'retry'])->name('retry');
    });

    // Letter Templates Management
    Route::prefix('letter-templates')->name('letter-templates.')->group(function () {
        Route::get('/', [Admin\LetterTemplateController::class, 'index'])->name('index');
        Route::get('/create', [Admin\LetterTemplateController::class, 'create'])->name('create');
        Route::post('/', [Admin\LetterTemplateController::class, 'store'])->name('store');
        Route::get('/{template}', [Admin\LetterTemplateController::class, 'show'])->name('show');
        Route::get('/{template}/edit', [Admin\LetterTemplateController::class, 'edit'])->name('edit');
        Route::put('/{template}', [Admin\LetterTemplateController::class, 'update'])->name('update');
        Route::delete('/{template}', [Admin\LetterTemplateController::class, 'destroy'])->name('destroy');
        Route::post('/{template}/duplicate', [Admin\LetterTemplateController::class, 'duplicate'])->name('duplicate');
        Route::get('/{template}/preview', [Admin\LetterTemplateController::class, 'preview'])->name('preview');
      Route::post('/{template}/toggle-active', [Admin\LetterTemplateController::class, 'toggleActive'])->name('toggle-active');
    Route::post('/{template}/set-default', [Admin\LetterTemplateController::class, 'setDefault'])->name('set-default');
  });

    // Institutional Emails Management
    Route::prefix('institutional-emails')->name('institutional-emails.')->group(function () {
        Route::get('/', [Admin\InstitutionalEmailController::class, 'index'])->name('index');
        Route::get('/create', [Admin\InstitutionalEmailController::class, 'create'])->name('create');
        Route::post('/', [Admin\InstitutionalEmailController::class, 'store'])->name('store');
        Route::get('/{email}', [Admin\InstitutionalEmailController::class, 'show'])->name('show');
        Route::get('/{email}/edit', [Admin\InstitutionalEmailController::class, 'edit'])->name('edit');
        Route::put('/{email}', [Admin\InstitutionalEmailController::class, 'update'])->name('update');
        Route::delete('/{email}', [Admin\InstitutionalEmailController::class, 'destroy'])->name('destroy');
        Route::post('/{email}/test', [Admin\InstitutionalEmailController::class, 'test'])->name('test');
     Route::post('/{email}/toggle-active', [Admin\InstitutionalEmailController::class, 'toggleActive'])->name('toggle-active');
    Route::post('/bulk-action', [Admin\InstitutionalEmailController::class, 'bulkAction'])->name('bulk-action');
    Route::get('/export', [Admin\InstitutionalEmailController::class, 'export'])->name('export');
   });

    // Tournament Notification Routes (for sending notifications from tournament pages)
    Route::prefix('tournaments/{tournament}')->name('tournaments.')->group(function () {
        Route::get('/send-notification', [Admin\TournamentNotificationController::class, 'show'])->name('send-notification');
        Route::post('/send-notification', [Admin\TournamentNotificationController::class, 'send'])->name('send-notification.post');
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
        // Route::get('/profile/edit', [Referee\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [Referee\ProfileController::class, 'update'])->name('profile.update');
        Route::get('profile', [Referee\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [Referee\ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [Referee\ProfileController::class, 'updatePassword'])->name('profile.update-password');

        // Availability Management - SEZIONE UNIFICATA E CORRETTA
        Route::prefix('availability')->name('availability.')->group(function () {
            // Views
            Route::get('/', [Referee\AvailabilityController::class, 'index'])->name('index');
            Route::get('/calendar', [Referee\AvailabilityController::class, 'calendar'])->name('calendar');

            // Actions
            Route::post('/save', [Referee\AvailabilityController::class, 'save'])->name('save'); // ← AGGIUNTA MANCANTE
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
    });
// =================================================================
// NOTIFICATION ROUTE
// Inserire nella sezione Admin routes (middleware admin_or_superadmin)
// =================================================================

// NOTIFICHE - Gestione completa
Route::prefix('notifications')->name('notifications.')->group(function () {

    // Visualizzazione notifiche
    Route::get('/', [Admin\NotificationController::class, 'index'])->name('index');
    Route::get('/{notification}', [Admin\NotificationController::class, 'show'])->name('show');

    // Gestione notifiche
    Route::post('/{notification}/resend', [Admin\NotificationController::class, 'resend'])->name('resend');
    Route::post('/{notification}/cancel', [Admin\NotificationController::class, 'cancel'])->name('cancel');

    // Statistiche e report
    Route::get('/stats/dashboard', [Admin\NotificationController::class, 'stats'])->name('stats');
    Route::get('/export/csv', [Admin\NotificationController::class, 'exportCsv'])->name('export');
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
        // API Calendar - differentiate by user_type parameter
        Route::get('calendar/events', [Api\CalendarController::class, 'index'])->name('calendar.events');

        // Other API routes...
        Route::get('tournaments/search', [Api\TournamentController::class, 'search'])->name('tournaments.search');
        Route::get('referees/search', [Api\RefereeController::class, 'search'])->name('referees.search');
    });
});
// =================================================================
// ROUTE API per JavaScript (da aggiungere nella sezione API)
// =================================================================

Route::prefix('api')->middleware(['auth'])->group(function () {

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
    })->name('api.institutional-emails');

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
    })->name('api.letter-templates');

    // API per statistiche notifiche
    Route::get('/notifications/stats/{days?}', function ($days = 30) {
        return \App\Models\Notification::getStatistics($days);
    })->name('api.notification-stats');
});

// // =================================================================
// // PUBLIC ROUTES - Tournament Calendar
// // =================================================================
// Route::middleware(['auth'])->group(function () {
//     // PUBLIC TOURNAMENT ROUTES
//     Route::get('tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
//     Route::get('tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');

//     // PUBLIC CALENDAR - View Only Focus
//     Route::get('tournaments/calendar', [TournamentController::class, 'calendar'])->name('tournaments.calendar');
// });


// =================================================================
// PUBLIC ROUTES (No authentication required)
// =================================================================

// Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0')
    ]);
})->name('health');

// =================================================================
// API ROUTES - Calendar Data (for future AJAX)
// =================================================================
Route::middleware(['auth'])->prefix('api')->name('api.')->group(function () {
    // Calendar Events API (for real-time updates)
    Route::get('calendar/admin', [Api\CalendarController::class, 'adminEvents'])->name('calendar.admin');
    Route::get('calendar/referee', [Api\CalendarController::class, 'refereeEvents'])->name('calendar.referee');
    Route::get('calendar/public', [Api\CalendarController::class, 'publicEvents'])->name('calendar.public');

    // Toggle availability API (for AJAX toggle)
    Route::post('availability/toggle', [Api\AvailabilityController::class, 'toggle'])->name('availability.toggle');
});

// =================================================================
// FALLBACK ROUTE
// =================================================================
Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});
