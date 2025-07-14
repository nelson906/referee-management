<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * CLEANUP & DATA MIGRATION from old structure to new
     */
    public function up(): void
    {
        // ✅ STEP 1: Migrate Referee data from old referees table to users table
        $this->migrateRefereeDataToUsers();

        // ✅ STEP 2: Clean referees table - keep only extension fields
        $this->cleanRefereesTable();

        // ✅ STEP 3: Update tournament foreign keys if needed
        $this->updateTournamentForeignKeys();

        // ✅ STEP 4: Sync tournament_types settings
        $this->syncTournamentTypeSettings();
    }

    /**
     * Migrate referee core data from referees to users table
     */
    private function migrateRefereeDataToUsers(): void
    {
        // Check if old referees table exists and has data
        if (!Schema::hasTable('referees_old')) {
            // If this is a fresh install, skip migration
            return;
        }

        DB::transaction(function () {
            // Get all users that are referees but missing referee data in users table
            $usersNeedingUpdate = DB::table('users')
                ->leftJoin('referees_old', 'users.id', '=', 'referees_old.user_id')
                ->where('users.user_type', 'referee')
                ->whereNull('users.referee_code')
                ->whereNotNull('referees_old.id')
                ->select('users.id', 'referees_old.*')
                ->get();

            foreach ($usersNeedingUpdate as $userData) {
                DB::table('users')
                    ->where('id', $userData->id)
                    ->update([
                        'referee_code' => $userData->referee_code,
                        'level' => $userData->level,
                        'category' => $userData->category,
                        'zone_id' => $userData->zone_id,
                        'certified_date' => $userData->certified_date,
                        'phone' => $userData->phone ?? null,
                        'is_active' => $userData->is_active ?? true,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    /**
     * Clean referees table - remove duplicate fields
     */
    private function cleanRefereesTable(): void
    {
        if (!Schema::hasTable('referees_old')) {
            return;
        }

        DB::transaction(function () {
            // Migrate extension data from old referees to new referees table
            $refereesToMigrate = DB::table('referees_old')->get();

            foreach ($refereesToMigrate as $oldReferee) {
                DB::table('referees')->updateOrInsert(
                    ['user_id' => $oldReferee->user_id],
                    [
                        'address' => $oldReferee->address,
                        'postal_code' => $oldReferee->postal_code,
                        'tax_code' => $oldReferee->tax_code,
                        'badge_number' => $oldReferee->badge_number,
                        'first_certification_date' => $oldReferee->first_certification_date,
                        'last_renewal_date' => $oldReferee->last_renewal_date,
                        'expiry_date' => $oldReferee->expiry_date,
                        'qualifications' => $oldReferee->qualifications,
                        'languages' => $oldReferee->languages,
                        'available_for_international' => $oldReferee->available_for_international ?? false,
                        'specializations' => $oldReferee->specializations,
                        'total_tournaments' => $oldReferee->total_tournaments ?? 0,
                        'tournaments_current_year' => $oldReferee->tournaments_current_year ?? 0,
                        'profile_completed_at' => $oldReferee->profile_completed_at,
                        'created_at' => $oldReferee->created_at,
                        'updated_at' => $oldReferee->updated_at,
                    ]
                );
            }
        });
    }

    /**
     * Update tournament foreign keys if using old names
     */
    private function updateTournamentForeignKeys(): void
    {
        // Check if tournaments table has old foreign key name
        if (Schema::hasColumn('tournaments', 'tournament_category_id')) {

            // Check if tournament_categories table exists (old name)
            if (Schema::hasTable('tournament_categories')) {
                // Migrate data from tournament_categories to tournament_types
                DB::table('tournament_types')->insertUsing(
                    ['id', 'name', 'code', 'description', 'is_national', 'level', 'required_level',
                     'min_referees', 'max_referees', 'sort_order', 'is_active', 'settings', 'created_at', 'updated_at'],
                    DB::table('tournament_categories')->select([
                        'id', 'name', 'code', 'description', 'is_national', 'level', 'required_level',
                        'min_referees', 'max_referees', 'sort_order', 'is_active', 'settings', 'created_at', 'updated_at'
                    ])
                );
            }

            // Update foreign key column name
            Schema::table('tournaments', function (Blueprint $table) {
                $table->renameColumn('tournament_category_id', 'tournament_type_id');
            });
        }
    }

    /**
     * Sync tournament_types settings with physical columns
     */
    private function syncTournamentTypeSettings(): void
    {
        DB::table('tournament_types')->orderBy('id')->each(function ($tournamentType) {
            $settings = json_decode($tournamentType->settings, true) ?? [];

            // Sync physical columns with JSON settings
            $settings['min_referees'] = $tournamentType->min_referees;
            $settings['max_referees'] = $tournamentType->max_referees;
            $settings['required_referee_level'] = $tournamentType->required_level;

            DB::table('tournament_types')
                ->where('id', $tournamentType->id)
                ->update(['settings' => json_encode($settings)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is one-way only
        throw new Exception('This cleanup migration cannot be reversed. Restore from backup if needed.');
    }
};
