<?php

/**
 * ========================================
 * DATA IMPROVEMENT SEEDER
 * ========================================
 * File: database/seeders/DataImprovementSeeder.php
 *
 * Migrazione migliorativa dal database reale Sql1466239_4
 * - Confronta dati esistenti con database source reale
 * - Segnala conflitti per permettere scelte
 * - Migliora dati corrotti dalla migrazione precedente
 * - Usa le corrispondenze corrette specificate
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

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔄 Inizio migrazione migliorativa dal database reale Sql1466239_4...');

        // 1. Setup connessione al database reale
        $this->setupRealDatabaseConnection();

        // 2. Verifica connessione
        if (!$this->checkRealDatabase()) {
            $this->command->error('❌ Impossibile connettersi al database Sql1466239_4');
            return;
        }

        // 3. Esegui migrazioni migliorative nell'ordine corretto
        $this->command->info('✅ Database verificato, procedo con migrazione migliorativa...');

        $this->improveArbitri();      // arbitri -> users + referees
        $this->improveCircoli();      // circoli -> clubs
        $this->improveGare();         // gare_2025 -> tournaments

        // 4. Report finale
        $this->showImprovementReport();

        // 5. Chiudi connessione
        $this->closeRealDatabaseConnection();
    }

    /**
     * ✅ MIGRAZIONE MIGLIORATIVA ARBITRI
     * arbitri (Nome+Cognome) -> users.name + referees (extension)
     */
    private function improveArbitri()
    {
        $this->command->info('👥 Migrazione migliorativa arbitri...');

        try {
            $realArbitri = DB::connection('real')->table('arbitri')->get();

            $this->command->info("🔍 Trovati " . $realArbitri->count() . " arbitri nel database reale");

            foreach ($realArbitri as $arbitro) {
                // ✅ Chiave di ricerca: Nome + Cognome = users.name
                $fullName = trim($arbitro->Nome . ' ' . $arbitro->Cognome);

                if (empty($fullName) || $fullName === ' ') {
                    $this->command->warn("   ⚠️ Arbitro senza nome: ID {$arbitro->id}");
                    continue;
                }

                // Cerca nel database target per nome
                $existingUser = DB::table('users')
                    ->where('name', 'LIKE', "%{$fullName}%")
                    ->orWhere('name', 'LIKE', "%{$arbitro->Nome}%")
                    ->where('user_type', 'referee')
                    ->first();

                if ($existingUser) {
                    // ✅ ARBITRO ESISTENTE - Confronta e segnala conflitti
                    $this->checkArbitroConflicts($arbitro, $existingUser, $fullName);
                } else {
                    // ✅ NUOVO ARBITRO - Aggiungi
                    $this->addNewArbitro($arbitro, $fullName);
                }
            }

        } catch (\Exception $e) {
            $this->command->error("❌ Errore migrazione arbitri: " . $e->getMessage());
        }
    }

    /**
     * ✅ CONTROLLO CONFLITTI ARBITRO
     */
    private function checkArbitroConflicts($realArbitro, $existingUser, $fullName)
    {
        $conflicts = [];

        // Controlli specifici per ogni campo
        if ($this->isDifferent($existingUser->email, $realArbitro->Email)) {
            $conflicts['email'] = [
                'existing' => $existingUser->email,
                'real' => $realArbitro->Email,
                'field' => 'Email'
            ];
        }

        if ($this->isDifferent($existingUser->phone, $realArbitro->Cellulare)) {
            $conflicts['phone'] = [
                'existing' => $existingUser->phone,
                'real' => $realArbitro->Cellulare,
                'field' => 'Telefono'
            ];
        }

        if ($this->isDifferent($existingUser->city, $realArbitro->Citta)) {
            $conflicts['city'] = [
                'existing' => $existingUser->city,
                'real' => $realArbitro->Citta,
                'field' => 'Città'
            ];
        }

        // Controllo livello con normalizzazione
        $realLevel = $this->mapLivelloToLevel($realArbitro->Livello_2025);
        if ($this->isDifferent($existingUser->level, $realLevel)) {
            $conflicts['level'] = [
                'existing' => $existingUser->level,
                'real' => $realLevel,
                'field' => 'Livello',
                'original' => $realArbitro->Livello_2025
            ];
        }

        // Controllo zona
        $realZoneId = $this->mapZonaToZoneId($realArbitro->Zona);
        if ($this->isDifferent($existingUser->zone_id, $realZoneId)) {
            $conflicts['zone_id'] = [
                'existing' => $existingUser->zone_id,
                'real' => $realZoneId,
                'field' => 'Zona',
                'original' => $realArbitro->Zona
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
            // Verifica dati estesi arbitro (referees table)
            $this->checkRefereeExtensionData($realArbitro, $existingUser);
        }
    }

    /**
     * ✅ CONTROLLO DATI ESTESI ARBITRO (referees table)
     */
    private function checkRefereeExtensionData($realArbitro, $existingUser)
    {
        $existingReferee = DB::table('referees')->where('user_id', $existingUser->id)->first();

        if (!$existingReferee) {
            // Crea record referee mancante
            $this->createMissingRefereeRecord($realArbitro, $existingUser);
            return;
        }

        $improvements = [];

        // Controllo indirizzo
        if (empty($existingReferee->address) && !empty($realArbitro->Casa)) {
            $improvements['address'] = $realArbitro->Casa;
        }

        // Controllo prima nomina
        if (empty($existingReferee->first_certification_date) && !empty($realArbitro->Prima_Nomina)) {
            $improvements['first_certification_date'] = $this->parseDate($realArbitro->Prima_Nomina);
        }

        if (!empty($improvements)) {
            $this->improvements[] = [
                'type' => 'referee_extension',
                'name' => $existingUser->name,
                'user_id' => $existingUser->id,
                'improvements' => $improvements
            ];

            // Applica miglioramenti automaticamente
            DB::table('referees')
                ->where('user_id', $existingUser->id)
                ->update($improvements);

            $this->command->info("   ✅ Migliorati dati estesi per: {$existingUser->name}");
        }
    }

    /**
     * ✅ AGGIUNGI NUOVO ARBITRO
     */
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

        $userId = DB::table('users')->insertGetId($userData);

        // Crea record referee esteso
        $refereeData = [
            'user_id' => $userId,
            'address' => $realArbitro->Casa ?? null,
            'first_certification_date' => $this->parseDate($realArbitro->Prima_Nomina),
            'profile_completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('referees')->insert($refereeData);

        $this->newRecords[] = [
            'type' => 'arbitro',
            'name' => $fullName,
            'id' => $userId
        ];

        $this->command->info("   ➕ Aggiunto nuovo arbitro: {$fullName} (ID: {$userId})");
    }

    /**
     * ✅ MIGRAZIONE MIGLIORATIVA CIRCOLI
     * circoli.Circolo_Id -> clubs.code (chiave di ricerca)
     */
    private function improveCircoli()
    {
        $this->command->info('🏌️ Migrazione migliorativa circoli...');

        try {
            $realCircoli = DB::connection('real')->table('circoli')->get();

            $this->command->info("🔍 Trovati " . $realCircoli->count() . " circoli nel database reale");

            foreach ($realCircoli as $circolo) {
                // ✅ Chiave di ricerca: Circolo_Id = clubs.code
                if (empty($circolo->Circolo_Id)) {
                    $this->command->warn("   ⚠️ Circolo senza ID: {$circolo->Circolo_Nome}");
                    continue;
                }

                $existingClub = DB::table('clubs')->where('code', $circolo->Circolo_Id)->first();

                if ($existingClub) {
                    // ✅ CIRCOLO ESISTENTE - Confronta e migliora
                    $this->checkCircoloConflicts($circolo, $existingClub);
                } else {
                    // ✅ NUOVO CIRCOLO - Aggiungi
                    $this->addNewCircolo($circolo);
                }
            }

        } catch (\Exception $e) {
            $this->command->error("❌ Errore migrazione circoli: " . $e->getMessage());
        }
    }

    /**
     * ✅ CONTROLLO CONFLITTI CIRCOLO
     */
    private function checkCircoloConflicts($realCircolo, $existingClub)
    {
        $improvements = [];

        // Controlli e miglioramenti automatici
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

        // Mapping zona
        $realZoneId = $this->mapZonaToZoneId($realCircolo->Zona);
        if ($this->isDifferent($existingClub->zone_id, $realZoneId)) {
            $improvements['zone_id'] = $realZoneId;
        }

        if (!empty($improvements)) {
            DB::table('clubs')->where('id', $existingClub->id)->update($improvements);

            $this->improvements[] = [
                'type' => 'circolo',
                'name' => $existingClub->name,
                'id' => $existingClub->id,
                'improvements' => $improvements
            ];

            $this->command->info("   ✅ Migliorato circolo: {$existingClub->name} (" . count($improvements) . " campi)");
        }
    }

    /**
     * ✅ AGGIUNGI NUOVO CIRCOLO
     */
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
            'is_active' => $realCircolo->SedeGara ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $clubId = DB::table('clubs')->insertGetId($clubData);

        $this->newRecords[] = [
            'type' => 'circolo',
            'name' => $realCircolo->Circolo_Nome,
            'id' => $clubId
        ];

        $this->command->info("   ➕ Aggiunto nuovo circolo: {$realCircolo->Circolo_Nome} (ID: {$clubId})");
    }

    /**
     * ✅ MIGRAZIONE MIGLIORATIVA GARE
     * gare_2025.Nome_gara -> tournaments.name (chiave di ricerca)
     */
    private function improveGare()
    {
        $this->command->info('🏆 Migrazione migliorativa gare...');

        try {
            $realGare = DB::connection('real')->table('gare_2025')->get();

            $this->command->info("🔍 Trovati " . $realGare->count() . " gare nel database reale");

            foreach ($realGare as $gara) {
                if (empty($gara->Nome_gara)) {
                    $this->command->warn("   ⚠️ Gara senza nome");
                    continue;
                }

                // ✅ Chiave di ricerca: Nome_gara = tournaments.name
                $existingTournament = DB::table('tournaments')
                    ->where('name', 'LIKE', "%{$gara->Nome_gara}%")
                    ->first();

                if ($existingTournament) {
                    // ✅ GARA ESISTENTE - Migliora
                    $this->improveTournament($gara, $existingTournament);
                } else {
                    // ✅ NUOVA GARA - Aggiungi
                    $this->addNewTournament($gara);
                }
            }

        } catch (\Exception $e) {
            $this->command->error("❌ Errore migrazione gare: " . $e->getMessage());
        }
    }

    /**
     * ✅ MIGLIORA TORNEO ESISTENTE
     */
    private function improveTournament($realGara, $existingTournament)
    {
        $improvements = [];

        // Mappa circolo per code
        if (!empty($realGara->Circolo)) {
            $club = DB::table('clubs')->where('code', $realGara->Circolo)->first();
            if ($club && $this->isDifferent($existingTournament->club_id, $club->id)) {
                $improvements['club_id'] = $club->id;
            }
        }

        // Date
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

        // Zona
        $realZoneId = $this->mapZonaToZoneId($realGara->Zona);
        if ($this->isDifferent($existingTournament->zone_id, $realZoneId)) {
            $improvements['zone_id'] = $realZoneId;
        }

        if (!empty($improvements)) {
            DB::table('tournaments')->where('id', $existingTournament->id)->update($improvements);

            $this->improvements[] = [
                'type' => 'torneo',
                'name' => $existingTournament->name,
                'id' => $existingTournament->id,
                'improvements' => $improvements
            ];

            $this->command->info("   ✅ Migliorato torneo: {$existingTournament->name}");
        }
    }

    // ========================================
    // METODI HELPER
    // ========================================

    /**
     * ✅ Mappa livello arbitro
     */
    private function mapLivelloToLevel($livello): string
    {
        if (empty($livello)) {
            return RefereeLevelsHelper::normalize('Aspirante');
        }

        // Mapping specifico dal database reale
        $mapping = [
            'Aspirante' => 'Aspirante',
            'Primo Livello' => '1_livello',
            '1° Livello' => '1_livello',
            'Regionale' => 'Regionale',
            'Nazionale' => 'Nazionale',
            'Internazionale' => 'Internazionale',
            'Archivio' => 'Archivio',
        ];

        $normalized = $mapping[$livello] ?? RefereeLevelsHelper::normalize($livello);
        return $normalized ?? 'Aspirante';
    }

    /**
     * ✅ Mappa zona
     */
    private function mapZonaToZoneId($zona): ?int
    {
        if (empty($zona)) {
            return null;
        }

        $zoneMapping = [
            'SZR1' => 1, 'szr1' => 1,
            'SZR2' => 2, 'szr2' => 2,
            'SZR3' => 3, 'szr3' => 3,
            'SZR4' => 4, 'szr4' => 4,
            'SZR5' => 5, 'szr5' => 5,
            'SZR6' => 6, 'szr6' => 6,
            'SZR7' => 7, 'szr7' => 7,
            'CRC' => 8, 'crc' => 8,
        ];

        return $zoneMapping[$zona] ?? null;
    }

    /**
     * ✅ Controlla se due valori sono diversi
     */
    private function isDifferent($existing, $new): bool
    {
        // Normalizza per confronto
        $existing = trim($existing ?? '');
        $new = trim($new ?? '');

        return $existing !== $new && !empty($new);
    }

    /**
     * ✅ Parse date
     */
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

    /**
     * ✅ Genera email per arbitro
     */
    private function generateEmail($name): string
    {
        $slug = strtolower(str_replace(' ', '.', $name));
        return $slug . '@temp.federgolf.it';
    }

    /**
     * ✅ Genera codice arbitro
     */
    private function generateRefereeCode(): string
    {
        do {
            $code = 'ARB' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (DB::table('users')->where('referee_code', $code)->exists());

        return $code;
    }

    /**
     * ✅ Crea record referee mancante
     */
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

        DB::table('referees')->insert($refereeData);

        $this->command->info("   ➕ Creato record referee mancante per: {$existingUser->name}");
    }

    /**
     * ✅ REPORT FINALE
     */
    private function showImprovementReport()
    {
        $this->command->info('📊 REPORT MIGRAZIONE MIGLIORATIVA:');

        $this->command->info("   🔥 Conflitti trovati: " . count($this->conflicts));
        $this->command->info("   ✅ Miglioramenti applicati: " . count($this->improvements));
        $this->command->info("   ➕ Nuovi record aggiunti: " . count($this->newRecords));

        if (!empty($this->conflicts)) {
            $this->command->error("\n🔥 CONFLITTI DA RISOLVERE:");
            foreach ($this->conflicts as $conflict) {
                $this->command->error("   {$conflict['type']}: {$conflict['name']} (ID: {$conflict['user_id']})");
            }
            $this->command->info("\n💡 Per risolvere i conflitti, modifica manualmente i record o esegui il seeder con --resolve-conflicts");
        }

        if (!empty($this->newRecords)) {
            $this->command->info("\n➕ NUOVI RECORD AGGIUNTI:");
            foreach ($this->newRecords as $record) {
                $this->command->info("   {$record['type']}: {$record['name']} (ID: {$record['id']})");
            }
        }
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

            // Test query
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
