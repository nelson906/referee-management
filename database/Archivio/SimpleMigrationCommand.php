<?php
// app/Console/Commands/SimpleMigrationCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Zone;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Referee;
use Carbon\Carbon;

class SimpleMigrationCommand extends Command
{
    protected $signature = 'golf:simple-migration {old_db_name} {--clean}';
    protected $description = 'Migrazione semplificata e testata per risolvere referees vuoti';

    private $oldDb;
    private $stats = [];

    public function handle()
    {
        $this->oldDb = $this->argument('old_db_name');

        $this->info("🚀 MIGRAZIONE SEMPLIFICATA");
        $this->info("Database: {$this->oldDb}");

        try {
            $this->setupConnection();

            if ($this->option('clean')) {
                $this->cleanData();
            }

            $this->createBaseData();
            $this->migrateUsers();
            $this->migrateReferees();
            $this->migrateClubs();
            $this->migrateTournaments();

            $this->printStats();
            $this->info("✅ MIGRAZIONE COMPLETATA!");

        } catch (\Exception $e) {
            $this->error("❌ ERRORE: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function setupConnection()
    {
        config(['database.connections.old_db' => [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => $this->oldDb,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        DB::connection('old_db')->getPdo();
        $this->info("✅ Connessione OK");
    }

    private function cleanData()
    {
        if (!$this->confirm('Cancellare tutti i dati esistenti?')) {
            exit(0);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        $tables = ['assignments', 'availabilities', 'tournaments', 'clubs', 'users', 'referees'];
        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->truncate();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->info("🧹 Dati puliti");
    }

    private function createBaseData()
    {
        $this->info("🏗️ Creazione dati base...");

        // Zone
        $zones = [
            ['id' => 1, 'name' => 'Zona 1', 'description' => 'SZR1', 'is_national' => false],
            ['id' => 2, 'name' => 'Zona 2', 'description' => 'SZR2', 'is_national' => false],
            ['id' => 3, 'name' => 'Zona 3', 'description' => 'SZR3', 'is_national' => false],
            ['id' => 4, 'name' => 'Zona 4', 'description' => 'SZR4', 'is_national' => false],
            ['id' => 5, 'name' => 'Zona 5', 'description' => 'SZR5', 'is_national' => false],
            ['id' => 6, 'name' => 'Zona 6', 'description' => 'SZR6', 'is_national' => false],
            ['id' => 7, 'name' => 'Zona 7', 'description' => 'SZR7', 'is_national' => false],
            ['id' => 8, 'name' => 'CRC', 'description' => 'Comitato Regionale Calabria', 'is_national' => true],
        ];

        foreach ($zones as $zone) {
            Zone::updateOrCreate(['id' => $zone['id']], $zone);
        }

        // Tournament Types
        $types = [
            ['id' => 1, 'name' => 'Gara Zonale', 'code' => 'ZON', 'is_national' => false, 'required_level' => '1_livello', 'min_referees' => 1, 'max_referees' => 2],
            ['id' => 2, 'name' => 'Coppa Italia', 'code' => 'CI', 'is_national' => true, 'required_level' => 'Regionale', 'min_referees' => 2, 'max_referees' => 3],
            ['id' => 3, 'name' => 'Campionato Nazionale', 'code' => 'CN', 'is_national' => true, 'required_level' => 'Nazionale', 'min_referees' => 2, 'max_referees' => 4],
        ];

        foreach ($types as $type) {
            TournamentType::updateOrCreate(['id' => $type['id']], $type);
        }

        $this->info("✅ Dati base OK");
    }

    private function migrateUsers()
    {
        $this->info("👥 Migrazione Users da arbitri...");

        try {
            $arbitri = DB::connection('old_db')->table('arbitri')->get();
            $count = 0;

            foreach ($arbitri as $arbitro) {
                if (empty($arbitro->Email)) continue;

                $level = $this->mapLevel($arbitro->Livello_2025 ?? 'ASP');
                $zoneId = $this->extractZoneId($arbitro->Zona ?? 'SZR1');
                $isActive = !empty($arbitro->Livello_2025) && strtoupper($arbitro->Livello_2025) !== 'ARCH';

                $userData = [
                    'name' => trim(($arbitro->Nome ?? '') . ' ' . ($arbitro->Cognome ?? '')),
                    'email' => $arbitro->Email,
                    'password' => Hash::make($arbitro->Password ?? 'password123'),
                    'user_type' => 'referee',
                    'referee_code' => 'ARB' . str_pad($arbitro->id, 4, '0', STR_PAD_LEFT),
                    'level' => $level,
                    'category' => 'misto',
                    'zone_id' => $zoneId,
                    'certified_date' => $this->parseDate($arbitro->Prima_Nomina) ?? now(),
                    'phone' => $arbitro->Cellulare ?? null,
                    'city' => $arbitro->Citta ?? null,
                    'is_active' => $isActive,
                    'email_verified_at' => now(),
                ];

                User::updateOrCreate(['email' => $arbitro->Email], $userData);
                $count++;
            }

            $this->stats['users'] = $count;
            $this->info("✅ Users: {$count}");

        } catch (\Exception $e) {
            $this->warn("⚠️ Errore users: " . $e->getMessage());
        }
    }

    private function migrateReferees()
    {
        $this->info("⚖️ Creazione Referees...");

        $users = User::where('user_type', 'referee')->get();
        $count = 0;

        foreach ($users as $user) {
            // Cerca dati arbitro corrispondente
            $arbitro = null;
            try {
                $arbitro = DB::connection('old_db')
                    ->table('arbitri')
                    ->where('Email', $user->email)
                    ->first();
            } catch (\Exception $e) {
                // Ignora errori
            }

            $refereeData = [
                'user_id' => $user->id,
                'address' => $arbitro->Pr_abit ?? null,
                'badge_number' => $user->referee_code,
                'first_certification_date' => $this->parseDate($arbitro->Prima_Nomina ?? $user->certified_date),
                'last_renewal_date' => $this->parseDate($arbitro->Ultimo_Esame ?? $user->certified_date),
                'expiry_date' => $this->calculateExpiry($arbitro->Ultimo_Esame ?? $user->certified_date),
                'experience_years' => $this->calculateYears($arbitro->Prima_Nomina ?? $user->certified_date),
                'available_for_international' => in_array($user->level, ['Nazionale', 'Internazionale']),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            Referee::updateOrCreate(['user_id' => $user->id], $refereeData);
            $count++;
        }

        $this->stats['referees'] = $count;
        $this->info("✅ Referees: {$count}");
    }

    private function migrateClubs()
    {
        $this->info("🏌️ Migrazione Clubs...");

        try {
            $circoli = DB::connection('old_db')->table('circoli')->get();
            $count = 0;

            foreach ($circoli as $circolo) {
                $clubData = [
                    'id' => $circolo->Id,
                    'code' => $circolo->Circolo_Id ?? 'CLUB' . $circolo->Id,
                    'name' => $circolo->Circolo_Nome ?? 'Club',
                    'address' => $circolo->Indirizzo ?? null,
                    'postal_code' => $circolo->CAP ?? null,
                    'city' => $circolo->Città ?? null,
                    'province' => $circolo->Provincia ?? null,
                    'region' => $circolo->Regione ?? null,
                    'email' => $circolo->Email ?? null,
                    'phone' => $circolo->Telefono ?? null,
                    'website' => $circolo->Web ?? null,
                    'is_active' => strtoupper(trim($circolo->SedeGara ?? 'Y')) === 'Y',
                    'zone_id' => $this->extractZoneId($circolo->Zona ?? 'SZR1'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                Club::updateOrCreate(['id' => $circolo->Id], $clubData);
                $count++;
            }

            $this->stats['clubs'] = $count;
            $this->info("✅ Clubs: {$count}");

        } catch (\Exception $e) {
            $this->warn("⚠️ Errore clubs: " . $e->getMessage());
        }
    }

    private function migrateTournaments()
    {
        $this->info("🏆 Migrazione Tournaments...");

        try {
            $gare = DB::connection('old_db')->table('gare_2025')->get();
            $count = 0;

            foreach ($gare as $gara) {
                $clubId = $this->findClubId($gara->Circolo ?? '');
                if (!$clubId) continue;

                $club = Club::find($clubId);
                if (!$club) continue;

                $typeId = $this->mapTournamentType($gara->Tipo ?? 'ZON');

                $tournamentData = [
                    'id' => $gara->id,
                    'name' => $gara->Nome_gare ?? 'Torneo',
                    'tournament_type_id' => $typeId,
                    'club_id' => $clubId,
                    'start_date' => $this->parseDate($gara->StartTime) ?? now(),
                    'end_date' => $this->parseDate($gara->EndTime) ?? now(),
                    'zone_id' => $this->extractZoneId($gara->Zona) ?? $club->zone_id,
                    'availability_deadline' => $this->calculateDeadline($gara->StartTime),
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                Tournament::updateOrCreate(['id' => $gara->id], $tournamentData);
                $count++;
            }

            $this->stats['tournaments'] = $count;
            $this->info("✅ Tournaments: {$count}");

        } catch (\Exception $e) {
            $this->warn("⚠️ Errore tournaments: " . $e->getMessage());
        }
    }

    private function printStats()
    {
        $this->info("\n📊 STATISTICHE MIGRAZIONE:");
        foreach ($this->stats as $table => $count) {
            $this->info("  {$table}: {$count}");
        }
    }

    // Helper methods semplificati
    private function mapLevel($livello)
    {
        $livello = strtoupper(trim($livello));
        if ($livello === 'ASP') return 'Aspirante';
        if ($livello === '1°') return '1_livello';
        if ($livello === 'REG') return 'Regionale';
        if (in_array($livello, ['NAZ', 'NAZ/INT'])) return 'Nazionale';
        if ($livello === 'INT') return 'Internazionale';
        if ($livello === 'ARCH') return 'Archivio';
        return '1_livello';
    }

    private function extractZoneId($zona)
    {
        if (preg_match('/(\d+)/', $zona, $matches)) {
            return min((int)$matches[1], 8);
        }
        return 1;
    }

    private function parseDate($dateString)
    {
        if (empty($dateString)) return null;
        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function calculateExpiry($lastRenewal)
    {
        $date = $this->parseDate($lastRenewal);
        return $date ? $date->addYears(2) : null;
    }

    private function calculateYears($firstCert)
    {
        $date = $this->parseDate($firstCert);
        return $date ? now()->diffInYears($date) : 0;
    }

    private function findClubId($clubName)
    {
        if (empty($clubName)) return null;
        return Club::where('name', 'LIKE', "%{$clubName}%")
            ->orWhere('code', 'LIKE', "%{$clubName}%")
            ->value('id');
    }

    private function mapTournamentType($tipo)
    {
        $tipo = strtoupper(trim($tipo));
        if (in_array($tipo, ['CN', 'NAZ', 'NAZIONALE'])) return 3;
        if (in_array($tipo, ['CI', 'REG', 'REGIONALE'])) return 2;
        return 1;
    }

    private function calculateDeadline($startTime)
    {
        $date = $this->parseDate($startTime);
        return $date ? $date->subDays(7)->format('Y-m-d') : now()->format('Y-m-d');
    }
}
