<?php
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

class SimpleMigrationCommand extends Command
{
    protected $signature = 'golf:migrate {database_name} {--clean}';
    protected $description = 'Migrazione funzionante per risolvere referees vuoti';

    private $oldDb;

    public function handle()
    {
        $this->oldDb = $this->argument('database_name');

        $this->info("🚀 MIGRAZIONE DATABASE: {$this->oldDb}");

        try {
            $this->setupConnection();

            if ($this->option('clean')) {
                $this->cleanTables();
            }

            $this->createBasicData();
            $this->importUsers();
            $this->createReferees();
            $this->importClubs();
            $this->importTournaments();

            $this->showResults();

        } catch (\Exception $e) {
            $this->error("ERRORE: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function setupConnection()
    {
        config(['database.connections.old' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $this->oldDb,
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        DB::connection('old')->getPdo();
        $this->info("✅ Connesso a database {$this->oldDb}");
    }

    private function cleanTables()
    {
        if (!$this->confirm('Vuoi cancellare tutti i dati esistenti?')) {
            exit(0);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        $tables = ['assignments', 'availabilities', 'tournaments', 'clubs', 'users', 'referees'];
        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->truncate();
                $this->info("Pulita tabella: {$table}");
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function createBasicData()
    {
        $this->info("Creando zone e tipi torneo...");

        // Zone
        $zones = [
            ['id' => 1, 'name' => 'SZR1', 'description' => 'Zona 1', 'is_national' => 0],
            ['id' => 2, 'name' => 'SZR2', 'description' => 'Zona 2', 'is_national' => 0],
            ['id' => 3, 'name' => 'SZR3', 'description' => 'Zona 3', 'is_national' => 0],
            ['id' => 4, 'name' => 'SZR4', 'description' => 'Zona 4', 'is_national' => 0],
            ['id' => 5, 'name' => 'SZR5', 'description' => 'Zona 5', 'is_national' => 0],
            ['id' => 6, 'name' => 'SZR6', 'description' => 'Zona 6', 'is_national' => 0],
            ['id' => 7, 'name' => 'SZR7', 'description' => 'Zona 7', 'is_national' => 0],
            ['id' => 8, 'name' => 'CRC', 'description' => 'Comitato Regionale', 'is_national' => 1],
        ];

        foreach ($zones as $zone) {
            Zone::updateOrCreate(['id' => $zone['id']], $zone);
        }

        // Tipi Torneo con enum corretti
        $types = [
            ['id' => 1, 'name' => 'Zonale', 'code' => 'ZON', 'is_national' => 0, 'required_level' => 'primo_livello', 'min_referees' => 1, 'max_referees' => 2],
            ['id' => 2, 'name' => 'Regionale', 'code' => 'REG', 'is_national' => 1, 'required_level' => 'regionale', 'min_referees' => 2, 'max_referees' => 3],
            ['id' => 3, 'name' => 'Nazionale', 'code' => 'NAZ', 'is_national' => 1, 'required_level' => 'nazionale', 'min_referees' => 2, 'max_referees' => 4],
        ];

        foreach ($types as $type) {
            TournamentType::updateOrCreate(['id' => $type['id']], $type);
        }

        $this->info("✅ Dati base creati");
    }

    private function importUsers()
    {
        $this->info("Importando utenti da arbitri...");

        $arbitri = DB::connection('old')->table('arbitri')->get();
        $count = 0;

        foreach ($arbitri as $arbitro) {
            if (empty($arbitro->Email)) {
                continue;
            }

            $name = trim(($arbitro->Nome ?? '') . ' ' . ($arbitro->Cognome ?? ''));
            $level = $this->getLevelFromArbitro($arbitro->Livello_2025 ?? '');
            $zone = $this->getZoneFromString($arbitro->Zona ?? '');
            $active = $this->isArbitroActive($arbitro->Livello_2025 ?? '');

            $user = [
                'name' => $name,
                'email' => $arbitro->Email,
                'password' => Hash::make($arbitro->Password ?? 'password123'),
                'user_type' => 'referee',
                'referee_code' => 'ARB' . str_pad($arbitro->id, 4, '0', STR_PAD_LEFT),
                'level' => $level,
                'category' => 'misto',
                'zone_id' => $zone,
                'certified_date' => $this->parseDate($arbitro->Prima_Nomina),
                'phone' => $arbitro->Cellulare,
                'city' => $arbitro->Citta,
                'is_active' => $active,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            User::updateOrCreate(['email' => $arbitro->Email], $user);
            $count++;
        }

        $this->info("✅ Utenti importati: {$count}");
    }

    private function createReferees()
    {
        $this->info("Creando record referees...");

        $users = User::where('user_type', 'referee')->get();
        $count = 0;

        foreach ($users as $user) {
            // Cerca arbitro corrispondente
            $arbitro = DB::connection('old')
                ->table('arbitri')
                ->where('Email', $user->email)
                ->first();

            $referee = [
                'user_id' => $user->id,
                'address' => $arbitro->Pr_abit ?? null,
                'badge_number' => $user->referee_code,
                'first_certification_date' => $this->parseDate($arbitro->Prima_Nomina ?? null),
                'last_renewal_date' => $this->parseDate($arbitro->Ultimo_Esame ?? null),
                'expiry_date' => $this->calculateExpiry($arbitro->Ultimo_Esame ?? null),
                'experience_years' => $this->calculateYears($arbitro->Prima_Nomina ?? null),
                'available_for_international' => in_array($user->level, ['Nazionale', 'Internazionale']),
                'total_tournaments' => 0,
                'tournaments_current_year' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            Referee::updateOrCreate(['user_id' => $user->id], $referee);
            $count++;
        }

        $this->info("✅ Referees creati: {$count}");
    }

    private function importClubs()
    {
        $this->info("Importando circoli...");

        try {
            $circoli = DB::connection('old')->table('circoli')->get();
            $count = 0;

            foreach ($circoli as $circolo) {
                $club = [
                    'id' => $circolo->Id,
                    'code' => $circolo->Circolo_Id ?? 'CLUB' . $circolo->Id,
                    'name' => $circolo->Circolo_Nome ?? 'Club',
                    'address' => $circolo->Indirizzo,
                    'postal_code' => $circolo->CAP,
                    'city' => $circolo->Città,
                    'province' => $circolo->Provincia,
                    'region' => $circolo->Regione,
                    'email' => $circolo->Email,
                    'phone' => $circolo->Telefono,
                    'website' => $circolo->Web,
                    'is_active' => strtoupper($circolo->SedeGara ?? 'Y') === 'Y',
                    'zone_id' => $this->getZoneFromString($circolo->Zona ?? ''),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                Club::updateOrCreate(['id' => $circolo->Id], $club);
                $count++;
            }

            $this->info("✅ Circoli importati: {$count}");

        } catch (\Exception $e) {
            $this->warn("Errore circoli: " . $e->getMessage());
        }
    }

    private function importTournaments()
    {
        $this->info("Importando tornei...");

        try {
            $gare = DB::connection('old')->table('gare_2025')->get();
            $count = 0;

            foreach ($gare as $gara) {
                $clubId = $this->findClub($gara->Circolo ?? '');
                if (!$clubId) {
                    continue;
                }

                $club = Club::find($clubId);
                if (!$club) {
                    continue;
                }

                $tournament = [
                    'id' => $gara->id,
                    'name' => $gara->Nome_gare ?? 'Torneo',
                    'tournament_type_id' => $this->getTournamentType($gara->Tipo ?? ''),
                    'club_id' => $clubId,
                    'start_date' => $this->parseDate($gara->StartTime) ?? now(),
                    'end_date' => $this->parseDate($gara->EndTime) ?? now(),
                    'zone_id' => $this->getZoneFromString($gara->Zona ?? '') ?: $club->zone_id,
                    'availability_deadline' => $this->getDeadline($gara->StartTime),
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                Tournament::updateOrCreate(['id' => $gara->id], $tournament);
                $count++;
            }

            $this->info("✅ Tornei importati: {$count}");

        } catch (\Exception $e) {
            $this->warn("Errore tornei: " . $e->getMessage());
        }
    }

    private function showResults()
    {
        $this->info("\n=== RISULTATI MIGRAZIONE ===");
        $this->info("Users: " . User::count());
        $this->info("Referees: " . Referee::count());
        $this->info("Clubs: " . Club::count());
        $this->info("Tournaments: " . Tournament::count());
        $this->info("✅ MIGRAZIONE COMPLETATA!");
    }

    // Helper methods
    private function getLevelFromArbitro($livello)
    {
        $livello = strtoupper(trim($livello));

        if ($livello === 'ASP') return 'Aspirante';
        if ($livello === '1°') return '1_livello';
        if ($livello === 'REG') return 'Regionale';
        if ($livello === 'NAZ' || $livello === 'NAZ/INT') return 'Nazionale';
        if ($livello === 'INT') return 'Internazionale';
        if ($livello === 'ARCH') return 'Archivio';

        return '1_livello';
    }

    private function getZoneFromString($zona)
    {
        if (preg_match('/(\d+)/', $zona, $matches)) {
            $num = (int)$matches[1];
            return min($num, 8);
        }
        return 1;
    }

    private function isArbitroActive($livello)
    {
        return !empty($livello) && strtoupper($livello) !== 'ARCH';
    }

    private function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function calculateExpiry($lastRenewal)
    {
        $date = $this->parseDate($lastRenewal);
        if (!$date) {
            return null;
        }
        return $date->addYears(2);
    }

    private function calculateYears($firstCert)
    {
        $date = $this->parseDate($firstCert);
        if (!$date) {
            return 0;
        }
        return now()->diffInYears($date);
    }

    private function findClub($clubName)
    {
        if (empty($clubName)) {
            return null;
        }

        return Club::where('name', 'LIKE', "%{$clubName}%")
            ->orWhere('code', 'LIKE', "%{$clubName}%")
            ->value('id');
    }

    private function getTournamentType($tipo)
    {
        $tipo = strtoupper(trim($tipo));

        if (in_array($tipo, ['NAZ', 'CN', 'NAZIONALE'])) return 3;
        if (in_array($tipo, ['REG', 'CI', 'REGIONALE'])) return 2;

        return 1; // Default zonale
    }

    private function getDeadline($startTime)
    {
        $date = $this->parseDate($startTime);
        if (!$date) {
            return now()->format('Y-m-d');
        }
        return $date->subDays(7)->format('Y-m-d');
    }
}
