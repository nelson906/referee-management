<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\LetterTemplate;
use App\Models\Letterhead;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FileStorageService
{
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
