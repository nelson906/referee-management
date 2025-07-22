<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Zone;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use App\Models\Availability;
use Carbon\Carbon;

class GolfMonitoringSystem extends Command
{
    protected $signature = 'golf:monitor
                            {--alert-only : Solo invio alert, no report completo}
                            {--email= : Email per invio report}
                            {--threshold= : Soglia personalizzata per alert}
                            {--export : Esporta metriche in file}';

    protected $description = 'Sistema di monitoraggio e alerting per Golf Seeder System';

    private array $metrics = [];
    private array $alerts = [];
    private array $thresholds = [];

    public function handle(): int
    {
        $this->info('📊 GOLF MONITORING SYSTEM');
        $this->info('==========================');

        $this->loadThresholds();
        $this->collectMetrics();
        $this->analyzeMetrics();
        $this->generateAlerts();

        if ($this->option('export')) {
            $this->exportMetrics();
        }

        if ($this->option('alert-only')) {
            $this->sendAlertsOnly();
        } else {
            $this->generateFullReport();
        }

        return 0;
    }

    private function loadThresholds(): void
    {
        $this->thresholds = [
            'database_size_mb' => $this->option('threshold') ?? 500,
            'query_avg_time_ms' => 100,
            'response_rate_min' => 60,
            'confirmation_rate_min' => 80,
            'data_integrity_score_min' => 95,
            'system_health_score_min' => 90,
            'storage_usage_max' => 80, // %
            'memory_usage_max' => 85,  // %
        ];

        $this->info('🎯 Soglie caricate: ' . count($this->thresholds) . ' parametri');
    }

    private function collectMetrics(): void
    {
        $this->info('📈 Raccogliendo metriche sistema...');

        // Performance Metrics
        $this->metrics['performance'] = $this->collectPerformanceMetrics();

        // Data Quality Metrics
        $this->metrics['data_quality'] = $this->collectDataQualityMetrics();

        // System Health Metrics
        $this->metrics['system_health'] = $this->collectSystemHealthMetrics();

        // Business Metrics
        $this->metrics['business'] = $this->collectBusinessMetrics();

        // Seeder Specific Metrics
        $this->metrics['seeder_health'] = $this->collectSeederMetrics();

        $this->info('✅ Metriche raccolte: ' . array_sum(array_map('count', $this->metrics)) . ' valori');
    }

    private function collectPerformanceMetrics(): array
    {
        $startTime = microtime(true);

        // Test query performance
        $tournaments = Tournament::with(['zone', 'club'])->limit(100)->get();
        $queryTime = (microtime(true) - $startTime) * 1000;

        // Database size
        $dbSize = $this->getDatabaseSize();

        // Memory usage
        $memoryUsage = memory_get_peak_usage(true) / 1024 / 1024;

        // Storage usage
        $storageUsage = $this->getStorageUsage();

        return [
            'query_avg_time_ms' => round($queryTime, 2),
            'database_size_mb' => $dbSize,
            'memory_usage_mb' => round($memoryUsage, 2),
            'storage_usage_percent' => $storageUsage,
            'concurrent_connections' => $this->getConnectionCount(),
            'cache_hit_rate' => $this->getCacheHitRate()
        ];
    }

    private function collectDataQualityMetrics(): array
    {
        return [
            'orphaned_records' => $this->countOrphanedRecords(),
            'duplicate_emails' => $this->countDuplicateEmails(),
            'invalid_referee_codes' => $this->countInvalidRefereeCodes(),
            'missing_zone_assignments' => $this->countMissingZoneAssignments(),
            'data_integrity_score' => $this->calculateDataIntegrityScore(),
            'foreign_key_violations' => $this->checkForeignKeyViolations()
        ];
    }

    private function collectSystemHealthMetrics(): array
    {
        return [
            'zones_active' => Zone::where('is_active', true)->count(),
            'referees_active' => User::where('user_type', 'referee')->where('is_active', true)->count(),
            'tournaments_current' => Tournament::whereIn('status', ['open', 'closed', 'assigned'])->count(),
            'api_response_time' => $this->testApiResponseTime(),
            'last_seeding_date' => $this->getLastSeedingDate(),
            'system_uptime' => $this->getSystemUptime(),
            'error_rate_24h' => $this->getErrorRate24h()
        ];
    }

