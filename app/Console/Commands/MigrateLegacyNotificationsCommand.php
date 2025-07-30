<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\Notification;
use App\Models\Assignment;

class MigrateLegacyNotificationsCommand extends Command
{
    /**
     * 🔄 Comando per migrazione sistema legacy → nuovo sistema
     */
    protected $signature = 'notifications:migrate-legacy
                           {--batch-size=500 : Dimensione batch per elaborazione}
                           {--dry-run : Simula migrazione senza modifiche}
                           {--force : Forza migrazione senza conferme}
                           {--repair : Ripara dati inconsistenti}
                           {--rollback : Rollback al sistema legacy}';

    protected $description = 'Migra notifiche dal sistema legacy al nuovo sistema raggruppato per torneo';

    /**
     * 🚀 Esecuzione comando
     */
    public function handle(): int
    {
        $this->info('🔄 MIGRAZIONE SISTEMA NOTIFICHE LEGACY → NUOVO SISTEMA');
        $this->line('');

        if ($this->option('rollback')) {
            return $this->rollbackMigration();
        }

        if ($this->option('repair')) {
            return $this->repairData();
        }

        return $this->runMigration();
    }

    /**
     * 🔄 Esegue migrazione principale
     */
    private function runMigration(): int
    {
        // 1. Analisi situazione attuale
        $analysis = $this->analyzeCurrentState();
        $this->displayAnalysis($analysis);

        if (!$this->option('force') && !$this->confirm('Procedere con la migrazione?')) {
            $this->warn('❌ Migrazione annullata');
            return 1;
        }

        // 2. Backup dati legacy
        $this->info('📦 Creazione backup dati legacy...');
        $backupPath = $this->createBackup();
        $this->info("✅ Backup creato: {$backupPath}");

        // 3. Migrazione per fasi
        $results = [
            'legacy_notifications_migrated' => 0,
            'tournament_notifications_created' => 0,
            'errors' => [],
            'warnings' => []
        ];

        DB::transaction(function () use (&$results) {
            // Fase 1: Migra notifiche individuali legacy
            $results['legacy_notifications_migrated'] = $this->migrateLegacyNotifications();

            // Fase 2: Crea record tournament_notifications raggruppati
            $results['tournament_notifications_created'] = $this->createTournamentNotificationRecords();

            // Fase 3: Pulisci dati inconsistenti
            $this->cleanupInconsistentData($results);

            // Fase 4: Valida migrazione
            $this->validateMigration($results);
        });

        // 4. Report finale
        $this->displayMigrationResults($results);

        return count($results['errors']) > 0 ? 1 : 0;
    }

    /**
     * 📊 Analizza stato attuale
     */
    private function analyzeCurrentState(): array
    {
        $this->info('🔍 Analisi stato attuale...');

        return [
            'total_notifications' => Notification::count(),
            'legacy_notifications' => Notification::legacySystem()->count(),
            'new_system_notifications' => Notification::newSystem()->count(),
            'tournament_notifications' => TournamentNotification::count(),
            'tournaments_total' => Tournament::count(),
            'tournaments_with_assignments' => Tournament::has('assignments')->count(),
            'tournaments_ready_for_migration' => $this->getTournamentsReadyForMigration()->count(),
            'orphaned_notifications' => $this->getOrphanedNotifications()->count(),
            'duplicate_notifications' => $this->getDuplicateNotifications()->count()
        ];
    }

    /**
     * 📋 Mostra analisi
     */
    private function displayAnalysis(array $analysis): void
    {
        $this->line('📊 STATO ATTUALE SISTEMA:');
        $this->table(['Metrica', 'Valore'], [
            ['Notifiche totali', number_format($analysis['total_notifications'])],
            ['Notifiche legacy', number_format($analysis['legacy_notifications'])],
            ['Notifiche nuovo sistema', number_format($analysis['new_system_notifications'])],
            ['Record TournamentNotification', number_format($analysis['tournament_notifications'])],
            ['Tornei totali', number_format($analysis['tournaments_total'])],
            ['Tornei con assegnazioni', number_format($analysis['tournaments_with_assignments'])],
            ['Tornei pronti per migrazione', number_format($analysis['tournaments_ready_for_migration'])],
            ['Notifiche orfane', number_format($analysis['orphaned_notifications'])],
            ['Notifiche duplicate', number_format($analysis['duplicate_notifications'])]
        ]);

        if ($analysis['orphaned_notifications'] > 0) {
            $this->warn("⚠️ Trovate {$analysis['orphaned_notifications']} notifiche orfane (senza torneo associato)");
        }

        if ($analysis['duplicate_notifications'] > 0) {
            $this->warn("⚠️ Trovate {$analysis['duplicate_notifications']} notifiche duplicate");
        }
    }

