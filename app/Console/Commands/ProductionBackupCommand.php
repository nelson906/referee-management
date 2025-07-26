<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProductionBackupCommand extends Command
{
    protected $signature = 'backup:production {--notification}';

    public function handle()
    {
        $this->info('🔄 Avvio backup produzione...');

        // Backup database
        Artisan::call('backup:run', ['--only-db' => true]);

        // Backup files critici
        $this->backupCriticalFiles();

        // Verifica integrità
        $this->verifyBackup();

        if ($this->option('notification')) {
            $this->sendNotification();
        }

        $this->info('✅ Backup completato');
    }
}
