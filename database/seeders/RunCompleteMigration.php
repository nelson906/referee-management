<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Services\AssignmentMigrationService;

class RunCompleteMigration extends Seeder
{
    public function run()
    {
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════╗\n";
        echo "║           MIGRAZIONE COMPLETA MULTI-ANNO              ║\n";
        echo "╚═══════════════════════════════════════════════════════╝\n";

        // FASE 1: Crea tournaments_yyyy e gare_yyyy
        $masterSeeder = new MasterMigrationSeeder();
        $masterSeeder->createStandardTournamentsForAllYears();

        // FASE 2: Trasforma gare_yyyy in assignments e availabilities
        $migrationService = new AssignmentMigrationService();

        $years = range(2015, date('Y'));
        $failed = [];

        foreach ($years as $year) {
            echo "\n\n════════════════════════════════════════════════════════\n";
            if (!$migrationService->processYear($year)) {
                $failed[] = $year;
            }
        }

        // Report finale
        echo "\n\n";
        echo "╔═══════════════════════════════════════════════════════╗\n";
        echo "║                  MIGRAZIONE COMPLETATA                ║\n";
        echo "╚═══════════════════════════════════════════════════════╝\n";

        if (empty($failed)) {
            echo "✅ Tutti gli anni migrati con successo!\n";
        } else {
            echo "⚠️  Anni con errori: " . implode(', ', $failed) . "\n";
            echo "   Eseguire manualmente per questi anni.\n";
        }

        // Suggerimenti post-migrazione
        echo "\n📝 PROSSIMI PASSI:\n";
        echo "1. Verificare i dati migrati con:\n";
        echo "   php artisan tinker\n";
        echo "   >>> DB::table('assignments_2025')->count()\n";
        echo "   >>> DB::table('availabilities_2025')->count()\n";
        echo "\n";
        echo "2. Testare il curriculum arbitri\n";
        echo "3. Verificare che il cambio anno funzioni correttamente\n";
        echo "4. Eliminare le tabelle non più usate:\n";
        echo "   - tournaments (singola)\n";
        echo "   - assignments (singola)\n";
        echo "   - availabilities (singola)\n";
    }
}