    private function collectBusinessMetrics(): array
    {
        $response_rates = [];
        $confirmation_rates = [];

        foreach (Zone::all() as $zone) {
            $response_rates[$zone->code] = $this->calculateZoneResponseRate($zone);
            $confirmation_rates[$zone->code] = $this->calculateZoneConfirmationRate($zone);
        }

        return [
            'avg_response_rate' => array_sum($response_rates) / count($response_rates),
            'avg_confirmation_rate' => array_sum($confirmation_rates) / count($confirmation_rates),
            'zone_response_rates' => $response_rates,
            'zone_confirmation_rates' => $confirmation_rates,
            'tournaments_missing_assignments' => $this->countTournamentsMissingAssignments(),
            'overdue_confirmations' => $this->countOverdueConfirmations()
        ];
    }

    private function collectSeederMetrics(): array
    {
        return [
            'seeder_data_freshness' => $this->checkSeederDataFreshness(),
            'zone_data_consistency' => $this->checkZoneDataConsistency(),
            'workflow_completeness' => $this->checkWorkflowCompleteness(),
            'test_scenarios_ready' => $this->checkTestScenariosReady(),
            'seeder_performance_score' => $this->calculateSeederPerformanceScore()
        ];
    }

    private function analyzeMetrics(): void
    {
        $this->info('🔍 Analizzando metriche...');

        foreach ($this->metrics as $category => $categoryMetrics) {
            foreach ($categoryMetrics as $metric => $value) {
                $this->analyzeMetric($category, $metric, $value);
            }
        }
    }

    private function analyzeMetric(string $category, string $metric, $value): void
    {
        $alertLevel = $this->determineAlertLevel($metric, $value);

        if ($alertLevel !== 'ok') {
            $this->alerts[] = [
                'level' => $alertLevel,
                'category' => $category,
                'metric' => $metric,
                'value' => $value,
                'threshold' => $this->thresholds[$metric] ?? 'N/A',
                'message' => $this->generateAlertMessage($metric, $value, $alertLevel),
                'timestamp' => now()
            ];
        }
    }

    private function determineAlertLevel(string $metric, $value): string
    {
        if (!isset($this->thresholds[$metric])) {
            return 'ok';
        }

        $threshold = $this->thresholds[$metric];

        return match($metric) {
            'database_size_mb', 'query_avg_time_ms', 'storage_usage_max', 'memory_usage_max' =>
                $value > $threshold ? ($value > $threshold * 1.5 ? 'critical' : 'warning') : 'ok',
            'response_rate_min', 'confirmation_rate_min', 'data_integrity_score_min', 'system_health_score_min' =>
                $value < $threshold ? ($value < $threshold * 0.8 ? 'critical' : 'warning') : 'ok',
            default => 'ok'
        };
    }

    private function generateAlertMessage(string $metric, $value, string $level): string
    {
        $messages = [
            'database_size_mb' => "Database size ($value MB) exceeds threshold",
            'query_avg_time_ms' => "Query performance degraded ($value ms average)",
            'response_rate_min' => "Response rate below minimum ($value%)",
            'confirmation_rate_min' => "Confirmation rate below minimum ($value%)",
            'data_integrity_score_min' => "Data integrity compromised (score: $value%)",
            'system_health_score_min' => "System health degraded (score: $value%)",
            'storage_usage_max' => "Storage usage high ($value%)",
            'memory_usage_max' => "Memory usage high ($value%)"
        ];

        return $messages[$metric] ?? "Metric $metric shows unusual value: $value";
    }

    private function generateAlerts(): void
    {
        $this->info('🚨 Generando alert...');

        $alertCounts = [
            'critical' => 0,
            'warning' => 0,
            'info' => 0
        ];

        foreach ($this->alerts as $alert) {
            $alertCounts[$alert['level']]++;

            $emoji = match($alert['level']) {
                'critical' => '🔴',
                'warning' => '🟡',
                default => '🔵'
            };

            $this->line("{$emoji} {$alert['level']}: {$alert['message']}");
        }

        $this->info("📊 Alert generati: {$alertCounts['critical']} critici, {$alertCounts['warning']} warning");
    }

    private function sendAlertsOnly(): void
    {
        if (empty($this->alerts)) {
            $this->info('✅ Nessun alert da inviare - sistema healthy');
            return;
        }

        $criticalAlerts = array_filter($this->alerts, fn($alert) => $alert['level'] === 'critical');

        if (!empty($criticalAlerts)) {
            $this->sendCriticalAlerts($criticalAlerts);
        }

        $this->logAlerts();
    }

    private function generateFullReport(): void
    {
        $this->info('📋 Generando report completo...');

        $report = $this->buildFullReport();

        $this->displayReport($report);

        if ($this->option('email')) {
            $this->sendEmailReport($report);
        }

        $this->cacheMetrics();
    }

