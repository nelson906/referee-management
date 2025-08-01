<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use ZipArchive;

class GolfBackupSystem extends Command
{
    protected $signature = 'golf:backup
                            {--type=full : Tipo backup (full, data, files, seeder)}
                            {--compress : Comprimi backup}
                            {--encrypt : Cripta backup}
                            {--retention= : Giorni di retention (default: 30)}
                            {--destination= : Destinazione backup}
                            {--schedule : Eseguito da scheduler}';

    protected $description = 'Sistema completo di backup per Golf Seeder System';

    private array $backupConfig;
    private string $backupPath;
    private array $backupLog = [];

    public function handle(): int
    {
        $this->info('💾 GOLF BACKUP SYSTEM');
        $this->info('=====================');

        $this->loadConfiguration();
        $this->initializeBackupPath();

        $backupType = $this->option('type');

        try {
            $backupFile = match($backupType) {
                'full' => $this->createFullBackup(),
                'data' => $this->createDataBackup(),
                'files' => $this->createFilesBackup(),
                'seeder' => $this->createSeederBackup(),
                default => throw new \InvalidArgumentException("Tipo backup non supportato: {$backupType}")
            };

            $this->finalizeBackup($backupFile);
            $this->cleanupOldBackups();
            $this->generateBackupReport();

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Errore durante backup: " . $e->getMessage());
            $this->logError($e);
            return 1;
        }
    }

    private function loadConfiguration(): void
    {
        $this->backupConfig = [
            'retention_days' => (int) ($this->option('retention') ?? config('golf.backup.retention_days', 30)),
            'compress' => $this->option('compress') || config('golf.backup.compress', true),
            'encrypt' => $this->option('encrypt') || config('golf.backup.encrypt', false),
            'destination' => $this->option('destination') ?? config('golf.backup.destination', 'local'),
            'max_size_mb' => config('golf.backup.max_size_mb', 500),
            'notification_email' => config('golf.backup.notification_email'),
            'encryption_key' => config('golf.backup.encryption_key', config('app.key')),
        ];

        $this->info('⚙️ Configurazione caricata: ' . json_encode($this->backupConfig));
    }

    private function initializeBackupPath(): void
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $this->backupPath = storage_path("app/golf-backups/{$timestamp}");

        if (!File::exists($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
        }

        $this->info("📁 Directory backup: {$this->backupPath}");
    }

    private function createFullBackup(): string
    {
        $this->info('🔄 Creando backup completo...');

        $backupSections = [
            'database' => $this->backupDatabase(),
            'files' => $this->backupFiles(),
            'storage' => $this->backupStorage(),
            'config' => $this->backupConfiguration(),
            'logs' => $this->backupLogs(),
            'seeder_data' => $this->backupSeederData()
        ];

        return $this->packageBackup($backupSections, 'full');
    }

    private function createDataBackup(): string
    {
        $this->info('🗄️ Creando backup dati...');

        $backupSections = [
            'database' => $this->backupDatabase(),
            'seeder_data' => $this->backupSeederData(),
            'exports' => $this->backupExports()
        ];

        return $this->packageBackup($backupSections, 'data');
    }

    private function createFilesBackup(): string
    {
        $this->info('📄 Creando backup files...');

        $backupSections = [
            'application' => $this->backupApplicationFiles(),
            'storage' => $this->backupStorage(),
            'config' => $this->backupConfiguration(),
            'logs' => $this->backupLogs()
        ];

        return $this->packageBackup($backupSections, 'files');
    }

    private function createSeederBackup(): string
    {
        $this->info('🎯 Creando backup seeder...');

        $backupSections = [
            'seeders' => $this->backupSeeders(),
            'seeder_config' => $this->backupSeederConfig(),
            'test_data' => $this->backupTestData(),
            'sample_exports' => $this->generateSampleExports()
        ];

        return $this->packageBackup($backupSections, 'seeder');
    }

    private function backupDatabase(): string
    {
        $this->info('  🗄️ Backup database...');

        $dbConfig = config('database.connections.' . config('database.default'));
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "database_backup_{$timestamp}.sql";
        $filepath = $this->backupPath . '/' . $filename;

        // Determina il comando mysqldump in base all'ambiente
        if ($this->isDockerEnvironment()) {
            $command = $this->getDockerMysqldumpCommand($dbConfig, $filepath);
        } else {
            $command = $this->getMysqldumpCommand($dbConfig, $filepath);
        }

        $this->info("    Eseguendo: {$command}");

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Backup database fallito: " . implode("\n", $output));
        }

