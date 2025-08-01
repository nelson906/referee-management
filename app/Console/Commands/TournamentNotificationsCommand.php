<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\Notification;
use App\Services\TournamentNotificationService;
use Illuminate\Support\Facades\DB;

class TournamentNotificationsCommand extends Command
{
    /**
     * 🎯 Nome e firma del comando
     */
    protected $signature = 'tournaments:notifications
                           {action : Azione da eseguire (list|send|resend|cleanup|migrate|stats)}
                           {--tournament= : ID del torneo specifico}
                           {--zone= : Filtra per zona}
                           {--status= : Filtra per stato}
                           {--days= : Giorni per cleanup (default: 90)}
                           {--dry-run : Simula senza effettuare modifiche}
                           {--force : Forza azione senza conferma}';

    /**
     * 📝 Descrizione del comando
     */
    protected $description = 'Gestione sistema notifiche tornei - Invio, monitoring e manutenzione';

    protected $notificationService;

    public function __construct(TournamentNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * 🚀 Esecuzione comando principale
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        $this->info("🎾 GESTIONE NOTIFICHE TORNEI - Azione: {$action}");
        $this->line('');

        return match($action) {
            'list' => $this->listTournaments(),
            'send' => $this->sendNotifications(),
            'resend' => $this->resendNotifications(),
            'cleanup' => $this->cleanupNotifications(),
            'migrate' => $this->migrateToNewSystem(),
            'stats' => $this->showStats(),
            default => $this->showHelp()
        };
    }

    /**
     * 📋 Lista tornei e stato notifiche
     */
    private function listTournaments(): int
    {
        $query = Tournament::with(['club', 'zone', 'assignments'])
                          ->orderBy('start_date', 'desc');

        // Applica filtri
        if ($this->option('zone')) {
            $query->where('zone_id', $this->option('zone'));
        }

        if ($this->option('status')) {
            switch ($this->option('status')) {
                case 'ready':
                    $query->whereDoesntHave('notifications')
                          ->whereHas('assignments');
                    break;
                case 'notified':
                    $query->whereHas('notifications');
                    break;
                case 'active':
                    $query->whereIn('status', ['open', 'closed', 'assigned']);
                    break;
            }
        }

        $tournaments = $query->take(50)->get();

        if ($tournaments->isEmpty()) {
            $this->warn('❌ Nessun torneo trovato con i criteri specificati');
            return 1;
        }

        // Tabella risultati
        $headers = ['ID', 'Nome', 'Date', 'Zona', 'Arbitri', 'Stato Notifiche', 'Azioni'];
        $rows = [];

        foreach ($tournaments as $tournament) {
            $hasNotifications = TournamentNotification::where('tournament_id', $tournament->id)->exists();
            $canSend = !$hasNotifications && $tournament->assignments->isNotEmpty();
            $canResend = $hasNotifications;

            $actions = [];
            if ($canSend) {
                $actions[] = '📧 Invia';
            }
            if ($canResend) {
                $actions[] = '🔄 Reinvia';
            }

            $notificationStatus = $hasNotifications ? '✅ Notificato' : ($canSend ? '⏳ Pronto' : '❌ Non pronto');

            $rows[] = [
                $tournament->id,
                $this->truncateText($tournament->name, 25),
                $tournament->start_date->format('d/m/Y') . '-' . $tournament->end_date->format('d/m/Y'),
                $tournament->zone->code ?? 'N/A',
                $tournament->assignments->count(),
                $notificationStatus,
                implode(', ', $actions) ?: '-'
            ];
        }

        $this->table($headers, $rows);

        // Statistiche riassuntive
        $total = $tournaments->count();
        $notified = $tournaments->filter(function($t) {
            return TournamentNotification::where('tournament_id', $t->id)->exists();
        })->count();
        $ready = $tournaments->filter(function($t) {
            return !TournamentNotification::where('tournament_id', $t->id)->exists() && $t->assignments->isNotEmpty();
        })->count();

        $this->line('');
        $this->info("📊 Riepilogo: {$total} tornei | {$ready} pronti | {$notified} notificati");

        return 0;
    }

