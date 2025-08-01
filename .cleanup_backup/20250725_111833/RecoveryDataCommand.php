<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Zone;
use App\Models\Tournament;
use App\Models\Referee;

class RecoveryDataCommand extends Command
{
    protected $signature = 'golf:recover {new_db_name} {--preview}';
    protected $description = 'Recupera dati specifici da golf_referee_new';

    private $newDb;
    private $preview;
    private $stats = [];

    public function handle()
    {
        $this->newDb = $this->argument('new_db_name');
        $this->preview = $this->option('preview');

        $this->info("🔄 RECUPERO DATI DA: {$this->newDb}");

        if ($this->preview) {
            $this->warn("👀 MODALITÀ PREVIEW - Nessun dato sarà salvato");
        }

        try {
            $this->setupConnection();
            $this->recoverZones();
            $this->recoverInstitutionalEmails();
            $this->recoverLetterTemplates();
            $this->recoverTournamentNames();
            $this->recoverAdminUsers();

            $this->showStats();

            if (!$this->preview) {
                $this->info("✅ RECUPERO COMPLETATO!");
            } else {
                $this->info("👀 ANTEPRIMA COMPLETATA - Rimuovi --preview per applicare");
            }

        } catch (\Exception $e) {
            $this->error("ERRORE: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function setupConnection()
    {
        config(['database.connections.new' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $this->newDb,
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        DB::connection('new')->getPdo();
        $this->info("✅ Connesso a {$this->newDb}");
    }

    private function recoverZones()
    {
        $this->info("\n🌍 RECUPERO ZONES da {$this->newDb}.zones");

        try {
            $newZones = DB::connection('new')->table('zones')->get();
            $count = 0;

            foreach ($newZones as $newZone) {
                $zoneData = [
                    'id' => $newZone->id,
                    'name' => $newZone->name,
                    'description' => $newZone->description ?? null,
                    'is_national' => $newZone->is_national ?? false,
                    'header_document_path' => $newZone->header_document_path ?? null,
                    'header_updated_at' => $newZone->header_updated_at ?? null,
                    'header_updated_by' => $newZone->header_updated_by ?? null,
                    'updated_at' => now(),
                ];

                $this->info("Zone: {$newZone->name}");

                if (!$this->preview) {
                    Zone::updateOrCreate(['id' => $newZone->id], $zoneData);
                }

                $count++;
            }

            $this->stats['zones'] = $count;
            $this->info("✅ Zones: {$count}");

        } catch (\Exception $e) {
            $this->warn("Errore zones: " . $e->getMessage());
        }
    }

    private function recoverInstitutionalEmails()
    {
        $this->info("\n📧 RECUPERO INSTITUTIONAL_EMAILS da {$this->newDb}.fixed_addresses");

        try {
            $addresses = DB::connection('new')->table('fixed_addresses')->get();
            $count = 0;

            foreach ($addresses as $address) {
                $emailData = [
                    'name' => $address->name ?? 'Email Istituzionale',
                    'email' => $address->email,
                    'description' => $address->description ?? null,
                    'is_active' => $address->is_active ?? true,
                    'zone_id' => $address->zone_id ?? null,
                    'category' => $this->mapCategory($address->type ?? 'altro'),
                    'receive_all_notifications' => $address->receive_all_notifications ?? false,
                    'notification_types' => $this->parseJson($address->notification_types ?? null),
                    'created_at' => $address->created_at ?? now(),
                    'updated_at' => now(),
                ];

                $this->info("Email: {$address->email}");

                if (!$this->preview) {
                    DB::table('institutional_emails')->updateOrInsert(
                        ['email' => $address->email],
                        $emailData
                    );
                }

                $count++;
            }

            $this->stats['institutional_emails'] = $count;
            $this->info("✅ Email istituzionali: {$count}");

        } catch (\Exception $e) {
            $this->warn("Errore institutional_emails: " . $e->getMessage());
        }
    }

    private function recoverLetterTemplates()
    {
        $this->info("\n📝 RECUPERO LETTER_TEMPLATES da {$this->newDb}.letterheads");

        try {
            $letterheads = DB::connection('new')->table('letterheads')->get();
            $count = 0;

            foreach ($letterheads as $letterhead) {
                $templateData = [
                    'name' => $letterhead->name ?? 'Template',
                    'type' => $this->mapTemplateType($letterhead->type ?? 'assignment'),
                    'subject' => $letterhead->subject ?? 'Oggetto',
                    'body' => $letterhead->body ?? $letterhead->content ?? '',
                    'zone_id' => $letterhead->zone_id ?? null,
                    'tournament_type_id' => $letterhead->tournament_type_id ?? null,
                    'is_active' => $letterhead->is_active ?? true,
                    'is_default' => $letterhead->is_default ?? false,
                    'variables' => $this->parseJson($letterhead->variables ?? null),
                    'created_at' => $letterhead->created_at ?? now(),
                    'updated_at' => now(),
                ];

                $this->info("Template: {$letterhead->name}");

                if (!$this->preview) {
                    DB::table('letter_templates')->updateOrInsert(
                        [
                            'name' => $letterhead->name,
                            'zone_id' => $letterhead->zone_id,
                        ],
                        $templateData
                    );
                }

                $count++;
            }

            $this->stats['letter_templates'] = $count;
            $this->info("✅ Template lettere: {$count}");

        } catch (\Exception $e) {
            $this->warn("Errore letter_templates: " . $e->getMessage());
        }
    }

    private function recoverTournamentNames()
    {
        $this->info("\n🏆 RECUPERO NOMI TORNEI da {$this->newDb}.tournaments");

        try {
            $newTournaments = DB::connection('new')->table('tournaments')->get();
            $fixed = 0;

            foreach ($newTournaments as $newTournament) {
                $currentTournament = Tournament::find($newTournament->id);

                if ($currentTournament) {
                    // Verifica se il nome è mancante o generico
                    $needsUpdate = empty($currentTournament->name) ||
                                  $currentTournament->name === 'Torneo' ||
                                  strlen($currentTournament->name) < 5;

                    if ($needsUpdate && !empty($newTournament->name)) {
                        $this->info("Torneo {$newTournament->id}: '{$currentTournament->name}' → '{$newTournament->name}'");

                        if (!$this->preview) {
                            $currentTournament->update([
                                'name' => $newTournament->name,
                                'notes' => $newTournament->notes ?? $currentTournament->notes,
                                'convocation_letter' => $newTournament->convocation_letter ?? $currentTournament->convocation_letter,
                                'club_letter' => $newTournament->club_letter ?? $currentTournament->club_letter,
                            ]);
                        }

                        $fixed++;
                    }
                } else {
                    $this->warn("Torneo {$newTournament->id} non trovato nel database corrente");
                }
            }

            $this->stats['tournament_names'] = $fixed;
            $this->info("✅ Nomi tornei ripristinati: {$fixed}");

        } catch (\Exception $e) {
            $this->warn("Errore tournament names: " . $e->getMessage());
        }
    }

    private function recoverAdminUsers()
    {
        $this->info("\n👑 RECUPERO ADMIN USERS da {$this->newDb}");

        try {
            // Recupera solo super_admin e admin
            $adminUsers = DB::connection('new')
                ->table('users')
                ->whereIn('user_type', ['super_admin', 'national_admin', 'admin'])
                ->get();

            $count = 0;

            foreach ($adminUsers as $adminUser) {
                $userData = [
                    'name' => $adminUser->name,
                    'email' => $adminUser->email,
                    'user_type' => $adminUser->user_type,
                    'level' => $this->mapUserLevel($adminUser->level ?? 'Aspirante'),
                    'referee_code' => $adminUser->referee_code ?? 'N/A',
                    'category' => $adminUser->category ?? 'misto',
                    'zone_id' => $adminUser->zone_id ?? null,
                    'certified_date' => $adminUser->certified_date ?? now(),
                    'password' => $adminUser->password,
                    'email_verified_at' => $adminUser->email_verified_at,
                    'phone' => $adminUser->phone ?? null,
                    'city' => $adminUser->city ?? null,
                    'is_active' => $adminUser->is_active ?? true,
                    'last_login_at' => $adminUser->last_login_at ?? null,
                    'preferences' => $this->parseJson($adminUser->preferences ?? null),
                    'remember_token' => $adminUser->remember_token,
                    'updated_at' => now(),
                ];

                $this->info("Admin: {$adminUser->name} ({$adminUser->user_type})");

                if (!$this->preview) {
                    $user = User::updateOrCreate(
                        ['email' => $adminUser->email],
                        $userData
                    );

                    // Recupera anche il record referee se esiste
                    $this->recoverAdminReferee($user, $adminUser);
                }

                $count++;
            }

            $this->stats['admin_users'] = $count;
            $this->info("✅ Admin users: {$count}");

        } catch (\Exception $e) {
            $this->warn("Errore admin users: " . $e->getMessage());
        }
    }

    private function recoverAdminReferee($user, $adminUser)
    {
        try {
            $refereeData = DB::connection('new')
                ->table('referees')
                ->where('user_id', $adminUser->id)
                ->first();

            if ($refereeData && !$this->preview) {
                $referee = [
                    'user_id' => $user->id,
                    'address' => $refereeData->address ?? null,
                    'postal_code' => $refereeData->postal_code ?? null,
                    'tax_code' => $refereeData->tax_code ?? null,
                    'badge_number' => $refereeData->badge_number ?? null,
                    'first_certification_date' => $refereeData->first_certification_date ?? null,
                    'last_renewal_date' => $refereeData->last_renewal_date ?? null,
                    'expiry_date' => $refereeData->expiry_date ?? null,
                    'bio' => $refereeData->bio ?? null,
                    'experience_years' => $refereeData->experience_years ?? 0,
                    'qualifications' => $this->parseJson($refereeData->qualifications ?? null),
                    'languages' => $this->parseJson($refereeData->languages ?? null),
                    'specializations' => $this->parseJson($refereeData->specializations ?? null),
                    'available_for_international' => $refereeData->available_for_international ?? false,
                    'preferences' => $this->parseJson($refereeData->preferences ?? null),
                    'total_tournaments' => $refereeData->total_tournaments ?? 0,
                    'tournaments_current_year' => $refereeData->tournaments_current_year ?? 0,
                    'profile_completed_at' => $refereeData->profile_completed_at ?? null,
                    'updated_at' => now(),
                ];

                Referee::updateOrCreate(['user_id' => $user->id], $referee);
            }
        } catch (\Exception $e) {
            // Ignora errori nel recupero referee admin
        }
    }

    private function showStats()
    {
        $this->info("\n📊 STATISTICHE RECUPERO:");
        foreach ($this->stats as $item => $count) {
            $this->info("  {$item}: {$count}");
        }
    }

    // Helper methods
    private function parseJson($field)
    {
        if (empty($field)) return null;

        if (is_string($field)) {
            $decoded = json_decode($field, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return $field;
    }

    private function mapCategory($type)
    {
        $type = strtolower(trim($type ?? ''));

        if (in_array($type, ['federation', 'federazione'])) return 'federazione';
        if (in_array($type, ['committee', 'comitato', 'comitati'])) return 'comitati';
        if (in_array($type, ['zone', 'zona'])) return 'zone';

        return 'altro';
    }

    private function mapTemplateType($type)
    {
        $type = strtolower(trim($type ?? ''));

        if (in_array($type, ['assignment', 'assegnazione'])) return 'assignment';
        if (in_array($type, ['convocation', 'convocazione'])) return 'convocation';
        if (in_array($type, ['club', 'circolo'])) return 'club';
        if (in_array($type, ['institutional', 'istituzionale'])) return 'institutional';

        return 'assignment';
    }

    private function mapUserLevel($level)
    {
        // Valori enum per users.level: 'Aspirante','1_livello','Regionale','Nazionale','Internazionale','Archivio'
        $level = strtolower(trim($level ?? ''));

        if (in_array($level, ['aspirante', 'asp'])) return 'Aspirante';
        if (in_array($level, ['primo_livello', '1_livello', '1°', 'primo'])) return '1_livello';
        if (in_array($level, ['regionale', 'reg'])) return 'Regionale';
        if (in_array($level, ['nazionale', 'naz'])) return 'Nazionale';
        if (in_array($level, ['internazionale', 'int'])) return 'Internazionale';
        if (in_array($level, ['nazionale_internazionale', 'naz_int', 'naz/int'])) return 'Nazionale';
        if (in_array($level, ['archivio', 'arch'])) return 'Archivio';

        return 'Aspirante'; // Default sicuro
    }
}
