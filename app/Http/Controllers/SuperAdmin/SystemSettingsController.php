<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use App\Models\Setting;

class SystemSettingsController extends Controller
{
    /**
     * Display the system settings.
     */
    public function index()
    {
        $settings = $this->getCurrentSettings();
        $systemInfo = $this->getSystemInfo();

        return view('super-admin.settings.index', compact('settings', 'systemInfo'));
    }

    /**
     * Update the system settings.
     */
    public function update(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string|max:255',
            'app_timezone' => 'required|string',
            'app_locale' => 'required|string',
            'mail_driver' => 'required|string',
            'mail_host' => 'nullable|string',
            'mail_port' => 'nullable|integer',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|string',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string',
            'system_maintenance' => 'boolean',
            'system_debug' => 'boolean',
            'cache_driver' => 'required|string',
            'session_lifetime' => 'required|integer|min:1|max:1440',
            'max_upload_size' => 'required|integer|min:1|max:100',
            'backup_enabled' => 'boolean',
            'backup_frequency' => 'required|string',
            'log_level' => 'required|string',
            'api_rate_limit' => 'required|integer|min:1|max:10000',
        ]);

        try {
            // Update database settings
            $this->updateSettings($request->all());

            // Clear cache
            Cache::flush();

            // Update .env file for critical settings
            $this->updateEnvFile([
                'APP_NAME' => $request->app_name,
                'APP_TIMEZONE' => $request->app_timezone,
                'APP_LOCALE' => $request->app_locale,
                'APP_DEBUG' => $request->system_debug ? 'true' : 'false',
                'MAIL_MAILER' => $request->mail_driver,
                'MAIL_HOST' => $request->mail_host,
                'MAIL_PORT' => $request->mail_port,
                'MAIL_USERNAME' => $request->mail_username,
                'MAIL_FROM_ADDRESS' => $request->mail_from_address,
                'MAIL_FROM_NAME' => $request->mail_from_name,
                'SESSION_LIFETIME' => $request->session_lifetime,
            ]);

            return redirect()->route('super-admin.settings.index')
                ->with('success', 'Impostazioni aggiornate con successo.');

        } catch (\Exception $e) {
            return redirect()->route('super-admin.settings.index')
                ->with('error', 'Errore durante l\'aggiornamento: ' . $e->getMessage());
        }
    }

    /**
     * Clear system cache
     */
    public function clearCache()
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');

            return response()->json(['success' => true, 'message' => 'Cache svuotata con successo']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
        }
    }

    /**
     * Optimize system
     */
    public function optimize()
    {
        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');

            return response()->json(['success' => true, 'message' => 'Sistema ottimizzato con successo']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
        }
    }

    /**
     * Get current settings
     */
    private function getCurrentSettings()
    {
        return [
            'app_name' => config('app.name'),
            'app_timezone' => config('app.timezone'),
            'app_locale' => config('app.locale'),
            'mail_driver' => config('mail.default'),
            'mail_host' => config('mail.mailers.smtp.host'),
            'mail_port' => config('mail.mailers.smtp.port'),
            'mail_username' => config('mail.mailers.smtp.username'),
            'mail_encryption' => config('mail.mailers.smtp.encryption'),
            'mail_from_address' => config('mail.from.address'),
            'mail_from_name' => config('mail.from.name'),
            'system_maintenance' => app()->isDownForMaintenance(),
            'system_debug' => config('app.debug'),
            'cache_driver' => config('cache.default'),
            'session_lifetime' => config('session.lifetime'),
            'max_upload_size' => ini_get('upload_max_filesize'),
            'backup_enabled' => $this->getSetting('backup_enabled', false),
            'backup_frequency' => $this->getSetting('backup_frequency', 'daily'),
            'log_level' => config('logging.level'),
            'api_rate_limit' => $this->getSetting('api_rate_limit', 1000),
        ];
    }

    /**
     * Get system information
     */
    private function getSystemInfo()
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'database_size' => $this->getDatabaseSize(),
            'storage_used' => $this->getStorageUsed(),
            'log_size' => $this->getLogSize(),
            'uptime' => $this->getSystemUptime(),
            'memory_usage' => $this->getMemoryUsage(),
        ];
    }

    /**
     * Update settings in database
     */
    private function updateSettings(array $settings)
    {
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    /**
     * Get setting value
     */
    private function getSetting($key, $default = null)
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Update .env file
     */
    private function updateEnvFile(array $data)
    {
        $envFile = base_path('.env');

        if (!File::exists($envFile)) {
            return;
        }

        $envContent = File::get($envFile);

        foreach ($data as $key => $value) {
            if ($value === null) continue;

            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}=\"{$value}\"";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        File::put($envFile, $envContent);
    }

    /**
     * Helper methods for system info
     */
    private function getDatabaseSize()
    {
        try {
            $size = \DB::selectOne("SELECT pg_size_pretty(pg_database_size(current_database())) as size");
            return $size->size ?? 'N/A';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    private function getStorageUsed()
    {
        $bytes = disk_total_space(storage_path()) - disk_free_space(storage_path());
        return $this->formatBytes($bytes);
    }

    private function getLogSize()
    {
        $logPath = storage_path('logs');
        $size = 0;

        if (is_dir($logPath)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($logPath)) as $file) {
                $size += $file->getSize();
            }
        }

        return $this->formatBytes($size);
    }

    private function getSystemUptime()
    {
        if (function_exists('sys_getloadavg')) {
            $uptime = shell_exec('uptime');
            return $uptime ? trim($uptime) : 'N/A';
        }
        return 'N/A';
    }

    private function getMemoryUsage()
    {
        $memory = memory_get_usage(true);
        return $this->formatBytes($memory);
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
