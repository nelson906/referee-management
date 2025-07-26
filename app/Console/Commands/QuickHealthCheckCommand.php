<?php
// COMMAND DI SUPPORTO: Quick Health Check
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QuickHealthCheckCommand extends Command
{
    protected $signature = 'system:health';
    protected $description = 'Quick health check del sistema';

    public function handle()
    {
        $this->info('🏥 QUICK HEALTH CHECK');
        $this->info('====================');

        // Database
        try {
            $users = DB::table('users')->count();
            $this->info("✅ Database: {$users} utenti");
        } catch (\Exception $e) {
            $this->error('❌ Database: Errore connessione');
            return 1;
        }

        // Cache
        try {
            \Cache::put('health_check', 'ok', 60);
            $test = \Cache::get('health_check');
            $this->info($test === 'ok' ? '✅ Cache: OK' : '❌ Cache: Errore');
        } catch (\Exception $e) {
            $this->error('❌ Cache: Errore');
        }

        // Storage
        $writable = is_writable(storage_path());
        $this->info($writable ? '✅ Storage: Scrivibile' : '❌ Storage: Non scrivibile');

        // Memory
        $memory = round(memory_get_usage(true) / 1024 / 1024, 2);
        $this->info("✅ Memory: {$memory}MB");

        $this->info('');
        $this->info('🎯 Sistema operativo');

        return 0;
    }
}
