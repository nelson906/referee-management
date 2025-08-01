<?php

/**
 * TASK 2: Semplificazione Sistema Template
 *
 * OBIETTIVO: Eliminare confusione tra LetterTemplate e Letterhead
 * TEMPO STIMATO: 3-4 ore
 * COMPLESSITÀ: Media
 *
 * PROBLEMA: Sovrapposizione responsabilità tra modelli
 * SOLUZIONE: Service unificato con logica chiara
 */

namespace App\Services;

use App\Models\LetterTemplate;
use App\Models\Letterhead;
use App\Models\Assignment;
use App\Models\Zone;
use Illuminate\Support\Facades\Log;

class TemplateService
{
    /**
     * METODO PRINCIPALE: Seleziona template ottimale
     * Elimina la logica complessa e confusa attuale
     */
    public function selectBestTemplate(string $type, Assignment $assignment): ?LetterTemplate
    {
        $tournament = $assignment->tournament;
        $zoneId = $tournament->zone_id;

        // 1. Cerca template specifico per zona e tipo torneo
        $template = LetterTemplate::where('type', $type)
            ->where('zone_id', $zoneId)
            ->where('tournament_type_id', $tournament->tournament_type_id)
            ->where('is_active', true)
            ->first();

        if ($template) {
            Log::info("Template selected: Zone+Type specific", [
                'template_id' => $template->id,
                'type' => $type,
                'zone_id' => $zoneId
            ]);
            return $template;
        }

        // 2. Cerca template specifico per zona
        $template = LetterTemplate::where('type', $type)
            ->where('zone_id', $zoneId)
            ->whereNull('tournament_type_id')
            ->where('is_active', true)
            ->first();

        if ($template) {
            Log::info("Template selected: Zone specific", [
                'template_id' => $template->id,
                'type' => $type,
                'zone_id' => $zoneId
            ]);
            return $template;
        }

        // 3. Cerca template default nazionale
        $template = LetterTemplate::where('type', $type)
            ->whereNull('zone_id')
            ->whereNull('tournament_type_id')
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($template) {
            Log::info("Template selected: National default", [
                'template_id' => $template->id,
                'type' => $type
            ]);
            return $template;
        }

        Log::warning("No template found", [
            'type' => $type,
            'zone_id' => $zoneId,
            'tournament_type_id' => $tournament->tournament_type_id
        ]);

        return null;
    }

    /**
     * METODO UNIFICATO: Genera contenuto completo
     * Combina template + letterhead in modo coerente
     */
    public function generateContent(LetterTemplate $template, Assignment $assignment): array
    {
        $variables = $this->prepareVariables($assignment);

        // Sostituisci variabili nel template
        $subject = $this->replaceVariables($template->subject, $variables);
        $body = $this->replaceVariables($template->body, $variables);

        // Ottieni letterhead appropriato
        $letterhead = $this->getLetterhead($assignment->tournament->zone_id);

        return [
            'subject' => $subject,
            'body' => $body,
            'letterhead' => $letterhead,
            'variables_used' => array_keys($variables),
            'template_info' => [
                'id' => $template->id,
                'name' => $template->name,
                'type' => $template->type
            ]
        ];
    }

    /**
     * Prepara variabili per sostituzione
     */
    private function prepareVariables(Assignment $assignment): array
    {
        $tournament = $assignment->tournament;
        $referee = $assignment->user;
        $club = $tournament->club;
        $zone = $tournament->zone;

        return [
            // Arbitro
            'referee_name' => $referee->name,
            'referee_email' => $referee->email,
            'referee_phone' => $referee->phone ?? 'Non specificato',
            'referee_level' => $referee->level ?? 'Standard',

            // Torneo
            'tournament_name' => $tournament->name,
            'tournament_dates' => $tournament->start_date->format('d/m/Y') .
                                 ($tournament->end_date != $tournament->start_date ?
                                  ' - ' . $tournament->end_date->format('d/m/Y') : ''),
            'tournament_category' => $tournament->tournamentCategory->name ?? 'Standard',
            'tournament_type' => $tournament->tournamentType->name ?? 'Generico',

            // Circolo
            'club_name' => $club->name,
            'club_address' => $club->address ?? 'Indirizzo non specificato',
            'club_phone' => $club->phone ?? 'Telefono non specificato',
            'club_email' => $club->email ?? 'Email non specificata',

            // Zona
            'zone_name' => $zone->name,
            'zone_code' => $zone->code,
            'zone_email' => strtolower($zone->code) . '@federgolf.it',

            // Assegnazione
            'assignment_role' => $assignment->role,
            'assignment_notes' => $assignment->notes ?? '',
            'assigned_date' => $assignment->created_at->format('d/m/Y'),
            'assigned_by_id' => $assignment->assignedBy->name ?? 'Sistema',

            // Date e sistema
            'current_date' => now()->format('d/m/Y'),
            'current_year' => now()->year,
        ];
    }

