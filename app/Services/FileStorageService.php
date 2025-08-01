<?php

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

/**
 * Service per la gestione centralizzata dei file
 * VERSIONE AGGIORNATA per storage basato su zone
 * Struttura: public/notification/{zone_name}/{file_name}
 */
class FileStorageService
{
    /**
     * Ottiene nome zona normalizzato per il path
     */
    public function getZoneFolderName(Tournament $tournament): string
    {
        if (!$tournament->club || !$tournament->club->zone) {
            throw new \Exception('Torneo non ha zona associata');
        }

        // Normalizza il nome della zona (SZR1, SZR2, ..., CRC)
        $zoneName = $tournament->club->zone->name;

        // Se il nome zona non è già nel formato corretto, crealo
        if (!preg_match('/^(SZR[1-7]|CRC)$/', $zoneName)) {
            // Fallback: usa l'ID della zona
            $zoneId = $tournament->club->zone->id;
            $zoneName = "SZR{$zoneId}";
        }

        return $zoneName;
    }

    /**
     * Genera path fisso per convocazione (NON CAMBIA mai)
     */
    public function getConvocationPath(Tournament $tournament): string
    {
        $zoneFolder = $this->getZoneFolderName($tournament);
        $tournamentSlug = \Str::slug($tournament->name, '_');
        $filename = "convocazione_{$tournament->id}_{$tournamentSlug}.docx";
        return "notification/{$zoneFolder}/{$filename}";
    }

    /**
     * Genera path fisso per lettera circolo (NON CAMBIA mai)
     */
    public function getClubLetterPath(Tournament $tournament): string
    {
        $zoneFolder = $this->getZoneFolderName($tournament);
        $tournamentSlug = \Str::slug($tournament->name, '_');
        $filename = "lettera_circolo_{$tournament->id}_{$tournamentSlug}.docx";
        return "notification/{$zoneFolder}/{$filename}";
    }

    /**
     * Controlla se file convocazione esiste
     */
    public function convocationExists(Tournament $tournament): bool
    {
        $path = $this->getConvocationPath($tournament);
        return Storage::disk('public')->exists($path);
    }

    /**
     * Controlla se file lettera circolo esiste
     */
    public function clubLetterExists(Tournament $tournament): bool
    {
        $path = $this->getClubLetterPath($tournament);
        return Storage::disk('public')->exists($path);
    }

