<?php
// Nuovo file: app/Services/AssignmentMigrationService.php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssignmentMigrationService
{
    private $stats = [];

    /**
     * Processa un singolo anno
     */
    public function processYear($year)
    {
        echo "\n🔄 PROCESSING YEAR {$year}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        $sourceTable = "gare_{$year}";

        if (!Schema::hasTable($sourceTable)) {
            echo "❌ Tabella {$sourceTable} non trovata\n";
            return false;
        }

        $this->stats[$year] = [
            'assignments' => 0,
            'availabilities' => 0,
            'errors' => 0,
            'cognomi_only' => 0
        ];

        try {
            // Crea tabelle destinazione
            $this->createYearTables($year);

            // Processa assignments
            $this->processAssignments($year);

            // Processa availabilities
            $this->processAvailabilities($year);

            // Report
            $this->printYearStats($year);

            // Cleanup tabella temporanea
            if ($this->confirmCleanup($year)) {
                DB::statement("DROP TABLE IF EXISTS {$sourceTable}");
                echo "✅ Tabella temporanea {$sourceTable} eliminata\n";
            }

            return true;

        } catch (\Exception $e) {
            echo "❌ ERRORE CRITICO: " . $e->getMessage() . "\n";
            echo "   Linea: " . $e->getLine() . " in " . $e->getFile() . "\n";
            return false;
        }
    }

    /**
     * Crea tabelle assignments_yyyy e availabilities_yyyy
     */
    private function createYearTables($year)
    {
        echo "\n📋 Creazione tabelle per anno {$year}...\n";

        // Assignments
        $assignTable = "assignments_{$year}";
        DB::statement("DROP TABLE IF EXISTS {$assignTable}");
        DB::statement("CREATE TABLE {$assignTable} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tournament_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            assigned_by_id BIGINT UNSIGNED DEFAULT 1,
            role VARCHAR(50) NOT NULL,
            assigned_at TIMESTAMP NULL,
            is_confirmed BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            UNIQUE KEY unique_assignment (tournament_id, user_id, role),
            KEY idx_user (user_id),
            KEY idx_tournament (tournament_id)
        )");
        echo "  ✅ Creata {$assignTable}\n";

        // Availabilities
        $availTable = "availabilities_{$year}";
        DB::statement("DROP TABLE IF EXISTS {$availTable}");
        DB::statement("CREATE TABLE {$availTable} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            tournament_id BIGINT UNSIGNED NOT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            UNIQUE KEY unique_availability (user_id, tournament_id),
            KEY idx_user (user_id),
            KEY idx_tournament (tournament_id)
        )");
        echo "  ✅ Creata {$availTable}\n";
    }

    /**
     * Processa assignments da TD, Arbitri, Osservatori
     */
    private function processAssignments($year)
    {
        echo "\n📋 Processing assignments per {$year}...\n";

        $sourceTable = "gare_{$year}";
        $destTable = "assignments_{$year}";

        $tornei = DB::table($sourceTable)->get();
        $processed = 0;

        foreach ($tornei as $torneo) {
            // Process TD
            if (!empty($torneo->TD)) {
                $this->createAssignment($torneo->id, $torneo->TD, 'Direttore di Torneo', $year, $torneo->Zona);
            }

            // Process Arbitri
            if (!empty($torneo->Arbitri)) {
                $arbitri = explode(',', $torneo->Arbitri);
                foreach ($arbitri as $arbitro) {
                    $this->createAssignment($torneo->id, trim($arbitro), 'Arbitro', $year, $torneo->Zona);
                }
            }

            // Process Osservatori
            if (!empty($torneo->Osservatori)) {
                $osservatori = explode(',', $torneo->Osservatori);
                foreach ($osservatori as $osservatore) {
                    $this->createAssignment($torneo->id, trim($osservatore), 'Osservatore', $year, $torneo->Zona);
                }
            }

            $processed++;
            if ($processed % 50 == 0) {
                echo "  Processati {$processed} tornei...\r";
            }
        }

        echo "\n  ✅ Processati {$processed} tornei\n";
    }

    /**
     * Crea singolo assignment
     */
    private function createAssignment($tournamentId, $fullName, $role, $year, $zona = null)
    {
        $fullName = trim($fullName);
        if (empty($fullName)) return;

        // LOGICA SPECIALE PRE-2021
        if ($year <= 2020) {
            // Solo cognomi in zona SZR6
            if ($zona != 'SZR6') {
                return; // Skip se non è SZR6
            }

            // Cerca per cognome
            $user = DB::table('users')
                ->where('zone_id', 6) // SZR6 ha id=6
                ->where('user_type', 'referee')
                ->where(function($q) use ($fullName) {
                    $q->where('name', 'LIKE', "% {$fullName}")
                      ->orWhere('name', 'LIKE', "{$fullName} %");
                })
                ->first();

            if ($user) {
                $this->stats[$year]['cognomi_only']++;
                echo "  🔍 Match cognome SZR6 ({$year}): '{$fullName}' → '{$user->name}'\n";
            }
        } else {
            // POST-2020: Nome completo
            $user = $this->findUserByFullName($fullName);
        }

        if (!$user) {
            return;
        }

        try {
            DB::table("assignments_{$year}")->insertOrIgnore([
                'tournament_id' => $tournamentId,
                'user_id' => $user->id,
                'assigned_by_id' => 1,
                'role' => $role,
                'assigned_at' => now(),
                'is_confirmed' => true, // Tornei passati sono confermati
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->stats[$year]['assignments']++;

        } catch (\Exception $e) {
            $this->stats[$year]['errors']++;
        }
    }

    /**
     * Processa availabilities da campo Disponibili
     */
    private function processAvailabilities($year)
    {
        echo "\n📅 Processing availabilities per {$year}...\n";

        $sourceTable = "gare_{$year}";
        $tornei = DB::table($sourceTable)
            ->whereNotNull('Disponibili')
            ->where('Disponibili', '!=', '')
            ->get();

        echo "  Trovati {$tornei->count()} tornei con disponibilità\n";

        foreach ($tornei as $torneo) {
            $nomi = explode(',', $torneo->Disponibili);

            foreach ($nomi as $nome) {
                $nome = trim($nome);
                if (empty($nome)) continue;

                // Stessa logica per pre-2021
                if ($year <= 2020 && $torneo->Zona == 'SZR6') {
                    $user = DB::table('users')
                        ->where('zone_id', 6)
                        ->where('user_type', 'referee')
                        ->where('name', 'LIKE', "% {$nome}")
                        ->first();
                } else if ($year > 2020) {
                    $user = $this->findUserByFullName($nome);
                } else {
                    continue; // Skip non-SZR6 pre-2021
                }

                if ($user) {
                    try {
                        DB::table("availabilities_{$year}")->insertOrIgnore([
                            'user_id' => $user->id,
                            'tournament_id' => $torneo->id,
                            'notes' => 'Migrato da CSV',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $this->stats[$year]['availabilities']++;
                    } catch (\Exception $e) {
                        $this->stats[$year]['errors']++;
                    }
                }
            }
        }
    }

    /**
     * Trova utente per nome completo (post-2020)
     */
    private function findUserByFullName($fullName)
    {
        // Prima cerca match diretto
        $user = DB::table('users')
            ->where('name', $fullName)
            ->where('user_type', 'referee')
            ->first();

        if ($user) return $user;

        // Prova inversione Cognome Nome -> Nome Cognome
        $parts = explode(' ', $fullName);
        if (count($parts) == 2) {
            $inverted = $parts[1] . ' ' . $parts[0];
            $user = DB::table('users')
                ->where('name', $inverted)
                ->where('user_type', 'referee')
                ->first();

            if ($user) return $user;
        }

        // Prova match parziale
        $user = DB::table('users')
            ->where('name', 'LIKE', "%{$fullName}%")
            ->where('user_type', 'referee')
            ->first();

        return $user;
    }

    /**
     * Stampa statistiche anno
     */
    private function printYearStats($year)
    {
        $stats = $this->stats[$year];

        echo "\n";
        echo "┌─────────────────────────────────────┐\n";
        echo "│      STATISTICHE ANNO {$year}         │\n";
        echo "├─────────────────────────────────────┤\n";
        echo "│ Assignments creati:    " . str_pad($stats['assignments'], 10, ' ', STR_PAD_LEFT) . " │\n";
        echo "│ Availabilities create: " . str_pad($stats['availabilities'], 10, ' ', STR_PAD_LEFT) . " │\n";

        if ($year <= 2020) {
            echo "│ Match solo cognome:    " . str_pad($stats['cognomi_only'], 10, ' ', STR_PAD_LEFT) . " │\n";
        }

        echo "│ Errori:                " . str_pad($stats['errors'], 10, ' ', STR_PAD_LEFT) . " │\n";
        echo "└─────────────────────────────────────┘\n";
    }

    private function confirmCleanup($year)
    {
        echo "\n⚠️  Eliminare tabella temporanea gare_{$year}? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        return trim($line) === 'y';
    }
}
