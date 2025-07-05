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
            $template = $this->getTemplate('circle', $tournament);

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
            'circle_name' => $tournament->club->name,
            'circle_address' => $tournament->club->full_address,
            'circle_phone' => $tournament->club->phone,
            'circle_email' => $tournament->club->email,
            'zone_name' => $tournament->zone->name,
            'role' => $assignment->role,
            'assignment_notes' => $assignment->notes,
            'assigned_date' => $assignment->assigned_at->format('d/m/Y'),
            'assigned_by' => $assignment->assignedBy->name,
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
            'circle_name' => $tournament->club->name,
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
     * Add letterhead to section
     */
    protected function addLetterhead($section, $zoneId): void
    {
        // Get active letterhead for zone
        $letterhead = Letterhead::where('zone_id', $zoneId)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->first();

        if (!$letterhead) {
            // Use default letterhead
            $letterhead = Letterhead::whereNull('zone_id')
                ->where('is_default', true)
                ->first();
        }

        if ($letterhead) {
            // Add header
            $header = $section->addHeader();

            // Add logo if exists
            if ($letterhead->logo_path && Storage::exists('public/' . $letterhead->logo_path)) {
                $header->addImage(
                    storage_path('app/public/' . $letterhead->logo_path),
                    [
                        'width' => 100,
                        'height' => 50,
                        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT
                    ]
                );
            }

            // Add header text
            if ($letterhead->header_content) {
                Html::addHtml($header, $letterhead->header_content);
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
        $section->addText("Circolo: {$variables['circle_name']}");
        $section->addText("Indirizzo: {$variables['circle_address']}");
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
