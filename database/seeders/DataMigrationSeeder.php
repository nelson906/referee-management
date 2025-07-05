<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DataMigrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
            // Configura connessione al database vecchio
        $this->setupOldDatabaseConnection();

    $this->command->info('🚀 Inizio migrazione dati dal vecchio database...');

        // 1. Migra zones (dovrebbero essere già presenti)
        $this->migrateZones();

        // 2. Migra users
        $this->migrateUsers();

        // 3. Crea referees dai users
        $this->createReferees();

        // 4. Migra clubs → clubs
        $this->migrateClubs();

        // 5. Migra tournament_types → tournament_categories
        $this->migrateTournamentTypes();

        // 6. Migra tournaments
        $this->migrateTournaments();

        // 7. Migra availabilities
        $this->migrateAvailabilities();

        // 8. Migra assignments
        $this->migrateAssignments();

        // 9. Crea dati di supporto
        $this->createSupportData();

        $this->command->info('✅ Migrazione dati completata!');
    }

    private function migrateZones()
    {
        $this->command->info('📍 Migrazione zones...');

        $oldZones = DB::connection('old')->table('zones')->get();

        foreach ($oldZones as $zone) {
            DB::table('zones')->updateOrInsert(
                ['id' => $zone->id],
                [
                    'name' => $zone->name,
                    'code' => 'Z' . str_pad($zone->id, 2, '0', STR_PAD_LEFT),
                    'description' => $zone->description,
                    'is_national' => $zone->is_national,
                    'is_active' => true,
                    'sort_order' => $zone->id * 10,
                    'created_at' => $zone->created_at,
                    'updated_at' => $zone->updated_at,
                ]
            );
        }

        $this->command->info("✅ Migrati " . $oldZones->count() . " zones");
    }

    private function migrateUsers()
    {
        $this->command->info('👥 Migrazione users...');

        $oldUsers = DB::connection('old')->table('users')->get();

        foreach ($oldUsers as $user) {
            // Mappa i livelli vecchi a quelli nuovi
            $levelMapping = [
                '1_livello' => 'primo_livello',
                'regionale' => 'regionale',
                'nazionale_internazionale' => 'nazionale',
                'aspirante' => 'aspirante',
                'archivio' => 'aspirante', // Mappa archivio ad aspirante
            ];

            $newLevel = $levelMapping[$user->level] ?? 'aspirante';

            DB::table('users')->updateOrInsert(
                ['id' => $user->id],
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'password' => $user->password,
                    'remember_token' => $user->remember_token,
                    'user_type' => $user->user_type,
                    'zone_id' => $user->zone_id,
                    'phone' => $user->phone,
                    'city' => null, // Non presente nel vecchio DB
                    'is_active' => $user->is_active,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            );
        }

        $this->command->info("✅ Migrati " . $oldUsers->count() . " users");
    }

    private function createReferees()
    {
        $this->command->info('🏌️ Creazione referees...');

        $refereeUsers = DB::table('users')->where('user_type', 'referee')->get();

        foreach ($refereeUsers as $user) {
            // Prendi i dati referee dal vecchio user
            $oldUser = DB::connection('old')->table('users')->where('id', $user->id)->first();

            if (!$oldUser) continue;

            // Mappa i livelli
            $levelMapping = [
                '1_livello' => 'primo_livello',
                'regionale' => 'regionale',
                'nazionale_internazionale' => 'nazionale',
                'aspirante' => 'aspirante',
                'archivio' => 'aspirante',
            ];

            $level = $levelMapping[$oldUser->level] ?? 'aspirante';

            DB::table('referees')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'zone_id' => $user->zone_id,
                    'referee_code' => $oldUser->referee_code ?: $this->generateRefereeCode(),
                    'level' => $level,
                    'category' => 'misto', // Default
                    'certified_date' => now()->subYears(2), // Default
                    'address' => null,
                    'postal_code' => null,
                    'tax_code' => null,
                    'profile_completed_at' => now(),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            );
        }

        $this->command->info("✅ Creati " . $refereeUsers->count() . " referees");
    }

    private function migrateClubs()
    {
        $this->command->info('🏌️ Migrazione clubs → clubs...');

        $oldClubs = DB::connection('old')->table('clubs')->get();

        foreach ($oldClubs as $club) {
            DB::table('clubs')->updateOrInsert(
                ['id' => $club->id],
                [
                    'name' => $club->name,
                    'code' => $club->short_name,
                    'zone_id' => $club->zone_id,
                    'address' => $club->address,
                    'city' => $club->city,
                    'province' => $club->province,
                    'phone' => $club->phone,
                    'email' => $club->email,
                    'is_active' => $club->is_active,
                    'notes' => $club->notes,
                    'created_at' => $club->created_at,
                    'updated_at' => $club->updated_at,
                ]
            );
        }

        $this->command->info("✅ Migrati " . $oldClubs->count() . " clubs → clubs");
    }

    private function migrateTournamentTypes()
    {
        $this->command->info('🏆 Migrazione tournament_types → tournament_categories...');

        $oldTypes = DB::connection('old')->table('tournament_types')->get();

        foreach ($oldTypes as $type) {
            // Mappa required_level
            $levelMapping = [
                '1_livello' => 'primo_livello',
                'regionale' => 'regionale',
                'nazionale' => 'nazionale',
                'internazionale' => 'internazionale',
            ];

            $requiredLevel = $levelMapping[$type->required_level] ?? 'primo_livello';

            // Crea settings JSON
            $settings = [
                'required_referee_level' => $requiredLevel,
                'min_referees' => 1,
                'max_referees' => $type->referees_needed,
                'visibility_zones' => $type->is_national ? 'all' : 'own',
            ];

            DB::table('tournament_categories')->updateOrInsert(
                ['id' => $type->id],
                [
                    'name' => $type->name,
                    'code' => $type->short_name,
                    'description' => $type->description,
                    'is_national' => $type->is_national,
                    'level' => $type->is_national ? 'nazionale' : 'zonale',
                    'required_level' => $requiredLevel,
                    'sort_order' => $type->id * 10,
                    'is_active' => $type->is_active,
                    'settings' => json_encode($settings),
                    'created_at' => $type->created_at,
                    'updated_at' => $type->updated_at,
                ]
            );
        }

        $this->command->info("✅ Migrati " . $oldTypes->count() . " tournament_types → tournament_categories");
    }

    private function migrateTournaments()
    {
        $this->command->info('🏆 Migrazione tournaments...');

        $oldTournaments = DB::connection('old')->table('tournaments')->get();

        foreach ($oldTournaments as $tournament) {
            DB::table('tournaments')->updateOrInsert(
                ['id' => $tournament->id],
                [
                    'name' => $tournament->name,
                    'start_date' => $tournament->start_date,
                    'end_date' => $tournament->end_date,
                    'availability_deadline' => $tournament->availability_deadline,
                    'club_id' => $tournament->club_id, // club_id → club_id
                    'tournament_category_id' => $tournament->tournament_type_id, // tournament_type_id → tournament_category_id
                    'zone_id' => $tournament->zone_id,
                    'notes' => $tournament->notes,
                    'status' => $tournament->status,
                    'convocation_letter' => $tournament->convocation_letter,
                    'club_letter' => $tournament->club_letter,
                    'letters_generated_at' => $tournament->letters_generated_at,
                    'convocation_file_path' => $tournament->convocation_file_path,
                    'convocation_file_name' => $tournament->convocation_file_name,
                    'convocation_generated_at' => $tournament->convocation_generated_at,
                    'club_letter_file_path' => $tournament->club_letter_file_path,
                    'club_letter_file_name' => $tournament->club_letter_file_name,
                    'club_letter_generated_at' => $tournament->club_letter_generated_at,
                    'documents_last_updated_by' => $tournament->documents_last_updated_by,
                    'document_version' => $tournament->document_version,
                    'created_at' => $tournament->created_at,
                    'updated_at' => $tournament->updated_at,
                ]
            );
        }

        $this->command->info("✅ Migrati " . $oldTournaments->count() . " tournaments");
    }

    private function migrateAvailabilities()
    {
        $this->command->info('📅 Migrazione availabilities...');

        $oldAvailabilities = DB::connection('old')->table('availabilities')->get();

        foreach ($oldAvailabilities as $availability) {
            DB::table('availabilities')->updateOrInsert(
                ['id' => $availability->id],
                [
                    'user_id' => $availability->user_id,
                    'tournament_id' => $availability->tournament_id,
                    'notes' => $availability->notes,
                    'submitted_at' => $availability->submitted_at ?: now(),
                    'created_at' => $availability->created_at,
                    'updated_at' => $availability->updated_at,
                ]
            );
        }

        $this->command->info("✅ Migrati " . $oldAvailabilities->count() . " availabilities");
    }

    private function migrateAssignments()
    {
        $this->command->info('📝 Migrazione assignments...');

        $oldAssignments = DB::connection('old')->table('assignments')->get();

        foreach ($oldAssignments as $assignment) {
            DB::table('assignments')->updateOrInsert(
                ['id' => $assignment->id],
                [
                    'tournament_id' => $assignment->tournament_id,
                    'user_id' => $assignment->user_id,
                    'assigned_by_id' => $assignment->assigned_by, // assigned_by → assigned_by_id
                    'role' => $assignment->role,
                    'notes' => $assignment->notes,
                    'is_confirmed' => $assignment->is_confirmed,
                    'assigned_at' => $assignment->assigned_at,
                    'created_at' => $assignment->created_at,
                    'updated_at' => $assignment->updated_at,
                ]
            );
        }

        $this->command->info("✅ Migrati " . $oldAssignments->count() . " assignments");
    }

    private function createSupportData()
    {
        $this->command->info('🔧 Creazione dati di supporto...');

        // Crea institutional_emails da fixed_addresses
        $fixedAddresses = DB::connection('old')->table('fixed_addresses')->where('active', 1)->get();

        foreach ($fixedAddresses as $address) {
            DB::table('institutional_emails')->updateOrInsert(
                ['email' => $address->email],
                [
                    'name' => $address->name,
                    'email' => $address->email,
                    'description' => $address->description,
                    'zone_id' => $address->zone_id,
                    'category' => $address->category ?? null,
                    'is_active' => $address->active,
                    'receive_all_notifications' => true,
                    'notification_types' => json_encode(['assignment', 'availability']),
                    'created_at' => $address->created_at,
                    'updated_at' => $address->updated_at,
                ]
            );
        }

        // Crea letter_templates da template_letters
        $templateLetters = DB::connection('old')->table('template_letters')->where('is_active', 1)->get();

        foreach ($templateLetters as $template) {
            DB::table('letter_templates')->updateOrInsert(
                ['name' => $template->name],
                [
                    'name' => $template->name,
                    'type' => $template->type === 'club' ? 'club' : $template->type,
                    'subject' => $template->header ?: 'Comunicazione',
                    'body' => $template->body,
                    'zone_id' => $template->zone_id,
                    'is_active' => $template->is_active,
                    'variables' => $template->merge_fields,
                    'created_at' => $template->created_at,
                    'updated_at' => $template->updated_at,
                ]
            );
        }

        $this->command->info("✅ Creati dati di supporto");
    }

    private function generateRefereeCode(): string
    {
        do {
            $code = 'ARB' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (DB::table('referees')->where('referee_code', $code)->exists());

        return $code;
    }

        private function setupOldDatabaseConnection()
    {
        config(['database.connections.old' => [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => 'golf_referee_new',
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);
    }

    private function checkOldDatabase(): bool
    {
        try {
            DB::connection('old')->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
