<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tournament;

class CheckMappingFixed extends Command
{
    protected $signature = 'golf:check-mapping-fixed {source_db}';
    protected $description = 'Check tournament mapping with flexible field handling';

    public function handle()
    {
        $sourceDb = $this->argument('source_db');
        
        config(['database.connections.source' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT'),
            'database' => $sourceDb,
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        $this->info("🔍 CONTROLLO MAPPING da {$sourceDb}");
        
        // Prima mostra struttura tabella source
        $this->showTableStructure($sourceDb);
        
        // Controlla primi 5 tornei
        $testIds = [1, 2, 3, 4, 5];
        
        foreach ($testIds as $id) {
            $this->info("\n🎯 TORNEO ID: {$id}");
            
            // Dati attuali
            $current = Tournament::find($id);
            if ($current) {
                $this->info("📍 ATTUALE:");
                $this->info("  Nome: {$current->name}");
                $this->info("  Date: {$current->start_date} - {$current->end_date}");
                $this->info("  Club ID: {$current->club_id}");
                $this->info("  Zone ID: {$current->zone_id}");
            } else {
                $this->warn("❌ Torneo ID {$id} NON TROVATO nel database attuale");
            }
            
            // Dati source con gestione campi flessibile
            $source = DB::connection('source')->table('tournaments')->find($id);
            if ($source) {
                $this->info("✅ SOURCE da {$sourceDb}:");
                $this->info("  Nome: " . ($source->name ?? 'N/A'));
                $this->info("  Start: " . ($source->start_date ?? 'N/A'));
                $this->info("  End: " . ($source->end_date ?? 'N/A'));
                $this->info("  Club ID: " . ($source->club_id ?? 'N/A'));
                $this->info("  Zone ID: " . ($source->zone_id ?? 'NON PRESENTE'));
                
                // Mostra TUTTI i campi disponibili
                $this->info("  📋 Altri campi:");
                foreach ((array)$source as $field => $value) {
                    if (!in_array($field, ['name', 'start_date', 'end_date', 'club_id', 'zone_id', 'id'])) {
                        $displayValue = is_string($value) ? substr($value, 0, 30) : $value;
                        $this->info("    {$field}: {$displayValue}");
                    }
                }
            } else {
                $this->warn("❌ Torneo ID {$id} NON TROVATO in {$sourceDb}");
            }
            
            // Confronto solo se entrambi esistono
            if ($current && $source) {
                $nameMatch = $current->name === ($source->name ?? '');
                $status = $nameMatch ? '✅ MATCH' : '❌ DIVERSO';
                $this->info("🔄 Nome: {$status}");
            }
        }
        
        $this->showSummary($sourceDb);
    }
    
    private function showTableStructure($sourceDb)
    {
        try {
            $this->info("\n📋 STRUTTURA TABELLA tournaments in {$sourceDb}:");
            $columns = DB::connection('source')->select("DESCRIBE tournaments");
            
            foreach ($columns as $col) {
                $this->info("  {$col->Field} ({$col->Type})");
            }
        } catch (\Exception $e) {
            $this->warn("Impossibile mostrare struttura: " . $e->getMessage());
        }
    }
    
    private function showSummary($sourceDb)
    {
        $this->info("\n📊 RIEPILOGO:");
        
        try {
            $currentCount = Tournament::count();
            $sourceCount = DB::connection('source')->table('tournaments')->count();
            
            $this->info("Tornei attuali: {$currentCount}");
            $this->info("Tornei in {$sourceDb}: {$sourceCount}");
            
            if ($currentCount !== $sourceCount) {
                $this->warn("⚠️ NUMERI DIVERSI! Potrebbe servire re-importazione completa");
            } else {
                $this->info("✅ Stesso numero di tornei");
            }
            
        } catch (\Exception $e) {
            $this->warn("Errore nel riepilogo: " . $e->getMessage());
        }
    }
}