    /**
     * 📦 Crea backup dati legacy
     */
    private function createBackup(): string
    {
        $timestamp = now()->format('Y_m_d_H_i_s');
        $backupDir = storage_path("app/backups/notifications");

        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupFile = "{$backupDir}/legacy_notifications_backup_{$timestamp}.sql";

        // Export notifiche legacy
        $legacyNotifications = Notification::legacySystem()->get();
        $tournamentNotifications = TournamentNotification::all();

        $backup = [
            'metadata' => [
                'created_at' => now()->toISOString(),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'legacy_count' => $legacyNotifications->count(),
                'tournament_count' => $tournamentNotifications->count()
            ],
            'legacy_notifications' => $legacyNotifications->toArray(),
            'tournament_notifications' => $tournamentNotifications->toArray()
        ];

        file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT));

        return $backupFile;
    }

    /**
     * 🔄 Migra notifiche legacy
     */
    private function migrateLegacyNotifications(): int
    {
        $this->info('🔄 Migrazione notifiche legacy...');

        $batchSize = $this->option('batch-size');
        $migrated = 0;

        Notification::legacySystem()
                   ->with(['assignment.tournament'])
                   ->chunk($batchSize, function ($notifications) use (&$migrated) {

                       $bar = $this->output->createProgressBar($notifications->count());
                       $bar->start();

                       foreach ($notifications as $notification) {
                           try {
                               if ($this->option('dry-run')) {
                                   // Simula migrazione
                                   if ($this->canMigrateNotification($notification)) {
                                       $migrated++;
                                   }
                               } else {
                                   if ($notification->migrateToNewSystem()) {
                                       $migrated++;
                                   }
                               }
                           } catch (\Exception $e) {
                               Log::error('Failed to migrate notification', [
                                   'notification_id' => $notification->id,
                                   'error' => $e->getMessage()
                               ]);
                           }

                           $bar->advance();
                       }

                       $bar->finish();
                       $this->line('');
                   });

        $this->info("✅ Migrate {$migrated} notifiche legacy");
        return $migrated;
    }

    /**
     * 🏆 Crea record TournamentNotification raggruppati
     */
    private function createTournamentNotificationRecords(): int
    {
        $this->info('🏆 Creazione record TournamentNotification...');

        $tournaments = $this->getTournamentsReadyForMigration();
        $created = 0;

        $bar = $this->output->createProgressBar($tournaments->count());
        $bar->start();

        foreach ($tournaments as $tournament) {
            try {
                if ($this->option('dry-run')) {
                    $created++;
                } else {
                    $this->createTournamentNotificationFromLegacy($tournament);
                    $created++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to create TournamentNotification', [
                    'tournament_id' => $tournament->id,
                    'error' => $e->getMessage()
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info("✅ Creati {$created} record TournamentNotification");

        return $created;
    }

    /**
     * 🏆 Crea TournamentNotification da legacy
     */
    private function createTournamentNotificationFromLegacy(Tournament $tournament): TournamentNotification
    {
        $notifications = Notification::where('tournament_id', $tournament->id)->get();

        // Raggruppa per tipo
        $byType = $notifications->groupBy('recipient_type');

        // Calcola statistiche
        $details = [
            'club' => [
                'sent' => $byType->get('club', collect())->where('status', 'sent')->count(),
                'failed' => $byType->get('club', collect())->where('status', 'failed')->count()
            ],
            'referees' => [
                'sent' => $byType->get('referee', collect())->where('status', 'sent')->count(),
                'failed' => $byType->get('referee', collect())->where('status', 'failed')->count()
            ],
            'institutional' => [
                'sent' => $byType->get('institutional', collect())->where('status', 'sent')->count(),
                'failed' => $byType->get('institutional', collect())->where('status', 'failed')->count()
            ]
        ];

        $totalSent = $details['club']['sent'] + $details['referees']['sent'] + $details['institutional']['sent'];
        $totalFailed = $details['club']['failed'] + $details['referees']['failed'] + $details['institutional']['failed'];

        $status = $totalFailed > 0 ? ($totalSent > 0 ? 'partial' : 'failed') : 'sent';

        return TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => $status,
            'total_recipients' => $totalSent + $totalFailed,
            'sent_at' => $notifications->max('sent_at') ?: $notifications->max('created_at'),
            'sent_by' => 1, // Sistema di migrazione
            'details' => $details,
            'templates_used' => [
                'club' => 'migrated_from_legacy',
                'referee' => 'migrated_from_legacy',
                'institutional' => 'migrated_from_legacy'
            ]
        ]);
    }

    /**
     * 🧹 Pulisce dati inconsistenti
     */
    private function cleanupInconsistentData(array &$results): void
    {
        $this->info('🧹 Pulizia dati inconsistenti...');

        // Rimuovi notifiche orfane
        $orphaned = $this->getOrphanedNotifications();
        if ($orphaned->count() > 0) {
            if (!$this->option('dry-run')) {
                $orphaned->delete();
            }
            $results['warnings'][] = "Rimosse {$orphaned->count()} notifiche orfane";
        }

        // Gestisci duplicati
        $duplicates = $this->getDuplicateNotifications();
        if ($duplicates->count() > 0) {
            if (!$this->option('dry-run')) {
                $this->removeDuplicateNotifications($duplicates);
            }
            $results['warnings'][] = "Gestiti {$duplicates->count()} duplicati";
        }
    }

    /**
     * ✅ Valida migrazione
     */
    private function validateMigration(array &$results): void
    {
        $this->info('✅ Validazione migrazione...');

        // Verifica che ogni notifica abbia tournament_id
        $withoutTournament = Notification::whereNull('tournament_id')->count();
        if ($withoutTournament > 0) {
            $results['errors'][] = "Trovate {$withoutTournament} notifiche senza tournament_id";
        }

        // Verifica coerenza TournamentNotification
        $inconsistentTournaments = TournamentNotification::whereDoesntHave('tournament')->count();
        if ($inconsistentTournaments > 0) {
            $results['errors'][] = "Trovati {$inconsistentTournaments} TournamentNotification senza torneo";
        }

        // Verifica statistiche
        foreach (TournamentNotification::all() as $tn) {
            $actualCount = Notification::where('tournament_id', $tn->tournament_id)->count();
            if ($actualCount !== $tn->total_recipients) {
                $results['warnings'][] = "TournamentNotification {$tn->id}: conteggio errato ({$actualCount} vs {$tn->total_recipients})";
            }
        }
    }

    /**
     * 📊 Mostra risultati migrazione
     */
    private function displayMigrationResults(array $results): void
    {
        $this->line('');
        $this->info('📊 RISULTATI MIGRAZIONE:');
        $this->line('');

        $this->table(['Operazione', 'Risultato'], [
            ['Notifiche legacy migrate', $results['legacy_notifications_migrated']],
            ['TournamentNotification creati', $results['tournament_notifications_created']],
            ['Errori', count($results['errors'])],
            ['Avvisi', count($results['warnings'])]
        ]);

        if (!empty($results['errors'])) {
            $this->line('');
            $this->error('❌ ERRORI RISCONTRATI:');
            foreach ($results['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        if (!empty($results['warnings'])) {
            $this->line('');
            $this->warn('⚠️ AVVISI:');
            foreach ($results['warnings'] as $warning) {
                $this->line("  • {$warning}");
            }
        }

        if (empty($results['errors'])) {
            $this->line('');
            $this->info('✅ Migrazione completata con successo!');

            if (!$this->option('dry-run')) {
                $this->info('🎯 Prossimi passi:');
                $this->line('  1. Testare il nuovo sistema con alcuni tornei');
                $this->line('  2. Aggiornare la configurazione');
                $this->line('  3. Formare gli utenti sul nuovo sistema');
                $this->line('  4. Monitorare performance e log');
            }
        }
    }

    /**
     * 🔧 Ripara dati inconsistenti
     */
    private function repairData(): int
    {
        $this->info('🔧 Riparazione dati inconsistenti...');

        $repairs = [
            'notifiche senza tournament_id' => function() {
                return $this->repairMissingTournamentIds();
            },
            'TournamentNotification orfani' => function() {
                return $this->repairOrphanedTournamentNotifications();
            },
            'statistiche errate' => function() {
                return $this->repairIncorrectStats();
            },
            'template mancanti' => function() {
                return $this->repairMissingTemplates();
            }
        ];

        $totalRepaired = 0;

        foreach ($repairs as $description => $repair) {
            try {
                $count = $repair();
                $this->info("✅ Riparate {$count} {$description}");
                $totalRepaired += $count;
            } catch (\Exception $e) {
                $this->error("❌ Errore riparazione {$description}: {$e->getMessage()}");
            }
        }

        $this->info("🔧 Riparazione completata: {$totalRepaired} elementi corretti");
        return 0;
    }

    /**
     * ↩️ Rollback migrazione
     */
    private function rollbackMigration(): int
    {
        $this->warn('↩️ ROLLBACK MIGRAZIONE AL SISTEMA LEGACY');

        if (!$this->confirm('Sei sicuro di voler tornare al sistema legacy? Questa operazione eliminerà tutti i dati del nuovo sistema.')) {
            return 1;
        }

        DB::transaction(function () {
            // Ripristina notifiche legacy
            Notification::whereNotNull('tournament_id')->update(['tournament_id' => null]);

            // Elimina TournamentNotification
            TournamentNotification::truncate();

            $this->info('✅ Rollback completato');
        });

        return 0;
    }

    // === HELPER METHODS ===

    private function getTournamentsReadyForMigration()
    {
        return Tournament::whereHas('assignments')
                         ->whereDoesntHave('notifications')
                         ->get();
    }

    private function getOrphanedNotifications()
    {
        return Notification::whereDoesntHave('tournament')->get();
    }

    private function getDuplicateNotifications()
    {
        return Notification::select('tournament_id', 'recipient_email', 'recipient_type')
                          ->groupBy('tournament_id', 'recipient_email', 'recipient_type')
                          ->havingRaw('COUNT(*) > 1')
                          ->get();
    }

    private function canMigrateNotification(Notification $notification): bool
    {
        return $notification->assignment &&
               $notification->assignment->tournament &&
               !empty($notification->recipient_email);
    }

    private function removeDuplicateNotifications($duplicates): void
    {
        foreach ($duplicates as $duplicate) {
            $notifications = Notification::where('tournament_id', $duplicate->tournament_id)
                                       ->where('recipient_email', $duplicate->recipient_email)
                                       ->where('recipient_type', $duplicate->recipient_type)
                                       ->orderBy('created_at', 'desc')
                                       ->get();

            // Mantieni solo il più recente
            $notifications->skip(1)->each(function($notification) {
                $notification->delete();
            });
        }
    }

    private function repairMissingTournamentIds(): int
    {
        $count = 0;

        Notification::whereNull('tournament_id')
                   ->whereNotNull('assignment_id')
                   ->with('assignment.tournament')
                   ->chunk(100, function($notifications) use (&$count) {
                       foreach ($notifications as $notification) {
                           if ($notification->assignment && $notification->assignment->tournament) {
                               $notification->update(['tournament_id' => $notification->assignment->tournament_id]);
                               $count++;
                           }
                       }
                   });

        return $count;
    }

    private function repairOrphanedTournamentNotifications(): int
    {
        return TournamentNotification::whereDoesntHave('tournament')->delete();
    }

    private function repairIncorrectStats(): int
    {
        $count = 0;

        TournamentNotification::chunk(50, function($tournamentNotifications) use (&$count) {
            foreach ($tournamentNotifications as $tn) {
                $actualCount = Notification::where('tournament_id', $tn->tournament_id)->count();
                if ($actualCount !== $tn->total_recipients) {
                    $tn->update(['total_recipients' => $actualCount]);
                    $count++;
                }
            }
        });

        return $count;
    }

    private function repairMissingTemplates(): int
    {
        return TournamentNotification::whereNull('templates_used')
                                   ->update([
                                       'templates_used' => [
                                           'club' => 'legacy_migrated',
                                           'referee' => 'legacy_migrated',
                                           'institutional' => 'legacy_migrated'
                                       ]
                                   ]);
    }
}
