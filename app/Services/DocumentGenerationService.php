<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Tournament;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\LetterTemplate;
use App\Models\Letterhead;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class DocumentGenerationService
{
    /**
     * Generate convocation letter for an assignment
     */
    public function generateConvocationLetter(Assignment $assignment): string
    {
        try {
            // Load relationships
            $assignment->load([
                'user',
                'tournament.club',
                'tournament.zone',
                'tournament.tournamentCategory',
                'assignedBy'
            ]);

            // Get template
            $template = $this->getTemplate('convocation', $assignment->tournament);

            // Prepare variables
            $variables = $this->getConvocationVariables($assignment);

            // Create document
            $phpWord = new PhpWord();
            $this->configureDocument($phpWord);

            // Add letterhead
            $section = $phpWord->addSection();
            $this->addLetterhead($section, $assignment->tournament->zone_id);

            // Add content
            $this->addDocumentContent($section, $template, $variables);

            // Add footer
            $this->addFooter($section, $assignment->tournament->zone);

            // Save document
            $filename = $this->generateFilename('convocation', $assignment);
            $path = $this->saveDocument($phpWord, $filename);

            // Update assignment
            $assignment->tournament->update([
                'convocation_file_path' => $path,
                'convocation_file_name' => $filename,
                'convocation_generated_at' => Carbon::now(),
            ]);

            return $path;

        } catch (\Exception $e) {
            Log::error('Error generating convocation letter', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate club letter for a tournament
     */
    public function generateClubLetter(Tournament $tournament): string
    {
        try {
            // Load relationships
            $tournament->load([
                'club',
                'zone',
                'tournamentCategory',
                'assignedReferees'
            ]);

            // Get template
            $template = $this->getTemplate('club', $tournament);

            // Prepare variables
            $variables = $this->getClubLetterVariables($tournament);

            // Create document
            $phpWord = new PhpWord();
            $this->configureDocument($phpWord);

            // Add letterhead
            $section = $phpWord->addSection();
            $this->addLetterhead($section, $tournament->zone_id);

            // Add content
            $this->addDocumentContent($section, $template, $variables);

            // Add referee list
            $this->addRefereeList($section, $tournament);

            // Add footer
            $this->addFooter($section, $tournament->zone);

            // Save document
            $filename = $this->generateFilename('club_letter', $tournament);
            $path = $this->saveDocument($phpWord, $filename);

            // Update tournament
            $tournament->update([
                'club_letter_file_path' => $path,
                'club_letter_file_name' => $filename,
                'club_letter_generated_at' => Carbon::now(),
                'documents_last_updated_by' => auth()->id(),
            ]);

            $tournament->incrementDocumentVersion();

            return $path;

        } catch (\Exception $e) {
            Log::error('Error generating club letter', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
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

    /**
     * Get template for document type
     */
    protected function getTemplate(string $type, Tournament $tournament): ?LetterTemplate
    {
        return LetterTemplate::active()
            ->ofType($type)
            ->forZone($tournament->zone_id)
            ->forCategory($tournament->tournament_category_id)
            ->orderBy('tournament_category_id', 'desc') // Prefer category-specific
            ->orderBy('zone_id', 'desc') // Then zone-specific
            ->first();
    }

    /**
     * Get convocation variables
     */
    protected function getConvocationVariables(Assignment $assignment): array
    {
        $tournament = $assignment->tournament;
        $referee = $assignment->user;

        return [
            'referee_name' => $referee->name,
            'referee_code' => $referee->referee_code,
            'referee_level' => ucfirst($referee->level),
            'tournament_name' => $tournament->name,
            'tournament_dates' => $tournament->date_range,
            'tournament_category' => $tournament->tournamentCategory->name,
            'club_name' => $tournament->club->name,
            'club_address' => $tournament->club->full_address,
            'club_phone' => $tournament->club->phone,
            'club_email' => $tournament->club->email,
            'zone_name' => $tournament->zone->name,
            'role' => $assignment->role,
            'assignment_notes' => $assignment->notes,
            'assigned_date' => $assignment->assigned_at->format('d/m/Y'),
            'assigned_by_id' => $assignment->assignedBy->name,
            'current_date' => Carbon::now()->format('d/m/Y'),
            'current_year' => Carbon::now()->year,
        ];
    }

    /**
     * Get club letter variables
     */
    protected function getClubLetterVariables(Tournament $tournament): array
    {
        return [
            'tournament_name' => $tournament->name,
            'tournament_dates' => $tournament->date_range,
            'tournament_category' => $tournament->tournamentCategory->name,
            'club_name' => $tournament->club->name,
            'contact_person' => $tournament->club->contact_person,
            'zone_name' => $tournament->zone->name,
            'total_referees' => $tournament->assignedReferees->count(),
            'current_date' => Carbon::now()->format('d/m/Y'),
            'current_year' => Carbon::now()->year,
        ];
    }

    /**
     * Configure document settings
     */
    protected function configureDocument(PhpWord $phpWord): void
    {
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        // Document properties
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('Golf Referee System');
        $properties->setCompany('Federazione Italiana Golf');
        $properties->setTitle('Comunicazione Ufficiale');
        $properties->setSubject('Arbitraggio Tornei Golf');
    }

/**
 * Add letterhead to section - VERSIONE CORRETTA
 */
protected function addLetterhead($section, $zoneId): void
{
    // Get active letterhead for zone
    $letterhead = \App\Models\Letterhead::where('zone_id', $zoneId)
        ->where('is_active', true)
        ->orderBy('is_default', 'desc')
        ->first();

    if (!$letterhead) {
        // Use default letterhead
        $letterhead = \App\Models\Letterhead::whereNull('zone_id')
            ->where('is_default', true)
            ->first();
    }

    if ($letterhead && $letterhead->logo_path) {
        // Debug - aggiungi temporaneamente per verificare
        \Log::info('Letterhead processing', [
            'letterhead_id' => $letterhead->id,
            'logo_path' => $letterhead->logo_path,
            'full_path' => storage_path('app/public/' . $letterhead->logo_path),
            'file_exists' => file_exists(storage_path('app/public/' . $letterhead->logo_path)),
        ]);

        $logoPath = storage_path('app/public/' . $letterhead->logo_path);

        if (file_exists($logoPath)) {
            // ✅ INTESTAZIONE A INIZIO DOCUMENTO (non header)
            $section->addImage($logoPath, [
                'width' => 550,           // Larghezza quasi completa
                'height' => 80,           // Altezza intestazione
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                'marginTop' => 0,
                'marginBottom' => 300,    // Spazio dopo l'intestazione
            ]);

            // Aggiungi uno spazio dopo l'intestazione
            $section->addTextBreak(1);

        } else {
            \Log::warning('Logo file not found: ' . $logoPath);
        }
    }

    // Fallback testuale se non c'è logo
    if (!$letterhead || !$letterhead->logo_path) {
        if ($letterhead && $letterhead->header_content) {
            $section->addText($letterhead->header_content, [
                'bold' => true,
                'size' => 14,
            ], [
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
            ]);
            $section->addTextBreak(2);
        }
    }
}


    /**
     * Add document content
     */
    protected function addDocumentContent($section, ?LetterTemplate $template, array $variables): void
    {
        if (!$template) {
            // Use default content
            $this->addDefaultContent($section, $variables);
            return;
        }

        // Replace variables in template
        $content = $template->body;
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        // Add content to document
        Html::addHtml($section, nl2br($content));
    }

    /**
     * Add default content when no template exists
     */
    protected function addDefaultContent($section, array $variables): void
    {
        $section->addText('CONVOCAZIONE ARBITRO', ['bold' => true, 'size' => 14], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $section->addTextBreak(2);

        $section->addText("Gentile {$variables['referee_name']},", ['bold' => true]);
        $section->addTextBreak();

        $section->addText("Con la presente La informiamo che è stato/a designato/a per arbitrare il seguente torneo:");
        $section->addTextBreak();

        $section->addText("Torneo: {$variables['tournament_name']}", ['bold' => true]);
        $section->addText("Date: {$variables['tournament_dates']}");
        $section->addText("Categoria: {$variables['tournament_category']}");
        $section->addText("Circolo: {$variables['club_name']}");
        $section->addText("Indirizzo: {$variables['club_address']}");
        $section->addText("Ruolo: {$variables['role']}");
        $section->addTextBreak();

        $section->addText("La preghiamo di confermare la Sua presenza contattando direttamente il circolo ospitante.");
        $section->addTextBreak();

        $section->addText("Cordiali saluti,");
        $section->addText("Comitato Regionale Arbitri");
    }

    /**
     * Add referee list to club letter
     */
    protected function addRefereeList($section, Tournament $tournament): void
    {
        if ($tournament->assignedReferees->isEmpty()) {
            return;
        }

        $section->addTextBreak();
        $section->addText('ARBITRI DESIGNATI:', ['bold' => true, 'size' => 12]);
        $section->addTextBreak();

        // Create table
        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 80
        ]);

        // Header row
        $table->addRow();
        $table->addCell(3000)->addText('Nome', ['bold' => true]);
        $table->addCell(2000)->addText('Codice', ['bold' => true]);
        $table->addCell(2000)->addText('Livello', ['bold' => true]);
        $table->addCell(2000)->addText('Ruolo', ['bold' => true]);

        // Data rows
        foreach ($tournament->assignments as $assignment) {
            $table->addRow();
            $table->addCell(3000)->addText($assignment->user->name);
            $table->addCell(2000)->addText($assignment->user->referee_code);
            $table->addCell(2000)->addText(ucfirst($assignment->user->level));
            $table->addCell(2000)->addText($assignment->role);
        }
    }

    /**
     * Add footer to section
     */
    protected function addFooter($section, $zone): void
    {
        $footer = $section->addFooter();

        $footer->addText(
            "Comitato Regionale Arbitri - {$zone->name}",
            ['size' => 9],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );

        $footer->addText(
            "Documento generato il " . Carbon::now()->format('d/m/Y \a\l\l\e H:i'),
            ['size' => 8, 'italic' => true],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
    }

    /**
     * Generate filename
     */
    protected function generateFilename(string $type, $model): string
    {
        $date = Carbon::now()->format('Ymd');

        if ($model instanceof Assignment) {
            $referee = str_replace(' ', '_', $model->user->name);
            $tournament = str_replace(' ', '_', $model->tournament->name);
            return "{$type}_{$date}_{$referee}_{$tournament}.docx";
        }

        if ($model instanceof Tournament) {
            $tournament = str_replace(' ', '_', $model->name);
            return "{$type}_{$date}_{$tournament}.docx";
        }

        return "{$type}_{$date}.docx";
    }

    /**
     * Save document to storage
     */
    protected function saveDocument(PhpWord $phpWord, string $filename): string
    {
        $path = 'documents/' . Carbon::now()->format('Y/m') . '/' . $filename;

        // Create directory if not exists
        Storage::makeDirectory('public/documents/' . Carbon::now()->format('Y/m'));

        // Save document
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save(storage_path('app/public/' . $path));

        return $path;
    }
}
