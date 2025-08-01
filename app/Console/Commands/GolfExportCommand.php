<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Zone;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use App\Models\Availability;
use Carbon\Carbon;

/**
 * Comando per export dati Golf
 */
class GolfExportCommand extends Command
{
    protected $signature = 'golf:export
                            {type : Tipo di export (zones, users, tournaments, all)}
                            {--zone= : Esporta solo una zona specifica}
                            {--format=json : Formato export (json, csv, excel)}
                            {--output= : File di output personalizzato}';

    protected $description = 'Esporta dati del sistema Golf in vari formati';

    public function handle(): int
    {
        $type = $this->argument('type');
        $zone = $this->option('zone');
        $format = $this->option('format');
        $output = $this->option('output');

        $this->info("📤 Export {$type} in formato {$format}...");

        try {
            $data = $this->collectData($type, $zone);
            $filename = $this->exportData($data, $type, $format, $output);

            $this->info("✅ Export completato: {$filename}");
            $this->info("📊 Record esportati: " . count($data));

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Errore durante export: " . $e->getMessage());
            return 1;
        }
    }

    private function collectData(string $type, ?string $zone): array
    {
        $zoneId = $zone ? Zone::where('code', $zone)->value('id') : null;

        return match($type) {
            'zones' => Zone::all()->toArray(),
            'users' => $this->getUsersData($zoneId),
            'tournaments' => $this->getTournamentsData($zoneId),
            'all' => $this->getAllData($zoneId),
            default => throw new \InvalidArgumentException("Tipo export non supportato: {$type}")
        };
    }

    private function getUsersData(?int $zoneId): array
    {
        $query = User::with('zone');
        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }
        return $query->get()->toArray();
    }

    private function getTournamentsData(?int $zoneId): array
    {
        $query = Tournament::with(['zone', 'club', 'tournamentType']);
        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }
        return $query->get()->toArray();
    }

    private function getAllData(?int $zoneId): array
    {
        return [
            'zones' => Zone::all()->toArray(),
            'users' => $this->getUsersData($zoneId),
            'tournaments' => $this->getTournamentsData($zoneId),
            'availabilities' => Availability::with('tournament', 'referee')->get()->toArray(),
            'assignments' => Assignment::with('tournament', 'referee')->get()->toArray(),
        ];
    }

    private function exportData(array $data, string $type, string $format, ?string $output): string
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = $output ?: "golf_export_{$type}_{$timestamp}.{$format}";

        switch ($format) {
            case 'json':
                Storage::put($filename, json_encode($data, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->exportToCsv($data, $filename);
                break;
            case 'excel':
                $this->exportToExcel($data, $filename);
                break;
            default:
                throw new \InvalidArgumentException("Formato non supportato: {$format}");
        }

        return $filename;
    }

    private function exportToCsv(array $data, string $filename): void
    {
        // CSV export implementation
        $csv = fopen('php://temp', 'w+');

        if (!empty($data)) {
            $headers = array_keys($data[0]);
            fputcsv($csv, $headers);

            foreach ($data as $row) {
                fputcsv($csv, $row);
            }
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        Storage::put($filename, $content);
    }

    private function exportToExcel(array $data, string $filename): void
    {
        // Excel export would require additional packages like PhpSpreadsheet
        throw new \Exception("Excel export non ancora implementato");
    }
}

