<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Core system settings
            SettingsSeeder::class,

            // Zones and administrative structure
            ZonesSeeder::class,

            // Tournament categories
            TournamentCategoriesSeeder::class,

            // Users (Super Admin, Zone Admins, etc.)
            UsersSeeder::class,

            // Golf clubs
            ClubsSeeder::class,

            // Referees
            RefereesSeeder::class,

            // Sample tournaments (for development/testing)
            TournamentsSeeder::class,

            // Sample assignments (for development/testing)
            AssignmentsSeeder::class,

            // Notifications templates
            NotificationTemplatesSeeder::class,
        ]);
    }
}
