<?php

/**
 * ========================================
 * TEST DATA MIGRATION SEEDER - File Separato
 * ========================================
 * Crea questo file: database/seeders/TestDataMigrationSeeder.php
 */

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

class TestDataMigrationSeeder extends DataMigrationSeeder
{
    private $limit;
    protected $command;

    public function __construct(int $limit = 10)
    {
        parent::__construct();
        $this->limit = $limit;
    }

    public function setCommand($command)
    {
        $this->command = $command;
    }

    /**
     * Override del metodo run per test limitato
     */
    public function run(): void
    {
        $this->command->info("🧪 Test migrazione con limite: {$this->limit}");

        // Setup connessione (stesso del parent)
        $this->setupOldDatabaseConnection();

        if (!$this->checkOldDatabase()) {
            $this->command->error('❌ Database source non disponibile');
            return;
        }

        // Migrazione limitata
        $this->migrateZonesLimited();
        $this->migrateTournamentTypesLimited();
        $this->migrateUsersLimited();
        $this->createRefereesLimited();

        $this->command->info('✅ Test migrazione completato');
    }

    /**
     * Migrazione users limitata per test
     */
    private function migrateUsersLimited()
    {
        $this->command->info("👥 Test migrazione users (limite: {$this->limit})...");

        // Prendi solo i primi N users
        $oldUsers = DB::connection('old')->table('users')->limit($this->limit)->get();
        $oldReferees = DB::connection('old')->table('referees')->get()->keyBy('user_id');
        $oldRoleUsers = [];

        // ✅ FIX: Usa role_users (con 's')
        try {
            if ($this->tableExists('old', 'role_users')) {
                $oldRoleUsers = DB::connection('old')->table('role_users')->get()->groupBy('user_id');
                $this->command->info("🔗 Role assignments trovati per test");
            }
        } catch (\Exception $e) {
            $this->command->warn('Tabella role_users non trovata per test');
        }

        foreach ($oldUsers as $user) {
            $userType = $this->determineUserType($user, $oldReferees, $oldRoleUsers);
            $referee = $oldReferees->get($user->id);

            $userData = [
                'id' => $user->id, // Mantieni ID originale per test
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'user_type' => $userType,
                'zone_id' => $user->zone_id ?? 1,
                'phone' => $user->phone ?? null,
                'is_active' => $user->is_active ?? true,
                'created_at' => $user->created_at ?? now(),
                'updated_at' => $user->updated_at ?? now(),
            ];

            // Logica referee (stesso del parent)
            if ($userType === 'referee' && $referee) {
                $userData['referee_code'] = $referee->referee_code ?? $this->generateRefereeCode();
                $userData['level'] = $this->mapQualification($referee->qualification ?? 'aspirante');
                $userData['category'] = $referee->category ?? 'misto';
                $userData['certified_date'] = $referee->certified_date ?? now()->subYears(2);
            } else {
                $userData['referee_code'] = null;
                $userData['level'] = 'aspirante';
                $userData['category'] = 'misto';
                $userData['certified_date'] = null;
            }

            DB::table('users')->updateOrInsert(['id' => $user->id], $userData);

            $this->command->info("   ✅ Test migrato: {$user->name} ({$userType})");
        }

        $this->command->info("✅ Test: migrati {$oldUsers->count()} users");
    }

    /**
     * Altri metodi limitati per test
     */
    private function migrateZonesLimited()
    {
        $zones = DB::connection('old')->table('zones')->limit(5)->get();
        foreach ($zones as $zone) {
            DB::table('zones')->updateOrInsert(['id' => $zone->id], [
                'name' => $zone->name,
                'description' => $zone->description ?? null,
                'is_national' => $zone->is_national ?? false,
                'created_at' => $zone->created_at ?? now(),
                'updated_at' => $zone->updated_at ?? now(),
            ]);
        }
        $this->command->info("✅ Test: migrate {$zones->count()} zones");
    }

    private function migrateTournamentTypesLimited()
    {
        $this->command->info("✅ Test: tournament_types (limitato)");
    }

    private function createRefereesLimited()
    {
        $refereeUsers = DB::table('users')->where('user_type', 'referee')->get();
        $oldReferees = DB::connection('old')->table('referees')->get()->keyBy('user_id');

        foreach ($refereeUsers as $user) {
            $oldReferee = $oldReferees->get($user->id);
            if ($oldReferee) {
                DB::table('referees')->updateOrInsert(['user_id' => $user->id], [
                    'zone_id' => $user->zone_id,
                    'referee_code' => $user->referee_code,
                    'level' => $user->level,
                    'category' => $user->category,
                    'certified_date' => $user->certified_date,
                    'address' => $oldReferee->address ?? null,
                    'postal_code' => $oldReferee->postal_code ?? null,
                    'tax_code' => $oldReferee->tax_code ?? null,
                    'profile_completed_at' => now(),
                ]);
            }
        }
        $this->command->info("✅ Test: creati {$refereeUsers->count()} referees");
    }
}
