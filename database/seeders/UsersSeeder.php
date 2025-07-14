<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Zone;
use App\Models\Referee;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get zones for assignments
        $zones = Zone::all();

        if ($zones->isEmpty()) {
            $this->command->warn('⚠️ Nessuna zona trovata. Esegui prima ZonesSeeder.');
            return;
        }

        // 1. SUPER ADMIN
        $superAdmin = User::updateOrCreate([
            'email' => 'superadmin@example.com'
        ], [
            'name' => 'Super Administrator',
            'password' => Hash::make('password'),
            'user_type' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // 2. NATIONAL ADMIN (CRC)
        $nationalAdmin = User::updateOrCreate([
            'email' => 'crc@example.com'
        ], [
            'name' => 'Commissione Regole e Competizioni',
            'password' => Hash::make('password'),
            'user_type' => 'national_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // 3. ZONE ADMINS
        foreach ($zones as $zone) {
            User::updateOrCreate([
                'email' => strtolower($zone->code) . '.admin@example.com'
            ], [
                'name' => 'Admin ' . $zone->name,
                'password' => Hash::make('password'),
                'user_type' => 'admin',
                'zone_id' => $zone->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        // 4. REFEREE SAMPLES (USER-CENTRIC APPROACH)
        $refereeData = [
            [
                'name' => 'Mario Rossi',
                'email' => 'mario.rossi@example.com',
                'referee_code' => 'ARB001',
                'level' => 'nazionale',
                'category' => 'misto',
                'phone' => '+39 333 1234567',
                'city' => 'Roma',
                'zone_id' => $zones->where('code', 'CENTRO')->first()?->id ?? $zones->first()->id,
                'certified_date' => now()->subYears(5),
            ],
            [
                'name' => 'Giulia Bianchi',
                'email' => 'giulia.bianchi@example.com',
                'referee_code' => 'ARB002',
                'level' => 'regionale',
                'category' => 'femminile',
                'phone' => '+39 333 2345678',
                'city' => 'Milano',
                'zone_id' => $zones->where('code', 'NORD')->first()?->id ?? $zones->first()->id,
                'certified_date' => now()->subYears(3),
            ],
            [
                'name' => 'Luca Verdi',
                'email' => 'luca.verdi@example.com',
                'referee_code' => 'ARB003',
                'level' => 'primo_livello',
                'category' => 'maschile',
                'phone' => '+39 333 3456789',
                'city' => 'Napoli',
                'zone_id' => $zones->where('code', 'SUD')->first()?->id ?? $zones->first()->id,
                'certified_date' => now()->subYears(2),
            ],
            [
                'name' => 'Anna Neri',
                'email' => 'anna.neri@example.com',
                'referee_code' => 'ARB004',
                'level' => 'aspirante',
                'category' => 'misto',
                'phone' => '+39 333 4567890',
                'city' => 'Palermo',
                'zone_id' => $zones->where('code', 'SICILIA')->first()?->id ?? $zones->first()->id,
                'certified_date' => now()->subYear(),
            ],
            [
                'name' => 'Roberto Blu',
                'email' => 'roberto.blu@example.com',
                'referee_code' => 'ARB005',
                'level' => 'internazionale',
                'category' => 'misto',
                'phone' => '+39 333 5678901',
                'city' => 'Cagliari',
                'zone_id' => $zones->where('code', 'SARDEGNA')->first()?->id ?? $zones->first()->id,
                'certified_date' => now()->subYears(8),
            ],
        ];

        foreach ($refereeData as $refData) {
            // Create USER with referee data (USER-CENTRIC)
            $user = User::updateOrCreate([
                'email' => $refData['email']
            ], array_merge($refData, [
                'password' => Hash::make('password'),
                'user_type' => 'referee',
                'is_active' => true,
                'email_verified_at' => now(),
            ]));

            // Create REFEREE extension record if needed
            Referee::updateOrCreate([
                'user_id' => $user->id
            ], [
                'zone_id' => $user->zone_id,
                'referee_code' => $user->referee_code,
                'level' => $user->level,
                'category' => $user->category,
                'certified_date' => $user->certified_date,
                'bio' => 'Arbitro di golf certificato',
                'experience_years' => rand(1, 10),
                'specializations' => json_encode(['stroke_play', 'match_play']),
                'languages' => json_encode(['it', 'en']),
                'profile_completed_at' => now(),
            ]);
        }

        $this->command->info('✅ Utenti creati con successo:');
        $this->command->info('   - 1 Super Admin');
        $this->command->info('   - 1 National Admin');
        $this->command->info('   - ' . $zones->count() . ' Zone Admins');
        $this->command->info('   - ' . count($refereeData) . ' Referees');
        $this->command->info('📧 Email/Password: [email]/password per tutti');
    }
}
