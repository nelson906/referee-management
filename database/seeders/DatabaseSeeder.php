<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 AVVIO SEEDING COMPLETO SISTEMA GOLF');
        $this->command->info('=====================================');
        $this->command->info('Questo processo creerà dati puliti e strutturati per testing');
        $this->command->info('');

        $startTime = microtime(true);

        // Disabilita controlli foreign key temporaneamente
        Schema::disableForeignKeyConstraints();

try {
            // 1. Zone Geografiche (base del sistema)
            $this->callSeederWithTiming(ZoneSeeder::class);

            // 2. Utenti Amministratori (gestione sistema)
            $this->callSeederWithTiming(UserSeeder::class);

            // 3. Tipologie Tornei (categorie e regole)
            $this->callSeederWithTiming(TournamentTypeSeeder::class);

            // 4. Circoli Golf (location tornei)
            $this->callSeederWithTiming(ClubsSeeder::class);

            // 5. Arbitri (utenti principali del sistema)
            $this->callSeederWithTiming(RefereeSeeder::class);

            // 6. Tornei (eventi da arbitrare)
            $this->callSeederWithTiming(TournamentSeeder::class);

            // 7. Disponibilità Arbitri (workflow principale)
            $this->callSeederWithTiming(AvailabilitySeeder::class);

            // 8. Assegnazioni Arbitri (completamento workflow)
            $this->callSeederWithTiming(AssignmentsSeeder::class);

            // 9. Seeder opzionali
            $this->callOptionalSeeders();

        } catch (\Exception $e) {
            $this->command->error("❌ Errore durante il seeding: " . $e->getMessage());
            throw $e;
        } finally {
            // Riabilita controlli foreign key
            Schema::enableForeignKeyConstraints();
        }

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        $this->showFinalSummary($executionTime);
    }

    /**
     * Esegue un seeder con misurazione del tempo
     */
    private function callSeederWithTiming(string $seederClass): void
    {
        $this->command->info("▶️  Eseguendo {$seederClass}...");

        $start = microtime(true);
        $this->call($seederClass);
        $end = microtime(true);

        $time = round($end - $start, 2);
        $this->command->info("✅ {$seederClass} completato in {$time}s");
        $this->command->info('');
    }

    /**
     * Esegue seeder opzionali
     */
    private function callOptionalSeeders(): void
    {
        $this->command->info('📋 Eseguendo seeder opzionali...');

        // Seeder per notifiche (se esiste)
    if (class_exists('Database\Seeders\NotificationSeeder')) {
        $this->callSeederWithTiming('Database\Seeders\NotificationSeeder');
    }

    if (class_exists('Database\Seeders\LetterTemplateSeeder')) {
        $this->callSeederWithTiming('Database\Seeders\LetterTemplateSeeder');
    }

    if (class_exists('Database\Seeders\SystemConfigSeeder')) {
        $this->callSeederWithTiming('Database\Seeders\SystemConfigSeeder');
    }
    }

    /**
     * Mostra riassunto finale completo
     */
    private function showFinalSummary(float $executionTime): void
    {
        $this->command->info('');
        $this->command->info('🎉 SEEDING COMPLETATO CON SUCCESSO!');
        $this->command->info('=====================================');
        $this->command->info("⏱️  Tempo totale: {$executionTime}s");
        $this->command->info('');

        // Statistiche finali per verifica
        $this->showDatabaseStatistics();

        // Credenziali per testing
        $this->showTestingCredentials();

        // Scenari di test preparati
        $this->showTestingScenarios();

        $this->command->info('=====================================');
        $this->command->info('🚀 Sistema pronto per il testing!');
        $this->command->info('');
    }

    /**
     * Mostra statistiche database finale
     */
    private function showDatabaseStatistics(): void
    {
        $this->command->info('📊 STATISTICHE DATABASE:');

        // Conta record per tabella principale
        $stats = [
            'Zone' => $this->safeCount('zones'),
            'Utenti Totali' => $this->safeCount('users'),
            '  - Admin' => $this->safeCount('users', ['user_type' => 'admin']),
            '  - Arbitri' => $this->safeCount('users', ['user_type' => 'referee']),
            'Tipologie Tornei' => $this->safeCount('tournament_types'),
            'Circoli' => $this->safeCount('clubs'),
            'Tornei' => $this->safeCount('tournaments'),
            'Disponibilità' => $this->safeCount('availabilities'),
            'Assegnazioni' => $this->safeCount('assignments'),
        ];

        foreach ($stats as $label => $count) {
            $this->command->info("   {$label}: {$count}");
        }
        $this->command->info('');
    }

    /**
     * Conta record in modo sicuro
     */
    private function safeCount(string $table, array $where = []): int
    {
        try {
            $query = DB::table($table);

            foreach ($where as $column => $value) {
                $query->where($column, $value);
            }

            return $query->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Mostra credenziali per testing
     */
    private function showTestingCredentials(): void
    {
        $this->command->info('🔐 CREDENZIALI PER TESTING:');
        $this->command->info('Password universale: password123');
        $this->command->info('');
        $this->command->info('👑 SUPER ADMIN:');
        $this->command->info('   Email: superadmin@golf.it');
        $this->command->info('   Accesso: completo a tutto il sistema');
        $this->command->info('');
        $this->command->info('🌍 NATIONAL ADMIN (CRC):');
        $this->command->info('   Email: crc@golf.it');
        $this->command->info('   Accesso: tutti i tornei nazionali e inter-zonali');
        $this->command->info('');
        $this->command->info('📍 ZONE ADMIN (esempi):');
        $this->command->info('   SZR1: admin.SZR1@golf.it (Piemonte-VdA)');
        $this->command->info('   SZR2: admin.SZR2@golf.it (Lombardia)');
        $this->command->info('   SZR6: admin.SZR6@golf.it (Lazio-Abruzzo)');
        $this->command->info('   Accesso: solo dati della propria zona');
        $this->command->info('');
        $this->command->info('⚖️  ARBITRI (esempi per SZR6):');

        // Mostra alcuni arbitri di esempio
        try {
            $sampleReferees = DB::table('users')
                ->join('zones', 'users.zone_id', '=', 'zones.id')
                ->where('users.user_type', 'referee')
                ->where('zones.code', 'SZR6')
                ->select('users.email', 'users.level')
                ->limit(3)
                ->get();

            foreach ($sampleReferees as $referee) {
                $this->command->info("   {$referee->email} ({$referee->level})");
            }
        } catch (\Exception $e) {
            $this->command->info('   (Esempi non disponibili - controllare database)');
        }

        $this->command->info('');
    }

    /**
     * Mostra scenari di test preparati
     */
    private function showTestingScenarios(): void
    {
        $this->command->info('🧪 SCENARI DI TEST PREPARATI:');
        $this->command->info('');

        $this->command->info('✅ SCENARIO 1 - Test Isolamento Zone:');
        $this->command->info('   • Login come admin.SZR6@golf.it');
        $this->command->info('   • Verifica: vedi solo dati zona SZR6');
        $this->command->info('   • Test: tentativo accesso dati SZR1 → errore 403');
        $this->command->info('');

        $this->command->info('✅ SCENARIO 2 - Workflow Disponibilità:');
        $this->command->info('   • Login come arbitro zona SZR6');
        $this->command->info('   • Visualizza tornei aperti della zona');
        $this->command->info('   • Dichiara disponibilità entro deadline');
        $this->command->info('   • Verifica: non vedi tornei altre zone');
        $this->command->info('');

        $this->command->info('✅ SCENARIO 3 - Processo Assegnazioni:');
        $this->command->info('   • Login come admin zona');
        $this->command->info('   • Visualizza tornei "closed" con disponibilità');
        $this->command->info('   • Assegna arbitri rispettando min/max');
        $this->command->info('   • Verifica: solo arbitri con disponibilità');
        $this->command->info('');

        $this->command->info('✅ SCENARIO 4 - Accesso Nazionale:');
        $this->command->info('   • Login come crc@golf.it');
        $this->command->info('   • Dashboard aggregata tutte zone');
        $this->command->info('   • Gestione tornei nazionali');
        $this->command->info('   • Arbitri nazionali disponibili per tutte zone');
        $this->command->info('');

        $this->command->info('✅ SCENARIO 5 - Storico e Reportistica:');
        $this->command->info('   • Tornei completati con assegnazioni storiche');
        $this->command->info('   • Statistiche per zone e arbitri');
        $this->command->info('   • Export dati per analisi');
        $this->command->info('');
    }

    /**
     * Reset specifico per development
     */
    public function resetDatabase(): void
    {
        $this->command->info('🔄 RESET DATABASE IN CORSO...');

        Schema::disableForeignKeyConstraints();

        try {
            // Lista tabelle in ordine di dipendenza (inverso)
            $tables = [
                'assignments',
                'availabilities',
                'tournaments',
                'clubs',
                'users',
                'tournament_types',
                'zones'
            ];

            foreach ($tables as $table) {
                try {
                    DB::table($table)->truncate();
                    $this->command->info("✅ Tabella {$table} svuotata");
                } catch (\Exception $e) {
                    $this->command->warn("⚠️ Impossibile svuotare {$table}: " . $e->getMessage());
                }
            }

        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->command->info('✅ Reset completato - database pulito');
    }
}