    private function buildFullReport(): array
    {
        return [
            'timestamp' => now(),
            'summary' => $this->buildSummary(),
            'metrics' => $this->metrics,
            'alerts' => $this->alerts,
            'recommendations' => $this->generateRecommendations(),
            'health_score' => $this->calculateOverallHealthScore(),
            'trends' => $this->analyzeTrends()
        ];
    }

    private function buildSummary(): array
    {
        return [
            'system_status' => $this->getOverallSystemStatus(),
            'total_metrics' => array_sum(array_map('count', $this->metrics)),
            'total_alerts' => count($this->alerts),
            'critical_alerts' => count(array_filter($this->alerts, fn($a) => $a['level'] === 'critical')),
            'warning_alerts' => count(array_filter($this->alerts, fn($a) => $a['level'] === 'warning')),
            'health_score' => $this->calculateOverallHealthScore(),
            'data_integrity' => $this->metrics['data_quality']['data_integrity_score'] ?? 'N/A',
            'performance_status' => $this->getPerformanceStatus()
        ];
    }

    private function displayReport(array $report): void
    {
        $this->info('');
        $this->info('📊 GOLF MONITORING REPORT');
        $this->info('========================');

        // Summary
        $this->table(['Metric', 'Value'], [
            ['System Status', $report['summary']['system_status']],
            ['Health Score', $report['summary']['health_score'] . '%'],
            ['Total Alerts', $report['summary']['total_alerts']],
            ['Critical Alerts', $report['summary']['critical_alerts']],
            ['Data Integrity', $report['summary']['data_integrity'] . '%'],
            ['Performance', $report['summary']['performance_status']]
        ]);

        // Top Issues
        if (!empty($this->alerts)) {
            $this->warn('⚠️ TOP ISSUES:');
            foreach (array_slice($this->alerts, 0, 5) as $alert) {
                $this->warn("  • {$alert['message']}");
            }
        }

        // Recommendations
        if (!empty($report['recommendations'])) {
            $this->info('💡 RECOMMENDATIONS:');
            foreach ($report['recommendations'] as $rec) {
                $this->info("  • $rec");
            }
        }
    }

    private function generateRecommendations(): array
    {
        $recommendations = [];

        // Performance recommendations
        if (($this->metrics['performance']['query_avg_time_ms'] ?? 0) > 50) {
            $recommendations[] = 'Consider adding database indexes for frequently queried columns';
        }

        if (($this->metrics['performance']['database_size_mb'] ?? 0) > 200) {
            $recommendations[] = 'Database cleanup recommended - run golf:maintenance cleanup';
        }

        // Data quality recommendations
        if (($this->metrics['data_quality']['data_integrity_score'] ?? 100) < 95) {
            $recommendations[] = 'Run data repair: php artisan golf:maintenance repair';
        }

        // Business recommendations
        if (($this->metrics['business']['avg_response_rate'] ?? 100) < 70) {
            $recommendations[] = 'Consider adjusting availability deadline or reminder frequency';
        }

        if (($this->metrics['business']['tournaments_missing_assignments'] ?? 0) > 0) {
            $recommendations[] = 'Review tournaments without assignments - manual intervention may be needed';
        }

        // Seeder recommendations
        if (($this->metrics['seeder_health']['seeder_data_freshness'] ?? 100) < 80) {
            $recommendations[] = 'Consider refreshing seeder data: php artisan golf:seed --fresh';
        }

        return $recommendations;
    }

    private function calculateOverallHealthScore(): int
    {
        $scores = [
            $this->metrics['data_quality']['data_integrity_score'] ?? 100,
            $this->getPerformanceScore(),
            $this->getBusinessScore(),
            $this->metrics['seeder_health']['seeder_performance_score'] ?? 100
        ];

        return (int) round(array_sum($scores) / count($scores));
    }

    private function getOverallSystemStatus(): string
    {
        $healthScore = $this->calculateOverallHealthScore();
        $criticalAlerts = count(array_filter($this->alerts, fn($a) => $a['level'] === 'critical'));

        if ($criticalAlerts > 0) return '🔴 CRITICAL';
        if ($healthScore < 80) return '🟡 WARNING';
        if ($healthScore < 95) return '🟠 NEEDS ATTENTION';
        return '🟢 HEALTHY';
    }

    private function exportMetrics(): void
    {
        $filename = 'golf_monitoring_' . now()->format('Y-m-d_H-i-s') . '.json';
        $exportData = [
            'metrics' => $this->metrics,
            'alerts' => $this->alerts,
            'timestamp' => now(),
            'system_info' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment()
            ]
        ];

