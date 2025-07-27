<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;

class CreateGolfAdmin extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'golf:create-admin
                            {type? : Tipo admin (zone|national|super|all)}
                            {--zone= : Codice zona specifica (es. SZR1)}
                            {--email= : Email personalizzata}
                            {--password= : Password personalizzata}
                            {--name= : Nome personalizzato}
                            {--dry-run : Esegui in modalità simulazione}
                            {--force : Forza creazione anche se esistono admin}';

    /**
     * The console command description.
     */
    protected $description = 'Crea amministratori per il sistema Golf Referee Management';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🏌️ GOLF ADMIN CREATOR');
        $this->info('====================');

        $type = $this->argument('type') ?? 'all';
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('🧪 MODALITÀ DRY-RUN ATTIVATA - Nessuna modifica al database');
        }

        // Verifica prerequisiti
        if (!$this->checkPrerequisites()) {
            return 1;
        }

        // Esegui creazione in base al tipo richiesto
        switch ($type) {
            case 'zone':
            case 'zones':
                return $this->createZoneAdmins($dryRun, $force);

            case 'national':
                return $this->createNationalAdmin($dryRun, $force);

            case 'super':
                return $this->createSuperAdmin($dryRun, $force);

            case 'all':
                $this->createZoneAdmins($dryRun, $force);
                $this->createNationalAdmin($dryRun, $force);
                $this->createSuperAdmin($dryRun, $force);
                return 0;

            default:
                $this->error("❌ Tipo non valido: {$type}");
                $this->info("Tipi validi: zone, national, super, all");
                return 1;
        }
    }

    /**
     * Verifica prerequisiti del sistema
     */
    private function checkPrerequisites(): bool
    {
        // Verifica che esistano le zone
        $zoneCount = Zone::count();
        if ($zoneCount === 0) {
            $this->error('❌ Nessuna zona trovata nel database!');
            $this->info('Esegui prima: php artisan db:seed --class=ZoneSeeder');
            return false;
        }

        $this->info("✅ Trovate {$zoneCount} zone nel database");
        return true;
    }

    /**
     * Crea amministratori zonali (SZR1-SZR7)
     */
    private function createZoneAdmins(bool $dryRun = false, bool $force = false): int
    {
        $this->info('👤 Creazione amministratori zonali...');

        $zoneFilter = $this->option('zone');

        $zonesQuery = Zone::where('is_national', false);

        if ($zoneFilter) {
            $zonesQuery->where('code', strtoupper($zoneFilter));
        }

        $zones = $zonesQuery->get();

        if ($zones->isEmpty()) {
            $this->warn('⚠️ Nessuna zona zonale trovata');
            return 0;
        }

        $created = 0;
        $skipped = 0;

        foreach ($zones as $zone) {
            if ($this->createAdminForZone($zone, 'admin', $dryRun, $force)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $this->info("✅ Admin zonali: {$created} creati, {$skipped} saltati");
        return 0;
    }

    /**
     * Crea amministratore nazionale (CRC)
     */
    private function createNationalAdmin(bool $dryRun = false, bool $force = false): int
    {
        $this->info('🏛️ Creazione amministratore nazionale...');

        $nationalZones = Zone::where('is_national', true)->get();

        if ($nationalZones->isEmpty()) {
            $this->warn('⚠️ Nessuna zona nazionale trovata');
            return 0;
        }

        $created = 0;
        foreach ($nationalZones as $zone) {
            if ($this->createAdminForZone($zone, 'national_admin', $dryRun, $force)) {
                $created++;
            }
        }

        $this->info("✅ Admin nazionali: {$created} creati");
        return 0;
    }

    /**
     * Crea super amministratore
     */
    private function createSuperAdmin(bool $dryRun = false, bool $force = false): int
    {
        $this->info('👑 Creazione super amministratore...');

        // Controlla se esiste già
        if (!$force && !$dryRun) {
            $existing = User::where('user_type', 'super_admin')->first();
            if ($existing) {
                $this->warn("⏭️ Super admin già esistente: {$existing->name} ({$existing->email})");
                return 0;
            }
        }

        $email = $this->option('email') ?? 'superadmin@federgolf.it';
        $password = $this->option('password') ?? $this->generateSecurePassword('SUPER');
        $name = $this->option('name') ?? 'Super Amministratore FIG';

        if ($dryRun) {
            $this->info("🧪 DRY-RUN: Creazione Super Admin");
            $this->line("  Nome: {$name}");
            $this->line("  Email: {$email}");
            $this->line("  Password: {$password}");
            return 0;
        }

        try {
            $superAdmin = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'user_type' => 'super_admin',
                'zone_id' => null,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            $this->info("✅ Super Admin creato: {$superAdmin->name}");
            $this->warn("🔑 Email: {$email}");
            $this->warn("🔑 Password: {$password}");
            $this->warn("⚠️ CAMBIA LA PASSWORD AL PRIMO ACCESSO!");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Errore creazione Super Admin: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Crea admin per una zona specifica
     */
    private function createAdminForZone(Zone $zone, string $userType, bool $dryRun = false, bool $force = false): bool
    {
        // Controlla se esiste già
        if (!$force && !$dryRun) {
            $existing = User::where('zone_id', $zone->id)
                           ->where('user_type', $userType)
                           ->first();
            if ($existing) {
                $this->warn("⏭️ Admin già esistente per {$zone->code}: {$existing->name}");
                return false;
            }
        }

        $email = $this->option('email') ?? $this->generateAdminEmail($zone->code);
        $password = $this->option('password') ?? $this->generateSecurePassword($zone->code);
        $name = $this->option('name') ?? $this->generateAdminName($zone, $userType);

        if ($dryRun) {
            $this->info("🧪 DRY-RUN: Creazione {$userType} per {$zone->code}");
            $this->line("  Nome: {$name}");
            $this->line("  Email: {$email}");
            $this->line("  Password: {$password}");
            return true;
        }

        try {
            // Controlla conflitto email
            if (User::where('email', $email)->exists()) {
                $email = $this->resolveEmailConflict($email, $zone->code);
            }

            $admin = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'user_type' => $userType,
                'zone_id' => $zone->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            $this->info("✅ {$userType} creato per {$zone->code}: {$admin->name}");
            $this->line("  🔑 Email: {$email}");
            $this->line("  🔑 Password: {$password}");

            return true;

        } catch (\Exception $e) {
            $this->error("❌ Errore creazione {$userType} per {$zone->code}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Genera email admin per zona
     */
    private function generateAdminEmail(string $zoneCode): string
    {
        return strtolower($zoneCode) . '@federgolf.it';
    }

    /**
     * Genera nome admin per zona
     */
    private function generateAdminName(Zone $zone, string $userType): string
    {
        if ($userType === 'national_admin') {
            return "Amministratore Nazionale {$zone->code}";
        } else {
            return "Amministratore {$zone->name}";
        }
    }

    /**
     * Genera password sicura
     */
    private function generateSecurePassword(string $zoneCode): string
    {
        $base = $zoneCode . '2024';
        $symbols = ['@', '#', '!', '$'];
        $suffix = $symbols[array_rand($symbols)] . 'Golf';

        // return $base . $suffix;
        return 'password';
    }

    /**
     * Risolve conflitto email
     */
    private function resolveEmailConflict(string $originalEmail, string $zoneCode): string
    {
        $counter = 1;
        $baseEmail = str_replace('@federgolf.it', '', $originalEmail);

        do {
            $newEmail = $baseEmail . $counter . '@federgolf.it';
            $counter++;
        } while (User::where('email', $newEmail)->exists() && $counter < 100);

        $this->warn("🔄 Email modificata: {$originalEmail} → {$newEmail}");
        return $newEmail;
    }

    // /**
    //  * Mostra help interattivo
    //  */
    // public function handle(): int
    // {
    //     // Se nessun argomento, mostra help interattivo
    //     if (!$this->argument('type')) {
    //         return $this->interactiveMode();
    //     }

    //     // Codice esistente...
    //     return parent::handle();
    // }

    /**
     * Modalità interattiva
     */
    private function interactiveMode(): int
    {
        $this->info('🏌️ GOLF ADMIN CREATOR - Modalità Interattiva');
        $this->info('============================================');

        $type = $this->choice(
            'Che tipo di amministratore vuoi creare?',
            [
                'all' => 'Tutti gli admin (zonali + nazionale + super)',
                'zone' => 'Solo admin zonali (SZR1-SZR7)',
                'national' => 'Solo admin nazionale (CRC)',
                'super' => 'Solo super admin',
            ],
            'all'
        );

        $dryRun = $this->confirm('Eseguire in modalità dry-run (simulazione)?', false);
        $force = false;

        if (!$dryRun) {
            $force = $this->confirm('Forzare creazione anche se esistono già admin?', false);
        }

        // Esegui la creazione
        $this->call('golf:create-admin', [
            'type' => $type,
            '--dry-run' => $dryRun,
            '--force' => $force,
        ]);

        return 0;
    }
}