    /**
     * Salva convocazione per torneo
     */
    public function storeConvocation(string $content, Tournament $tournament): string
    {
        try {
            $relativePath = $this->getConvocationPath($tournament);
            $zoneFolder = $this->getZoneFolderName($tournament);

            // Crea directory se non esiste
            Storage::disk('public')->makeDirectory("notification/{$zoneFolder}");

            // Salva file (sovrascrive se esiste)
            Storage::disk('public')->put($relativePath, $content);

            // Aggiorna record torneo
            $filename = basename($relativePath);
            $tournament->update([
                'convocation_file_path' => $relativePath,
                'convocation_file_name' => $filename,
                'convocation_generated_at' => now(),
            ]);

            Log::info('Convocazione salvata', [
                'tournament_id' => $tournament->id,
                'zone' => $zoneFolder,
                'file_path' => $relativePath
            ]);

            return $relativePath;

        } catch (\Exception $e) {
            Log::error('Errore salvataggio convocazione', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Salva lettera circolo per torneo
     */
    public function storeClubLetter(string $content, Tournament $tournament): string
    {
        try {
            $relativePath = $this->getClubLetterPath($tournament);
            $zoneFolder = $this->getZoneFolderName($tournament);

            // Crea directory se non esiste
            Storage::disk('public')->makeDirectory("notification/{$zoneFolder}");

            // Salva file (sovrascrive se esiste)
            Storage::disk('public')->put($relativePath, $content);

            // Aggiorna record torneo
            $filename = basename($relativePath);
            $tournament->update([
                'club_letter_file_path' => $relativePath,
                'club_letter_file_name' => $filename,
                'club_letter_generated_at' => now(),
            ]);

            Log::info('Lettera circolo salvata', [
                'tournament_id' => $tournament->id,
                'zone' => $zoneFolder,
                'file_path' => $relativePath
            ]);

            return $relativePath;

        } catch (\Exception $e) {
            Log::error('Errore salvataggio lettera circolo', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Elimina convocazione per torneo
     */
    public function deleteConvocation(Tournament $tournament): bool
    {
        try {
            $relativePath = $this->getConvocationPath($tournament);
            $deleted = false;

            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
                $deleted = true;
            }

            // Pulisci record torneo
            $tournament->update([
                'convocation_file_path' => null,
                'convocation_file_name' => null,
                'convocation_generated_at' => null,
            ]);

            Log::info('Convocazione eliminata', [
                'tournament_id' => $tournament->id,
                'file_path' => $relativePath,
                'deleted' => $deleted
            ]);

            return $deleted;

        } catch (\Exception $e) {
            Log::error('Errore eliminazione convocazione', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Elimina lettera circolo per torneo
     */
    public function deleteClubLetter(Tournament $tournament): bool
    {
        try {
            $relativePath = $this->getClubLetterPath($tournament);
            $deleted = false;

            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
                $deleted = true;
            }

            // Pulisci record torneo
            $tournament->update([
                'club_letter_file_path' => null,
                'club_letter_file_name' => null,
                'club_letter_generated_at' => null,
            ]);

            Log::info('Lettera circolo eliminata', [
                'tournament_id' => $tournament->id,
                'file_path' => $relativePath,
                'deleted' => $deleted
            ]);

            return $deleted;

        } catch (\Exception $e) {
            Log::error('Errore eliminazione lettera circolo', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Upload file convocazione modificata (mantiene stesso nome)
     */
    public function uploadModifiedConvocation(UploadedFile $file, Tournament $tournament): string
    {
        try {
            $relativePath = $this->getConvocationPath($tournament);
            $zoneFolder = $this->getZoneFolderName($tournament);

            // Crea directory se non esiste
            Storage::disk('public')->makeDirectory("notification/{$zoneFolder}");

            // Salva file sovrascrivendo quello esistente
            $content = file_get_contents($file->getRealPath());
            Storage::disk('public')->put($relativePath, $content);

            // Aggiorna record torneo
            $tournament->update([
                'convocation_file_path' => $relativePath,
                'convocation_file_name' => basename($relativePath),
                'convocation_generated_at' => now(),
            ]);

            Log::info('Convocazione modificata caricata', [
                'tournament_id' => $tournament->id,
                'zone' => $zoneFolder,
                'file_path' => $relativePath,
                'original_filename' => $file->getClientOriginalName()
            ]);

            return $relativePath;

        } catch (\Exception $e) {
            Log::error('Errore upload convocazione modificata', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Upload file lettera circolo modificata (mantiene stesso nome)
     */
    public function uploadModifiedClubLetter(UploadedFile $file, Tournament $tournament): string
    {
        try {
            $relativePath = $this->getClubLetterPath($tournament);
            $zoneFolder = $this->getZoneFolderName($tournament);

            // Crea directory se non esiste
            Storage::disk('public')->makeDirectory("notification/{$zoneFolder}");

            // Salva file sovrascrivendo quello esistente
            $content = file_get_contents($file->getRealPath());
            Storage::disk('public')->put($relativePath, $content);

            // Aggiorna record torneo
            $tournament->update([
                'club_letter_file_path' => $relativePath,
                'club_letter_file_name' => basename($relativePath),
                'club_letter_generated_at' => now(),
            ]);

            Log::info('Lettera circolo modificata caricata', [
                'tournament_id' => $tournament->id,
                'zone' => $zoneFolder,
                'file_path' => $relativePath,
                'original_filename' => $file->getClientOriginalName()
            ]);

            return $relativePath;

        } catch (\Exception $e) {
            Log::error('Errore upload lettera circolo modificata', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Ottieni URL pubblico per download
     */
    public function getDownloadUrl(string $filePath): string
    {
        return Storage::disk('public')->url($filePath);
    }

    /**
     * Ottieni path completo del file
     */
    public function getFullPath(string $relativePath): string
    {
        return storage_path('app/public/' . $relativePath);
    }

    /**
     * Controlla se file esiste
     */
    public function fileExists(string $relativePath): bool
    {
        return Storage::disk('public')->exists($relativePath);
    }

    /**
     * Ottieni informazioni file
     */
    public function getFileInfo(string $relativePath): array
    {
        if (!$this->fileExists($relativePath)) {
            return ['exists' => false];
        }

        try {
            return [
                'exists' => true,
                'path' => $relativePath,
                'full_path' => $this->getFullPath($relativePath),
                'size' => Storage::disk('public')->size($relativePath),
                'last_modified' => Storage::disk('public')->lastModified($relativePath),
                'mime_type' => Storage::disk('public')->mimeType($relativePath),
                'url' => $this->getDownloadUrl($relativePath),
                'size_human' => $this->formatBytes(Storage::disk('public')->size($relativePath))
            ];
        } catch (\Exception $e) {
            Log::error('Errore ottenimento info file', [
                'path' => $relativePath,
                'error' => $e->getMessage()
            ]);
            return ['exists' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Controlla stato documenti per un torneo
     */
    public function getDocumentStatus(Tournament $tournament): array
    {
        $convocationPath = $this->getConvocationPath($tournament);
        $clubLetterPath = $this->getClubLetterPath($tournament);

        return [
            'convocation' => [
                'exists' => $this->fileExists($convocationPath),
                'path' => $convocationPath,
                'filename' => basename($convocationPath),
                'generated_at' => $tournament->convocation_generated_at,
                'display_name' => "Convocazione SZR - {$tournament->name}",
                'info' => $this->getFileInfo($convocationPath)
            ],
            'club_letter' => [
                'exists' => $this->fileExists($clubLetterPath),
                'path' => $clubLetterPath,
                'filename' => basename($clubLetterPath),
                'generated_at' => $tournament->club_letter_generated_at,
                'display_name' => "Lettera Circolo - {$tournament->name}",
                'info' => $this->getFileInfo($clubLetterPath)
            ]
        ];
    }

    /**
     * Lista tutti i file per una zona
     */
    public function listZoneFiles(string $zoneName): array
    {
        try {
            $directory = "notification/{$zoneName}";

            if (!Storage::disk('public')->exists($directory)) {
                return [];
            }

            $files = Storage::disk('public')->files($directory);
            $result = [];

            foreach ($files as $file) {
                $info = $this->getFileInfo($file);
                if ($info['exists']) {
                    $result[] = $info;
                }
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Errore lista file zona', [
                'zone' => $zoneName,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Crea backup di un file esistente
     */
    public function backupFile(string $relativePath): ?string
    {
        if (!$this->fileExists($relativePath)) {
            return null;
        }

        try {
            $pathInfo = pathinfo($relativePath);
            $backupPath = $pathInfo['dirname'] . '/backup_' .
                         now()->format('Ymd_His') . '_' .
                         $pathInfo['basename'];

            Storage::disk('public')->copy($relativePath, $backupPath);

            Log::info('Backup file creato', [
                'original' => $relativePath,
                'backup' => $backupPath
            ]);

            return $backupPath;

        } catch (\Exception $e) {
            Log::error('Errore creazione backup', [
                'file' => $relativePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Pulisci file vecchi per una zona
     */
    public function cleanupOldFiles(string $zoneName, int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);
        $deletedCount = 0;

        try {
            $directory = "notification/{$zoneName}";

            if (!Storage::disk('public')->exists($directory)) {
                return 0;
            }

            $files = Storage::disk('public')->files($directory);

            foreach ($files as $file) {
                // Non eliminare file di backup o file principali
                if (strpos(basename($file), 'backup_') !== false) {
                    continue;
                }

                $lastModified = Storage::disk('public')->lastModified($file);

                if ($lastModified < $cutoffDate->timestamp) {
                    Storage::disk('public')->delete($file);
                    $deletedCount++;

                    Log::info('File vecchio eliminato', [
                        'file' => $file,
                        'last_modified' => date('Y-m-d H:i:s', $lastModified)
                    ]);
                }
            }

            Log::info('Cleanup completato', [
                'zone' => $zoneName,
                'days_old' => $daysOld,
                'deleted_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Errore cleanup file', [
                'zone' => $zoneName,
                'error' => $e->getMessage()
            ]);
        }

        return $deletedCount;
    }

    /**
     * Ottieni statistiche storage per zona
     */
    public function getZoneStorageStats(string $zoneName): array
    {
        try {
            $directory = "notification/{$zoneName}";

            if (!Storage::disk('public')->exists($directory)) {
                return [
                    'zone' => $zoneName,
                    'exists' => false,
                    'total_files' => 0,
                    'total_size' => 0,
                    'total_size_human' => '0 B'
                ];
            }

            $files = Storage::disk('public')->files($directory);
            $totalSize = 0;
            $fileTypes = [];

            foreach ($files as $file) {
                $size = Storage::disk('public')->size($file);
                $totalSize += $size;

                $extension = pathinfo($file, PATHINFO_EXTENSION);
                $fileTypes[$extension] = ($fileTypes[$extension] ?? 0) + 1;
            }

            return [
                'zone' => $zoneName,
                'exists' => true,
                'total_files' => count($files),
                'total_size' => $totalSize,
                'total_size_human' => $this->formatBytes($totalSize),
                'file_types' => $fileTypes,
                'directory' => $directory
            ];

        } catch (\Exception $e) {
            Log::error('Errore statistiche zona', [
                'zone' => $zoneName,
                'error' => $e->getMessage()
            ]);

            return [
                'zone' => $zoneName,
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verifica integrità file per un torneo
     */
    public function verifyTournamentFiles(Tournament $tournament): array
    {
        $issues = [];

        // Verifica convocazione
        if ($tournament->convocation_file_path) {
            if (!$this->fileExists($tournament->convocation_file_path)) {
                $issues[] = "File convocazione mancante: {$tournament->convocation_file_path}";
            } else {
                $expectedPath = $this->getConvocationPath($tournament);
                if ($tournament->convocation_file_path !== $expectedPath) {
                    $issues[] = "Path convocazione non standard: {$tournament->convocation_file_path} (dovrebbe essere: {$expectedPath})";
                }
            }
        }

        // Verifica lettera circolo
        if ($tournament->club_letter_file_path) {
            if (!$this->fileExists($tournament->club_letter_file_path)) {
                $issues[] = "File lettera circolo mancante: {$tournament->club_letter_file_path}";
            } else {
                $expectedPath = $this->getClubLetterPath($tournament);
                if ($tournament->club_letter_file_path !== $expectedPath) {
                    $issues[] = "Path lettera circolo non standard: {$tournament->club_letter_file_path} (dovrebbe essere: {$expectedPath})";
                }
            }
        }

        return [
            'tournament_id' => $tournament->id,
            'zone' => $this->getZoneFolderName($tournament),
            'has_issues' => !empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Formatta dimensione in bytes in formato leggibile
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Crea tutte le directory zone se non esistono
     */
    public function initializeZoneDirectories(): array
    {
        $zones = ['SZR1', 'SZR2', 'SZR3', 'SZR4', 'SZR5', 'SZR6', 'SZR7', 'CRC'];
        $created = [];

        foreach ($zones as $zone) {
            $directory = "notification/{$zone}";

            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
                $created[] = $zone;

                Log::info('Directory zona creata', ['zone' => $zone, 'path' => $directory]);
            }
        }

        return $created;
    }

        /**
     * Salva file nella zona corretta
     */
    public function storeInZone($fileData, Tournament $tournament, $extension)
    {
        $zone = $this->getZoneName($tournament);
        $filename = $fileData['filename'];

        // Path secondo struttura esistente
        $relativePath = "convocationi/{$zone}/generated/{$filename}";

        // Copia file dalla posizione temporanea
        Storage::disk('public')->put($relativePath, file_get_contents($fileData['path']));

        // Elimina file temporaneo
        unlink($fileData['path']);

        return $relativePath;
    }

    /**
     * Recupera nome zona normalizzato
     */
    private function getZoneName(Tournament $tournament): string
    {
        $zoneId = $tournament->club->zone_id;

        return match($zoneId) {
            1 => 'SZR1',
            2 => 'SZR2',
            3 => 'SZR3',
            4 => 'SZR4',
            5 => 'SZR5',
            6 => 'SZR6',
            7 => 'SZR7',
            8 => 'CRC',
            default => 'SZR' . $zoneId
        };
    }



}
