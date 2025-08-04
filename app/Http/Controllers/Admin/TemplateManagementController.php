<?php
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
