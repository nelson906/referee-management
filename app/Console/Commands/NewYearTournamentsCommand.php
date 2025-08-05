<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NewYearTournamentsCommand extends Command
{
    protected $signature = 'tournaments:new-year {--force : Forza esecuzione senza conferma}';
    protected $description = 'Prepara il sistema tornei per il nuovo anno';

    public function handle()
    {
        $currentYear = date('Y');
        $previousYear = $currentYear - 1;

        $this->info("🎆 PREPARAZIONE NUOVO ANNO {$currentYear}");

        // Conferma operazione
        if (!$this->option('force')) {
            if (!$this->confirm("Vuoi archiviare tournaments in tournaments_{$previousYear} e preparare per {$currentYear}?")) {
                return;
            }
        }

        // STEP 1: Clona tournaments → tournaments_PREV_YEAR
        $this->cloneTournamentsTable($previousYear);

        // STEP 2: Crea tournaments_CURRENT_YEAR vuota
        $this->createTournamentTableForYear($currentYear);

        // STEP 3: Svuota tournaments per nuovo anno
        $this->truncateMainTable();

        // STEP 4: Aggiorna sessione anno corrente
        session(['selected_year' => $currentYear]);

        $this->info("✅ Sistema pronto per l'anno {$currentYear}!");
    }

    private function cloneTournamentsTable($year)
    {
        $this->info("📋 Clonazione tournaments → tournaments_{$year}...");

        $tableName = "tournaments_{$year}";

        // Se esiste già, chiedi conferma
        if (Schema::hasTable($tableName)) {
            if (!$this->confirm("La tabella {$tableName} esiste già. Sovrascrivere?")) {
                return;
            }
            Schema::dropIfExists($tableName);
        }

        // Clona struttura
        DB::statement("CREATE TABLE {$tableName} LIKE tournaments");

        // Copia dati
        DB::statement("INSERT INTO {$tableName} SELECT * FROM tournaments");

        $count = DB::table($tableName)->count();
        $this->info("✅ Archiviati {$count} tornei in {$tableName}");
    }

    private function createTournamentTableForYear($year)
    {
        $tableName = "tournaments_{$year}";

        if (!Schema::hasTable($tableName)) {
            DB::statement("CREATE TABLE {$tableName} LIKE tournaments");
            $this->info("✅ Creata tabella {$tableName}");
        }
    }

    private function truncateMainTable()
    {
        $this->info("🧹 Pulizia tabella tournaments...");

        // Disabilita temporaneamente foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('tournaments')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info("✅ Tabella tournaments svuotata");
    }
}
