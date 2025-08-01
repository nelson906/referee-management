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

class DocumentGenerationService
{
    /**
     * Genera documento Word per circolo (facsimile)
     */
    public function generateClubDocument(Tournament $tournament): array
    {
        // Carica template base
        $templatePath = storage_path('app/templates/facsimile_convocazione.docx');
        $template = new TemplateProcessor($templatePath);

        // Prepara dati arbitri
        $arbitri = $tournament->assignments->map(function($assignment) {
            return $assignment->user->name . ' ' . $assignment->role;
        })->implode("\n");

        // Sostituisci variabili
        $template->setValue('circolo_nome', $tournament->club->name);
        $template->setValue('torneo_nome', $tournament->name);
        $template->setValue('torneo_date', $tournament->date_range);
        $template->setValue('arbitri_lista', $arbitri);
        $template->setValue('szr_email', "szr{$tournament->zone_id}@federgolf.it");

        // Nome file secondo convenzione
        $filename = Str::upper(Str::slug($tournament->club->name)) . '-' .
                   Str::slug($tournament->name, '-') . '.docx';

        // Salva temporaneamente
        $tempPath = storage_path('app/temp/' . $filename);
        $template->saveAs($tempPath);

        return [
            'path' => $tempPath,
            'filename' => $filename,
            'type' => 'club_facsimile'
        ];
    }
}
