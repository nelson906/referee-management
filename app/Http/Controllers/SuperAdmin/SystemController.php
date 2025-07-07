<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    /**
     * Display system logs.
     */
    public function logs(Request $request)
    {
        $logFile = $request->get('file', 'laravel.log');
        $logPath = storage_path("logs/{$logFile}");

        $logs = [];
        $availableFiles = [];

        // Get available log files
        $logFiles = File::files(storage_path('logs'));
        foreach ($logFiles as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                $availableFiles[] = $file->getFilename();
            }
        }

        // Read log file if exists
        if (File::exists($logPath)) {
            $content = File::get($logPath);
            $lines = array_reverse(explode("\n", $content));

            foreach ($lines as $line) {
                if (trim($line) && preg_match('/^\[(.*?)\].*?(ERROR|WARNING|INFO|DEBUG).*?: (.*)/', $line, $matches)) {
                    $logs[] = [
                        'timestamp' => $matches[1],
                        'level' => $matches[2],
                        'message' => $matches[3],
                        'full_line' => $line
                    ];
                }

                // Limit to 1000 entries for performance
                if (count($logs) >= 1000) {
                    break;
                }
            }
        }

        return view('super-admin.system.logs', compact('logs', 'availableFiles', 'logFile'));
    }

    /**
     * Display system activity.
     */
    public function activity(Request $request)
    {
        $period = $request->get('period', '7'); // days
        $startDate = now()->subDays($period);

        // User activity
        $userActivity = DB::table('users')
            ->select(DB::raw('DATE(updated_at) as date'), DB::raw('count(*) as count'))
            ->where('updated_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Tournament activity
        $tournamentActivity = DB::table('tournaments')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Assignment activity
        $assignmentActivity = DB::table('tournament_assignments')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Active users
        $activeUsers = DB::table('users')
            ->where('last_login_at', '>=', now()->subDays(1))
            ->count();

        // System stats
        $systemStats = [
            'total_users' => DB::table('users')->count(),
            'active_users_24h' => $activeUsers,
            'total_tournaments' => DB::table('tournaments')->count(),
            'active_tournaments' => DB::table('tournaments')->whereIn('status', ['open', 'closed', 'assigned'])->count(),
            'total_assignments' => DB::table('tournament_assignments')->count(),
            'pending_assignments' => DB::table('tournament_assignments')->where('status', 'pending')->count(),
        ];

        return view('super-admin.system.activity', compact(
            'userActivity',
            'tournamentActivity',
            'assignmentActivity',
            'systemStats',
            'period'
        ));
    }

    /**
     * Display system performance metrics.
     */
    public function performance(Request $request)
    {
        $metrics = $this->getPerformanceMetrics();
        $dbStats = $this->getDatabaseStats();
        $cacheStats = $this->getCacheStats();

        return view('super-admin.system.performance', compact('metrics', 'dbStats', 'cacheStats'));
    }

    /**
     * Toggle maintenance mode.
     */
    public function toggleMaintenance(Request $request)
    {
        try {
            if (app()->isDownForMaintenance()) {
                Artisan::call('up');
                $message = 'Modalità manutenzione disattivata.';
                $status = false;
            } else {
                $secret = $request->get('secret', \Str::random(8));
                Artisan::call('down', [
                    '--secret' => $secret,
                    '--render' => 'errors.503'
                ]);
                $message = 'Modalità manutenzione attivata.';
                $status = true;
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'maintenance_mode' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Clear specific cache types.
     */
    public function clearCache(Request $request)
    {
        $type = $request->get('type', 'all');

        try {
            switch ($type) {
                case 'config':
                    Artisan::call('config:clear');
                    $message = 'Cache configurazione svuotata.';
                    break;

                case 'view':
                    Artisan::call('view:clear');
                    $message = 'Cache viste svuotata.';
                    break;

                case 'route':
                    Artisan::call('route:clear');
                    $message = 'Cache rotte svuotata.';
                    break;

                case 'application':
                    Cache::flush();
                    $message = 'Cache applicazione svuotata.';
                    break;

                default:
                    Artisan::call('cache:clear');
                    Artisan::call('config:clear');
                    Artisan::call('view:clear');
                    Artisan::call('route:clear');
                    Cache::flush();
                    $message = 'Tutte le cache svuotate.';
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Optimize system performance.
     */
    public function optimize(Request $request)
    {
        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');

            if (app()->environment('production')) {
                Artisan::call('optimize');
            }

            return response()->json([
                'success' => true,
                'message' => 'Sistema ottimizzato con successo.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Run system diagnostics.
     */
    public function diagnostics()
    {
        $diagnostics = [
            'php_version' => [
                'value' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '8.1.0', '>=') ? 'success' : 'warning',
                'message' => version_compare(PHP_VERSION, '8.1.0', '>=') ? 'OK' : 'Aggiornamento consigliato'
            ],
            'laravel_version' => [
                'value' => app()->version(),
                'status' => 'success',
                'message' => 'OK'
            ],
            'database_connection' => [
                'value' => $this->testDatabaseConnection(),
                'status' => $this->testDatabaseConnection() ? 'success' : 'error',
                'message' => $this->testDatabaseConnection() ? 'Connessa' : 'Errore connessione'
            ],
            'cache_working' => [
                'value' => $this->testCache(),
                'status' => $this->testCache() ? 'success' : 'warning',
                'message' => $this->testCache() ? 'Funzionante' : 'Problemi rilevati'
            ],
            'storage_writable' => [
                'value' => is_writable(storage_path()),
                'status' => is_writable(storage_path()) ? 'success' : 'error',
                'message' => is_writable(storage_path()) ? 'Scrivibile' : 'Non scrivibile'
            ],
            'disk_space' => [
                'value' => $this->getAvailableDiskSpace(),
                'status' => $this->getAvailableDiskSpace() > 1000 ? 'success' : 'warning',
                'message' => $this->formatBytes($this->getAvailableDiskSpace()) . ' disponibili'
            ],
            'memory_usage' => [
                'value' => memory_get_usage(true),
                'status' => memory_get_usage(true) < 128 * 1024 * 1024 ? 'success' : 'warning',
                'message' => $this->formatBytes(memory_get_usage(true)) . ' in uso'
            ]
        ];

        return response()->json($diagnostics);
    }

    /**
     * Get performance metrics.
     */
    private function getPerformanceMetrics()
    {
        return [
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => $this->parseSize(ini_get('memory_limit'))
            ],
            'execution_time' => [
                'max' => ini_get('max_execution_time'),
                'current' => microtime(true) - LARAVEL_START
            ],
            'upload_limits' => [
                'max_filesize' => $this->parseSize(ini_get('upload_max_filesize')),
                'post_max_size' => $this->parseSize(ini_get('post_max_size'))
            ]
        ];
    }

    /**
     * Get database statistics.
     */
    private function getDatabaseStats()
    {
        try {
            $stats = [
                'connection' => DB::connection()->getDatabaseName(),
                'tables_count' => count(DB::select('SHOW TABLES')),
                'size' => $this->getDatabaseSize(),
            ];

            // Table sizes
            $tables = DB::select("
                SELECT table_name as 'table',
                       ROUND(((data_length + index_length) / 1024 / 1024), 2) as 'size_mb'
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
                LIMIT 10
            ");

            $stats['largest_tables'] = $tables;

            return $stats;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get cache statistics.
     */
    private function getCacheStats()
    {
        try {
            return [
                'driver' => config('cache.default'),
                'working' => $this->testCache(),
                'keys_count' => $this->getCacheKeysCount()
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Test database connection.
     */
    private function testDatabaseConnection()
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Test cache functionality.
     */
    private function testCache()
    {
        try {
            $key = 'test_cache_key_' . time();
            Cache::put($key, 'test_value', 60);
            $value = Cache::get($key);
            Cache::forget($key);
            return $value === 'test_value';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get available disk space in bytes.
     */
    private function getAvailableDiskSpace()
    {
        return disk_free_space(storage_path());
    }

    /**
     * Get database size.
     */
    private function getDatabaseSize()
    {
        try {
            $result = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
            ");

            return $result[0]->size_mb ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get cache keys count (approximation).
     */
    private function getCacheKeysCount()
    {
        try {
            // This is a simplified approach - actual implementation depends on cache driver
            return 'N/A';
        } catch (\Exception $e) {
            return 'Error';
        }
    }

    /**
     * Parse size string to bytes.
     */
    private function parseSize($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        return round($size);
    }

    /**
     * Format bytes to human readable.
     */
    private function formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
}
