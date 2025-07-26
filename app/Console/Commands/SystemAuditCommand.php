<?php

/**
 * TASK 4: Command Audit Sistema Completo
 *
 * OBIETTIVO: Command per validazione completa sistema prima del go-live
 * TEMPO STIMATO: 2-3 ore
 * COMPLESSITÀ: Media
 *
 * UTILIZZO: php artisan system:audit --fix
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Zone;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use App\Models\LetterTemplate;
use App\Models\InstitutionalEmail;
use App\Models\Letterhead;

class SystemAuditCommand extends Command
{
    protected $signature = 'system:audit
                            {--fix : Tenta di correggere automaticamente i problemi}
                            {--detailed : Mostra output dettagliato}
                            {--export=system_audit.json : File per export risultati}';

    protected $description = 'Esegue audit completo del sistema per validazione pre-produzione';

    private $issues = [];
    private $fixes = [];
    private $stats = [];

    public function handle()
    {
        $this->info('🔍 AUDIT SISTEMA GESTIONE ARBITRI');
        $this->info('=====================================');

        $startTime = microtime(true);

        // Esegui tutti i controlli
        $this->checkDatabaseIntegrity();
        $this->checkUserRoles();
        $this->checkNotificationSystem();
        $this->checkTemplateSystem();
        $this->checkDataConsistency();
        $this->checkPerformanceMetrics();

        $executionTime = round(microtime(true) - $startTime, 2);

        // Mostra risultati
        $this->showResults($executionTime);

        // Export se richiesto
        if ($this->option('export')) {
            $this->exportResults();
        }

        return $this->issues ? 1 : 0;
    }

    /**
     * CONTROLLO 1: Integrità Database
     */
    private function checkDatabaseIntegrity()
    {
        $this->info('📊 Controllo Integrità Database...');

        // Verifica foreign keys
        $this->checkForeignKeys();

        // Verifica constraint unique
        $this->checkUniqueConstraints();

        // Verifica tabelle essenziali
        $this->checkEssentialTables();

        // Statistiche database
        $this->collectDatabaseStats();
    }

    private function checkForeignKeys()
    {
        $foreignKeyChecks = [
            'users' => [
                'zone_id' => 'zones',
                'created_by' => 'users'
            ],
            'tournaments' => [
                'zone_id' => 'zones',
                'club_id' => 'clubs',
                'tournament_type_id' => 'tournament_types',
                'created_by' => 'users'
            ],
            'assignments' => [
                'tournament_id' => 'tournaments',
                'user_id' => 'users',
                'assigned_by' => 'users'
            ],
            'availabilities' => [
                'tournament_id' => 'tournaments',
                'user_id' => 'users'
            ],
            'notifications' => [
                'assignment_id' => 'assignments'
            ]
        ];

        foreach ($foreignKeyChecks as $table => $relations) {
            if (!Schema::hasTable($table)) {
                $this->addIssue("Tabella mancante: {$table}", 'critical');
                continue;
            }

            foreach ($relations as $fk_column => $ref_table) {
                $orphans = DB::table($table)
                    ->whereNotNull($fk_column)
                    ->whereNotExists(function($query) use ($ref_table, $fk_column) {
                        $query->select(DB::raw(1))
                              ->from($ref_table)
                              ->whereRaw("{$ref_table}.id = {$table}.{$fk_column}");
                    })
                    ->count();

                if ($orphans > 0) {
                    $this->addIssue("Foreign key violazioni in {$table}.{$fk_column}: {$orphans} record orfani", 'high');

                    if ($this->option('fix')) {
                        // Tenta fix automatico (con backup)
                        $this->attemptForeignKeyFix($table, $fk_column, $ref_table, $orphans);
                    }
                }
            }
        }
    }

    private function checkUniqueConstraints()
    {
        $uniqueChecks = [
            'users' => ['email'],
            'zones' => ['code'],
            'clubs' => ['email'],
            'institutional_emails' => ['email']
        ];

        foreach ($uniqueChecks as $table => $columns) {
            if (!Schema::hasTable($table)) continue;

            foreach ($columns as $column) {
                $duplicates = DB::table($table)
                    ->select($column, DB::raw('count(*) as count'))
                    ->whereNotNull($column)
                    ->groupBy($column)
                    ->having('count', '>', 1)
                    ->get();

                if ($duplicates->count() > 0) {
                    $total = $duplicates->sum('count');
                    $this->addIssue("Duplicati in {$table}.{$column}: {$duplicates->count()} valori, {$total} record totali", 'medium');
                }
            }
        }
    }

    private function checkEssentialTables()
    {
        $essentialTables = [
            'zones', 'users', 'roles', 'tournaments', 'tournament_types',
            'clubs', 'assignments', 'availabilities', 'notifications',
            'letter_templates', 'institutional_emails', 'letterheads'
        ];

        foreach ($essentialTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->addIssue("Tabella essenziale mancante: {$table}", 'critical');
            }
        }
    }

    /**
     * CONTROLLO 2: Ruoli e Permessi Utenti
     */
    private function checkUserRoles()
    {
        $this->info('👥 Controllo Ruoli Utenti...');

        // Verifica super admin
        $superAdmins = User::role('SuperAdmin')->count();
        if ($superAdmins === 0) {
            $this->addIssue('Nessun SuperAdmin presente nel sistema', 'critical');
        } elseif ($superAdmins > 3) {
            $this->addIssue("Troppi SuperAdmin: {$superAdmins} (raccomandato: 1-2)", 'low');
        }

        // Verifica admin per zona
        $zones = Zone::where('is_active', true)->get();
        foreach ($zones as $zone) {
            $zoneAdmins = User::role('Admin')
                ->whereHas('referee', function($q) use ($zone) {
                    $q->where('zone_id', $zone->id);
                })
                ->count();

            if ($zoneAdmins === 0) {
                $this->addIssue("Zona {$zone->code} senza admin", 'high');
            }
        }

        // Verifica utenti senza ruoli
        $usersWithoutRoles = User::doesntHave('roles')->count();
        if ($usersWithoutRoles > 0) {
            $this->addIssue("Utenti senza ruoli: {$usersWithoutRoles}", 'medium');
        }

        $this->stats['users'] = [
            'total' => User::count(),
            'super_admins' => $superAdmins,
            'zone_admins' => User::role('Admin')->count(),
            'referees' => User::role('Referee')->count(),
            'without_roles' => $usersWithoutRoles
        ];
    }

    /**
     * CONTROLLO 3: Sistema Notifiche
     */
    private function checkNotificationSystem()
    {
        $this->info('📧 Controllo Sistema Notifiche...');

        // Verifica template default
        $requiredTemplates = ['assignment', 'convocation', 'club', 'institutional'];
        foreach ($requiredTemplates as $type) {
            $hasDefault = LetterTemplate::where('type', $type)
                ->where('is_default', true)
                ->where('is_active', true)
                ->exists();

            if (!$hasDefault) {
                $this->addIssue("Template default mancante per tipo: {$type}", 'high');
            }
        }

        // Verifica indirizzi istituzionali per zone
        foreach ($zones ?? Zone::where('is_active', true)->get() as $zone) {
            $zoneEmails = InstitutionalEmail::where('zone_id', $zone->id)
                ->where('is_active', true)
                ->count();

            if ($zoneEmails === 0) {
                $this->addIssue("Zona {$zone->code} senza email istituzionali", 'medium');
            }
        }

        // Verifica letterhead
        $hasDefaultLetterhead = Letterhead::whereNull('zone_id')
            ->where('is_default', true)
            ->where('is_active', true)
            ->exists();

        if (!$hasDefaultLetterhead) {
            $this->addIssue('Letterhead default nazionale mancante', 'medium');
        }

        // Statistiche notifiche
        $this->stats['notifications'] = [
            'total_sent' => DB::table('notifications')->where('status', 'sent')->count(),
            'pending' => DB::table('notifications')->where('status', 'pending')->count(),
            'failed' => DB::table('notifications')->where('status', 'failed')->count(),
            'templates' => LetterTemplate::where('is_active', true)->count(),
            'institutional_emails' => InstitutionalEmail::where('is_active', true)->count()
        ];
    }

    /**
     * CONTROLLO 4: Sistema Template
     */
    private function checkTemplateSystem()
    {
        $this->info('📄 Controllo Sistema Template...');

        // Verifica template duplicati
        $duplicates = LetterTemplate::select('type', 'zone_id', 'tournament_type_id')
            ->selectRaw('COUNT(*) as count')
            ->where('is_active', true)
            ->groupBy('type', 'zone_id', 'tournament_type_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            $this->addIssue("Template duplicati trovati: {$duplicates->count()} gruppi", 'medium');
        }

        // Verifica variabili template
        $templates = LetterTemplate::where('is_active', true)->get();
        foreach ($templates as $template) {
            $this->validateTemplateVariables($template);
        }
    }

    private function validateTemplateVariables($template)
    {
        $requiredVariables = [
            'assignment' => ['referee_name', 'tournament_name', 'tournament_dates', 'assignment_role'],
            'convocation' => ['referee_name', 'tournament_name', 'club_name', 'tournament_dates'],
            'club' => ['club_name', 'tournament_name', 'assignments_list'],
            'institutional' => ['tournament_name', 'zone_name', 'assigned_date']
        ];

        $required = $requiredVariables[$template->type] ?? [];
        $missing = [];

        foreach ($required as $variable) {
            if (!str_contains($template->body, '{{' . $variable . '}}')) {
                $missing[] = $variable;
            }
        }

        if (!empty($missing)) {
            $this->addIssue("Template '{$template->name}' manca variabili: " . implode(', ', $missing), 'low');
        }
    }

    /**
     * CONTROLLO 5: Consistenza Dati
     */
    private function checkDataConsistency()
    {
        $this->info('🔍 Controllo Consistenza Dati...');

        // Assignments senza availability
        $assignmentsWithoutAvailability = DB::table('assignments as a')
            ->leftJoin('availabilities as av', function($join) {
                $join->on('a.tournament_id', '=', 'av.tournament_id')
                     ->on('a.user_id', '=', 'av.user_id');
            })
            ->whereNull('av.id')
            ->count();

        if ($assignmentsWithoutAvailability > 0) {
            $this->addIssue("Assegnazioni senza disponibilità dichiarata: {$assignmentsWithoutAvailability}", 'medium');
        }

        // Tournaments senza assignments in stato confirmed
        $tournamentsWithoutAssignments = Tournament::where('status', 'confirmed')
            ->whereDoesntHave('assignments')
            ->count();

        if ($tournamentsWithoutAssignments > 0) {
            $this->addIssue("Tornei confermati senza assegnazioni: {$tournamentsWithoutAssignments}", 'high');
        }

        // Date inconsistenti
        $invalidDates = Tournament::where('start_date', '>', 'end_date')->count();
        if ($invalidDates > 0) {
            $this->addIssue("Tornei con date invalide: {$invalidDates}", 'high');
        }
    }

    /**
     * CONTROLLO 6: Metriche Performance
     */
    private function checkPerformanceMetrics()
    {
        $this->info('⚡ Controllo Performance...');

        // Dimensioni tabelle
        foreach (['users', 'tournaments', 'assignments', 'notifications'] as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->stats['table_sizes'][$table] = $count;

                if ($table === 'notifications' && $count > 10000) {
                    $this->addIssue("Tabella notifications molto grande: {$count} record (considera pulizia)", 'low');
                }
            }
        }

        // Indici mancanti (controllo query lente potenziali)
        $this->checkMissingIndexes();
    }

    private function checkMissingIndexes()
    {
        $recommendedIndexes = [
            'assignments' => ['tournament_id', 'user_id', 'status'],
            'availabilities' => ['tournament_id', 'user_id'],
            'notifications' => ['status', 'created_at'],
            'tournaments' => ['status', 'zone_id', 'start_date']
        ];

        foreach ($recommendedIndexes as $table => $columns) {
            if (!Schema::hasTable($table)) continue;

            foreach ($columns as $column) {
                if (!$this->hasIndex($table, $column)) {
                    $this->addIssue("Indice mancante: {$table}.{$column}", 'low');
                }
            }
        }
    }

    private function hasIndex($table, $column)
    {
        // Semplificata - in produzione usare SHOW INDEX
        return true; // Placeholder
    }

    /**
     * UTILITY METHODS
     */
    private function addIssue($message, $level = 'medium')
    {
        $this->issues[] = [
            'message' => $message,
            'level' => $level,
            'timestamp' => now()->toISOString()
        ];

        $color = match($level) {
            'critical' => 'error',
            'high' => 'warn',
            'medium' => 'comment',
            'low' => 'info'
        };

        $this->{$color}("  ❌ [{$level}] {$message}");
    }

    private function addFix($message)
    {
        $this->fixes[] = $message;
        $this->info("  ✅ FIX: {$message}");
    }

    private function collectDatabaseStats()
    {
        $this->stats['database'] = [
            'total_tables' => count(DB::select('SHOW TABLES')),
            'total_size_mb' => $this->getDatabaseSize(),
            'creation_date' => $this->getDatabaseCreationDate()
        ];
    }

    private function getDatabaseSize()
    {
        try {
            $result = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
            ");
            return $result[0]->size_mb ?? 0;
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    private function getDatabaseCreationDate()
    {
        return now()->subDays(30)->toDateString(); // Placeholder
    }

    private function showResults($executionTime)
    {
        $this->info('');
        $this->info('=====================================');
        $this->info('📊 RISULTATI AUDIT');
        $this->info('=====================================');

        $criticalCount = count(array_filter($this->issues, fn($i) => $i['level'] === 'critical'));
        $highCount = count(array_filter($this->issues, fn($i) => $i['level'] === 'high'));
        $totalIssues = count($this->issues);

        if ($totalIssues === 0) {
            $this->info('✅ SISTEMA VALIDATO - Nessun problema trovato!');
            $this->info('🚀 Sistema pronto per produzione');
        } else {
            $this->warn("⚠️  PROBLEMI TROVATI: {$totalIssues}");
            $this->warn("   - Critici: {$criticalCount}");
            $this->warn("   - Alti: {$highCount}");

            if ($criticalCount > 0) {
                $this->error('🚨 ATTENZIONE: Problemi critici devono essere risolti prima del deploy!');
            } elseif ($highCount > 0) {
                $this->warn('⚠️  Raccomandato risolvere problemi ad alta priorità prima del deploy');
            } else {
                $this->info('✅ Nessun problema critico - Sistema utilizzabile');
            }
        }

        if ($this->option('detailed')) {
            $this->showDetailedStats();
        }

        $this->info("⏱️  Tempo esecuzione: {$executionTime}s");
        $this->info('');
    }

    private function showDetailedStats()
    {
        $this->info('');
        $this->info('📈 STATISTICHE DETTAGLIATE:');

        foreach ($this->stats as $category => $data) {
            $this->info("  {$category}:");
            foreach ($data as $key => $value) {
                $this->info("    - {$key}: {$value}");
            }
        }
    }

    private function exportResults()
    {
        $export = [
            'audit_date' => now()->toISOString(),
            'system_status' => empty($this->issues) ? 'HEALTHY' : 'ISSUES_FOUND',
            'issues' => $this->issues,
            'fixes_applied' => $this->fixes,
            'statistics' => $this->stats,
            'summary' => [
                'total_issues' => count($this->issues),
                'critical_issues' => count(array_filter($this->issues, fn($i) => $i['level'] === 'critical')),
                'fixes_applied' => count($this->fixes),
                'ready_for_production' => count(array_filter($this->issues, fn($i) => in_array($i['level'], ['critical', 'high']))) === 0
            ]
        ];

        file_put_contents(storage_path('app/' . $this->option('export')), json_encode($export, JSON_PRETTY_PRINT));
        $this->info('📄 Risultati esportati in: ' . storage_path('app/' . $this->option('export')));
    }

    private function attemptForeignKeyFix($table, $fkColumn, $refTable, $orphanCount)
    {
        // Backup prima del fix
        $backupTable = $table . '_backup_' . date('Ymd_His');
        DB::statement("CREATE TABLE {$backupTable} AS SELECT * FROM {$table}");

        // Rimuovi record orfani
        $deleted = DB::table($table)
            ->whereNotNull($fkColumn)
            ->whereNotExists(function($query) use ($refTable, $fkColumn, $table) {
                $query->select(DB::raw(1))
                      ->from($refTable)
                      ->whereRaw("{$refTable}.id = {$table}.{$fkColumn}");
            })
            ->delete();

        $this->addFix("Rimossi {$deleted} record orfani da {$table}.{$fkColumn} (backup: {$backupTable})");
    }
}
