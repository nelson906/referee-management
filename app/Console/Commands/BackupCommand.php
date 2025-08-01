<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupService;

class BackupCommand extends Command
{
    protected $signature = 'backup:run
                            {--type=full : Tipo di backup (full|database|files)}
                            {--compress : Comprimi i backup}
                            {--verify : Verifica integrità}
                            {--cloud : Upload su cloud}
                            {--notify : Invia notifica email}';

    protected $description = 'Esegue backup del sistema';

    public function handle(BackupService $backupService)
    {
        $type = $this->option('type');

        $options = [
            'compress' => $this->option('compress'),
            'verify' => $this->option('verify'),
            'cloud' => $this->option('cloud'),
            'notify' => $this->option('notify')
        ];

        $this->info("🔄 Avvio backup tipo: {$type}");

        switch ($type) {
            case 'full':
                $result = $backupService->performFullBackup($options);
                break;
            default:
                $this->error("Tipo backup non supportato: {$type}");
                return 1;
        }

        if ($result['success']) {
            $this->info("✅ Backup completato: {$result['backup_id']}");
            $this->info("📊 Dimensione: {$result['size_mb']} MB");
            $this->info("⏱️ Durata: {$result['duration_seconds']}s");
        } else {
            $this->error("❌ Backup fallito: {$result['backup_id']}");
            foreach ($result['errors'] as $error) {
                $this->error("  - {$error}");
            }
            return 1;
        }

        return 0;
    }
}
