<?php

/**
 * ========================================
 * ANALYZE DATABASE COMMAND - COMPLETO
 * ========================================
 * Sostituisci il contenuto di app/Console/Commands/AnalyzeDatabaseCommand.php
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AnalyzeDatabaseCommand extends Command
{
    protected $signature = 'golf:analyze-database {--export= : Export results to file}';
    protected $description = 'Analizza la struttura del database gestione_arbitri';

    public function handle()
    {
        $this->info('🚀 Avvio analisi database gestione_arbitri...');
        $this->info('=====================================');

        // Setup connessione
        $this->setupOldDatabaseConnection();

        $analysis = [
            'connection_status' => $this->testConnection(),
            'tables_structure' => $this->analyzeTablesStructure(),
            'roles_permissions' => $this->analyzeRolesPermissions(),
            'users_analysis' => $this->analyzeUsers(),
            'referees_analysis' => $this->analyzeReferees(),
            'data_quality' => $this->checkDataQuality(),
            'migration_readiness' => $this->assessMigrationReadiness()
        ];

        // Export se richiesto
        if ($this->option('export')) {
            $this->exportAnalysis($analysis);
        }

        $this->info('✅ Analisi completata!');
        return 0;
    }

    /**
     * 🔗 Test connessione database
     */
    private function testConnection(): array
    {
        try {
            $this->info('📡 Test connessione...');

            // Test connessione PDO
            $pdo = DB::connection('old')->getPdo();

            // Verifica database exists
            $result = DB::connection('old')->select(
                "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?",
                ['gestione_arbitri']
            );

            $status = [
                'connected' => true,
                'database_exists' => !empty($result),
                'driver' => $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME),
                'server_version' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION)
            ];

            $this->info('✅ Connessione stabilita');
            $this->info("   Driver: {$status['driver']}");
            $this->info("   Server: {$status['server_version']}");

            return $status;

        } catch (\Exception $e) {
            $this->error('❌ Errore connessione: ' . $e->getMessage());
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 📋 Analizza struttura tabelle
     */
    private function analyzeTablesStructure(): array
    {
        $this->info('📋 Analisi struttura tabelle...');

        try {
            $tables = DB::connection('old')->select('SHOW TABLES');
            $tableKey = 'Tables_in_gestione_arbitri';

            $structure = [];
            $critical_tables = ['users', 'referees', 'roles', 'permissions', 'role_user', 'zones', 'tournaments'];

            foreach ($tables as $table) {
                $tableName = $table->$tableKey;

                // Conta record
                try {
                    $count = DB::connection('old')->table($tableName)->count();
                } catch (\Exception $e) {
                    $count = 'ERROR';
                }

                // Verifica se è critica
                $isCritical = in_array($tableName, $critical_tables);

                $structure[$tableName] = [
                    'records' => $count,
                    'is_critical' => $isCritical,
                    'priority' => $isCritical ? 'HIGH' : 'MEDIUM'
                ];

                $marker = $isCritical ? '🔴' : '🔵';
                $this->info("   {$marker} {$tableName}: {$count} records");
            }

            $this->info('✅ Analizzate ' . count($tables) . ' tabelle');
            return $structure;

        } catch (\Exception $e) {
            $this->error('❌ Errore analisi tabelle: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 👥 Analisi Roles & Permissions
     */
    private function analyzeRolesPermissions(): array
    {
        $this->info('👥 Analisi Roles & Permissions...');

        try {
            $analysis = [];

            // 1. Analizza tabella roles
            if ($this->tableExists('roles')) {
                $roles = DB::connection('old')->table('roles')->get();
                $analysis['roles'] = $roles->map(function($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'guard_name' => $role->guard_name ?? 'web'
                    ];
                })->toArray();

                $this->info('🏷️  ROLES trovati:');
                foreach ($analysis['roles'] as $role) {
                    $this->info("   ID: {$role['id']} | Name: {$role['name']} | Guard: {$role['guard_name']}");
                }
            }

            // 2. Analizza tabella permissions
            if ($this->tableExists('permissions')) {
                $permissions = DB::connection('old')->table('permissions')->get();
                $analysis['permissions'] = $permissions->pluck('name', 'id')->toArray();
                $this->info('🔑 Permissions: ' . count($analysis['permissions']));
            }

            // 3. Analizza role_user assignments
            if ($this->tableExists('role_user')) {
                $roleUsers = DB::connection('old')->table('role_user')
                    ->select('role_id', DB::raw('COUNT(*) as user_count'))
                    ->groupBy('role_id')
                    ->get();

                $analysis['role_assignments'] = $roleUsers->pluck('user_count', 'role_id')->toArray();

                $this->info('👤 Assegnazioni role_user:');
                foreach ($analysis['role_assignments'] as $roleId => $userCount) {
                    $roleName = collect($analysis['roles'])->firstWhere('id', $roleId)['name'] ?? 'Unknown';
                    $this->info("   Role {$roleId} ({$roleName}): {$userCount} users");
                }
            }

            // 4. Mapping to new system
            $analysis['mapping_strategy'] = $this->generateRoleMapping($analysis['roles'] ?? []);

            return $analysis;

        } catch (\Exception $e) {
            $this->error('❌ Errore analisi roles: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 🧑‍💼 Analisi Users
     */
    private function analyzeUsers(): array
    {
        $this->info('🧑‍💼 Analisi Users...');

        try {
            $users = DB::connection('old')->table('users')->get();

            $analysis = [
                'total_users' => $users->count(),
                'email_verified' => $users->where('email_verified_at', '!=', null)->count(),
                'has_zone' => $users->where('zone_id', '!=', null)->count(),
                'active_users' => $users->where('is_active', true)->count()
            ];

            // Analizza legacy flags
            $legacyFlags = [
                'is_admin' => $users->where('is_admin', true)->count(),
                'is_super_admin' => $users->where('is_super_admin', true)->count()
            ];
            $analysis['legacy_flags'] = $legacyFlags;

            // Preview sample users
            $sampleUsers = $users->take(3)->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'zone_id' => $user->zone_id,
                    'is_admin' => $user->is_admin ?? false,
                    'is_super_admin' => $user->is_super_admin ?? false
                ];
            });
            $analysis['sample_users'] = $sampleUsers->toArray();

            $this->info("📊 USERS STATISTICS:");
            $this->info("   Total users: {$analysis['total_users']}");
            $this->info("   Email verified: {$analysis['email_verified']}");
            $this->info("   Has zone: {$analysis['has_zone']}");
            $this->info("   Active: {$analysis['active_users']}");
            $this->info("   Legacy admin flags: {$legacyFlags['is_admin']} admin, {$legacyFlags['is_super_admin']} super_admin");

            return $analysis;

        } catch (\Exception $e) {
            $this->error('❌ Errore analisi users: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 🏌️ Analisi Referees
     */
    private function analyzeReferees(): array
    {
        $this->info('🏌️ Analisi Referees...');

        try {
            if (!$this->tableExists('referees')) {
                return ['error' => 'Tabella referees non trovata'];
            }

            $referees = DB::connection('old')->table('referees')->get();

            $analysis = [
                'total_referees' => $referees->count(),
                'has_referee_code' => $referees->where('referee_code', '!=', null)->count(),
                'qualifications' => $referees->groupBy('qualification')->map->count()->toArray(),
                'categories' => $referees->groupBy('category')->map->count()->toArray(),
                'zones' => $referees->groupBy('zone_id')->map->count()->toArray()
            ];

            // Preview sample referees
            $sampleReferees = $referees->take(3)->map(function($referee) {
                return [
                    'user_id' => $referee->user_id,
                    'referee_code' => $referee->referee_code,
                    'qualification' => $referee->qualification,
                    'category' => $referee->category,
                    'zone_id' => $referee->zone_id
                ];
            });
            $analysis['sample_referees'] = $sampleReferees->toArray();

            $this->info("📊 REFEREES STATISTICS:");
            $this->info("   Total referees: {$analysis['total_referees']}");
            $this->info("   Has referee_code: {$analysis['has_referee_code']}");
            $this->info("   Qualifications: " . json_encode($analysis['qualifications']));

            return $analysis;

        } catch (\Exception $e) {
            $this->error('❌ Errore analisi referees: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * ✅ Verifica qualità dati
     */
    private function checkDataQuality(): array
    {
        $this->info('✅ Verifica qualità dati...');

        $issues = [];

        try {
            // 1. Users senza email
            $usersNoEmail = DB::connection('old')->table('users')
                ->where('email', '')
                ->orWhereNull('email')
                ->count();

            if ($usersNoEmail > 0) {
                $issues[] = "Users senza email: {$usersNoEmail}";
            }

            // 2. Referees senza user corrispondente
            if ($this->tableExists('referees')) {
                $orphanReferees = DB::connection('old')->table('referees')
                    ->leftJoin('users', 'referees.user_id', '=', 'users.id')
                    ->whereNull('users.id')
                    ->count();

                if ($orphanReferees > 0) {
                    $issues[] = "Referees orfani (senza user): {$orphanReferees}";
                }
            }

            // 3. Users con role_user ma senza user corrispondente
            if ($this->tableExists('role_user')) {
                $orphanRoleUsers = DB::connection('old')->table('role_user')
                    ->leftJoin('users', 'role_user.user_id', '=', 'users.id')
                    ->whereNull('users.id')
                    ->count();

                if ($orphanRoleUsers > 0) {
                    $issues[] = "Role assignments orfani: {$orphanRoleUsers}";
                }
            }

            $quality = [
                'issues_found' => count($issues),
                'issues' => $issues,
                'quality_score' => count($issues) === 0 ? 'EXCELLENT' : (count($issues) <= 2 ? 'GOOD' : 'NEEDS_ATTENTION')
            ];

            $this->info("📊 QUALITÀ DATI: {$quality['quality_score']}");
            if (!empty($issues)) {
                foreach ($issues as $issue) {
                    $this->info("   ⚠️  {$issue}");
                }
            }

            return $quality;

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 🎯 Valuta readiness per migrazione
     */
    private function assessMigrationReadiness(): array
    {
        $this->info('🎯 Valutazione readiness migrazione...');

        $requirements = [
            'database_accessible' => true,
            'critical_tables_exist' => $this->tableExists('users') && $this->tableExists('referees'),
            'has_users' => DB::connection('old')->table('users')->count() > 0,
            'has_roles_system' => $this->tableExists('roles') && $this->tableExists('role_user'),
            'data_integrity' => true // Da implementare controlli specifici
        ];

        $readiness = [
            'requirements' => $requirements,
            'passed' => array_sum($requirements),
            'total' => count($requirements),
            'percentage' => (array_sum($requirements) / count($requirements)) * 100,
            'recommendation' => ''
        ];

        if ($readiness['percentage'] >= 90) {
            $readiness['recommendation'] = 'READY - Procedi con migrazione completa';
        } elseif ($readiness['percentage'] >= 70) {
            $readiness['recommendation'] = 'MOSTLY_READY - Test con subset prima';
        } else {
            $readiness['recommendation'] = 'NOT_READY - Risolvi issues critici';
        }

        $this->info("📊 MIGRAZIONE READINESS: {$readiness['percentage']}%");
        $this->info("   Recommendation: {$readiness['recommendation']}");

        return $readiness;
    }

    /**
     * 🗺️ Genera strategia mapping roles
     */
    private function generateRoleMapping(array $roles): array
    {
        $mapping = [];

        foreach ($roles as $role) {
            $oldName = strtolower($role['name']);

            $newUserType = match($oldName) {
                'super admin', 'super-admin', 'superadmin' => 'super_admin',
                'national admin', 'national-admin', 'nationaladmin' => 'national_admin',
                'admin', 'administrator' => 'admin',
                'referee', 'arbitro' => 'referee',
                default => 'referee'
            };

            $mapping[$role['id']] = [
                'old_name' => $role['name'],
                'new_user_type' => $newUserType,
                'strategy' => 'role_based_mapping'
            ];
        }

        $this->info('🗺️  MAPPING STRATEGY:');
        foreach ($mapping as $roleId => $map) {
            $this->info("   Role {$roleId} ('{$map['old_name']}') → {$map['new_user_type']}");
        }

        return $mapping;
    }

    /**
     * 🔧 Helper methods
     */
    private function setupOldDatabaseConnection()
    {
        $oldDbConfig = [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => 'gestione_arbitri',
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
        ];

        config(['database.connections.old' => $oldDbConfig]);
    }

    private function tableExists($table): bool
    {
        try {
            DB::connection('old')->table($table)->limit(1)->get();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function exportAnalysis(array $analysis)
    {
        $filename = $this->option('export');
        $content = json_encode($analysis, JSON_PRETTY_PRINT);
        file_put_contents($filename, $content);
        $this->info("📄 Analisi esportata in: {$filename}");
    }
}
