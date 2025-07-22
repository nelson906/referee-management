<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\Helpers\SeederHelper;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('👥 Creando Utenti Amministratori Sistema Golf...');

        // Elimina utenti admin esistenti per evitare duplicati
        User::whereIn('user_type', ['super_admin', 'national_admin', 'admin'])->delete();

        // Crea Super Admin
        $this->createSuperAdmin();

        // Crea National Admin (CRC)
        $this->createNationalAdmin();

        // Crea Zone Admin
        $this->createZoneAdmins();

        // Valida e mostra riassunto
        $this->validateUsers();
        $this->showUserSummary();
    }

    /**
     * Crea Super Admin globale
     */
    private function createSuperAdmin(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Sistema',
            'email' => 'superadmin@golf.it',
            'email_verified_at' => now(),
            'password' => SeederHelper::getTestPassword(),
            'user_type' => 'super_admin',
            'zone_id' => null,
            'is_active' => true,
            'phone' => '+39 06 1234567',
            'city' => 'Roma',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("✅ Super Admin creato: {$superAdmin->email}");
    }

    /**
     * Crea National Admin (CRC - Comitato Regole e Competizioni)
     */
    private function createNationalAdmin(): void
    {
        $nationalAdmin = User::create([
            'name' => 'CRC Admin Nazionale',
            'email' => 'crc@golf.it',
            'email_verified_at' => now(),
            'password' => SeederHelper::getTestPassword(),
            'user_type' => 'national_admin',
            'zone_id' => null,
            'is_active' => true,
            'phone' => '+39 06 7890123',
            'city' => 'Roma',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("✅ National Admin creato: {$nationalAdmin->email}");

        // Opzionalmente crea un secondo National Admin
        $secondNationalAdmin = User::create([
            'name' => 'CRC Admin Vicario',
            'email' => 'crc.vicario@golf.it',
            'email_verified_at' => now(),
            'password' => SeederHelper::getTestPassword(),
            'user_type' => 'national_admin',
            'zone_id' => null,
            'is_active' => true,
            'phone' => '+39 06 4567890',
            'city' => 'Roma',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("✅ National Admin Vicario creato: {$secondNationalAdmin->email}");
    }

    /**
     * Crea Zone Admin (uno per ogni zona)
     */
    private function createZoneAdmins(): void
    {
        $zones = Zone::orderBy('code')->get();

        foreach ($zones as $zone) {
            $zoneName = $this->getZoneShortName($zone->name);

            $zoneAdmin = User::create([
                'name' => "Admin {$zoneName}",
                'email' => "admin.{$zone->code}@golf.it",
                'email_verified_at' => now(),
                'password' => SeederHelper::getTestPassword(),
                'user_type' => 'admin',
                'zone_id' => $zone->id,
                'is_active' => true,
                'phone' => $this->generatePhoneForZone($zone->code),
                'city' => $this->getMainCityForZone($zone->code),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("✅ Zone Admin creato: {$zoneAdmin->email} per zona {$zone->code}");
        }
    }

    /**
     * Ottieni nome breve zona
     */
    private function getZoneShortName(string $zoneName): string
    {
        $shortNames = [
            'Piemonte-Valle d\'Aosta' => 'Piemonte-VdA',
            'Lombardia' => 'Lombardia',
            'Veneto-Trentino' => 'Veneto-Trentino',
            'Emilia Romagna-Marche' => 'Emilia-Marche',
            'Toscana-Umbria' => 'Toscana-Umbria',
            'Lazio-Abruzzo-Molise' => 'Lazio-Abruzzo',
            'Sud Italia-Sicilia-Sardegna' => 'Sud-Isole'
        ];

        return $shortNames[$zoneName] ?? $zoneName;
    }

    /**
     * Genera telefono per zona
     */
    private function generatePhoneForZone(string $zoneCode): string
    {
        $phoneNumbers = [
            'SZR1' => '+39 011 1234567', // Torino
            'SZR2' => '+39 02 1234567',  // Milano
            'SZR3' => '+39 041 1234567', // Venezia
            'SZR4' => '+39 051 1234567', // Bologna
            'SZR5' => '+39 055 1234567', // Firenze
            'SZR6' => '+39 06 1234567',  // Roma
            'SZR7' => '+39 081 1234567', // Napoli
        ];

        return $phoneNumbers[$zoneCode] ?? '+39 06 0000000';
    }

    /**
     * Ottieni città principale per zona
     */
    private function getMainCityForZone(string $zoneCode): string
    {
        $cities = [
            'SZR1' => 'Torino',
            'SZR2' => 'Milano',
            'SZR3' => 'Venezia',
            'SZR4' => 'Bologna',
            'SZR5' => 'Firenze',
            'SZR6' => 'Roma',
            'SZR7' => 'Napoli',
        ];

        return $cities[$zoneCode] ?? 'Roma';
    }

    /**
     * Valida utenti creati
     */
    private function validateUsers(): void
    {
        $this->command->info('🔍 Validando utenti amministratori...');

        // Verifica Super Admin
        $superAdmins = User::where('user_type', 'super_admin')->count();
        if ($superAdmins !== 1) {
            $this->command->error("❌ Errore: dovrebbe esserci 1 Super Admin, trovati {$superAdmins}");
            return;
        }

        // Verifica National Admin
        $nationalAdmins = User::where('user_type', 'national_admin')->count();
        if ($nationalAdmins < 1 || $nationalAdmins > 2) {
            $this->command->error("❌ Errore: dovrebbero esserci 1-2 National Admin, trovati {$nationalAdmins}");
            return;
        }

        // Verifica Zone Admin
        $zoneAdmins = User::where('user_type', 'admin')->count();
        $totalZones = Zone::count();
        if ($zoneAdmins !== $totalZones) {
            $this->command->error("❌ Errore: dovrebbero esserci {$totalZones} Zone Admin, trovati {$zoneAdmins}");
            return;
        }

        // Verifica che ogni zona abbia il suo admin
        $zonesWithoutAdmin = Zone::whereNotExists(function ($query) {
            $query->select('id')
                  ->from('users')
                  ->where('user_type', 'admin')
                  ->whereColumn('users.zone_id', 'zones.id');
        })->count();

        if ($zonesWithoutAdmin > 0) {
            $this->command->error("❌ Errore: {$zonesWithoutAdmin} zone senza admin");
            return;
        }

        // Verifica email univoche
        $totalUsers = User::whereIn('user_type', ['super_admin', 'national_admin', 'admin'])->count();
        $uniqueEmails = User::whereIn('user_type', ['super_admin', 'national_admin', 'admin'])
                           ->distinct('email')->count();

        if ($totalUsers !== $uniqueEmails) {
            $this->command->error("❌ Errore: email duplicate tra amministratori");
            return;
        }

        $this->command->info('✅ Validazione utenti amministratori completata con successo');
    }

    /**
     * Mostra riassunto utenti creati
     */
    private function showUserSummary(): void
    {
        $this->command->info('');
        $this->command->info('👥 RIASSUNTO UTENTI AMMINISTRATORI:');
        $this->command->info('=====================================');

        // Super Admin
        $superAdmin = User::where('user_type', 'super_admin')->first();
        if ($superAdmin) {
            $this->command->info("🔴 SUPER ADMIN: {$superAdmin->email} ({$superAdmin->name})");
        }

        // National Admin
        $nationalAdmins = User::where('user_type', 'national_admin')->get();
        foreach ($nationalAdmins as $admin) {
            $this->command->info("🟡 NATIONAL ADMIN: {$admin->email} ({$admin->name})");
        }

        // Zone Admin
        $zoneAdmins = User::where('user_type', 'admin')
                         ->with('zone')
                         ->orderBy('zone_id')
                         ->get();

        foreach ($zoneAdmins as $admin) {
            $zoneCode = $admin->zone ? $admin->zone->code : 'N/A';
            $this->command->info("🟢 ZONE ADMIN: {$admin->email} ({$zoneCode} - {$admin->name})");
        }

        $this->command->info('=====================================');
        $this->command->info('📧 CREDENZIALI TEST:');
        $this->command->info('Password per tutti: password123');
        $this->command->info('');
        $this->command->info('🔐 LOGIN RAPIDI:');
        $this->command->info('Super Admin: superadmin@golf.it');
        $this->command->info('National Admin: crc@golf.it');
        $this->command->info('Zone SZR6 Admin: admin.SZR6@golf.it');
        $this->command->info('=====================================');

        $totalAdmins = User::whereIn('user_type', ['super_admin', 'national_admin', 'admin'])->count();
        $this->command->info("Totale amministratori: {$totalAdmins}");
        $this->command->info('');
    }
}
