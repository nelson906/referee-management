<?php
// app/Console/Commands/RecoveryMigrationCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Zone;
use App\Models\Tournament;
use App\Models\Referee;

class RecoveryMigrationCommand extends Command
{
    protected $signature = 'golf:recovery-migration {old_db_name} {new_db_name} {--preview : Solo anteprima senza salvare}';
    protected $description = 'Recupera dati specifici da golf_referee_new: institutional_emails, letter_templates, tournaments, zones, admin users';

    private $oldDb;
    private $newDb;
    private $preview = false;
    private $recoveryLog = [];

    public function handle()
    {
        $this->oldDb = $this->argument('old_db_name');
        $this->newDb = $this->argument('new_db_name');
        $this->preview = $this->option('preview');

        $this->info("🔄 RECUPERO DATI DA golf_referee_new");
        $this->info("Database originale: {$this->oldDb}");
        $this->info("Database nuovo: {$this->newDb}");

        if ($this->preview) {
            $this->warn("👀 MODALITÀ PREVIEW - Nessun dato sarà salvato");
        }

        try {
            $this->setupConnections();
            $this->analyzeAvailableData();

            // Recupero dati nell'ordine corretto
            $this->recoverZones();
            $this->recoverInstitutionalEmails();
            $this->recoverLetterTemplates();
            $this->recoverAdminUsers();
            $this->recoverTournamentNames(); // FIX: Recupera nomi tornei mancanti

            $this->printRecoveryReport();

            if (!$this->preview) {
                $this->info("✅ RECUPERO COMPLETATO!");
            } else {
                $this->info("👀 ANTEPRIMA COMPLETATA - Usa senza --preview per applicare");
            }

        } catch (\Exception $e) {
            $this->error("❌ ERRORE: " . $e->getMessage());
            $this->error("Stack: " . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function setupConnections()
    {
        $this->info("🔧 Setup connessioni database...");

        // Connessione database originale
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

        // Connessione database nuovo (golf_referee_new)
        config(['database.connections.new_db' => [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => $this->newDb,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        // Test connessioni
        try {
            DB::connection('old_db')->getPdo();
            $this->info("✅ Connessione {$this->oldDb} OK");
        } catch (\Exception $e) {
            throw new \Exception("Connessione {$this->oldDb} fallita: " . $e->getMessage());
        }

        try {
            DB::connection('new_db')->getPdo();
            $this->info("✅ Connessione {$this->newDb} OK");
        } catch (\Exception $e) {
            throw new \Exception("Connessione {$this->newDb} fallita: " . $e->getMessage());
        }
    }

    private function analyzeAvailableData()
    {
        $this->info("\n🔍 ANALISI DATI DISPONIBILI");
        $this->info(str_repeat("-", 50));

        $newDbTables = $this->getAvailableTables('new_db');

        $expectedTables = [
            'fixed_addresses' => 'institutional_emails',
            'letterheads' => 'letter_templates',
            'tournaments' => 'tournaments',
            'zones' => 'zones',
            'users' => 'admin users',
            'referees' => 'admin referees'
        ];

        foreach ($expectedTables as $table => $purpose) {
            $exists = in_array($table, $newDbTables);
            $status = $exists ? "✅" : "❌";

            if ($exists) {
                $count = DB::connection('new_db')->table($table)->count();
                $this->info("{$status} {$table} → {$purpose}: {$count} record");
            } else {
                $this->info("{$status} {$table} → {$purpose}: NON TROVATA");
            }
        }
    }

    private function recoverZones()
    {
        $this->info("\n🌍 RECUPERO ZONES da golf_referee_new.zones");
        $this->info(str_repeat("-", 40));

        try {
            $newZones = DB::connection('new_db')->table('zones')->get();
            $zonesRecovered = 0;

            foreach ($newZones as $newZone) {
                $zoneData = [
                    'id' => $newZone->id,
                    'name' => $newZone->name,
                    'description' => $newZone->description ?? null,
                    'is_national' => $newZone->is_national ?? false,
                    'header_document_path' => $newZone->header_document_path ?? null,
                    'header_updated_at' => $newZone->header_updated_at ?? null,
                    'header_updated_by' => $newZone->header_updated_by ?? null,
                    'created_at' => $newZone->created_at ?? now(),
                    'updated_at' => $newZone->updated_at ?? now(),
                ];

                if (!$this->preview) {
                    Zone::updateOrCreate(['id' => $newZone->id], $zoneData);
                }

                $zonesRecovered++;
                $this->info("✅ Zone: {$newZone->name}");
            }

            $this->recoveryLog['zones'] = $zonesRecovered;
            $this->info("📊 Zones recuperate: {$zonesRecovered}");

        } catch (\Exception $e) {
            $this->warn("⚠️ Errore recupero zones: " . $e->getMessage());
        }
    }

    private function recoverInstitutionalEmails()
    {
        $this->info("\n📧 RECUPERO INSTITUTIONAL_EMAILS da golf_referee_new.fixed_addresses");
        $this->info(str_repeat("-", 40));

        try {
            $fixedAddresses = DB::connection('new_db')->table('fixed_addresses')->get();
            $emailsRecovered = 0;

            foreach ($fixedAddresses as $address) {
                $emailData = [
                    'name' => $address->name ?? 'Email Istituzionale',
                    'email' => $address->email,
                    'description' => $address->description ?? null,
                    'is_active' => $address->is_active ?? true,
                    'zone_id' => $address->zone_id ?? null,
                    'category' => $this->mapEmailCategory($address->type ?? 'altro'),
                    'receive_all_notifications' => $address->receive_all_notifications ?? false,
                    'notification_types' => $this->parseJsonField($address->notification_types),
                    'created_at' => $address->created_at ?? now(),
                    'updated_at' => $address->updated_at ?? now(),
                ];

                if (!$this->preview) {
                    DB::table('institutional_emails')->updateOrInsert(
                        ['email' => $address->email],
                        $emailData
                    );
                }

                $emailsRecovered++;
                $this->info("✅ Email: {$address->email} ({$address->name})");
            }

            $this->recoveryLog['institutional_emails'] = $emailsRecovered;
            $this->info("📊 Email istituzionali recuperate: {$emailsRecovered}");

        } catch (\Exception $e) {
            $this->warn("⚠️ Errore recupero institutional_emails: " . $e->getMessage());
        }
    }

    private function recoverLetterTemplates()
    {
        $this->info("\n📝 RECUPERO LETTER_TEMPLATES da golf_referee_new.letterheads");
        $this->info(str_repeat("-", 40));

        try {
            $letterheads = DB::connection('new_db')->table('letterheads')->get();
            $templatesRecovered = 0;

            foreach ($letterheads as $letterhead) {
                $templateData = [
                    'name' => $letterhead->name ?? 'Template',
                    'type' => $this->mapTemplateType($letterhead->type ?? 'assignment'),
                    'subject' => $letterhead->subject ?? 'Oggetto template',
                    'body' => $letterhead->body ?? $letterhead->content ?? '',
                    'zone_id' => $letterhead->zone_id ?? null,
                    'tournament_type_id' => $letterhead->tournament_type_id ?? null,
                    'is_active' => $letterhead->is_active ?? true,
                    'is_default' => $letterhead->is_default ?? false,
                    'variables' => $this->parseJsonField($letterhead->variables),
                    'created_at' => $letterhead->created_at ?? now(),
                    'updated_at' => $letterhead->updated_at ?? now(),
                ];

                if (!$this->preview) {
                    DB::table('letter_templates')->updateOrInsert(
                        [
                            'name' => $letterhead->name,
                            'zone_id' => $letterhead->zone_id,
                            'type' => $templateData['type']
                        ],
                        $templateData
                    );
                }

                $templatesRecovered++;
                $this->info("✅ Template: {$letterhead->name}");
            }

            $this->recoveryLog['letter_templates'] = $templatesRecovered;
            $this->info("📊 Template lettere recuperati: {$templatesRecovered}");

        } catch (\Exception $e) {
            $this->warn("⚠️ Errore recupero letter_templates: " . $e->getMessage());
        }
    }

    private function recoverAdminUsers()
    {
        $this->info("\n👑 RECUPERO ADMIN USERS da golf_referee_new");
        $this->info(str_repeat("-", 40));

        try {
            // Recupera solo super_admin e admin
            $adminUsers = DB::connection('new_db')
                ->table('users')
                ->whereIn('user_type', ['super_admin', 'national_admin', 'admin'])
                ->get();

            $adminsRecovered = 0;

            foreach ($adminUsers as $adminUser) {
                $userData = [
                    'name' => $adminUser->name,
                    'email' => $adminUser->email,
                    'user_type' => $adminUser->user_type,
                    'level' => $adminUser->level ?? 'Aspirante',
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
                    'preferences' => $this->parseJsonField($adminUser->preferences),
                    'remember_token' => $adminUser->remember_token,
                    'created_at' => $adminUser->created_at ?? now(),
                    'updated_at' => $adminUser->updated_at ?? now(),
                ];

                if (!$this->preview) {
                    $user = User::updateOrCreate(
                        ['email' => $adminUser->email],
                        $userData
                    );

                    // Se ha record referee corrispondente, recuperalo
                    $this->recoverAdminRefereeData($user, $adminUser);
                }

                $adminsRecovered++;
                $this->info("✅ Admin: {$adminUser->name} ({$adminUser->user_type})");
            }

            $this->recoveryLog['admin_users'] = $adminsRecovered;
            $this->info("📊 Utenti admin recuperati: {$adminsRecovered}");

        } catch (\Exception $e) {
            $this->warn("⚠️ Errore recupero admin users: " . $e->getMessage());
        }
    }

    private function recoverAdminRefereeData($user, $adminUser)
    {
        try {
            $refereeData = DB::connection('new_db')
                ->table('referees')
                ->where('user_id', $adminUser->id)
                ->first();

            if ($refereeData) {
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
                    'qualifications' => $this->parseJsonField($refereeData->qualifications),
                    'languages' => $this->parseJsonField($refereeData->languages),
                    'specializations' => $this->parseJsonField($refereeData->specializations),
                    'available_for_international' => $refereeData->available_for_international ?? false,
                    'preferences' => $this->parseJsonField($refereeData->preferences),
                    'total_tournaments' => $refereeData->total_tournaments ?? 0,
                    'tournaments_current_year' => $refereeData->tournaments_current_year ?? 0,
                    'profile_completed_at' => $refereeData->profile_completed_at ?? null,
                    'created_at' => $refereeData->created_at ?? now(),
                    'updated_at' => $refereeData->updated_at ?? now(),
                ];

                Referee::updateOrCreate(['user_id' => $user->id], $referee);
            }
        } catch (\Exception $e) {
            // Ignora errori nel recupero dati referee admin
        }
    }

    private function recoverTournamentNames()
    {
        $this->info("\n🏆 FIX NOMI TORNEI da golf_referee_new.tournaments");
        $this->info(str_repeat("-", 40));

        try {
            $newTournaments = DB::connection('new_db')->table('tournaments')->get();
            $tournamentsFixed = 0;

            foreach ($newTournaments as $newTournament) {
                // Cerca torneo corrispondente nel database attuale
                $currentTournament = Tournament::find($newTournament->id);

                if ($currentTournament) {
                    // Verifica se il nome è mancante o generico
                    if (empty($currentTournament->name) ||
                        $currentTournament->name === 'Torneo' ||
                        strlen($currentTournament->name) < 5) {

                        if (!$this->preview) {
                            $currentTournament->update([
                                'name' => $newTournament->name,
                                'notes' => $newTournament->notes ?? $currentTournament->notes,
                                'convocation_letter' => $newTournament->convocation_letter ?? $currentTournament->convocation_letter,
                                'club_letter' => $newTournament->club_letter ?? $currentTournament->club_letter,
                            ]);
                        }

                        $tournamentsFixed++;
                        $this->info("✅ Torneo {$newTournament->id}: '{$currentTournament->name}' → '{$newTournament->name}'");
                    }
                } else {
                    // Torneo non esiste, verifica se recuperarlo completamente
                    $this->warn("⚠️ Torneo {$newTournament->id} non trovato nel database attuale");
                }
            }

            $this->recoveryLog['tournament_names_fixed'] = $tournamentsFixed;
            $this->info("📊 Nomi tornei ripristinati: {$tournamentsFixed}");

        } catch (\Exception $e) {
            $this->warn("⚠️ Errore fix nomi tornei: " . $e->getMessage());
        }
    }

    private function printRecoveryReport()
    {
        $this->info("\n" . str_repeat("=", 60));
        $this->info("📊 REPORT RECUPERO DATI");
        $this->info(str_repeat("=", 60));

        foreach ($this->recoveryLog as $section => $count) {
            $this->info("🔸 " . strtoupper(str_replace('_', ' ', $section)) . ": {$count}");
        }

        $total = array_sum($this->recoveryLog);
        $this->info("\n🎯 TOTALE ELEMENTI RECUPERATI: {$total}");

        if ($this->preview) {
            $this->warn("\n👀 MODALITÀ PREVIEW ATTIVA - Nessun dato è stato salvato");
            $this->info("Esegui senza --preview per applicare le modifiche");
        }
    }

    // Helper Methods
    private function getAvailableTables($connection): array
    {
        $tables = DB::connection($connection)->select("SHOW TABLES");
        return array_map(function($table) {
            return array_values((array)$table)[0];
        }, $tables);
    }

    private function parseJsonField($field)
    {
        if (empty($field)) return null;
        if (is_string($field)) {
            $decoded = json_decode($field, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }
        return $field;
    }

    private function mapEmailCategory($type): string
    {
        return match (strtolower(trim($type ?? ''))) {
            'federation', 'federazione' => 'federazione',
            'committee', 'comitato', 'comitati' => 'comitati',
            'zone', 'zona', 'zone' => 'zone',
            default => 'altro'
        };
    }

    private function mapTemplateType($type): string
    {
        return match (strtolower(trim($type ?? ''))) {
            'assignment', 'assegnazione' => 'assignment',
            'convocation', 'convocazione' => 'convocation',
            'club', 'circolo' => 'club',
            'institutional', 'istituzionale' => 'institutional',
            default => 'assignment'
        };
    }
}
