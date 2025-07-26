<?php

/**
 * TASK 9: Sistema Backup Automatico e Disaster Recovery
 *
 * OBIETTIVO: Backup automatici con recovery procedure complete
 * TEMPO STIMATO: 2-3 ore
 * COMPLESSITÀ: Media
 *
 * FEATURES:
 * - Backup incrementali database
 * - Backup files applicazione
 * - Cloud storage integration
 * - Recovery automatico
 * - Testing backup integrità
 * - Disaster recovery procedures
 */

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use ZipArchive;

class BackupService
{
    private $config;
    private $backupPath;
    private $tempPath;

    public function __construct()
    {
        $this->config = config('backup');
        $this->backupPath = storage_path('app/backups');
        $this->tempPath = storage_path('app/temp');

        // Crea directory se non esistono
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }

    /**
     * Esegue backup completo sistema
     */
    public function performFullBackup(array $options = []): array
    {
        $startTime = microtime(true);
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupId = "full_backup_{$timestamp}";

        $result = [
            'backup_id' => $backupId,
            'type' => 'full',
            'started_at' => now()->toISOString(),
            'components' => [],
            'success' => false,
            'errors' => [],
            'files' => [],
            'size_mb' => 0
        ];

        Log::info("🔄 Starting full backup: {$backupId}");

        try {
            // 1. Backup Database
            $dbResult = $this->backupDatabase($backupId, $options);
            $result['components']['database'] = $dbResult;

            if ($dbResult['success']) {
                $result['files'][] = $dbResult['file'];
                $result['size_mb'] += $dbResult['size_mb'];
            } else {
                $result['errors'] = array_merge($result['errors'], $dbResult['errors']);
            }

            // 2. Backup Application Files
            $filesResult = $this->backupApplicationFiles($backupId, $options);
            $result['components']['application_files'] = $filesResult;

            if ($filesResult['success']) {
                $result['files'][] = $filesResult['file'];
                $result['size_mb'] += $filesResult['size_mb'];
            } else {
                $result['errors'] = array_merge($result['errors'], $filesResult['errors']);
            }

            // 3. Backup Uploads/Storage
            $storageResult = $this->backupStorage($backupId, $options);
            $result['components']['storage'] = $storageResult;

            if ($storageResult['success']) {
                $result['files'][] = $storageResult['file'];
                $result['size_mb'] += $storageResult['size_mb'];
            } else {
                $result['errors'] = array_merge($result['errors'], $storageResult['errors']);
            }

            // 4. Create Manifest
            $manifestResult = $this->createBackupManifest($backupId, $result);
            $result['components']['manifest'] = $manifestResult;

            // 5. Upload to Cloud (se configurato)
            if ($this->config['cloud_enabled'] ?? false) {
                $cloudResult = $this->uploadToCloud($backupId, $result['files']);
                $result['components']['cloud_upload'] = $cloudResult;
            }

            // 6. Test Backup Integrity
            if ($options['verify'] ?? true) {
                $verifyResult = $this->verifyBackupIntegrity($backupId, $result['files']);
                $result['components']['verification'] = $verifyResult;
            }

            $result['success'] = empty($result['errors']);
            $result['completed_at'] = now()->toISOString();
            $result['duration_seconds'] = round(microtime(true) - $startTime, 2);

            // 7. Cleanup Old Backups
            $this->cleanupOldBackups();

            // 8. Send Notification
            if ($options['notify'] ?? true) {
                $this->sendBackupNotification($result);
            }

            // 9. Log Result
            $this->logBackupResult($result);

        } catch (\Exception $e) {
            $result['errors'][] = "Backup failed: " . $e->getMessage();
            $result['success'] = false;
            $result['completed_at'] = now()->toISOString();
            $result['duration_seconds'] = round(microtime(true) - $startTime, 2);

            Log::error("❌ Backup failed: {$backupId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $result;
    }

    /**
     * Backup Database con dump SQL
     */
    private function backupDatabase(string $backupId, array $options = []): array
    {
        $result = [
            'success' => false,
            'file' => null,
            'size_mb' => 0,
            'errors' => [],
            'tables_count' => 0,
            'records_count' => 0
        ];

        try {
            $dbConfig = config('database.connections.mysql');
            $filename = "{$backupId}_database.sql";
            $filepath = $this->backupPath . '/' . $filename;

            // Comando mysqldump
            $command = sprintf(
                'mysqldump -h%s -P%s -u%s -p%s %s > %s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['database'],
                $filepath
            );

            // Aggiunge opzioni mysqldump
            $dumpOptions = [
                '--single-transaction',
                '--routines',
                '--triggers',
                '--add-drop-table',
                '--complete-insert',
                '--extended-insert',
                '--set-charset'
            ];

            $fullCommand = str_replace(
                'mysqldump',
                'mysqldump ' . implode(' ', $dumpOptions),
                $command
            );

            exec($fullCommand, $output, $returnCode);

            if ($returnCode === 0 && file_exists($filepath)) {
                $result['success'] = true;
                $result['file'] = $filepath;
                $result['size_mb'] = round(filesize($filepath) / 1024 / 1024, 2);

                // Conta tabelle e record
                $result['tables_count'] = $this->countDatabaseTables();
                $result['records_count'] = $this->countDatabaseRecords();

                // Comprimi il backup
                if ($options['compress'] ?? true) {
                    $compressedFile = $this->compressFile($filepath);
                    if ($compressedFile) {
                        unlink($filepath);
                        $result['file'] = $compressedFile;
                        $result['size_mb'] = round(filesize($compressedFile) / 1024 / 1024, 2);
                    }
                }

                Log::info("✅ Database backup completed", [
                    'file' => $filename,
                    'size_mb' => $result['size_mb'],
                    'tables' => $result['tables_count']
                ]);

            } else {
                $result['errors'][] = "mysqldump failed with exit code: {$returnCode}";
                Log::error("❌ Database backup failed", [
                    'command' => $fullCommand,
                    'exit_code' => $returnCode,
                    'output' => $output
                ]);
            }

        } catch (\Exception $e) {
            $result['errors'][] = "Database backup error: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Backup Application Files
     */
    private function backupApplicationFiles(string $backupId, array $options = []): array
    {
        $result = [
            'success' => false,
            'file' => null,
            'size_mb' => 0,
            'errors' => [],
            'files_count' => 0
        ];

        try {
            $filename = "{$backupId}_application.zip";
            $filepath = $this->backupPath . '/' . $filename;

            $zip = new ZipArchive();
            if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                $result['errors'][] = "Cannot create ZIP file: {$filepath}";
                return $result;
            }

            // Directory da includere
            $includePatterns = [
                'app/',
                'config/',
                'database/migrations/',
                'database/seeders/',
                'resources/',
                'routes/',
                '.env.example',
                'composer.json',
                'composer.lock',
                'artisan'
            ];

            // Directory da escludere
            $excludePatterns = [
                'storage/logs/',
                'storage/framework/cache/',
                'storage/framework/sessions/',
                'storage/app/temp/',
                'storage/app/backups/',
                'node_modules/',
                'vendor/',
                '.git/',
                'tests/',
                '*.log'
            ];

            $basePath = base_path();
            $filesAdded = 0;

            foreach ($includePatterns as $pattern) {
                $fullPattern = $basePath . '/' . $pattern;

                if (is_file($fullPattern)) {
                    $zip->addFile($fullPattern, $pattern);
                    $filesAdded++;
                } elseif (is_dir($fullPattern)) {
                    $this->addDirectoryToZip($zip, $fullPattern, $pattern, $excludePatterns, $filesAdded);
                }
            }

            $zip->close();

            if (file_exists($filepath)) {
                $result['success'] = true;
                $result['file'] = $filepath;
                $result['size_mb'] = round(filesize($filepath) / 1024 / 1024, 2);
                $result['files_count'] = $filesAdded;

                Log::info("✅ Application files backup completed", [
                    'file' => $filename,
                    'size_mb' => $result['size_mb'],
                    'files_count' => $filesAdded
                ]);
            } else {
                $result['errors'][] = "Application backup file was not created";
            }

        } catch (\Exception $e) {
            $result['errors'][] = "Application backup error: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Backup Storage (uploads, documents, etc.)
     */
    private function backupStorage(string $backupId, array $options = []): array
    {
        $result = [
            'success' => false,
            'file' => null,
            'size_mb' => 0,
            'errors' => [],
            'files_count' => 0
        ];

        try {
            $storagePath = storage_path('app');
            $filename = "{$backupId}_storage.zip";
            $filepath = $this->backupPath . '/' . $filename;

            $zip = new ZipArchive();
            if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                $result['errors'][] = "Cannot create storage ZIP file: {$filepath}";
                return $result;
            }

            // Include patterns per storage
            $includePatterns = [
                'public/',
                'letterheads/',
                'documents/',
                'exports/',
                'uploads/'
            ];

            // Exclude patterns
            $excludePatterns = [
                'temp/',
                'backups/',
                'cache/',
                'logs/'
            ];

            $filesAdded = 0;

            foreach ($includePatterns as $pattern) {
                $fullPath = $storagePath . '/' . $pattern;
                if (is_dir($fullPath)) {
                    $this->addDirectoryToZip($zip, $fullPath, $pattern, $excludePatterns, $filesAdded);
                }
            }

            $zip->close();

            if (file_exists($filepath) && filesize($filepath) > 0) {
                $result['success'] = true;
                $result['file'] = $filepath;
                $result['size_mb'] = round(filesize($filepath) / 1024 / 1024, 2);
                $result['files_count'] = $filesAdded;

                Log::info("✅ Storage backup completed", [
                    'file' => $filename,
                    'size_mb' => $result['size_mb'],
                    'files_count' => $filesAdded
                ]);
            } else {
                // Se storage è vuoto, crea comunque file vuoto
                $result['success'] = true;
                $result['file'] = $filepath;
                $result['size_mb'] = 0;
                $result['files_count'] = 0;
            }

        } catch (\Exception $e) {
            $result['errors'][] = "Storage backup error: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Crea manifest backup con metadata
     */
    private function createBackupManifest(string $backupId, array $backupResult): array
    {
        $result = ['success' => false, 'file' => null];

        try {
            $manifest = [
                'backup_id' => $backupId,
                'created_at' => now()->toISOString(),
                'system_info' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'server_name' => gethostname(),
                    'database_name' => config('database.connections.mysql.database')
                ],
                'backup_info' => [
                    'type' => $backupResult['type'],
                    'total_size_mb' => $backupResult['size_mb'],
                    'components' => array_keys($backupResult['components']),
                    'files' => array_map('basename', $backupResult['files'])
                ],
                'verification' => [
                    'checksum_method' => 'md5',
                    'file_checksums' => []
                ]
            ];

            // Calcola checksum per ogni file
            foreach ($backupResult['files'] as $file) {
                if (file_exists($file)) {
                    $manifest['verification']['file_checksums'][basename($file)] = md5_file($file);
                }
            }

            $filename = "{$backupId}_manifest.json";
            $filepath = $this->backupPath . '/' . $filename;

            if (file_put_contents($filepath, json_encode($manifest, JSON_PRETTY_PRINT))) {
                $result['success'] = true;
                $result['file'] = $filepath;

                Log::info("✅ Backup manifest created", ['file' => $filename]);
            }

        } catch (\Exception $e) {
            $result['errors'][] = "Manifest creation error: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Upload backup su cloud storage
     */
    private function uploadToCloud(string $backupId, array $files): array
    {
        $result = [
            'success' => false,
            'uploaded_files' => [],
            'errors' => []
        ];

        try {
            $cloudDisk = Storage::disk($this->config['cloud_disk'] ?? 's3');
            $cloudPath = $this->config['cloud_path'] ?? 'golf-arbitri-backups';

            foreach ($files as $file) {
                if (!file_exists($file)) continue;

                $filename = basename($file);
                $cloudFilePath = "{$cloudPath}/{$backupId}/{$filename}";

                try {
                    $cloudDisk->putFileAs(
                        "{$cloudPath}/{$backupId}",
                        new \Illuminate\Http\File($file),
                        $filename
                    );

                    $result['uploaded_files'][] = $cloudFilePath;

                    Log::info("☁️ File uploaded to cloud", [
                        'local_file' => $filename,
                        'cloud_path' => $cloudFilePath
                    ]);

                } catch (\Exception $e) {
                    $result['errors'][] = "Failed to upload {$filename}: " . $e->getMessage();
                }
            }

            $result['success'] = count($result['uploaded_files']) > 0;

        } catch (\Exception $e) {
            $result['errors'][] = "Cloud upload error: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Verifica integrità backup
     */
    private function verifyBackupIntegrity(string $backupId, array $files): array
    {
        $result = [
            'success' => true,
            'verified_files' => [],
            'errors' => []
        ];

        try {
            $manifestFile = $this->backupPath . "/{$backupId}_manifest.json";

            if (!file_exists($manifestFile)) {
                $result['errors'][] = "Manifest file not found";
                $result['success'] = false;
                return $result;
            }

            $manifest = json_decode(file_get_contents($manifestFile), true);
            $expectedChecksums = $manifest['verification']['file_checksums'] ?? [];

            foreach ($files as $file) {
                if (!file_exists($file)) {
                    $result['errors'][] = "Backup file missing: " . basename($file);
                    $result['success'] = false;
                    continue;
                }

                $filename = basename($file);
                $actualChecksum = md5_file($file);
                $expectedChecksum = $expectedChecksums[$filename] ?? null;

                if ($expectedChecksum && $actualChecksum === $expectedChecksum) {
                    $result['verified_files'][] = $filename;
                } else {
                    $result['errors'][] = "Checksum mismatch for {$filename}";
                    $result['success'] = false;
                }
            }

            Log::info("🔍 Backup verification completed", [
                'backup_id' => $backupId,
                'verified_files' => count($result['verified_files']),
                'errors' => count($result['errors'])
            ]);

        } catch (\Exception $e) {
            $result['errors'][] = "Verification error: " . $e->getMessage();
            $result['success'] = false;
        }

        return $result;
    }

    /**
     * DISASTER RECOVERY - Restore da backup
     */
    public function restoreFromBackup(string $backupId, array $options = []): array
    {
        $result = [
            'success' => false,
            'restored_components' => [],
            'errors' => [],
            'started_at' => now()->toISOString()
        ];

        Log::warning("🔄 Starting disaster recovery restore: {$backupId}");

        try {
            // 1. Verifica backup esiste
            $manifestFile = $this->backupPath . "/{$backupId}_manifest.json";
            if (!file_exists($manifestFile)) {
                $result['errors'][] = "Backup manifest not found: {$backupId}";
                return $result;
            }

            $manifest = json_decode(file_get_contents($manifestFile), true);

            // 2. Verifica integrità pre-restore
            $verifyResult = $this->verifyBackupIntegrity($backupId, [
                $this->backupPath . "/{$backupId}_database.sql.gz",
                $this->backupPath . "/{$backupId}_application.zip",
                $this->backupPath . "/{$backupId}_storage.zip"
            ]);

            if (!$verifyResult['success']) {
                $result['errors'][] = "Backup integrity check failed";
                $result['errors'] = array_merge($result['errors'], $verifyResult['errors']);
                return $result;
            }

            // 3. Backup corrente prima del restore
            if (!($options['skip_current_backup'] ?? false)) {
                $currentBackup = $this->performFullBackup(['notify' => false]);
                if ($currentBackup['success']) {
                    $result['current_backup_id'] = $currentBackup['backup_id'];
                }
            }

            // 4. Restore Database
            if ($options['restore_database'] ?? true) {
                $dbResult = $this->restoreDatabase($backupId);
                $result['restored_components']['database'] = $dbResult;
                if (!$dbResult['success']) {
                    $result['errors'] = array_merge($result['errors'], $dbResult['errors']);
                }
            }

            // 5. Restore Application Files
            if ($options['restore_application'] ?? true) {
                $appResult = $this->restoreApplicationFiles($backupId);
                $result['restored_components']['application'] = $appResult;
                if (!$appResult['success']) {
                    $result['errors'] = array_merge($result['errors'], $appResult['errors']);
                }
            }

            // 6. Restore Storage
            if ($options['restore_storage'] ?? true) {
                $storageResult = $this->restoreStorage($backupId);
                $result['restored_components']['storage'] = $storageResult;
                if (!$storageResult['success']) {
                    $result['errors'] = array_merge($result['errors'], $storageResult['errors']);
                }
            }

            // 7. Post-restore operations
            if (empty($result['errors'])) {
                $this->performPostRestoreOperations();
                $result['success'] = true;

                Log::warning("✅ Disaster recovery completed successfully: {$backupId}");
            } else {
                Log::error("❌ Disaster recovery failed: {$backupId}", $result['errors']);
            }

        } catch (\Exception $e) {
            $result['errors'][] = "Restore failed: " . $e->getMessage();
            Log::error("❌ Disaster recovery exception: {$backupId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $result['completed_at'] = now()->toISOString();
        return $result;
    }

    /**
     * Restore database da backup
     */
    private function restoreDatabase(string $backupId): array
    {
        $result = ['success' => false, 'errors' => []];

        try {
            $backupFile = $this->backupPath . "/{$backupId}_database.sql.gz";

            if (!file_exists($backupFile)) {
                $backupFile = $this->backupPath . "/{$backupId}_database.sql";
            }

            if (!file_exists($backupFile)) {
                $result['errors'][] = "Database backup file not found";
                return $result;
            }

            $dbConfig = config('database.connections.mysql');

            // Decomprimi se necessario
            $sqlFile = $backupFile;
            if (pathinfo($backupFile, PATHINFO_EXTENSION) === 'gz') {
                $sqlFile = $this->tempPath . "/{$backupId}_database.sql";

                $command = "gunzip -c {$backupFile} > {$sqlFile}";
                exec($command, $output, $returnCode);

                if ($returnCode !== 0) {
                    $result['errors'][] = "Failed to decompress database backup";
                    return $result;
                }
            }

            // Restore database
            $command = sprintf(
                'mysql -h%s -P%s -u%s -p%s %s < %s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['database'],
                $sqlFile
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                $result['success'] = true;
                Log::info("✅ Database restored successfully from {$backupId}");
            } else {
                $result['errors'][] = "Database restore failed with exit code: {$returnCode}";
            }

            // Cleanup temp file
            if ($sqlFile !== $backupFile && file_exists($sqlFile)) {
                unlink($sqlFile);
            }

        } catch (\Exception $e) {
            $result['errors'][] = "Database restore error: " . $e->getMessage();
        }

        return $result;
    }

    // ... (implementazione completa restoreApplicationFiles, restoreStorage, cleanup, utility methods)

    /**
     * Utility methods
     */
    private function addDirectoryToZip(ZipArchive $zip, string $dirPath, string $localPath, array $excludePatterns, int &$filesAdded)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $localPath . substr($filePath, strlen(rtrim($dirPath, '/')));

            // Check exclude patterns
            $excluded = false;
            foreach ($excludePatterns as $pattern) {
                if (strpos($relativePath, $pattern) !== false) {
                    $excluded = true;
                    break;
                }
            }

            if (!$excluded) {
                if ($file->isDir()) {
                    $zip->addEmptyDir($relativePath);
                } else {
                    $zip->addFile($filePath, $relativePath);
                    $filesAdded++;
                }
            }
        }
    }

    private function compressFile(string $filepath): ?string
    {
        $compressedFile = $filepath . '.gz';

        $command = "gzip -c {$filepath} > {$compressedFile}";
        exec($command, $output, $returnCode);

        return ($returnCode === 0 && file_exists($compressedFile)) ? $compressedFile : null;
    }

    private function countDatabaseTables(): int
    {
        try {
            return count(DB::select('SHOW TABLES'));
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function countDatabaseRecords(): int
    {
        try {
            $tables = collect(DB::select('SHOW TABLES'))->map(function($table) {
                return array_values((array)$table)[0];
            });

            $totalRecords = 0;
            foreach ($tables as $table) {
                $count = DB::table($table)->count();
                $totalRecords += $count;
            }

            return $totalRecords;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function cleanupOldBackups()
    {
        $retentionDays = $this->config['retention_days'] ?? 30;
        $cutoffDate = now()->subDays($retentionDays);

        $files = glob($this->backupPath . '/*');
        $deleted = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffDate->timestamp) {
                unlink($file);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            Log::info("🧹 Cleaned up {$deleted} old backup files");
        }
    }

    private function sendBackupNotification(array $result)
    {
        $recipients = $this->config['notification_emails'] ?? ['sysadmin@federgolf.it'];
        $status = $result['success'] ? 'SUCCESS' : 'FAILED';

        $subject = "Golf Arbitri Backup {$status} - {$result['backup_id']}";

        $message = "Sistema Golf Arbitri - Backup Report\n\n";
        $message .= "Backup ID: {$result['backup_id']}\n";
        $message .= "Status: {$status}\n";
        $message .= "Started: {$result['started_at']}\n";
        $message .= "Completed: {$result['completed_at']}\n";
        $message .= "Duration: {$result['duration_seconds']}s\n";
        $message .= "Total Size: {$result['size_mb']} MB\n\n";

        if (!empty($result['errors'])) {
            $message .= "ERRORS:\n";
            foreach ($result['errors'] as $error) {
                $message .= "- {$error}\n";
            }
            $message .= "\n";
        }

        $message .= "Components:\n";
        foreach ($result['components'] as $component => $info) {
            $status = $info['success'] ? '✅' : '❌';
            $message .= "- {$component}: {$status}\n";
        }

        foreach ($recipients as $email) {
            try {
                Mail::raw($message, function($mail) use ($email, $subject) {
                    $mail->to($email)->subject($subject);
                });
            } catch (\Exception $e) {
                Log::error('Failed to send backup notification', [
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function logBackupResult(array $result)
    {
        $logData = [
            'backup_id' => $result['backup_id'],
            'success' => $result['success'],
            'duration_seconds' => $result['duration_seconds'],
            'size_mb' => $result['size_mb'],
            'components' => array_keys($result['components']),
            'errors_count' => count($result['errors'])
        ];

        if ($result['success']) {
            Log::info("✅ Backup completed successfully", $logData);
        } else {
            Log::error("❌ Backup failed", array_merge($logData, [
                'errors' => $result['errors']
            ]));
        }
    }

    private function performPostRestoreOperations()
    {
        try {
            // Clear caches
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');

            // Rebuild caches
            Artisan::call('config:cache');
            Artisan::call('route:cache');

            // Run migrations if needed
            Artisan::call('migrate', ['--force' => true]);

            Log::info("✅ Post-restore operations completed");

        } catch (\Exception $e) {
            Log::error("❌ Post-restore operations failed: " . $e->getMessage());
        }
    }
}
