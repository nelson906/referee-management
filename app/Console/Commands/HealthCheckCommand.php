<?php
// COMMAND PER HEALTH CHECK AUTOMATICO
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MonitoringService;

class HealthCheckCommand extends Command
{
    protected $signature = 'monitor:health {--silent}';
    protected $description = 'Esegue health check completo sistema';

    public function handle(MonitoringService $monitoringService)
    {
        if (!$this->option('silent')) {
            $this->info('🏥 Esecuzione Health Check...');
        }

        $results = $monitoringService->performHealthCheck();

        if (!$this->option('silent')) {
            $this->info("✅ Health Check completato in {$results['execution_time_ms']}ms");
            $this->info("📊 Stato: " . $results['overall_status']);
            $this->info("🔍 Controlli: " . count($results['checks']));
            $this->info("⚠️  Alert: " . count($results['alerts']));
        }

        return $results['overall_status'] === 'healthy' ? 0 : 1;
    }
}