    /**
     * 📧 Invia notifiche per tornei pronti
     */
    private function sendNotifications(): int
    {
        if ($tournamentId = $this->option('tournament')) {
            return $this->sendForSpecificTournament($tournamentId);
        }

        // Trova tornei pronti per notifica
        $tournaments = Tournament::whereDoesntHave('notifications')
                                ->whereHas('assignments')
                                ->with(['club', 'zone', 'assignments.referee', 'assignments.user'])
                                ->get();

        if ($tournaments->isEmpty()) {
            $this->info('✅ Nessun torneo pronto per le notifiche');
            return 0;
        }

        $this->info("📧 Trovati {$tournaments->count()} tornei pronti per notifiche:");

        foreach ($tournaments as $tournament) {
            $this->line("  • {$tournament->name} ({$tournament->start_date->format('d/m/Y')})");
        }

        if (!$this->option('force') && !$this->confirm('Procedere con l\'invio?')) {
            $this->warn('❌ Operazione annullata');
            return 1;
        }

        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($tournaments as $tournament) {
            try {
                if ($this->option('dry-run')) {
                    $this->line("🧪 [DRY RUN] Invio per: {$tournament->name}");
                    $results['success']++;
                    continue;
                }

                $this->line("📧 Invio notifiche per: {$tournament->name}");

                $result = $this->notificationService->sendTournamentNotifications($tournament, [
                    'club_template' => 'club_assignment_standard',
                    'referee_template' => 'referee_assignment_formal',
                    'institutional_template' => 'institutional_report_standard',
                    'include_attachments' => true,
                    'send_to_club' => true,
                    'send_to_referees' => true,
                    'send_to_institutional' => true,
                    'sent_by' => 1 // CLI user
                ]);

                $this->info("  ✅ Inviate a {$result['total_sent']} destinatari");
                $results['success']++;

            } catch (\Exception $e) {
                $this->error("  ❌ Errore: {$e->getMessage()}");
                $results['failed']++;
                $results['errors'][] = "{$tournament->name}: {$e->getMessage()}";
            }
        }

        $this->line('');
        $this->info("📊 Completato: {$results['success']} successi, {$results['failed']} errori");

        if (!empty($results['errors'])) {
            $this->line('');
            $this->error('❌ Errori riscontrati:');
            foreach ($results['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        return $results['failed'] > 0 ? 1 : 0;
    }

    /**
     * 📧 Invia per torneo specifico
     */
    private function sendForSpecificTournament($tournamentId): int
    {
        $tournament = Tournament::with(['club', 'zone', 'assignments.referee', 'assignments.user'])->find($tournamentId);

        if (!$tournament) {
            $this->error("❌ Torneo {$tournamentId} non trovato");
            return 1;
        }

        if ($tournament->assignments->isEmpty()) {
            $this->error("❌ Il torneo non ha arbitri assegnati");
            return 1;
        }

        if (TournamentNotification::where('tournament_id', $tournament->id)->exists()) {
            $this->warn("⚠️ Il torneo ha già notifiche inviate");
            if (!$this->option('force') && !$this->confirm('Procedere comunque?')) {
                return 1;
            }
        }

        $this->info("📧 Invio notifiche per: {$tournament->name}");

        if ($this->option('dry-run')) {
            $expected = 1 + $tournament->assignments->count() + 3; // Club + Arbitri + Istituzionali
            $this->line("🧪 [DRY RUN] Verrebbero inviate a {$expected} destinatari");
            return 0;
        }

        try {
            $result = $this->notificationService->sendTournamentNotifications($tournament, [
                'club_template' => 'club_assignment_standard',
                'referee_template' => 'referee_assignment_formal',
                'institutional_template' => 'institutional_report_standard',
                'include_attachments' => true,
                'send_to_club' => true,
                'send_to_referees' => true,
                'send_to_institutional' => true,
                'sent_by' => 1
            ]);

            $this->info("✅ Notifiche inviate con successo a {$result['total_sent']} destinatari");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Errore nell'invio: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * 🔄 Reinvia notifiche fallite
     */
    private function resendNotifications(): int
    {
        $query = TournamentNotification::whereIn('status', ['failed', 'partial'])
                                     ->with(['tournament.club', 'tournament.zone']);

        if ($this->option('tournament')) {
            $query->where('tournament_id', $this->option('tournament'));
        }

        $notifications = $query->get();

        if ($notifications->isEmpty()) {
            $this->info('✅ Nessuna notifica da reinviare');
            return 0;
        }

        $this->info("🔄 Trovate {$notifications->count()} notifiche da reinviare:");
        foreach ($notifications as $notification) {
            $this->line("  • {$notification->tournament->name} ({$notification->status})");
        }

        if (!$this->option('force') && !$this->confirm('Procedere con il reinvio?')) {
            return 1;
        }

        $results = ['success' => 0, 'failed' => 0];

        foreach ($notifications as $notification) {
            try {
                if ($this->option('dry-run')) {
                    $this->line("🧪 [DRY RUN] Reinvio: {$notification->tournament->name}");
                    $results['success']++;
                    continue;
                }

                $this->line("🔄 Reinvio: {$notification->tournament->name}");

                $this->notificationService->resendTournamentNotifications($notification);

                $this->info("  ✅ Reinviato con successo");
                $results['success']++;

            } catch (\Exception $e) {
                $this->error("  ❌ Errore: {$e->getMessage()}");
                $results['failed']++;
            }
        }

        $this->line('');
        $this->info("📊 Completato: {$results['success']} successi, {$results['failed']} errori");

        return $results['failed'] > 0 ? 1 : 0;
    }

    /**
     * 🧹 Pulizia notifiche vecchie
     */
    private function cleanupNotifications(): int
    {
        $days = (int) $this->option('days', 90);

        $this->info("🧹 Pulizia notifiche più vecchie di {$days} giorni");

        // Conta notifiche da eliminare
        $oldTournamentNotifications = TournamentNotification::where('sent_at', '<', now()->subDays($days))
                                                           ->where('status', 'sent');

        $oldIndividualNotifications = Notification::where('sent_at', '<', now()->subDays($days))
                                                 ->where('status', 'sent');

        $tournamentCount = $oldTournamentNotifications->count();
        $individualCount = $oldIndividualNotifications->count();

        if ($tournamentCount === 0 && $individualCount === 0) {
            $this->info('✅ Nessuna notifica da pulire');
            return 0;
        }

        $this->line("📊 Notifiche da eliminare:");
        $this->line("  • Notifiche torneo: {$tournamentCount}");
        $this->line("  • Notifiche individuali: {$individualCount}");

        if (!$this->option('force') && !$this->confirm('Procedere con la pulizia?')) {
            return 1;
        }

        if ($this->option('dry-run')) {
            $this->line("🧪 [DRY RUN] Verrebbero eliminate {$tournamentCount} + {$individualCount} notifiche");
            return 0;
        }

        DB::transaction(function () use ($oldTournamentNotifications, $oldIndividualNotifications) {
            $deletedTournament = $oldTournamentNotifications->delete();
            $deletedIndividual = $oldIndividualNotifications->delete();

            $this->info("✅ Eliminate {$deletedTournament} notifiche torneo");
            $this->info("✅ Eliminate {$deletedIndividual} notifiche individuali");
        });

        return 0;
    }

    /**
     * 🔄 Migrazione sistema legacy → nuovo sistema
     */
    private function migrateToNewSystem(): int
    {
        $this->info("🔄 Migrazione da sistema legacy a nuovo sistema");
        $this->warn("⚠️ Funzionalità in sviluppo - attualmente solo analisi");

        // Analizza notifiche legacy
        $legacyCount = Notification::whereNull('tournament_id')->count();
        $newSystemCount = TournamentNotification::count();

        $this->line('');
        $this->line("📊 Analisi stato migrazione:");
        $this->line("  • Notifiche legacy: {$legacyCount}");
        $this->line("  • Sistema nuovo: {$newSystemCount}");

        if ($legacyCount === 0) {
            $this->info('✅ Nessuna notifica legacy da migrare');
            return 0;
        }

        $this->line('');
        $this->warn('🚧 Migrazione completa non ancora implementata');
        $this->line('   Utilizzare interfaccia web per nuovo sistema');

        return 0;
    }

    /**
     * 📊 Statistiche sistema
     */
    private function showStats(): int
    {
        $this->info('📊 STATISTICHE SISTEMA NOTIFICHE');
        $this->line('');

        try {
            // Statistiche tornei
            $totalTournaments = Tournament::count();
            $tournamentsWithNotifications = Tournament::whereHas('notifications')->count();
            $readyTournaments = Tournament::whereDoesntHave('notifications')
                                         ->whereHas('assignments')
                                         ->count();

            $this->line('🏆 TORNEI:');
            $this->line("  • Totali: {$totalTournaments}");
            $this->line("  • Notificati: {$tournamentsWithNotifications}");
            $this->line("  • Pronti per notifica: {$readyTournaments}");

            // Statistiche notifiche torneo
            $tournamentNotifStats = TournamentNotification::getGlobalStats();
            $this->line('');
            $this->line('📧 NOTIFICHE TORNEO:');
            $this->line("  • Tornei notificati: {$tournamentNotifStats['total_tournaments_notified']}");
            $this->line("  • Destinatari raggiunti: " . number_format($tournamentNotifStats['total_recipients_reached']));
            $this->line("  • Tasso successo: {$tournamentNotifStats['success_rate']}%");
            $this->line("  • Questo mese: {$tournamentNotifStats['this_month']}");
            $this->line("  • Questa settimana: {$tournamentNotifStats['this_week']}");
            $this->line("  • Oggi: {$tournamentNotifStats['today']}");

            // Statistiche per stato
            $this->line('');
            $this->line('📈 PER STATO:');
            $statusStats = TournamentNotification::selectRaw('status, COUNT(*) as count, SUM(total_recipients) as recipients')
                                                ->groupBy('status')
                                                ->get();

            foreach ($statusStats as $stat) {
                $this->line("  • " . ucfirst($stat->status) . ": {$stat->count} tornei, {$stat->recipients} destinatari");
            }

            // Statistiche notifiche individuali se esistono
            if (class_exists('App\Models\Notification')) {
                $individualCount = Notification::count();
                $this->line('');
                $this->line('📨 NOTIFICHE INDIVIDUALI:');
                $this->line("  • Totali: " . number_format($individualCount));
                $this->line("  • Oggi: " . Notification::whereDate('sent_at', today())->count());
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Errore statistiche: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * ❓ Mostra aiuto
     */
    private function showHelp(): int
    {
        $this->error('❌ Azione non riconosciuta');
        $this->line('');
        $this->info('🎯 AZIONI DISPONIBILI:');
        $this->line('  list      - Lista tornei e stato notifiche');
        $this->line('  send      - Invia notifiche per tornei pronti');
        $this->line('  resend    - Reinvia notifiche fallite');
        $this->line('  cleanup   - Pulisci notifiche vecchie');
        $this->line('  migrate   - Migra da sistema legacy');
        $this->line('  stats     - Mostra statistiche');
        $this->line('');
        $this->info('🔧 OPZIONI:');
        $this->line('  --tournament=ID   Specifica torneo');
        $this->line('  --zone=ID         Filtra per zona');
        $this->line('  --status=STATUS   Filtra per stato');
        $this->line('  --days=N          Giorni per cleanup');
        $this->line('  --dry-run         Simula senza modifiche');
        $this->line('  --force           Salta conferme');
        $this->line('');
        $this->info('🔧 ESEMPI:');
        $this->line('  php artisan tournaments:notifications list --zone=6');
        $this->line('  php artisan tournaments:notifications send --tournament=123');
        $this->line('  php artisan tournaments:notifications cleanup --days=30 --dry-run');
        $this->line('  php artisan tournaments:notifications stats');

        return 1;
    }

    /**
     * 🔧 Helper: Tronca testo
     */
    private function truncateText(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length - 3) . '...' : $text;
    }
}