    /**
     * Sostituisce variabili nel testo
     */
    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }

        // Rimuovi variabili non trovate
        $text = preg_replace('/\{\{[^}]+\}\}/', '[Variabile non trovata]', $text);

        return $text;
    }

    /**
     * Ottieni letterhead per zona
     */
    private function getLetterhead(?int $zoneId): ?Letterhead
    {
        if ($zoneId) {
            // Prima cerca letterhead specifico per zona
            $letterhead = Letterhead::where('zone_id', $zoneId)
                ->where('is_active', true)
                ->first();

            if ($letterhead) {
                return $letterhead;
            }
        }

        // Fallback su letterhead nazionale
        return Letterhead::whereNull('zone_id')
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Valida configurazione template sistema
     */
    public function validateTemplateSystem(): array
    {
        $issues = [];

        // Verifica template default esistenti
        $requiredTypes = ['assignment', 'convocation', 'club', 'institutional'];

        foreach ($requiredTypes as $type) {
            $hasDefault = LetterTemplate::where('type', $type)
                ->where('is_default', true)
                ->where('is_active', true)
                ->exists();

            if (!$hasDefault) {
                $issues[] = "Manca template default per tipo: {$type}";
            }
        }

        // Verifica letterhead default
        $hasDefaultLetterhead = Letterhead::whereNull('zone_id')
            ->where('is_default', true)
            ->where('is_active', true)
            ->exists();

        if (!$hasDefaultLetterhead) {
            $issues[] = "Manca letterhead default nazionale";
        }

        // Verifica template duplicati
        $duplicates = LetterTemplate::select('type', 'zone_id', 'tournament_type_id')
            ->selectRaw('COUNT(*) as count')
            ->where('is_active', true)
            ->groupBy('type', 'zone_id', 'tournament_type_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            $issues[] = "Trovati {$duplicates->count()} template duplicati";
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'recommendations' => $this->getRecommendations($issues)
        ];
    }

    private function getRecommendations(array $issues): array
    {
        $recommendations = [];

        if (empty($issues)) {
            $recommendations[] = "Sistema template configurato correttamente";
            return $recommendations;
        }

        foreach ($issues as $issue) {
            if (str_contains($issue, 'template default')) {
                $recommendations[] = "Eseguire: php artisan template:create-defaults";
            }

            if (str_contains($issue, 'letterhead default')) {
                $recommendations[] = "Creare letterhead nazionale in Admin Panel";
            }

            if (str_contains($issue, 'duplicati')) {
                $recommendations[] = "Eseguire: php artisan template:cleanup-duplicates";
            }
        }

        return $recommendations;
    }
}


// CONTROLLER PER GESTIONE TEMPLATE SEMPLIFICATA
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TemplateService;
use App\Models\LetterTemplate;
use App\Models\Zone;
use App\Models\TournamentType;
use Illuminate\Http\Request;

class TemplateManagementController extends Controller
{
    protected $templateService;

    public function __construct(TemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Dashboard gestione template semplificata
     */
    public function index()
    {
        $validation = $this->templateService->validateTemplateSystem();

        $templates = LetterTemplate::with(['zone', 'tournamentType'])
            ->orderBy('type')
            ->orderBy('zone_id')
            ->get()
            ->groupBy('type');

        $zones = Zone::where('is_active', true)->get();
        $tournamentTypes = TournamentType::where('is_active', true)->get();

        return view('admin.templates.management', compact(
            'templates', 'zones', 'tournamentTypes', 'validation'
        ));
    }

    /**
     * Preview template con dati di esempio
     */
    public function preview(LetterTemplate $template)
    {
        // Crea assignment di esempio per preview
        $exampleAssignment = $this->createExampleAssignment();

        try {
            $content = $this->templateService->generateContent($template, $exampleAssignment);

            return view('admin.templates.preview', compact('template', 'content'));
        } catch (\Exception $e) {
            return back()->with('error', 'Errore nella generazione preview: ' . $e->getMessage());
        }
    }

    private function createExampleAssignment()
    {
        // Crea oggetti di esempio per preview
        return (object) [
            'tournament' => (object) [
                'name' => 'Torneo di Esempio',
                'start_date' => now()->addDays(30),
                'end_date' => now()->addDays(31),
                'zone_id' => 1,
                'zone' => (object) ['name' => 'SZR1', 'code' => 'SZR1'],
                'club' => (object) [
                    'name' => 'Golf Club Esempio',
                    'address' => 'Via Golf 123, Roma',
                    'phone' => '06-12345678',
                    'email' => 'info@golfclubexample.it'
                ],
                'tournamentCategory' => (object) ['name' => 'Categoria A'],
                'tournamentType' => (object) ['name' => 'Torneo Zonale']
            ],
            'user' => (object) [
                'name' => 'Mario Rossi',
                'email' => 'mario.rossi@example.com',
                'phone' => '333-1234567',
                'level' => 'Regionale'
            ],
            'role' => 'Direttore di Gara',
            'notes' => 'Note di esempio per l\'assegnazione',
            'created_at' => now(),
            'assignedBy' => (object) ['name' => 'Admin Zona']
        ];
    }
}

// ROUTES DA AGGIUNGERE
/*
Route::middleware(['auth', 'role:Admin|SuperAdmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/templates/management', [TemplateManagementController::class, 'index'])->name('templates.management');
    Route::get('/templates/{template}/preview', [TemplateManagementController::class, 'preview'])->name('templates.preview');
});
*/
