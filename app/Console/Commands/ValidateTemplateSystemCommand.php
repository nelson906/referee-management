<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TemplateService;

class ValidateTemplateSystemCommand extends Command
{
    protected $signature = 'template:validate';
    protected $description = 'Valida la configurazione del sistema template';

    public function handle(TemplateService $templateService)
    {
        $this->info('🔍 Validazione Sistema Template...');

        $validation = $templateService->validateTemplateSystem();

        if ($validation['is_valid']) {
            $this->info('✅ Sistema template configurato correttamente!');
        } else {
            $this->error('❌ Problemi trovati nel sistema template:');
            foreach ($validation['issues'] as $issue) {
                $this->line("  - {$issue}");
            }

            $this->info('📋 Raccomandazioni:');
            foreach ($validation['recommendations'] as $rec) {
                $this->line("  - {$rec}");
            }
        }

        return $validation['is_valid'] ? 0 : 1;
    }
}
