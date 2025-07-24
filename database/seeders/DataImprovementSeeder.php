<?php

/**
 * ========================================
 * DATA IMPROVEMENT SEEDER - VERSIONE CORRETTA
 * ========================================
 * File: database/seeders/DataImprovementSeeder.php
 *
 * CORREZIONI APPLICATE:
 * - Fix mapping 'Vero'/'Falso' per is_active
 * - Rimozione record GIOV (non sono referee)
 * - Auto-risoluzione conflitti sicuri
 * - Mapping circoli speciali con TBA virtuale
 * - Gestione errori migliorata
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Helpers\RefereeLevelsHelper;
use Carbon\Carbon;

class DataImprovementSeeder extends Seeder
{
    private $conflicts = [];
    private $improvements = [];
    private $newRecords = [];
    private $removedRecords = [];
    private $dryRun = false;
    private $simulatedTbaClub = null; // ✅ NUOVO: Per simulare TBA in dry-run

    /**
     * ✅ Inizializzazione dry-run
     */
    private function initializeDryRun()
    {
        $this->dryRun = config('seeder.dry_run', false) ||
                       app()->environment('testing');

        if ($this->dryRun) {
            $this->command->info('🔍 MODALITÀ SIMULAZIONE - Nessuna modifica verrà applicata');
        }
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeDryRun();

        $this->command->info('🔄 Inizio migrazione migliorativa dal database reale Sql1466239_4...');

        $this->setupRealDatabaseConnection();

        if (!$this->checkRealDatabase()) {
            $this->command->error('❌ Impossibile connettersi al database Sql1466239_4');
            return;
        }

        $this->command->info('✅ Database verificato, procedo con migrazione migliorativa...');

        $this->improveArbitri();
        $this->improveCircoli();
        $this->improveGare();

        $this->showImprovementReport();
        $this->closeRealDatabaseConnection();
    }

    // ========================================
    // MIGRAZIONE ARBITRI
    // ========================================

    private function improveArbitri()
    {
        $this->command->info('👥 Migrazione migliorativa arbitri...');

        try {
            $realArbitri = DB::connection('real')->table('arbitri')->get();
            $this->command->info("🔍 Trovati " . $realArbitri->count() . " arbitri nel database reale");

            foreach ($realArbitri as $arbitro) {
                $fullName = trim($arbitro->Nome . ' ' . $arbitro->Cognome);

                if (empty($fullName) || $fullName === ' ') {
                    $this->command->warn("   ⚠️ Arbitro senza nome: ID {$arbitro->id}");
                    continue;
                }

                if ($this->isInvalidRefereeLevel($arbitro->Livello_2025)) {
                    $this->handleInvalidRefereeLevel($arbitro, $fullName);
                    continue;
                }

                $existingUser = $this->findExistingArbitro($arbitro, $fullName);

                if ($existingUser) {
                    $this->processExistingArbitro($arbitro, $existingUser, $fullName);
                } else {
                    $this->addNewArbitro($arbitro, $fullName);
                }
            }

        } catch (\Exception $e) {
            $this->command->error("❌ Errore migrazione arbitri: " . $e->getMessage());
        }
    }

    private function findExistingArbitro($arbitro, $fullName)
    {
        $existingUser = DB::table('users')
            ->where('user_type', 'referee')
            ->where('name', 'LIKE', "%{$fullName}%")
            ->first();

        if ($existingUser) {
            $this->command->info("   🔍 Trovato con strategia: LIKE '%{$fullName}%'");
            return $existingUser;
        }

        $existingUser = DB::table('users')
            ->where('user_type', 'referee')
            ->where(function($query) use ($arbitro) {
                $query->where('name', 'LIKE', "%{$arbitro->Nome}%")
                      ->orWhere('name', 'LIKE', "%{$arbitro->Cognome}%");
            })
            ->first();

        if ($existingUser) {
            $this->command->info("   🔍 Trovato con strategia: nome/cognome separati");
        }

        return $existingUser;
    }

    private function isInvalidRefereeLevel($livello): bool
    {
        $invalidLevels = ['GIOV', 'GIOVANI', 'JUNIOR', 'YOUTH'];
        return in_array(strtoupper(trim($livello ?? '')), $invalidLevels);
    }

    private function handleInvalidRefereeLevel($arbitro, $fullName)
    {
        $existingUser = $this->findExistingArbitro($arbitro, $fullName);

        if ($existingUser) {
            $this->removeInvalidRefereeRecord($existingUser->id, $fullName, $arbitro->Livello_2025);
        } else {
            $this->command->warn("   ⚠️ Saltato record non-referee: {$fullName} (Livello: {$arbitro->Livello_2025})");
        }
    }

    private function removeInvalidRefereeRecord($userId, $fullName, $level)
    {
        if ($this->dryRun) {
            $this->command->warn("   [DRY-RUN] Rimozione record non-referee: {$fullName} (Livello: {$level}, ID: {$userId})");
            return;
        }

        try {
            $deletedReferees = DB::table('referees')->where('user_id', $userId)->delete();
            $deletedUsers = DB::table('users')->where('id', $userId)->delete();

            $this->removedRecords[] = [
                'type' => 'non_referee_removed',
                'name' => $fullName,
                'user_id' => $userId,
                'level' => $level
            ];

            $this->command->error("   🗑️ Rimosso record non-referee: {$fullName} (Livello: {$level})");

        } catch (\Exception $e) {
            $this->command->error("   ❌ Errore rimozione record {$fullName}: " . $e->getMessage());
        }
    }

    private function processExistingArbitro($arbitro, $existingUser, $fullName)
    {
        if ($this->autoResolveConflicts($arbitro, $existingUser, $fullName)) {
            return;
        }

        $this->checkArbitroConflicts($arbitro, $existingUser, $fullName);
    }

    private function autoResolveConflicts($arbitro, $existingUser, $fullName): bool
    {
        $safeUpdates = [];

        if (empty($existingUser->phone) && !empty($arbitro->Cellulare)) {
            $safeUpdates['phone'] = $arbitro->Cellulare;
        }

        if (empty($existingUser->city) && !empty($arbitro->Citta)) {
            $safeUpdates['city'] = $arbitro->Citta;
        }

        if (!empty($arbitro->Email) &&
            (strpos($existingUser->email, '@temp.federgolf.it') !== false ||
             empty($existingUser->email))) {
            $safeUpdates['email'] = $arbitro->Email;
        }

        $realLevel = $this->mapLivelloToLevel($arbitro->Livello_2025);
        if ($this->isLevelUpgrade($existingUser->level, $realLevel)) {
            $safeUpdates['level'] = $realLevel;
        }

        $realZoneId = $this->mapZonaToZoneId($arbitro->Zona);
        if (empty($existingUser->zone_id) && !empty($realZoneId)) {
            $safeUpdates['zone_id'] = $realZoneId;
        }

        if (!empty($safeUpdates)) {
            if ($this->dryRun) {
                $this->command->info("   [DRY-RUN] Auto-risoluzione per {$fullName}: " . json_encode($safeUpdates, JSON_UNESCAPED_UNICODE));
            } else {
                DB::table('users')->where('id', $existingUser->id)->update($safeUpdates);
                $this->command->info("   🔧 Auto-risolto: {$fullName} (" . count($safeUpdates) . " campi)");
            }

            $this->improvements[] = [
                'type' => 'arbitro_auto_resolved',
                'name' => $fullName,
                'user_id' => $existingUser->id,
                'improvements' => $safeUpdates
            ];

            $this->checkRefereeExtensionData($arbitro, $existingUser);

            return true;
        }

        return false;
    }

    private function isLevelUpgrade($currentLevel, $newLevel): bool
    {
        $hierarchy = [
            'Aspirante' => 1,
            '1_livello' => 2,
            'Regionale' => 3,
            'Nazionale' => 4,
            'Internazionale' => 5,
            'Archivio' => 0
        ];

        $currentRank = $hierarchy[$currentLevel] ?? 1;
        $newRank = $hierarchy[$newLevel] ?? 1;

        return $newRank > $currentRank;
    }

    private function checkArbitroConflicts($realArbitro, $existingUser, $fullName)
    {
        $conflicts = [];

        if ($this->isDifferent($existingUser->email, $realArbitro->Email) &&
            !empty($existingUser->email) &&
            strpos($existingUser->email, '@temp.federgolf.it') === false) {
            $conflicts['email'] = [
                'existing' => $existingUser->email,
                'real' => $realArbitro->Email,
                'field' => 'Email'
            ];
        }

        if ($this->isDifferent($existingUser->phone, $realArbitro->Cellulare) &&
            !empty($existingUser->phone)) {
            $conflicts['phone'] = [
                'existing' => $existingUser->phone,
                'real' => $realArbitro->Cellulare,
                'field' => 'Telefono'
            ];
        }

        $realLevel = $this->mapLivelloToLevel($realArbitro->Livello_2025);
        if ($this->isDifferent($existingUser->level, $realLevel) &&
            !$this->isLevelUpgrade($existingUser->level, $realLevel)) {
            $conflicts['level'] = [
                'existing' => $existingUser->level,
                'real' => $realLevel,
                'field' => 'Livello',
                'original' => $realArbitro->Livello_2025
            ];
        }

        if (!empty($conflicts)) {
            $this->conflicts[] = [
                'type' => 'arbitro',
                'name' => $fullName,
                'user_id' => $existingUser->id,
                'conflicts' => $conflicts
            ];

            $this->command->warn("   🔥 CONFLITTO: {$fullName} (ID: {$existingUser->id})");
            foreach ($conflicts as $field => $conflict) {
                $this->command->warn("      {$conflict['field']}: '{$conflict['existing']}' vs '{$conflict['real']}'");
            }
        } else {
            $this->checkRefereeExtensionData($realArbitro, $existingUser);
        }
    }

    private function checkRefereeExtensionData($realArbitro, $existingUser)
    {
        $existingReferee = DB::table('referees')->where('user_id', $existingUser->id)->first();

        if (!$existingReferee) {
            $this->createMissingRefereeRecord($realArbitro, $existingUser);
            return;
        }

        $improvements = [];

        if (empty($existingReferee->address) && !empty($realArbitro->Casa)) {
            $improvements['address'] = $realArbitro->Casa;
        }

        if (empty($existingReferee->first_certification_date) && !empty($realArbitro->Prima_Nomina)) {
            $improvements['first_certification_date'] = $this->parseDate($realArbitro->Prima_Nomina);
        }

        if (!empty($improvements)) {
            if ($this->dryRun) {
                $this->command->info("   [DRY-RUN] Miglioramento dati estesi per: {$existingUser->name}");
            } else {
                DB::table('referees')->where('user_id', $existingUser->id)->update($improvements);
                $this->command->info("   ✅ Migliorati dati estesi per: {$existingUser->name}");
            }

            $this->improvements[] = [
                'type' => 'referee_extension',
                'name' => $existingUser->name,
                'user_id' => $existingUser->id,
                'improvements' => $improvements
            ];
        }
    }

    private function addNewArbitro($realArbitro, $fullName)
    {
        $userData = [
            'name' => $fullName,
            'email' => $realArbitro->Email ?? $this->generateEmail($fullName),
            'password' => $realArbitro->Password ?? bcrypt('password123'),
            'user_type' => 'referee',
            'phone' => $realArbitro->Cellulare ?? null,
            'city' => $realArbitro->Citta ?? null,
            'level' => $this->mapLivelloToLevel($realArbitro->Livello_2025),
            'zone_id' => $this->mapZonaToZoneId($realArbitro->Zona),
            'referee_code' => $this->generateRefereeCode(),
            'category' => 'misto',
            'certified_date' => $this->parseDate($realArbitro->Prima_Nomina),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($this->dryRun) {
            $this->command->info("   [DRY-RUN] Nuovo arbitro: {$fullName}");
            $userId = 999;
        } else {
            $userId = DB::table('users')->insertGetId($userData);
        }

        $refereeData = [
            'user_id' => $userId,
            'address' => $realArbitro->Casa ?? null,
            'first_certification_date' => $this->parseDate($realArbitro->Prima_Nomina),
            'profile_completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (!$this->dryRun) {
            DB::table('referees')->insert($refereeData);
        }

        $this->newRecords[] = [
            'type' => 'arbitro',
            'name' => $fullName,
            'id' => $userId
        ];

        $mode = $this->dryRun ? '[DRY-RUN] ' : '';
        $this->command->info("   ➕ {$mode}Aggiunto nuovo arbitro: {$fullName} (ID: {$userId})");
    }

    // ========================================
    // MIGRAZIONE CIRCOLI
    // ========================================

    private function improveCircoli()
    {
        $this->command->info('🏌️ Migrazione migliorativa circoli...');

        try {
            $realCircoli = DB::connection('real')->table('circoli')->get();
            $this->command->info("🔍 Trovati " . $realCircoli->count() . " circoli nel database reale");

            foreach ($realCircoli as $circolo) {
                if (empty($circolo->Circolo_Id)) {
                    $this->command->warn("   ⚠️ Circolo senza ID: {$circolo->Circolo_Nome}");
                    continue;
                }

                $existingClub = DB::table('clubs')->where('code', $circolo->Circolo_Id)->first();

                if ($existingClub) {
                    $this->checkCircoloConflicts($circolo, $existingClub);
                } else {
                    $this->addNewCircolo($circolo);
                }
            }

        } catch (\Exception $e) {
            $this->command->error("❌ Errore migrazione circoli: " . $e->getMessage());
        }
    }

    private function checkCircoloConflicts($realCircolo, $existingClub)
    {
        $improvements = [];

        if (empty($existingClub->postal_code) && !empty($realCircolo->CAP)) {
            $improvements['postal_code'] = $realCircolo->CAP;
        }

        if (empty($existingClub->city) && !empty($realCircolo->Città)) {
            $improvements['city'] = $realCircolo->Città;
        }

        if (empty($existingClub->email) && !empty($realCircolo->Email)) {
            $improvements['email'] = $realCircolo->Email;
        }

        if (empty($existingClub->address) && !empty($realCircolo->Indirizzo)) {
            $improvements['address'] = $realCircolo->Indirizzo;
        }

        if (empty($existingClub->phone) && !empty($realCircolo->Telefono)) {
            $improvements['phone'] = $realCircolo->Telefono;
        }

        if (empty($existingClub->website) && !empty($realCircolo->Web)) {
            $improvements['website'] = $realCircolo->Web;
        }

        if (empty($existingClub->province) && !empty($realCircolo->Provincia)) {
            $improvements['province'] = $realCircolo->Provincia;
        }

        if (empty($existingClub->region) && !empty($realCircolo->Regione)) {
            $improvements['region'] = $realCircolo->Regione;
        }

        $realZoneId = $this->mapZonaToZoneId($realCircolo->Zona);
        if ($this->isDifferent($existingClub->zone_id, $realZoneId)) {
            $improvements['zone_id'] = $realZoneId;
        }

        if (!empty($improvements)) {
            if ($this->dryRun) {
                $this->command->info("   [DRY-RUN] Miglioramento circolo: {$existingClub->name}");
            } else {
                DB::table('clubs')->where('id', $existingClub->id)->update($improvements);
                $this->command->info("   ✅ Migliorato circolo: {$existingClub->name} (" . count($improvements) . " campi)");
            }

            $this->improvements[] = [
                'type' => 'circolo',
                'name' => $existingClub->name,
                'id' => $existingClub->id,
                'improvements' => $improvements
            ];
        }
    }

    private function addNewCircolo($realCircolo)
    {
        $clubData = [
            'name' => $realCircolo->Circolo_Nome,
            'code' => $realCircolo->Circolo_Id,
            'address' => $realCircolo->Indirizzo ?? null,
            'city' => $realCircolo->Città ?? null,
            'postal_code' => $realCircolo->CAP ?? null,
            'province' => $realCircolo->Provincia ?? null,
            'region' => $realCircolo->Regione ?? null,
            'phone' => $realCircolo->Telefono ?? null,
            'email' => $realCircolo->Email ?? null,
            'website' => $realCircolo->Web ?? null,
            'zone_id' => $this->mapZonaToZoneId($realCircolo->Zona),
            'is_active' => $this->mapBooleanValue($realCircolo->SedeGara ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $clubData = array_filter($clubData, function($value) {
            return $value !== null;
        });

        try {
            if ($this->dryRun) {
                $this->command->info("   [DRY-RUN] Nuovo circolo: {$realCircolo->Circolo_Nome}");
                $clubId = 999;
            } else {
                $clubId = DB::table('clubs')->insertGetId($clubData);
            }

            $this->newRecords[] = [
                'type' => 'circolo',
                'name' => $realCircolo->Circolo_Nome,
                'id' => $clubId
            ];

            $mode = $this->dryRun ? '[DRY-RUN] ' : '';
            $this->command->info("   ➕ {$mode}Aggiunto nuovo circolo: {$realCircolo->Circolo_Nome} (ID: {$clubId})");

        } catch (\Exception $e) {
            $this->command->error("   ❌ Errore inserimento circolo {$realCircolo->Circolo_Nome}: " . $e->getMessage());
        }
    }

    // ========================================
    // MIGRAZIONE GARE
    // ========================================

    private function improveGare()
    {
        $this->command->info('🏆 Migrazione migliorativa gare...');

        try {
            $this->ensureVirtualClubExists();

            $realGare = DB::connection('real')->table('gare_2025')->get();
            $this->command->info("🔍 Trovati " . $realGare->count() . " gare nel database reale");

            foreach ($realGare as $gara) {
                if (empty($gara->Nome_gara)) {
                    $this->command->warn("   ⚠️ Gara senza nome");
                    continue;
                }

                $existingTournament = DB::table('tournaments')
                    ->where('name', 'LIKE', "%{$gara->Nome_gara}%")
                    ->first();

                if ($existingTournament) {
                    $this->improveTournament($gara, $existingTournament);
                } else {
                    $this->addNewTournament($gara);
                }
            }

        } catch (\Exception $e) {
            $this->command->error("❌ Errore migrazione gare: " . $e->getMessage());
        }
    }

    private function improveTournament($realGara, $existingTournament)
    {
        $improvements = [];

        if (!empty($realGara->Circolo)) {
            $mappedClubCode = $this->mapSpecialClubCodes($realGara->Circolo);

            if ($mappedClubCode) {
                if ($mappedClubCode === 'TBA') {
                    $this->ensureVirtualClubExists();
                }

                // ✅ FIX: Usa il nuovo metodo che gestisce dry-run
                $club = $this->findClubByCode($mappedClubCode);
                if ($club && $this->isDifferent($existingTournament->club_id, $club->id)) {
                    $improvements['club_id'] = $club->id;
                } elseif (!$club && $mappedClubCode !== 'TBA') {
                    $this->command->warn("   ⚠️ Circolo non trovato: {$mappedClubCode} (originale: {$realGara->Circolo})");
                } elseif (!$club && $mappedClubCode === 'TBA') {
                    $this->command->error("   ❌ Errore: Circolo virtuale TBA non trovato dopo creazione");
                }
            } else {
                $this->command->info("   ℹ️ Circolo saltato (N/A): {$realGara->Circolo}");
            }
        }

        if (!empty($realGara->StartTime)) {
            $startDate = $this->parseDate($realGara->StartTime);
            if ($startDate && $this->isDifferent($existingTournament->start_date, $startDate)) {
                $improvements['start_date'] = $startDate;
            }
        }

        if (!empty($realGara->EndTime)) {
            $endDate = $this->parseDate($realGara->EndTime);
            if ($endDate && $this->isDifferent($existingTournament->end_date, $endDate)) {
                $improvements['end_date'] = $endDate;
            }
        }

        $realZoneId = $this->mapZonaToZoneId($realGara->Zona);
        if ($this->isDifferent($existingTournament->zone_id, $realZoneId)) {
            $improvements['zone_id'] = $realZoneId;
        }

        if (!empty($improvements)) {
            if ($this->dryRun) {
                $this->command->info("   [DRY-RUN] Miglioramento torneo: {$existingTournament->name}");
            } else {
                DB::table('tournaments')->where('id', $existingTournament->id)->update($improvements);
                $this->command->info("   ✅ Migliorato torneo: {$existingTournament->name}");
            }

            $this->improvements[] = [
                'type' => 'torneo',
                'name' => $existingTournament->name,
                'id' => $existingTournament->id,
                'improvements' => $improvements
            ];
        }
    }

    private function addNewTournament($realGara)
    {
        $clubId = null;
        if (!empty($realGara->Circolo)) {
            $mappedClubCode = $this->mapSpecialClubCodes($realGara->Circolo);
            if ($mappedClubCode) {
                if ($mappedClubCode === 'TBA') {
                    $this->ensureVirtualClubExists();
                }
                // ✅ FIX: Usa il nuovo metodo che gestisce dry-run
                $club = $this->findClubByCode($mappedClubCode);
                $clubId = $club->id ?? null;
            }
        }

        if (!$clubId) {
            $this->command->warn("   ⚠️ Circolo non trovato per gara: {$realGara->Nome_gara} (Circolo: {$realGara->Circolo})");
            return;
        }

        $tournamentTypeId = $this->mapTournamentType($realGara);
        $availabilityDeadline = $this->calculateDeadline($realGara->StartTime);

        $tournamentData = [
            'name' => $realGara->Nome_gara,
            'start_date' => $this->parseDate($realGara->StartTime),
            'end_date' => $this->parseDate($realGara->EndTime) ?? $this->parseDate($realGara->StartTime),
            'availability_deadline' => $availabilityDeadline,
            'club_id' => $clubId,
            'tournament_type_id' => $tournamentTypeId,
            'zone_id' => $this->mapZonaToZoneId($realGara->Zona),
            'notes' => $realGara->Note ?? null,
            'status' => $this->mapTournamentStatus($realGara->Stato ?? 'draft'),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $tournamentData = array_filter($tournamentData, function($value) {
            return $value !== null;
        });

        try {
            if ($this->dryRun) {
                $this->command->info("   [DRY-RUN] Nuovo torneo: {$realGara->Nome_gara}");
                $tournamentId = 999;
            } else {
                $tournamentId = DB::table('tournaments')->insertGetId($tournamentData);
            }

            $this->newRecords[] = [
                'type' => 'torneo',
                'name' => $realGara->Nome_gara,
                'id' => $tournamentId
            ];

            $mode = $this->dryRun ? '[DRY-RUN] ' : '';
            $this->command->info("   ➕ {$mode}Aggiunto nuovo torneo: {$realGara->Nome_gara} (ID: {$tournamentId})");

        } catch (\Exception $e) {
            $this->command->error("   ❌ Errore inserimento torneo {$realGara->Nome_gara}: " . $e->getMessage());
        }
    }

    /**
     * ✅ NUOVO: Trova circolo considerando anche simulazioni dry-run
     */
    private function findClubByCode($code)
    {
        // Prima cerca nel database reale
        $club = DB::table('clubs')->where('code', $code)->first();

        // Se non trovato e siamo in dry-run, controlla se è TBA simulato
        if (!$club && $this->dryRun && $code === 'TBA' && $this->simulatedTbaClub) {
            return $this->simulatedTbaClub;
        }

        return $club;
    }

    private function ensureVirtualClubExists()
    {
        // ✅ FIX: Usa il nuovo metodo che gestisce dry-run
        $tbaClub = $this->findClubByCode('TBA');

        if (!$tbaClub) {
            $virtualClubData = [
                'name' => 'To Be Assigned (Circolo Virtuale)',
                'code' => 'TBA',
                'address' => null,
                'city' => 'Virtuale',
                'postal_code' => null,
                'province' => null,
                'region' => null,
                'phone' => null,
                'email' => null,
                'website' => null,
                'zone_id' => 8,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($this->dryRun) {
                $this->command->info("   [DRY-RUN] Creazione circolo virtuale TBA");
                // ✅ FIX: In dry-run, simula l'esistenza del circolo TBA
                $this->simulatedTbaClub = (object)[
                    'id' => 9999,
                    'code' => 'TBA',
                    'name' => 'To Be Assigned (Circolo Virtuale)'
                ];
            } else {
                $clubId = DB::table('clubs')->insertGetId($virtualClubData);
                $this->command->info("   🏗️ Creato circolo virtuale TBA (ID: {$clubId})");

                $this->newRecords[] = [
                    'type' => 'circolo_virtuale',
                    'name' => 'To Be Assigned (Circolo Virtuale)',
                    'id' => $clubId
                ];
            }
        } else {
            $this->command->info("   ✅ Circolo virtuale TBA già esistente (ID: {$tbaClub->id})");
        }
    }

    // ========================================
    // METODI HELPER
    // ========================================

    private function mapBooleanValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            $trueValues = ['vero', 'true', '1', 'si', 'sì', 'yes'];
            $falseValues = ['falso', 'false', '0', 'no'];

            if (in_array($value, $trueValues)) {
                return true;
            }

            if (in_array($value, $falseValues)) {
                return false;
            }
        }

        return (bool) $value;
    }

    private function mapLivelloToLevel($livello): string
    {
        if (empty($livello)) {
            return RefereeLevelsHelper::normalize('Aspirante');
        }

        $mapping = [
            'Aspirante' => 'Aspirante',
            'ASP' => 'Aspirante',

            'Primo Livello' => '1_livello',
            '1° Livello' => '1_livello',
            '1°' => '1_livello',
            'PRIMO' => '1_livello',

            'Regionale' => 'Regionale',
            'REG' => 'Regionale',

            'Nazionale' => 'Nazionale',
            'NAZ' => 'Nazionale',
            'NAZ/INT' => 'Nazionale',

            'Internazionale' => 'Internazionale',
            'INT' => 'Internazionale',

            'Archivio' => 'Archivio',
            'ARCH' => 'Archivio',
            'ARCHIVE' => 'Archivio',
        ];

        $normalized = $mapping[$livello] ?? RefereeLevelsHelper::normalize($livello);
        return $normalized ?? 'Aspirante';
    }

    private function mapZonaToZoneId($zona): ?int
    {
        if (empty($zona)) {
            return null;
        }

        $zona = trim(strtoupper($zona));

        $zoneMapping = [
            'SZR1' => 1, 'SZR 1' => 1, 'ZONA 1' => 1,
            'SZR2' => 2, 'SZR 2' => 2, 'ZONA 2' => 2,
            'SZR3' => 3, 'SZR 3' => 3, 'ZONA 3' => 3,
            'SZR4' => 4, 'SZR 4' => 4, 'ZONA 4' => 4,
            'SZR5' => 5, 'SZR 5' => 5, 'ZONA 5' => 5,
            'SZR6' => 6, 'SZR 6' => 6, 'ZONA 6' => 6,
            'SZR7' => 7, 'SZR 7' => 7, 'ZONA 7' => 7,
            'CRC' => 8, 'NAZIONALE' => 8, 'NAZ' => 8,
        ];

        $zoneId = $zoneMapping[$zona] ?? null;

        if (!$zoneId && preg_match('/(\d+)/', $zona, $matches)) {
            $number = (int)$matches[1];
            if ($number >= 1 && $number <= 8) {
                $zoneId = $number;
            }
        }

        return $zoneId ?? 1;
    }

    private function mapSpecialClubCodes($clubCode): ?string
    {
        $specialMappings = [
            'TOLCINASCO' => 'TOLCIN',
            'TBA' => 'TBA',
            'TO_BE_ASSIGNED' => 'TBA',
            'N/A' => null,
            'ND' => null,
            '' => null,
        ];

        return $specialMappings[$clubCode] ?? $clubCode;
    }

    private function mapTournamentType($realGara): int
    {
        if (isset($realGara->Tipo_Torneo)) {
            $tipo = strtoupper(trim($realGara->Tipo_Torneo));

            if (in_array($tipo, ['NAZ', 'NAZIONALE', 'CN'])) return 3;
            if (in_array($tipo, ['REG', 'REGIONALE', 'CI'])) return 2;
            return 1;
        }

        $nomeGara = strtoupper($realGara->Nome_gara ?? '');
        if (strpos($nomeGara, 'NAZIONALE') !== false) return 3;
        if (strpos($nomeGara, 'REGIONALE') !== false) return 2;

        return 1;
    }

    private function mapTournamentStatus($status): string
    {
        $status = strtolower(trim($status ?? ''));

        $mapping = [
            'bozza' => 'draft',
            'draft' => 'draft',
            'pubblicata' => 'published',
            'published' => 'published',
            'aperta' => 'open',
            'open' => 'open',
            'chiusa' => 'closed',
            'closed' => 'closed',
            'completata' => 'completed',
            'completed' => 'completed',
            'cancellata' => 'cancelled',
            'cancelled' => 'cancelled',
        ];

        return $mapping[$status] ?? 'draft';
    }

    private function calculateDeadline($startTime): string
    {
        $startDate = $this->parseDate($startTime);

        if (!$startDate) {
            return now()->addDays(7)->format('Y-m-d');
        }

        $deadline = Carbon::parse($startDate)->subDays(7);

        if ($deadline->isPast()) {
            $deadline = now()->addDays(2);
        }

        return $deadline->format('Y-m-d');
    }

    private function isDifferent($existing, $new): bool
    {
        $existing = trim($existing ?? '');
        $new = trim($new ?? '');

        return $existing !== $new && !empty($new);
    }

    private function parseDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generateEmail($name): string
    {
        $slug = strtolower(str_replace(' ', '.', $name));
        return $slug . '@temp.federgolf.it';
    }

    private function generateRefereeCode(): string
    {
        do {
            $code = 'ARB' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (DB::table('users')->where('referee_code', $code)->exists());

        return $code;
    }

    private function createMissingRefereeRecord($realArbitro, $existingUser)
    {
        $refereeData = [
            'user_id' => $existingUser->id,
            'address' => $realArbitro->Casa ?? null,
            'first_certification_date' => $this->parseDate($realArbitro->Prima_Nomina),
            'profile_completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (!$this->dryRun) {
            DB::table('referees')->insert($refereeData);
        }

        $mode = $this->dryRun ? '[DRY-RUN] ' : '';
        $this->command->info("   ➕ {$mode}Creato record referee mancante per: {$existingUser->name}");
    }

    private function showImprovementReport()
    {
        $this->command->info('📊 REPORT MIGRAZIONE MIGLIORATIVA:');

        $autoResolved = collect($this->improvements)->where('type', 'arbitro_auto_resolved')->count();
        $unresolvedConflicts = collect($this->conflicts)->count();

        $this->command->info("   🔥 Conflitti trovati: " . ($unresolvedConflicts + $autoResolved));
        $this->command->info("   🔧 Auto-risoluti: " . $autoResolved);
        $this->command->info("   ⚠️ Non risolti: " . $unresolvedConflicts);
        $this->command->info("   ✅ Miglioramenti applicati: " . count($this->improvements));
        $this->command->info("   ➕ Nuovi record aggiunti: " . count($this->newRecords));
        $this->command->info("   🗑️ Record rimossi (non-referee): " . count($this->removedRecords));

        if ($unresolvedConflicts > 0) {
            $this->command->error("\n🔥 CONFLITTI NON RISOLTI: {$unresolvedConflicts}");
            $this->command->info("💡 Rivedi manualmente questi casi o implementa regole aggiuntive");
        } else {
            $this->command->info("\n🎉 TUTTI I CONFLITTI SONO STATI RISOLTI AUTOMATICAMENTE!");
        }

        if (count($this->removedRecords) > 0) {
            $this->command->info("\n🗑️ RECORD NON-REFEREE RIMOSSI:");
            foreach ($this->removedRecords as $record) {
                $this->command->info("   {$record['name']} (Livello: {$record['level']})");
            }
        }

        $mode = $this->dryRun ? "\n🔍 MODALITÀ SIMULAZIONE - Nessuna modifica applicata" : "\n✅ Migrazione migliorativa completata!";
        $this->command->info($mode);
    }

    // ========================================
    // SETUP DATABASE
    // ========================================

    private function setupRealDatabaseConnection()
    {
        $realDbConfig = [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => 'Sql1466239_4',
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
        ];

        config(['database.connections.real' => $realDbConfig]);
        $this->command->info("🔗 Connessione database reale Sql1466239_4 configurata");
    }

    private function checkRealDatabase(): bool
    {
        try {
            $pdo = DB::connection('real')->getPdo();
            $this->command->info('✅ Connessione al database reale stabilita');

            $count = DB::connection('real')->table('arbitri')->count();
            $this->command->info("✅ Test query: {$count} arbitri nel database reale");

            return true;

        } catch (\Exception $e) {
            $this->command->error('❌ Errore connessione database reale: ' . $e->getMessage());
            return false;
        }
    }

    private function closeRealDatabaseConnection()
    {
        try {
            DB::disconnect('real');
            $this->command->info('🔌 Connessione database reale chiusa');
        } catch (\Exception $e) {
            $this->command->warn('⚠️ Errore chiusura connessione: ' . $e->getMessage());
        }
    }
}
