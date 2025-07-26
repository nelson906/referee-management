<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MonitoringController extends Controller
{
    protected $monitoringService;

    public function __construct(MonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    /**
     * Dashboard monitoring principale
     */
    public function dashboard()
    {
        $healthCheck = Cache::get('system_health_check', []);
        $realtimeMetrics = $this->monitoringService->getRealtimeMetrics();

        return view('admin.monitoring.dashboard', compact('healthCheck', 'realtimeMetrics'));
    }

    /**
     * API endpoint per health check
     */
    public function healthCheck()
    {
        $results = $this->monitoringService->performHealthCheck();

        return response()->json($results)
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }

    /**
     * API endpoint per metriche real-time
     */
    public function realtimeMetrics()
    {
        $metrics = $this->monitoringService->getRealtimeMetrics();

        return response()->json($metrics);
    }

    /**
     * Storico health checks
     */
    public function history(Request $request)
    {
        $hours = $request->get('hours', 24);

        // Implementare recupero storico da file
        $history = collect(Storage::disk('local')->files('health_checks'))
            ->filter(function($file) use ($hours) {
                $timestamp = Storage::disk('local')->lastModified($file);
                return $timestamp >= now()->subHours($hours)->timestamp;
            })
            ->map(function($file) {
                $content = Storage::disk('local')->get($file);
                return json_decode($content, true);
            })
            ->sortByDesc('timestamp')
            ->values();

        return response()->json($history);
    }
}
