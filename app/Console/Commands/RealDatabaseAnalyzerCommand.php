<?php

/**
 * ========================================
 * ANALISI STRUTTURA REALE DATABASE
 * ========================================
 * Comando per scoprire la struttura REALE del database gestione_arbitri
 * invece di fare assunzioni sbagliate
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RealDatabaseAnalyzerCommand extends Command
{
    protected $signature = 'golf:analyze-real-structure';
    protected $description = 'Analizza la struttura REALE del database gestione_arbitri';

    public function handle()
    {
        $this->info('🔍 ANALISI STRUTTURA REALE DATABASE gestione_arbitri');
        $this->info('==================================================');

        // Setup connessione
        $this->setupOldDatabaseConnection();

        // 1. Analizza struttura zones
        $this->analyzeZonesStructure();

        // 2. Analizza relazione users-zones
        $this->analyzeUsersZonesRelation();

        // 3. Analizza tournament_types struttura
        $this->analyzeTournamentTypesStructure();

        // 4. Analizza clubs struttura
        $this->analyzeClubsStructure();

        // 5. Analizza tournaments struttura
        $this->analyzeTournamentsStructure();

        // 6. Analizza fixed_addresses
        $this->analyzeFixedAddresses();

        // 7. Analizza availabilities e assignments
        $this->analyzeAvailabilitiesAssignments();

        return 0;
    }

    private function analyzeZonesStructure()
    {
        $this->info('📍 ANALISI ZONES STRUTTURA');
        $this->info('========================');

        try {
            // Struttura tabella zones
            $columns = DB::connection('old')->select("DESCRIBE zones");
            $this->info('Colonne tabella zones:');
            foreach ($columns as $col) {
                $this->info("   - {$col->Field} ({$col->Type}) {$col->Null} {$col->Key} {$col->Default}");
            }

            // Dati zones
            $zones = DB::connection('old')->table('zones')->get();
            $this->info('Dati zones:');
            foreach ($zones as $zone) {
                $this->info("   ID: {$zone->id} | Name: {$zone->name} | is_national: " . ($zone->is_national ?? 'NULL'));
            }

        } catch (\Exception $e) {
            $this->error('Errore analisi zones: ' . $e->getMessage());
        }
    }

    private function analyzeUsersZonesRelation()
    {
        $this->info('👥 ANALISI RELAZIONE USERS-ZONES');
        $this->info('===============================');

        try {
            // Verifica campo zone_id in users
            $userColumns = DB::connection('old')->select("DESCRIBE users");
            $this->info('Colonne tabella users (zone-related):');
            foreach ($userColumns as $col) {
                if (strpos(strtolower($col->Field), 'zone') !== false) {
                    $this->info("   - {$col->Field} ({$col->Type}) {$col->Null} {$col->Default}");
                }
            }

            // Distribuzione users per zone
            $usersByZone = DB::connection('old')->table('users')
                ->select('zone_id', DB::raw('COUNT(*) as user_count'))
                ->groupBy('zone_id')
                ->orderBy('zone_id')
                ->get();

            $this->info('Distribuzione users per zone_id:');
            foreach ($usersByZone as $stat) {
                $this->info("   Zone {$stat->zone_id}: {$stat->user_count} users");
            }

            // Sample users con zone
            $sampleUsers = DB::connection('old')->table('users')
                ->select('id', 'name', 'zone_id')
                ->limit(10)
                ->get();

            $this->info('Sample users con zone_id:');
            foreach ($sampleUsers as $user) {
                $this->info("   User {$user->id} ({$user->name}): zone_id = {$user->zone_id}");
            }

        } catch (\Exception $e) {
            $this->error('Errore analisi users-zones: ' . $e->getMessage());
        }
    }

    private function analyzeTournamentTypesStructure()
    {
        $this->info('🏆 ANALISI TOURNAMENT_TYPES STRUTTURA');
        $this->info('====================================');

        try {
            // Verifica se esiste
            $exists = $this->tableExists('tournament_types');
            $this->info("Tabella tournament_types esiste: " . ($exists ? 'SÌ' : 'NO'));

            if ($exists) {
                // Struttura
                $columns = DB::connection('old')->select("DESCRIBE tournament_types");
                $this->info('Colonne tabella tournament_types:');
                foreach ($columns as $col) {
                    $this->info("   - {$col->Field} ({$col->Type}) {$col->Null} {$col->Default}");
                }

                // Sample data
                $sample = DB::connection('old')->table('tournament_types')->limit(3)->get();
                $this->info('Sample tournament_types:');
                foreach ($sample as $type) {
                    $this->info("   ID: {$type->id} | Name: {$type->name}");
                    $this->info("   Tutti i campi: " . json_encode($type));
                }
            }

        } catch (\Exception $e) {
            $this->error('Errore analisi tournament_types: ' . $e->getMessage());
        }
    }

    private function analyzeClubsStructure()
    {
        $this->info('🏌️ ANALISI CLUBS STRUTTURA');
        $this->info('=========================');

        try {
            $exists = $this->tableExists('clubs');
            $this->info("Tabella clubs esiste: " . ($exists ? 'SÌ' : 'NO'));

            if ($exists) {
                $count = DB::connection('old')->table('clubs')->count();
                $this->info("Record clubs: {$count}");

                // Struttura
                $columns = DB::connection('old')->select("DESCRIBE clubs");
                $this->info('Colonne clubs:');
                foreach ($columns as $col) {
                    $this->info("   - {$col->Field} ({$col->Type})");
                }

                // Sample
                $sample = DB::connection('old')->table('clubs')->limit(2)->get();
                foreach ($sample as $club) {
                    $this->info("   Club: {$club->name} | Zone: {$club->zone_id}");
                }
            }

        } catch (\Exception $e) {
            $this->error('Errore analisi clubs: ' . $e->getMessage());
        }
    }

    private function analyzeTournamentsStructure()
    {
        $this->info('🏆 ANALISI TOURNAMENTS STRUTTURA');
        $this->info('===============================');

        try {
            $exists = $this->tableExists('tournaments');
            $this->info("Tabella tournaments esiste: " . ($exists ? 'SÌ' : 'NO'));

            if ($exists) {
                $count = DB::connection('old')->table('tournaments')->count();
                $this->info("Record tournaments: {$count}");

                // Struttura
                $columns = DB::connection('old')->select("DESCRIBE tournaments");
                $this->info('Colonne tournaments:');
                foreach ($columns as $col) {
                    $this->info("   - {$col->Field} ({$col->Type})");
                }

                // Sample
                if ($count > 0) {
                    $sample = DB::connection('old')->table('tournaments')->limit(2)->get();
                    foreach ($sample as $tournament) {
                        $this->info("   Tournament: {$tournament->name}");
                        $this->info("   Campi: " . json_encode($tournament));
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error('Errore analisi tournaments: ' . $e->getMessage());
        }
    }

    private function analyzeFixedAddresses()
    {
        $this->info('📧 ANALISI FIXED_ADDRESSES');
        $this->info('=========================');

        try {
            $exists = $this->tableExists('fixed_addresses');
            $this->info("Tabella fixed_addresses esiste: " . ($exists ? 'SÌ' : 'NO'));

            if ($exists) {
                $count = DB::connection('old')->table('fixed_addresses')->count();
                $this->info("Record fixed_addresses: {$count}");

                // Struttura
                $columns = DB::connection('old')->select("DESCRIBE fixed_addresses");
                $this->info('Colonne fixed_addresses:');
                foreach ($columns as $col) {
                    $this->info("   - {$col->Field} ({$col->Type})");
                }

                // Sample
                if ($count > 0) {
                    $sample = DB::connection('old')->table('fixed_addresses')->limit(3)->get();
                    foreach ($sample as $addr) {
                        $this->info("   Fixed Address: " . json_encode($addr));
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error('Errore analisi fixed_addresses: ' . $e->getMessage());
        }
    }

    private function analyzeAvailabilitiesAssignments()
    {
        $this->info('📅 ANALISI AVAILABILITIES & ASSIGNMENTS');
        $this->info('======================================');

        try {
            // Availabilities
            $availExists = $this->tableExists('availabilities');
            $availCount = $availExists ? DB::connection('old')->table('availabilities')->count() : 0;
            $this->info("Tabella availabilities: " . ($availExists ? "SÌ ({$availCount} record)" : 'NO'));

            if ($availExists && $availCount > 0) {
                $columns = DB::connection('old')->select("DESCRIBE availabilities");
                $this->info('Colonne availabilities:');
                foreach ($columns as $col) {
                    $this->info("   - {$col->Field} ({$col->Type})");
                }
            }

            // Assignments
            $assignExists = $this->tableExists('assignments');
            $assignCount = $assignExists ? DB::connection('old')->table('assignments')->count() : 0;
            $this->info("Tabella assignments: " . ($assignExists ? "SÌ ({$assignCount} record)" : 'NO'));

            if ($assignExists && $assignCount > 0) {
                $columns = DB::connection('old')->select("DESCRIBE assignments");
                $this->info('Colonne assignments:');
                foreach ($columns as $col) {
                    $this->info("   - {$col->Field} ({$col->Type})");
                }
            }

        } catch (\Exception $e) {
            $this->error('Errore analisi availabilities/assignments: ' . $e->getMessage());
        }
    }

    private function tableExists($table): bool
    {
        try {
            DB::connection('old')->table($table)->limit(1)->get();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function setupOldDatabaseConnection()
    {
        $oldDbConfig = [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => 'gestione_arbitri',
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
        ];

        config(['database.connections.old' => $oldDbConfig]);
    }
}
