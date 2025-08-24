<?php

namespace App\Http\Controllers\Referee;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    protected function getAssignmentTable()
    {
        $year = session('selected_year', date('Y'));
        return "assignments_{$year}";
    }


    /**
     * Display the referee's assignments.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        // Anno di default: corrente
        $year = $request->get('year', now()->year);

        // Query base per le assegnazioni dell'arbitro
        $query = Assignment::where('user_id', $user->id)
            ->with(['tournament.club', 'tournament.zone'])
            ->whereHas('tournament', function ($q) use ($year) {
                $q->whereYear('start_date', $year);
            });

        // Filtro per stato
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'upcoming') {
                $query->whereHas('tournament', function ($q) {
                    $q->where('start_date', '>=', now());
                });
            } elseif ($status === 'completed') {
                $query->whereHas('tournament', function ($q) {
                    $q->where('end_date', '<', now());
                });
            } elseif ($status === 'confirmed') {
                $query->where('is_confirmed', true);
            } elseif ($status === 'pending') {
                $query->where('is_confirmed', false);
            }
        }

        // Ordina per data torneo
        $assignments = $query->orderBy(function ($query) {
            return $query->select('start_date')
                ->from('tournaments')
                ->whereColumn('tournaments.id', 'assignments.tournament_id');
        }, 'desc')
            ->paginate(15)
            ->withQueryString();

        // Statistiche per l'anno corrente
        $stats = [
            'total' => Assignment::where('user_id', $user->id)
                ->whereHas('tournament', function ($q) use ($year) {
                    $q->whereYear('start_date', $year);
                })->count(),

            'confirmed' => Assignment::where('user_id', $user->id)
                ->where('is_confirmed', true)
                ->whereHas('tournament', function ($q) use ($year) {
                    $q->whereYear('start_date', $year);
                })->count(),

            'upcoming' => Assignment::where('user_id', $user->id)
                ->whereHas('tournament', function ($q) use ($year) {
                    $q->where('start_date', '>=', now())
                        ->whereYear('start_date', $year);
                })->count(),

            'completed' => Assignment::where('user_id', $user->id)
                ->whereHas('tournament', function ($q) use ($year) {
                    $q->where('end_date', '<', now())
                        ->whereYear('start_date', $year);
                })->count(),
        ];
        $assignment = new Assignment();
        $tableName = $assignment->getTable();        // Anni disponibili per il filtro
        $availableYears = Assignment::where('user_id', $user->id)
            ->join('tournaments', $tableName . '.tournament_id', '=', 'tournaments.id')
            ->selectRaw('YEAR(tournaments.start_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        return view('referee.assignments.index', compact(
            'assignments',
            'stats',
            'year',
            'availableYears'
        ));
    }

    /**
     * Show assignment details.
     */
    public function show(Assignment $assignment): View
    {
        // Verifica che l'assegnazione appartenga all'arbitro loggato
        if ($assignment->user_id !== auth()->id()) {
            abort(403, 'Non autorizzato a visualizzare questa assegnazione.');
        }

        $assignment->load([
            'tournament.club',
            'tournament.zone',
            'tournament.assignments.user', // Altri arbitri del torneo
        ]);

        return view('referee.assignments.show', compact('assignment'));
    }
}
