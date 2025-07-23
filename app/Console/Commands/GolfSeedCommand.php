<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GolfSeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'golf:seed
                            {--fresh : Esegue fresh migration prima del seeding}
                            {--reset : Solo reset database senza seeding}
                            {--partial=* : Esegue solo seeder specifici}
                            {--skip-optional : Salta seeder opzionali}
                            {--force : Forza operazione senza conferma}';

    /**
     * The console command description.
     */
    protected $description = 'Gestisce il seeding completo del sistema Golf con opzioni avanzate';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->showHeader();

        // Gestisci opzioni specifiche
        if ($this->option('reset')) {
            return $this->handleReset();
        }

        if ($this->option('partial')) {
            return $this->handlePartialSeed();
        }

        // Seeding completo
        return $this->handleFullSeed();
    }

    /**
     * Mostra header del comando
     */
    private function showHeader(): void
    {
        $this->info('');
        $this->info('⛳ ==========================================');
        $this->info('⛳ SISTEMA GOLF - GESTIONE SEEDING');
        $this->info('⛳ ==========================================');
        $this->info('');
    }

    /**
     * Gestisce reset database
     */
    private function handleReset(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('⚠️  Sei sicuro di voler resettare il database? Tutti i dati verranno persi!')) {
                $this->info('Operazione annullata.');
                return 0;
            }
        }

        $this->info('🔄 Resetting database...');

        try {
            if ($this->option('fresh')) {
                $this->info('🔄 Eseguendo fresh migration...');
                Artisan::call('migrate:fresh', [], $this->getOutput());
            } else {
                $this->resetTables();
            }

            $this->info('✅ Database resettato con successo!');
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Errore durante il reset: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Gestisce seeding parziale
     */
    private function handlePartialSeed(): int
    {
        $requestedSeeders = $this->option('partial');
        $availableSeeders = $this->getAvailableSeeders();

        $this->info('🎯 Eseguendo seeding parziale...');
        $this->info('Seeder richiesti: ' . implode(', ', $requestedSeeders));

        foreach ($requestedSeeders as $seederName) {
            if (!in_array($seederName, array_keys($availableSeeders))) {
                $this->error("❌ Seeder '{$seederName}' non trovato!");
                $this->info('Seeder disponibili: ' . implode(', ', array_keys($availableSeeders)));
                return 1;
            }

            try {
                $this->info("▶️  Eseguendo {$seederName}...");
                Artisan::call("db:seed", ['--class' => $availableSeeders[$seederName]], $this->getOutput());
                $this->info("✅ {$seederName} completato");
            } catch (\Exception $e) {
                $this->error("❌ Errore in {$seederName}: " . $e->getMessage());
                return 1;
            }
        }

        $this->info('🎉 Seeding parziale completato!');
        return 0;
    }

    /**
     * Gestisce seeding completo
     */
    private function handleFullSeed(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('Vuoi procedere con il seeding completo?')) {
                $this->info('Operazione annullata.');
                return 0;
            }
        }

        try {
            // Fresh migration se richiesto
            if ($this->option('fresh')) {
                $this->info('🔄 Eseguendo fresh migration...');
                Artisan::call('migrate:fresh', [], $this->getOutput());
            }

            // Seeding principale
            $this->info('🚀 Avviando seeding completo...');
            $startTime = microtime(true);

            Artisan::call('db:seed', ['--class' => 'DatabaseSeeder'], $this->getOutput());

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            $this->showCompletionStats($executionTime);
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Errore durante il seeding: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Reset manuale delle tabelle
     */
    private function resetTables(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'assignments',
            'availabilities',
            'notifications',
            'letter_templates',
            'tournaments',
            'clubs',
            'users',
            'tournament_types',
            'zones'
        ];

        foreach ($tables as $table) {
            try {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                    $this->info("✅ Tabella {$table} svuotata");
                }
            } catch (\Exception $e) {
                $this->warn("⚠️ Impossibile svuotare {$table}: " . $e->getMessage());
            }
        }

        Schema::enableForeignKeyConstraints();
    }

