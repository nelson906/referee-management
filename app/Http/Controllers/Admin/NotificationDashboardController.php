<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 📊 Controller per Dashboard Notifiche Torneo
 */
class NotificationDashboardController extends Controller
{
    /**
     * 📊 Dashboard principale notifiche
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $zoneId = $user->user_type === 'admin' ? $user->zone_id : null;

        // Statistiche principali
        $stats = $this->getDashboardStats($zoneId);

        // Grafici e trends
        $charts = $this->getChartData($zoneId);

        // Notifiche recenti
        $recentNotifications = $this->getRecentNotifications($zoneId, 10);

        // Tornei urgenti da notificare
        $urgentTournaments = $this->getUrgentTournaments($zoneId);

        // Notifiche fallite da gestire
        $failedNotifications = $this->getFailedNotifications($zoneId, 5);

        return view('admin.notifications.dashboard', compact(
            'stats',
            'charts',
            'recentNotifications',
            'urgentTournaments',
            'failedNotifications'
        ));
    }

    /**
     * 📊 Statistiche dashboard (con cache)
     */
    private function getDashboardStats(?int $zoneId): array
    {
        $cacheKey = "notification_dashboard_stats_" . ($zoneId ?? 'global');

        return Cache::remember($cacheKey, 300, function() use ($zoneId) {
            $baseQuery = TournamentNotification::query();
            if ($zoneId) {
                $baseQuery->whereHas('tournament', function($q) use ($zoneId) {
                    $q->where('zone_id', $zoneId);
                });
            }

            // Statistiche base
            $totalNotifications = $baseQuery->count();
            $todayNotifications = $baseQuery->whereDate('sent_at', today())->count();
            $thisWeekNotifications = $baseQuery->whereBetween('sent_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count();
            $thisMonthNotifications = $baseQuery->whereMonth('sent_at', now()->month)->count();

            // Calcolo tasso successo
            $successfulNotifications = $baseQuery->where('status', 'sent')->count();
            $successRate = $totalNotifications > 0
                ? round(($successfulNotifications / $totalNotifications) * 100, 1)
                : 0;

            // Destinatari raggiunti
            $totalRecipients = $baseQuery->sum('total_recipients');
            $avgRecipientsPerTournament = $totalNotifications > 0
                ? round($totalRecipients / $totalNotifications, 1)
                : 0;

            // Tornei da notificare
            $pendingQuery = Tournament::readyForNotification();
            if ($zoneId) {
                $pendingQuery->where('zone_id', $zoneId);
            }
            $pendingTournaments = $pendingQuery->count();

            // Notifiche fallite
            $failedNotifications = $baseQuery->where('status', 'failed')->count();

            return [
                'total_notifications' => $totalNotifications,
                'today_notifications' => $todayNotifications,
                'week_notifications' => $thisWeekNotifications,
                'month_notifications' => $thisMonthNotifications,
                'success_rate' => $successRate,
                'total_recipients' => $totalRecipients,
                'avg_recipients' => $avgRecipientsPerTournament,
                'pending_tournaments' => $pendingTournaments,
                'failed_notifications' => $failedNotifications,
                'efficiency_score' => $this->calculateEfficiencyScore($successRate, $avgRecipientsPerTournament)
            ];
        });
    }

    /**
     * 📈 Dati per grafici dashboard
     */
    private function getChartData(?int $zoneId): array
    {
        $cacheKey = "notification_dashboard_charts_" . ($zoneId ?? 'global');

        return Cache::remember($cacheKey, 600, function() use ($zoneId) {
            // Trend ultimi 30 giorni
            $dailyTrend = $this->getDailyTrend($zoneId);

            // Distribuzione per tipo destinatario
            $recipientDistribution = $this->getRecipientDistribution($zoneId);

            // Distribuzione per stato
            $statusDistribution = $this->getStatusDistribution($zoneId);

            // Performance per zona (solo per super admin)
            $zonePerformance = $zoneId ? [] : $this->getZonePerformance();

            // Top template utilizzati
            $topTemplates = $this->getTopTemplates($zoneId);

            return [
                'daily_trend' => $dailyTrend,
                'recipient_distribution' => $recipientDistribution,
                'status_distribution' => $statusDistribution,
                'zone_performance' => $zonePerformance,
                'top_templates' => $topTemplates
            ];
        });
    }

    /**
     * 📅 Trend giornaliero ultimi 30 giorni
     */
    private function getDailyTrend(?int $zoneId): array
    {
        $query = TournamentNotification::select(
                DB::raw('DATE(sent_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_recipients) as recipients')
            )
            ->where('sent_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date');

        if ($zoneId) {
            $query->whereHas('tournament', function($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        }

        $data = $query->get();

        // Riempi giorni mancanti con zero
        $result = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayData = $data->firstWhere('date', $date);

            $result[] = [
                'date' => $date,
                'count' => $dayData ? $dayData->count : 0,
                'recipients' => $dayData ? $dayData->recipients : 0
            ];
        }

        return $result;
    }

    /**
     * 📊 Distribuzione per tipo destinatario
     */
    private function getRecipientDistribution(?int $zoneId): array
    {
        $query = Notification::select('recipient_type', DB::raw('COUNT(*) as count'))
                            ->where('created_at', '>=', now()->subDays(30))
                            ->groupBy('recipient_type');

        if ($zoneId) {
            $query->whereHas('tournament', function($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        }

        $data = $query->get();

        return [
            'club' => $data->firstWhere('recipient_type', 'club')->count ?? 0,
            'referee' => $data->firstWhere('recipient_type', 'referee')->count ?? 0,
            'institutional' => $data->firstWhere('recipient_type', 'institutional')->count ?? 0
        ];
    }

    /**
     * 📊 Distribuzione per stato
     */
    private function getStatusDistribution(?int $zoneId): array
    {
        $query = TournamentNotification::select('status', DB::raw('COUNT(*) as count'))
                                     ->groupBy('status');

        if ($zoneId) {
            $query->whereHas('tournament', function($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        }

        $data = $query->get();

        return [
            'sent' => $data->firstWhere('status', 'sent')->count ?? 0,
            'partial' => $data->firstWhere('status', 'partial')->count ?? 0,
            'failed' => $data->firstWhere('status', 'failed')->count ?? 0,
            'pending' => $data->firstWhere('status', 'pending')->count ?? 0
        ];
    }

    /**
     * 🌍 Performance per zona (solo super admin)
     */
    private function getZonePerformance(): array
    {
        return TournamentNotification::select(
                'zones.name as zone_name',
                'zones.code as zone_code',
                DB::raw('COUNT(*) as total_notifications'),
                DB::raw('SUM(total_recipients) as total_recipients'),
                DB::raw('AVG(CASE WHEN status = "sent" THEN 1 ELSE 0 END) * 100 as success_rate')
            )
            ->join('tournaments', 'tournament_notifications.tournament_id', '=', 'tournaments.id')
            ->join('zones', 'tournaments.zone_id', '=', 'zones.id')
            ->where('tournament_notifications.sent_at', '>=', now()->subDays(30))
            ->groupBy('zones.id', 'zones.name', 'zones.code')
            ->orderBy('total_notifications', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 📄 Top template utilizzati
     */
    private function getTopTemplates(?int $zoneId): array
    {
        $query = TournamentNotification::select(
                DB::raw('JSON_UNQUOTE(JSON_EXTRACT(templates_used, "$.club")) as template_name'),
                DB::raw('COUNT(*) as usage_count')
            )
            ->whereNotNull('templates_used')
            ->where('sent_at', '>=', now()->subDays(30))
            ->groupBy('template_name')
            ->orderBy('usage_count', 'desc')
            ->limit(5);

        if ($zoneId) {
            $query->whereHas('tournament', function($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        }

        return $query->get()->toArray();
    }

    /**
     * 📧 Notifiche recenti
     */
    private function getRecentNotifications(?int $zoneId, int $limit): array
    {
        $query = TournamentNotification::with(['tournament.club', 'tournament.zone', 'sentBy'])
                                     ->orderBy('sent_at', 'desc')
                                     ->limit($limit);

        if ($zoneId) {
            $query->whereHas('tournament', function($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        }

        return $query->get()->map(function($notification) {
            return [
                'id' => $notification->id,
                'tournament_name' => $notification->tournament->name,
                'club_name' => $notification->tournament->club->name,
                'zone_code' => $notification->tournament->zone->code,
                'status' => $notification->status,
                'status_formatted' => $notification->status_formatted,
                'total_recipients' => $notification->total_recipients,
                'sent_at' => $notification->sent_at,
                'sent_at_human' => $notification->time_ago,
                'sent_by' => $notification->sentBy?->name ?? 'Sistema',
                'success_rate' => $notification->stats['success_rate']
            ];
        })->toArray();
    }

    /**
     * ⚠️ Tornei urgenti da notificare
     */
    private function getUrgentTournaments(?int $zoneId): array
    {
        $query = Tournament::readyForNotification()
                          ->with(['club', 'zone', 'assignments'])
                          ->where('start_date', '<=', now()->addDays(14))
                          ->orderBy('start_date');

        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }

        return $query->get()->map(function($tournament) {
            $daysUntilStart = now()->diffInDays($tournament->start_date, false);

            return [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'club_name' => $tournament->club->name,
                'zone_code' => $tournament->zone->code,
                'start_date' => $tournament->start_date,
                'days_until_start' => $daysUntilStart,
                'urgency_level' => $this->calculateUrgencyLevel($daysUntilStart),
                'assignments_count' => $tournament->assignments->count(),
                'can_send_notifications' => $tournament->canSendNotifications(),
                'blockers' => $tournament->getNotificationBlockers()
            ];
        })->toArray();
    }

    /**
     * ❌ Notifiche fallite da gestire
     */
    private function getFailedNotifications(?int $zoneId, int $limit): array
    {
        $query = TournamentNotification::where('status', 'failed')
                                     ->with(['tournament.club', 'tournament.zone'])
                                     ->orderBy('sent_at', 'desc')
                                     ->limit($limit);

        if ($zoneId) {
            $query->whereHas('tournament', function($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        }

        return $query->get()->map(function($notification) {
            return [
                'id' => $notification->id,
                'tournament_name' => $notification->tournament->name,
                'club_name' => $notification->tournament->club->name,
                'zone_code' => $notification->tournament->zone->code,
                'error_message' => $notification->error_message,
                'sent_at' => $notification->sent_at,
                'failed_recipients' => $notification->stats['total_failed'],
                'can_resend' => $notification->canBeResent(),
                'error_details' => $notification->getErrorDetails()
            ];
        })->toArray();
    }

    /**
     * 📊 Calcola score efficienza
     */
    private function calculateEfficiencyScore(float $successRate, float $avgRecipients): int
    {
        // Score basato su tasso successo (70%) e copertura (30%)
        $successScore = min($successRate, 100) * 0.7;
        $coverageScore = min($avgRecipients / 6 * 100, 100) * 0.3; // 6 = recipients ideali per torneo

        return round($successScore + $coverageScore);
    }

    /**
     * ⚠️ Calcola livello urgenza
     */
    private function calculateUrgencyLevel(int $daysUntilStart): string
    {
        return match(true) {
            $daysUntilStart <= 3 => 'critical',
            $daysUntilStart <= 7 => 'high',
            $daysUntilStart <= 14 => 'medium',
            default => 'low'
        };
    }

    /**
     * 📊 API endpoint per aggiornamento real-time
     */
    public function apiStats(Request $request)
    {
        $user = $request->user();
        $zoneId = $user->user_type === 'admin' ? $user->zone_id : null;

        return response()->json([
            'stats' => $this->getDashboardStats($zoneId),
            'last_updated' => now()->toISOString()
        ]);
    }

    /**
     * 📈 API endpoint per dati grafici
     */
    public function apiCharts(Request $request)
    {
        $user = $request->user();
        $zoneId = $user->user_type === 'admin' ? $user->zone_id : null;

        return response()->json([
            'charts' => $this->getChartData($zoneId),
            'last_updated' => now()->toISOString()
        ]);
    }

    /**
     * 🔄 Endpoint per refresh cache
     */
    public function refreshCache(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->user_type, ['super_admin', 'admin'])) {
            abort(403, 'Non autorizzato');
        }

        $zoneId = $user->user_type === 'admin' ? $user->zone_id : null;

        // Cancella cache specifiche
        $cacheKeys = [
            "notification_dashboard_stats_" . ($zoneId ?? 'global'),
            "notification_dashboard_charts_" . ($zoneId ?? 'global')
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        return response()->json([
            'message' => 'Cache aggiornata con successo',
            'refreshed_at' => now()->toISOString()
        ]);
    }

    /**
     * 📊 Widget notifiche per dashboard principale
     */
    public function widget(Request $request)
    {
        $user = $request->user();
        $zoneId = $user->user_type === 'admin' ? $user->zone_id : null;

        $quickStats = [
            'today_sent' => TournamentNotification::when($zoneId, function($q) use ($zoneId) {
                                return $q->whereHas('tournament', function($subQ) use ($zoneId) {
                                    $subQ->where('zone_id', $zoneId);
                                });
                            })
                            ->whereDate('sent_at', today())
                            ->sum('total_recipients'),

            'pending_tournaments' => Tournament::readyForNotification()
                                             ->when($zoneId, function($q) use ($zoneId) {
                                                 return $q->where('zone_id', $zoneId);
                                             })
                                             ->count(),

            'failed_this_week' => TournamentNotification::when($zoneId, function($q) use ($zoneId) {
                                                          return $q->whereHas('tournament', function($subQ) use ($zoneId) {
                                                              $subQ->where('zone_id', $zoneId);
                                                          });
                                                      })
                                                      ->where('status', 'failed')
                                                      ->whereBetween('sent_at', [now()->startOfWeek(), now()->endOfWeek()])
                                                      ->count(),

            'success_rate_week' => $this->getWeekSuccessRate($zoneId)
        ];

        // Azioni rapide disponibili
        $quickActions = [
            [
                'title' => 'Invia Notifiche Massivo',
                'url' => route('admin.tournaments.bulk-notifications'),
                'icon' => 'fas fa-paper-plane',
                'class' => 'btn-primary',
                'enabled' => $quickStats['pending_tournaments'] > 0
            ],
            [
                'title' => 'Gestisci Fallimenti',
                'url' => route('admin.tournament-notifications.index', ['status' => 'failed']),
                'icon' => 'fas fa-exclamation-triangle',
                'class' => 'btn-warning',
                'enabled' => $quickStats['failed_this_week'] > 0
            ],
            [
                'title' => 'Report Completo',
                'url' => route('admin.notifications.dashboard'),
                'icon' => 'fas fa-chart-bar',
                'class' => 'btn-info',
                'enabled' => true
            ]
        ];

        return view('admin.dashboard.widgets.notifications', compact('quickStats', 'quickActions'));
    }

    /**
     * 📈 Calcola tasso successo settimanale
     */
    private function getWeekSuccessRate(?int $zoneId): float
    {
        $query = TournamentNotification::whereBetween('sent_at', [now()->startOfWeek(), now()->endOfWeek()]);

        if ($zoneId) {
            $query->whereHas('tournament', function($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        }

        $total = $query->count();
        if ($total === 0) return 0;

        $successful = $query->where('status', 'sent')->count();

        return round(($successful / $total) * 100, 1);
    }
}