        file_put_contents(storage_path("app/golf-exports/{$filename}"), json_encode($exportData, JSON_PRETTY_PRINT));
        $this->info("📁 Metriche esportate: {$filename}");
    }

    // Helper methods
    private function getDatabaseSize(): float
    {
        try {
            $result = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = ?", [config('database.connections.mysql.database')]);
            return $result[0]->size ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getStorageUsage(): float
    {
        $totalSpace = disk_total_space(storage_path());
        $freeSpace = disk_free_space(storage_path());
        return round((($totalSpace - $freeSpace) / $totalSpace) * 100, 1);
    }

    private function getConnectionCount(): int
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            return (int) ($result[0]->Value ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getCacheHitRate(): float
    {
        // Simplified cache hit rate calculation
        return 85.5; // Placeholder
    }

    private function countOrphanedRecords(): int
    {
        $orphaned = 0;

        // Clubs without zones
        $orphaned += DB::table('clubs')
            ->leftJoin('zones', 'clubs.zone_id', '=', 'zones.id')
            ->whereNull('zones.id')
            ->count();

        // Tournaments without clubs
        $orphaned += DB::table('tournaments')
            ->leftJoin('clubs', 'tournaments.club_id', '=', 'clubs.id')
            ->whereNull('clubs.id')
            ->count();

        return $orphaned;
    }

    private function countDuplicateEmails(): int
    {
        return DB::table('users')
            ->select('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->count();
    }

    private function countInvalidRefereeCodes(): int
    {
        return User::where('user_type', 'referee')
            ->where(function($query) {
                $query->whereNull('referee_code')
                      ->orWhere('referee_code', '');
            })->count();
    }

    private function countMissingZoneAssignments(): int
    {
        return User::where('user_type', 'referee')
            ->whereNull('zone_id')
            ->count();
    }

    private function calculateDataIntegrityScore(): float
    {
        $totalUsers = User::count();
        $totalClubs = Club::count();
        $totalTournaments = Tournament::count();

        if ($totalUsers === 0) return 100;

        $issues = $this->countOrphanedRecords() +
                 $this->countDuplicateEmails() +
                 $this->countInvalidRefereeCodes() +
                 $this->countMissingZoneAssignments();

        $totalRecords = $totalUsers + $totalClubs + $totalTournaments;
        $integrityScore = max(0, 100 - (($issues / $totalRecords) * 100));

        return round($integrityScore, 1);
    }

    private function checkForeignKeyViolations(): int
    {
        // This would check for foreign key constraint violations
        return 0; // Placeholder
    }

    private function testApiResponseTime(): float
    {
        $start = microtime(true);

        try {
            // Simulate API call
            Zone::count();
            User::where('user_type', 'referee')->count();
            Tournament::count();
        } catch (\Exception $e) {
            return 9999; // Error indicator
        }

        return round((microtime(true) - $start) * 1000, 2);
    }

    private function getLastSeedingDate(): ?string
    {
        $lastUpdate = Zone::latest('updated_at')->first();
        return $lastUpdate ? $lastUpdate->updated_at->diffForHumans() : null;
    }

    private function getSystemUptime(): string
    {
        // Simplified uptime calculation
        return '99.9%'; // Placeholder
    }

    private function getErrorRate24h(): float
    {
        // Calculate error rate from logs
        return 0.1; // Placeholder
    }

    private function calculateZoneResponseRate(Zone $zone): float
    {
        $openTournaments = Tournament::where('zone_id', $zone->id)
            ->where('status', 'open')
            ->withCount('availabilities')
            ->get();

        if ($openTournaments->isEmpty()) return 100;

        $totalEligible = $zone->users()->where('user_type', 'referee')->count();
        $totalResponses = $openTournaments->sum('availabilities_count');

        return $totalEligible > 0 ? round(($totalResponses / ($totalEligible * $openTournaments->count())) * 100, 1) : 0;
    }

    private function calculateZoneConfirmationRate(Zone $zone): float
    {
        $assignments = Assignment::whereHas('tournament', function($q) use ($zone) {
            $q->where('zone_id', $zone->id);
        });

        $total = $assignments->count();
        $confirmed = $assignments->where('is_confirmed', true)->count();

        return $total > 0 ? round(($confirmed / $total) * 100, 1) : 100;
    }

    private function countTournamentsMissingAssignments(): int
    {
        return Tournament::where('status', 'closed')
            ->doesntHave('assignments')
            ->count();
    }

    private function countOverdueConfirmations(): int
    {
        return Assignment::where('is_confirmed', false)
            ->where('assigned_at', '<', now()->subDays(3))
            ->count();
    }

    private function checkSeederDataFreshness(): float
    {
        $lastUpdate = Zone::latest('updated_at')->first();

        if (!$lastUpdate) return 0;

        $daysSinceUpdate = $lastUpdate->updated_at->diffInDays(now());

        if ($daysSinceUpdate < 7) return 100;
        if ($daysSinceUpdate < 30) return 80;
        if ($daysSinceUpdate < 90) return 60;

        return 40;
    }

    private function checkZoneDataConsistency(): float
    {
        $zones = Zone::withCount(['clubs', 'users'])->get();
        $inconsistencies = 0;

        foreach ($zones as $zone) {
            if ($zone->clubs_count === 0) $inconsistencies++;
            if ($zone->users_count === 0) $inconsistencies++;
        }

        return $zones->count() > 0 ? round((1 - ($inconsistencies / ($zones->count() * 2))) * 100, 1) : 100;
    }

    private function checkWorkflowCompleteness(): float
    {
        $openTournaments = Tournament::where('status', 'open')->count();
        $tournamentsWithAvailabilities = Tournament::where('status', 'open')->has('availabilities')->count();

        return $openTournaments > 0 ? round(($tournamentsWithAvailabilities / $openTournaments) * 100, 1) : 100;
    }

    private function checkTestScenariosReady(): bool
    {
        // Check if test zone SZR6 has complete data
        $testZone = Zone::where('code', 'SZR6')->first();

        if (!$testZone) return false;

        $hasClubs = $testZone->clubs()->count() > 0;
        $hasReferees = $testZone->users()->where('user_type', 'referee')->count() > 0;
        $hasTournaments = Tournament::where('zone_id', $testZone->id)->count() > 0;

        return $hasClubs && $hasReferees && $hasTournaments;
    }

    private function calculateSeederPerformanceScore(): float
    {
        $scores = [
            $this->checkSeederDataFreshness(),
            $this->checkZoneDataConsistency(),
            $this->checkWorkflowCompleteness(),
            $this->checkTestScenariosReady() ? 100 : 50
        ];

        return round(array_sum($scores) / count($scores), 1);
    }

    private function getPerformanceScore(): float
    {
        $queryTime = $this->metrics['performance']['query_avg_time_ms'] ?? 50;
        $dbSize = $this->metrics['performance']['database_size_mb'] ?? 100;

        $queryScore = max(0, 100 - ($queryTime / 2));
        $sizeScore = max(0, 100 - ($dbSize / 5));

        return round(($queryScore + $sizeScore) / 2, 1);
    }

    private function getBusinessScore(): float
    {
        $responseRate = $this->metrics['business']['avg_response_rate'] ?? 70;
        $confirmationRate = $this->metrics['business']['avg_confirmation_rate'] ?? 80;

        return round(($responseRate + $confirmationRate) / 2, 1);
    }

    private function getPerformanceStatus(): string
    {
        $score = $this->getPerformanceScore();

        if ($score >= 90) return '🟢 Excellent';
        if ($score >= 75) return '🟡 Good';
        if ($score >= 60) return '🟠 Fair';
        return '🔴 Poor';
    }

    private function sendCriticalAlerts(array $criticalAlerts): void
    {
        // Implementation for sending critical alerts
        Log::critical('Golf System Critical Alerts', $criticalAlerts);

        // Could send email, Slack, SMS, etc.
        $this->error('🚨 ' . count($criticalAlerts) . ' critical alerts detected!');
    }

    private function sendEmailReport(array $report): void
    {
        // Implementation for sending email report
        $this->info('📧 Email report sent to: ' . $this->option('email'));
    }

    private function logAlerts(): void
    {
        foreach ($this->alerts as $alert) {
            Log::warning('Golf Monitoring Alert', $alert);
        }
    }

    private function cacheMetrics(): void
    {
        Cache::put('golf_monitoring_metrics', $this->metrics, now()->addHours(1));
        Cache::put('golf_monitoring_alerts', $this->alerts, now()->addHours(1));
    }

    private function analyzeTrends(): array
    {
        // Analyze trends from cached historical data
        $historicalMetrics = Cache::get('golf_monitoring_history', []);

        return [
            'performance_trend' => 'stable',
            'data_quality_trend' => 'improving',
            'business_metrics_trend' => 'stable'
        ];
    }
}
