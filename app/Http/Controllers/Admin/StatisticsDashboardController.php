<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use \App\Models\Zone;
use Carbon\Carbon;

class StatisticsDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'disponibilita' => $this->getDisponibilitaStats(),
            'assegnazioni' => $this->getAssegnazioniStats(),
            'presenze_effettive' => $this->getPresenzeEffettiveStats(),
            'durata_per_ruolo' => $this->getDurataPerRuoloStats(),
        ];

            $zones = Zone::orderBy('name')->get();

            return view('admin.statistics.dashboard', compact('stats', 'zones'));
    }

    /**
     * STATISTICA 1: N Disponibilità per zona
     */
    private function getDisponibilitaStats()
    {
        return DB::table('availabilities as a')
            ->join('tournaments as t', 'a.tournament_id', '=', 't.id')
            ->join('zones as z', 't.zone_id', '=', 'z.id')
            ->join('users as u', 'a.user_id', '=', 'u.id')
            ->select([
                'z.name as zona',
                'z.code as codice_zona',
                DB::raw('COUNT(*) as totale_dichiarazioni'),
                DB::raw('COUNT(*) as disponibili'),
                DB::raw('0 as non_disponibili'),
                DB::raw('100 as percentuale_disponibilita'),
            ])
            ->groupBy('z.id', 'z.name', 'z.code')
            ->orderBy('totale_dichiarazioni', 'desc')
            ->get();
    }

    /**
     * STATISTICA 2: N Assegnazioni effettive
     */
    private function getAssegnazioniStats()
    {
        return DB::table('assignments as ass')
            ->join('tournaments as t', 'ass.tournament_id', '=', 't.id')
            ->join('zones as z', 't.zone_id', '=', 'z.id')
            ->join('users as u', 'ass.user_id', '=', 'u.id')
            ->select([
                'z.name as zona',
                'z.code as codice_zona',
                DB::raw('COUNT(*) as totale_assegnazioni'),
                DB::raw('COUNT(CASE WHEN ass.is_confirmed = 1 THEN 1 END) as confermate'),
                DB::raw('COUNT(CASE WHEN ass.is_confirmed = 0 OR ass.is_confirmed IS NULL THEN 1 END) as in_attesa'),
                DB::raw('0 as rifiutate'),
                DB::raw('ROUND(AVG(CASE WHEN ass.is_confirmed = 1 THEN 1 ELSE 0 END) * 100, 1) as tasso_conferma')
            ])
            ->groupBy('z.id', 'z.name', 'z.code')
            ->orderBy('totale_assegnazioni', 'desc')
            ->get();
    }

    /**
     * STATISTICA 3: N Presenze Effettive per arbitro
     */
    private function getPresenzeEffettiveStats()
    {
        return DB::table('assignments as ass')
            ->join('users as u', 'ass.user_id', '=', 'u.id')
            ->join('tournaments as t', 'ass.tournament_id', '=', 't.id')
            ->join('zones as z', 't.zone_id', '=', 'z.id')
            ->where('ass.is_confirmed', 1)
            ->where('t.status', 'completed')
            ->select([
                'u.name as arbitro',
                'u.email',
                'z.name as zona',
                'u.level as livello',
                DB::raw('COUNT(*) as presenze_totali'),
                DB::raw('COUNT(CASE WHEN ass.role = "Direttore di Gara" THEN 1 END) as come_direttore'),
                DB::raw('COUNT(CASE WHEN ass.role = "Arbitro" THEN 1 END) as come_arbitro'),
                DB::raw('COUNT(CASE WHEN ass.role = "Osservatore" THEN 1 END) as come_osservatore'),
                DB::raw('ROUND(COUNT(*) / 12.0, 1) as media_mensile')
            ])
            ->groupBy('u.id', 'u.name', 'u.email', 'z.name', 'u.level')
            ->having('presenze_totali', '>', 0)
            ->orderBy('presenze_totali', 'desc')
            ->limit(50)
            ->get();
    }

    /**
     * STATISTICA 4: Durata media per ruolo
     */
    private function getDurataPerRuoloStats()
    {
        return DB::table('assignments as ass')
            ->join('tournaments as t', 'ass.tournament_id', '=', 't.id')
            ->where('t.status', 'completed')
            ->where('ass.is_confirmed', 1)
            ->select([
                'ass.role as ruolo',
                DB::raw('COUNT(*) as numero_assegnazioni'),
                DB::raw('AVG(DATEDIFF(t.end_date, t.start_date) + 1) as durata_media_giorni'),
                DB::raw('MIN(DATEDIFF(t.end_date, t.start_date) + 1) as durata_minima'),
                DB::raw('MAX(DATEDIFF(t.end_date, t.start_date) + 1) as durata_massima'),
                DB::raw('COUNT(DISTINCT ass.user_id) as arbitri_coinvolti')
            ])
            ->groupBy('ass.role')
            ->orderBy('numero_assegnazioni', 'desc')
            ->get();
    }

    /**
     * API endpoint per statistiche AJAX
     */
    public function apiStats($type)
    {
        switch ($type) {
            case 'disponibilita':
                return response()->json($this->getDisponibilitaStats());
            case 'assegnazioni':
                return response()->json($this->getAssegnazioniStats());
            case 'presenze':
                return response()->json($this->getPresenzeEffettiveStats());
            case 'durata':
                return response()->json($this->getDurataPerRuoloStats());
            default:
                return response()->json(['error' => 'Tipo statistiche non valido'], 400);
        }
    }

    /**
     * Export CSV per tutte le statistiche
     */
    public function exportCsv()
    {
        $filename = 'statistiche_arbitri_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');

            // Disponibilità per zona
            fputcsv($file, ['=== DISPONIBILITÀ PER ZONA ===']);
            fputcsv($file, ['Zona', 'Codice', 'Totale Dichiarazioni', 'Disponibili', 'Non Disponibili', 'Percentuale']);
            foreach ($this->getDisponibilitaStats() as $row) {
                fputcsv($file, [
                    $row->zona,
                    $row->codice_zona,
                    $row->totale_dichiarazioni,
                    $row->disponibili,
                    $row->non_disponibili,
                    $row->percentuale_disponibilita . '%'
                ]);
            }

            fputcsv($file, []);

            // Assegnazioni per zona
            fputcsv($file, ['=== ASSEGNAZIONI PER ZONA ===']);
            fputcsv($file, ['Zona', 'Codice', 'Totale', 'Confermate', 'In Attesa', 'Rifiutate', 'Tasso Conferma']);
            foreach ($this->getAssegnazioniStats() as $row) {
                fputcsv($file, [
                    $row->zona,
                    $row->codice_zona,
                    $row->totale_assegnazioni,
                    $row->confermate,
                    $row->in_attesa,
                    $row->rifiutate,
                    $row->tasso_conferma . '%'
                ]);
            }

            fputcsv($file, []);

            // Durata per ruolo
            fputcsv($file, ['=== DURATA PER RUOLO ===']);
            fputcsv($file, ['Ruolo', 'Numero Assegnazioni', 'Durata Media (giorni)', 'Min', 'Max', 'Arbitri Coinvolti']);
            foreach ($this->getDurataPerRuoloStats() as $row) {
                fputcsv($file, [
                    $row->ruolo,
                    $row->numero_assegnazioni,
                    round($row->durata_media_giorni, 1),
                    $row->durata_minima,
                    $row->durata_massima,
                    $row->arbitri_coinvolti
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