/**
 * ✅ FIXED: Class resolution con namespace completo
 */
private function getAvailableSeeders(): array
{
    return [
        // ✅ CORE FOUNDATION
        'settings' => \Database\Seeders\SettingsSeeder::class,

        // ✅ MASTER DATA
        'zones' => \Database\Seeders\ZoneSeeder::class,
        'tournament-types' => \Database\Seeders\TournamentTypeSeeder::class,
        'users' => \Database\Seeders\UserSeeder::class,

        // ✅ BUSINESS DATA
        'clubs' => \Database\Seeders\ClubsSeeder::class,
        'tournaments' => \Database\Seeders\TournamentSeeder::class,
        'availabilities' => \Database\Seeders\AvailabilitySeeder::class,
        'assignments' => \Database\Seeders\AssignmentsSeeder::class,

        // ✅ SUPPORT SYSTEMS
        'support-data' => \Database\Seeders\SupportDataSeeder::class,
        'notifications' => \Database\Seeders\NotificationSeeder::class, // ✅ FIXED: namespace completo
    ];
}
    /**
     * Mostra statistiche completamento
     */
    private function showCompletionStats(float $executionTime): void
    {
        $this->info('');
        $this->info('📊 STATISTICHE SEEDING:');
        $this->info("⏱️  Tempo esecuzione: {$executionTime}s");

        // Conta record creati
        $stats = [
            'Zone' => $this->safeCount('zones'),
            'Utenti' => $this->safeCount('users'),
            'Tipologie Tornei' => $this->safeCount('tournament_types'),
            'Circoli' => $this->safeCount('clubs'),
            'Tornei' => $this->safeCount('tournaments'),
            'Disponibilità' => $this->safeCount('availabilities'),
            'Assegnazioni' => $this->safeCount('assignments'),
        ];

        foreach ($stats as $entity => $count) {
            $this->info("📈 {$entity}: {$count}");
        }

        $this->info('');
        $this->info('🎉 SEEDING COMPLETATO CON SUCCESSO!');
        $this->info('');
        $this->info('🔐 CREDENZIALI TEST:');
        $this->info('   Password universale: password123');
        $this->info('   Super Admin: superadmin@golf.it');
        $this->info('   National Admin: crc@golf.it');
        $this->info('   Zone Admin esempio: admin.SZR6@golf.it');
        $this->info('');
        $this->info('📖 Per la documentazione completa:');
        $this->info('   php artisan golf:docs');
        $this->info('');
    }

    /**
     * Conta record in modo sicuro
     */
    private function safeCount(string $table): int
    {
        try {
            return DB::table($table)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Mostra help esteso
     */
    public function showHelp(): void
    {
        $this->info('');
        $this->info('🔧 COMANDI DISPONIBILI:');
        $this->info('');
        $this->info('📦 Seeding completo:');
        $this->info('   php artisan golf:seed');
        $this->info('   php artisan golf:seed --fresh');
        $this->info('');
        $this->info('🔄 Reset database:');
        $this->info('   php artisan golf:seed --reset');
        $this->info('   php artisan golf:seed --reset --fresh');
        $this->info('');
        $this->info('🎯 Seeding parziale:');
        $this->info('   php artisan golf:seed --partial=zones,users');
        $this->info('   php artisan golf:seed --partial=tournaments');
        $this->info('');
        $this->info('⚡ Seeder disponibili:');
        foreach ($this->getAvailableSeeders() as $name => $class) {
            $this->info("   - {$name} ({$class})");
        }
        $this->info('');
        $this->info('💡 Opzioni aggiuntive:');
        $this->info('   --force        : Salta conferme interattive');
        $this->info('   --skip-optional: Salta seeder opzionali');
        $this->info('');
    }
}