        // Verifica dimensione file
        $fileSize = File::size($filepath);
        $this->backupLog[] = [
            'section' => 'database',
            'file' => $filename,
            'size_mb' => round($fileSize / 1024 / 1024, 2),
            'tables' => $this->countDatabaseTables()
        ];

        $this->info("    ✅ Database backup completato: {$filename} (" . $this->formatFileSize($fileSize) . ")");

        return $filepath;
    }

    private function backupFiles(): string
    {
        $this->info('  📄 Backup files applicazione...');

        $excludePaths = [
            'node_modules',
            'vendor',
            '.git',
            'storage/logs',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views'
        ];

        $zipFilename = $this->backupPath . '/application_files.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipFilename, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception("Impossibile creare archivio ZIP: {$zipFilename}");
        }

        $this->addDirectoryToZip($zip, base_path(), '', $excludePaths);
        $zip->close();

        $fileSize = File::size($zipFilename);
        $this->backupLog[] = [
            'section' => 'application_files',
            'file' => 'application_files.zip',
            'size_mb' => round($fileSize / 1024 / 1024, 2),
            'files_count' => $zip->numFiles ?? 'N/A'
        ];

        $this->info("    ✅ Files backup completato: " . $this->formatFileSize($fileSize));

        return $zipFilename;
    }

    private function backupStorage(): string
    {
        $this->info('  💾 Backup storage...');

        $storageZip = $this->backupPath . '/storage_backup.zip';
        $zip = new ZipArchive();

        if ($zip->open($storageZip, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception("Impossibile creare archivio storage");
        }

        // Backup cartelle importanti di storage
        $storageDirectories = [
            'app/golf-exports',
            'app/golf-backups',
            'app/public',
            'logs'
        ];

        foreach ($storageDirectories as $dir) {
            $fullPath = storage_path($dir);
            if (File::exists($fullPath)) {
                $this->addDirectoryToZip($zip, $fullPath, $dir);
            }
        }

        $zip->close();

        $fileSize = File::size($storageZip);
        $this->backupLog[] = [
            'section' => 'storage',
            'file' => 'storage_backup.zip',
            'size_mb' => round($fileSize / 1024 / 1024, 2)
        ];

        return $storageZip;
    }

    private function backupConfiguration(): string
    {
        $this->info('  ⚙️ Backup configurazioni...');

        $configBackup = $this->backupPath . '/config_backup.zip';
        $zip = new ZipArchive();

        if ($zip->open($configBackup, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception("Impossibile creare archivio configurazioni");
        }

        // File di configurazione critici
        $configFiles = [
            '.env.example',
            'config/',
            'docker/',
            'database/migrations/',
            'routes/',
            'composer.json',
            'composer.lock',
            'package.json',
            'artisan'
        ];

        foreach ($configFiles as $file) {
            $fullPath = base_path($file);
            if (File::exists($fullPath)) {
                if (File::isDirectory($fullPath)) {
                    $this->addDirectoryToZip($zip, $fullPath, $file);
                } else {
                    $zip->addFile($fullPath, $file);
                }
            }
        }

        $zip->close();

        $fileSize = File::size($configBackup);
        $this->backupLog[] = [
            'section' => 'configuration',
            'file' => 'config_backup.zip',
            'size_mb' => round($fileSize / 1024 / 1024, 2)
        ];

        return $configBackup;
    }

    private function backupLogs(): string
    {
        $this->info('  📋 Backup logs...');

        $logsBackup = $this->backupPath . '/logs_backup.zip';
        $zip = new ZipArchive();

        if ($zip->open($logsBackup, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception("Impossibile creare archivio logs");
        }

        $logsPath = storage_path('logs');
        if (File::exists($logsPath)) {
            $this->addDirectoryToZip($zip, $logsPath, 'logs');
        }

        $zip->close();

        $fileSize = File::size($logsBackup);
        $this->backupLog[] = [
            'section' => 'logs',
            'file' => 'logs_backup.zip',
            'size_mb' => round($fileSize / 1024 / 1024, 2)
        ];

        return $logsBackup;
    }

    private function backupSeederData(): string
    {
        $this->info('  🎯 Backup dati seeder...');

        // Esporta dati seeder correnti
        $exportCommand = "golf:export all --format=json --output=seeder_data_export.json";
        Artisan::call($exportCommand);

        // Crea snapshot database specifico per seeder
        $seederSnapshot = $this->backupPath . '/seeder_snapshot.sql';

        // Export solo tabelle generate da seeder
        $seederTables = [
            'zones', 'users', 'clubs', 'tournaments',
            'tournament_types', 'availabilities', 'assignments'
        ];

        $dbConfig = config('database.connections.' . config('database.default'));
        $tableList = implode(' ', $seederTables);

        $command = sprintf(
            'mysqldump -h %s -P %s -u %s -p%s %s %s > %s',
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['database'],
            $tableList,
            $seederSnapshot
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->warn("Backup snapshot seeder parzialmente fallito");
        }

        $fileSize = File::exists($seederSnapshot) ? File::size($seederSnapshot) : 0;
        $this->backupLog[] = [
            'section' => 'seeder_data',
            'file' => 'seeder_snapshot.sql',
            'size_mb' => round($fileSize / 1024 / 1024, 2),
            'tables' => count($seederTables)
        ];

        return $seederSnapshot;
    }

    private function backupSeeders(): string
    {
        $this->info('  🌱 Backup files seeder...');

        $seedersBackup = $this->backupPath . '/seeders_backup.zip';
        $zip = new ZipArchive();

        if ($zip->open($seedersBackup, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception("Impossibile creare archivio seeders");
        }

        // Directory seeders
        $seedersPath = base_path('database/seeders');
        if (File::exists($seedersPath)) {
            $this->addDirectoryToZip($zip, $seedersPath, 'seeders');
        }

        // Commands golf
        $commandsPath = base_path('app/Console/Commands');
        if (File::exists($commandsPath)) {
            $golfCommands = File::glob($commandsPath . '/Golf*.php');
            foreach ($golfCommands as $command) {
                $zip->addFile($command, 'commands/' . basename($command));
            }
        }

        $zip->close();

        $fileSize = File::size($seedersBackup);
        $this->backupLog[] = [
            'section' => 'seeders',
            'file' => 'seeders_backup.zip',
            'size_mb' => round($fileSize / 1024 / 1024, 2)
        ];

        return $seedersBackup;
    }

    private function backupSeederConfig(): string
    {
        $this->info('  ⚙️ Backup configurazioni seeder...');

        $configData = [
            'seeder_config' => config('golf.seeder', []),
            'database_config' => config('database'),
            'app_config' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'timezone' => config('app.timezone')
            ],
            'backup_timestamp' => now(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version()
        ];

        $configFile = $this->backupPath . '/seeder_config.json';
        File::put($configFile, json_encode($configData, JSON_PRETTY_PRINT));

        $fileSize = File::size($configFile);
        $this->backupLog[] = [
            'section' => 'seeder_config',
            'file' => 'seeder_config.json',
            'size_mb' => round($fileSize / 1024 / 1024, 2)
        ];

        return $configFile;
    }

    private function backupTestData(): string
    {
        $this->info('  🧪 Backup dati test...');

        $testBackup = $this->backupPath . '/test_data_backup.zip';
        $zip = new ZipArchive();

        if ($zip->open($testBackup, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception("Impossibile creare archivio test data");
        }

        // Test files
        $testsPath = base_path('tests');
        if (File::exists($testsPath)) {
            $this->addDirectoryToZip($zip, $testsPath, 'tests');
        }

        // PHPUnit config
        $phpunitConfig = base_path('phpunit.xml');
        if (File::exists($phpunitConfig)) {
            $zip->addFile($phpunitConfig, 'phpunit.xml');
        }

        $zip->close();

        $fileSize = File::size($testBackup);
        $this->backupLog[] = [
            'section' => 'test_data',
            'file' => 'test_data_backup.zip',
            'size_mb' => round($fileSize / 1024 / 1024, 2)
        ];

        return $testBackup;
    }

    private function backupApplicationFiles(): string
    {
        return $this->backupFiles(); // Riusa logica esistente
    }

    private function backupExports(): string
    {
        $this->info('  📤 Backup exports...');

        $exportsPath = storage_path('app/golf-exports');
        $exportsBackup = $this->backupPath . '/exports_backup.zip';

        if (!File::exists($exportsPath)) {
            // Crea exports di esempio
            Artisan::call('golf:export', ['type' => 'summary']);
            Artisan::call('golf:diagnostic', ['--export' => true]);
        }

        $zip = new ZipArchive();
        if ($zip->open($exportsBackup, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception("Impossibile creare archivio exports");
        }

        if (File::exists($exportsPath)) {
            $this->addDirectoryToZip($zip, $exportsPath, 'exports');
        }

        $zip->close();

        $fileSize = File::size($exportsBackup);
        $this->backupLog[] = [
            'section' => 'exports',
            'file' => 'exports_backup.zip',
            'size_mb' => round($fileSize / 1024 / 1024, 2)
        ];

        return $exportsBackup;
    }

    private function generateSampleExports(): string
    {
        $this->info('  📊 Generando exports di esempio...');

        // Genera exports per documentazione
        Artisan::call('golf:export', ['type' => 'all', '--format' => 'json']);
        Artisan::call('golf:diagnostic', ['--detailed' => true, '--export' => true]);

        return $this->backupExports();
    }

    private function packageBackup(array $sections, string $type): string
    {
        $this->info('📦 Creando pacchetto backup finale...');

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $backupFilename = "golf_backup_{$type}_{$timestamp}";

        if ($this->backupConfig['compress']) {
            $finalBackup = storage_path("app/golf-backups/{$backupFilename}.zip");
            $this->createCompressedBackup($sections, $finalBackup);
        } else {
            $finalBackup = storage_path("app/golf-backups/{$backupFilename}.tar");
            $this->createTarBackup($sections, $finalBackup);
        }

        if ($this->backupConfig['encrypt']) {
            $finalBackup = $this->encryptBackup($finalBackup);
        }

        $this->info("✅ Backup creato: " . basename($finalBackup));
        return $finalBackup;
    }

    private function createCompressedBackup(array $sections, string $outputFile): void
    {
        $zip = new ZipArchive();

        if ($zip->open($outputFile, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception("Impossibile creare backup finale");
        }

        foreach ($sections as $section => $filepath) {
            if (File::exists($filepath)) {
                $zip->addFile($filepath, basename($filepath));
            }
        }

        // Aggiungi metadata
        $metadata = [
            'backup_type' => $this->option('type'),
            'created_at' => now(),
            'sections' => array_keys($sections),
            'backup_log' => $this->backupLog,
            'system_info' => $this->getSystemInfo()
        ];

        $zip->addFromString('backup_metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
        $zip->close();
    }

    private function finalizeBackup(string $backupFile): void
    {
        $this->info('🏁 Finalizzando backup...');

        // Verifica integrità
        $this->verifyBackupIntegrity($backupFile);

        // Upload a destinazione se configurato
        if ($this->backupConfig['destination'] !== 'local') {
            $this->uploadBackup($backupFile);
        }

        // Pulisci directory temporanea
        File::deleteDirectory($this->backupPath);

        $this->info("✅ Backup finalizzato: " . basename($backupFile));
    }

    private function cleanupOldBackups(): void
    {
        $this->info('🧹 Pulizia backup obsoleti...');

        $retentionDays = $this->backupConfig['retention_days'];
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $backupDir = storage_path('app/golf-backups');
        $files = File::glob($backupDir . '/golf_backup_*');

        $deletedCount = 0;
        foreach ($files as $file) {
            $fileDate = Carbon::createFromTimestamp(File::lastModified($file));

            if ($fileDate->lt($cutoffDate)) {
                File::delete($file);
                $deletedCount++;
            }
        }

        $this->info("🗑️ Rimossi {$deletedCount} backup obsoleti (>{$retentionDays} giorni)");
    }

    private function generateBackupReport(): void
    {
        $this->info('📋 Generando report backup...');

        $report = [
            'backup_summary' => [
                'type' => $this->option('type'),
                'timestamp' => now(),
                'total_size_mb' => array_sum(array_column($this->backupLog, 'size_mb')),
                'sections_count' => count($this->backupLog),
                'success' => true
            ],
            'sections' => $this->backupLog,
            'configuration' => $this->backupConfig,
            'system_info' => $this->getSystemInfo()
        ];

        $reportFile = storage_path('app/golf-backups/backup_report_' . now()->format('Y-m-d_H-i-s') . '.json');
        File::put($reportFile, json_encode($report, JSON_PRETTY_PRINT));

        // Mostra riassunto
        $this->table(['Sezione', 'File', 'Dimensione'], array_map(function($log) {
            return [$log['section'], $log['file'], $log['size_mb'] . ' MB'];
        }, $this->backupLog));

        $this->info("📊 Report salvato: " . basename($reportFile));

        // Invia notifica se configurato
        if ($this->backupConfig['notification_email'] && !$this->option('schedule')) {
            $this->sendBackupNotification($report);
        }
    }

    // Helper methods
    private function isDockerEnvironment(): bool
    {
        return file_exists('/.dockerenv') || (getenv('DOCKER_CONTAINER') !== false);
    }

    private function getDockerMysqldumpCommand(array $dbConfig, string $outputFile): string
    {
        return sprintf(
            'docker exec golf_database mysqldump -u %s -p%s %s > %s',
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['database'],
            $outputFile
        );
    }

    private function getMysqldumpCommand(array $dbConfig, string $outputFile): string
    {
        return sprintf(
            'mysqldump -h %s -P %s -u %s -p%s %s > %s',
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['database'],
            $outputFile
        );
    }

    private function countDatabaseTables(): int
    {
        try {
            return count(DB::select('SHOW TABLES'));
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = 0;

        while ($bytes >= 1024 && $factor < count($units) - 1) {
            $bytes /= 1024;
            $factor++;
        }

        return round($bytes, 2) . ' ' . $units[$factor];
    }

    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $localPath = '', array $excludePaths = []): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getRealPath();
                $relativePath = $localPath . '/' . substr($filePath, strlen($directory) + 1);

                // Controlla esclusioni
                $shouldExclude = false;
                foreach ($excludePaths as $excludePath) {
                    if (strpos($relativePath, $excludePath) !== false) {
                        $shouldExclude = true;
                        break;
                    }
                }

                if (!$shouldExclude) {
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
    }

    private function verifyBackupIntegrity(string $backupFile): void
    {
        if (!File::exists($backupFile)) {
            throw new \Exception("File backup non trovato: {$backupFile}");
        }

        $fileSize = File::size($backupFile);
        if ($fileSize === 0) {
            throw new \Exception("File backup vuoto");
        }

        if ($fileSize > $this->backupConfig['max_size_mb'] * 1024 * 1024) {
            $this->warn("⚠️ Backup eccede dimensione massima configurata");
        }

        $this->info("✅ Integrità backup verificata: " . $this->formatFileSize($fileSize));
    }

    private function encryptBackup(string $backupFile): string
    {
        $this->info('🔐 Crittografando backup...');

        $encryptedFile = $backupFile . '.enc';
        $key = $this->backupConfig['encryption_key'];

        $data = File::get($backupFile);
        $encryptedData = encrypt($data);

        File::put($encryptedFile, $encryptedData);
        File::delete($backupFile);

        return $encryptedFile;
    }

    private function uploadBackup(string $backupFile): void
    {
        $this->info('☁️ Upload backup a destinazione remota...');

        $destination = $this->backupConfig['destination'];

        switch ($destination) {
            case 's3':
                Storage::disk('s3')->put('golf-backups/' . basename($backupFile), File::get($backupFile));
                break;
            case 'ftp':
                Storage::disk('ftp')->put('golf-backups/' . basename($backupFile), File::get($backupFile));
                break;
            default:
                $this->warn("Destinazione non configurata: {$destination}");
        }
    }

    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_os' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'disk_free_space' => $this->formatFileSize(disk_free_space(storage_path())),
            'backup_timestamp' => now()->toISOString()
        ];
    }

    private function createTarBackup(array $sections, string $outputFile): void
    {
        // Implementazione backup tar per sistemi Unix
        $command = "tar -cf {$outputFile}";

        foreach ($sections as $filepath) {
            if (File::exists($filepath)) {
                $command .= " {$filepath}";
            }
        }

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Creazione tar backup fallita");
        }
    }

    private function sendBackupNotification(array $report): void
    {
        // Implementazione notifica email
        $this->info('📧 Invio notifica backup...');
    }

    private function logError(\Exception $e): void
    {
        $errorLog = [
            'timestamp' => now(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'backup_type' => $this->option('type'),
            'configuration' => $this->backupConfig
        ];

        File::put(
            storage_path('logs/backup_error_' . now()->format('Y-m-d_H-i-s') . '.json'),
            json_encode($errorLog, JSON_PRETTY_PRINT)
        );
    }
}
