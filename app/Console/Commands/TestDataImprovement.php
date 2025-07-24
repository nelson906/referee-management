<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\DataImprovementSeeder;

class TestDataImprovement extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:data-improvement {--dry-run : Simulate without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Test DataImprovementSeeder with optional dry-run mode';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Imposta la modalità dry-run se richiesta
        if ($this->option('dry-run')) {
            config(['seeder.dry_run' => true]);
            $this->info('🔍 MODALITÀ DRY-RUN ATTIVATA');
        }

        try {
            // Crea e esegui il seeder
            $seeder = new DataImprovementSeeder();
            $seeder->setCommand($this);
            $seeder->run();

            $this->info('✅ Comando completato con successo!');

        } catch (\Exception $e) {
            $this->error('❌ Errore durante l\'esecuzione: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
