<?php
// COMMAND PER ESEGUIRE TUTTI I TEST E2E
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunE2ETestsCommand extends Command
{
    protected $signature = 'test:e2e
                            {--coverage : Genera report coverage}
                            {--parallel : Esegue test in parallelo}';

    protected $description = 'Esegue suite completa test end-to-end';

    public function handle()
    {
        $this->info('🧪 ESECUZIONE TEST END-TO-END');
        $this->info('===============================');

        $options = ['--group' => 'e2e'];

        if ($this->option('coverage')) {
            $options['--coverage-html'] = 'storage/app/coverage';
            $this->info('📊 Coverage report verrà generato in storage/app/coverage');
        }

        if ($this->option('parallel')) {
            $options['--parallel'] = true;
            $this->info('⚡ Esecuzione in modalità parallela');
        }

        // Prepara database test
        $this->info('📋 Preparazione ambiente test...');
        Artisan::call('migrate:fresh', ['--env' => 'testing']);

        // Esegui test
        $this->info('🚀 Avvio test...');
        $exitCode = Artisan::call('test', $options);

        if ($exitCode === 0) {
            $this->info('✅ TUTTI I TEST SUPERATI!');
            $this->info('🎉 Sistema validato per produzione');
        } else {
            $this->error('❌ ALCUNI TEST FALLITI');
            $this->error('🚨 Controllare i risultati prima del deploy');
        }

        return $exitCode;
    }
}
