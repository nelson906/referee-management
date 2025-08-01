<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * ✅ FIXED: FK constraints safe pattern like TournamentSeeder
     */
    public function run(): void
    {
        $this->command->info('🔔 Creando Notifiche Sistema...');

        if (!Schema::hasTable('notifications')) {
            $this->command->warn('⚠️ Tabella notifications non trovata - saltando seeder');
            return;
        }

        // ✅ FIXED: Same pattern as TournamentSeeder
        Schema::disableForeignKeyConstraints();
        try {
            DB::table('notifications')->truncate();

            $totalNotifications = 0;

            // Crea notifiche per assegnazioni
            $totalNotifications += $this->createAssignmentNotifications();

            $this->command->info("🏆 Notifiche create con successo: {$totalNotifications} notifiche totali");
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * ✅ Crea notifiche email per assegnazioni (schema personalizzato)
     */
    private function createAssignmentNotifications(): int
    {
        $this->command->info("📋 Creando notifiche assegnazioni...");

        // Use correct relationship and field names
        $assignments = Assignment::with(['user', 'tournament.club'])
                                ->where('is_confirmed', true)  // Solo confermate
                                ->get();

        if ($assignments->isEmpty()) {
            $this->command->warn('⚠️ Nessun assignment confermato trovato');
            return 0;
        }

        $created = 0;

        foreach ($assignments->take(20) as $assignment) { // Limite per non esagerare
            // Notifica email all'arbitro
            $created += $this->createRefereeEmailNotification($assignment);

            // Alcune notifiche al circolo (50% chance)
            if (rand(0, 1)) {
                $created += $this->createClubEmailNotification($assignment);
            }

            // Alcune notifiche istituzionali (30% chance)
            if (rand(0, 9) < 3) {
                $created += $this->createInstitutionalEmailNotification($assignment);
            }
        }

        return $created;
    }

    /**
     * ✅ Notifica email all'arbitro
     */
    private function createRefereeEmailNotification(Assignment $assignment): int
    {
        try {
            DB::table('notifications')->insert([
                'assignment_id' => $assignment->id,
                'recipient_type' => 'referee',
                'recipient_email' => $assignment->user->email,
                'subject' => "Assegnazione Confermata: {$assignment->tournament->name}",
                'body' => $this->generateRefereeEmailBody($assignment),
                'template_used' => 'assignment_referee',
                'status' => $this->getRandomEmailStatus(),
                'sent_at' => $this->generateSentDate($assignment),
                'error_message' => null,
                'retry_count' => 0,
                'attachments' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        } catch (\Exception $e) {
            $this->command->warn("⚠️ Errore creazione notifica arbitro: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Notifica email al circolo
     */
    private function createClubEmailNotification(Assignment $assignment): int
    {
        try {
            // Email fittizia del circolo basata sul nome
            $clubSlug = \Illuminate\Support\Str::slug($assignment->tournament->club->name);
            $clubEmail = "segreteria@{$clubSlug}.golf.it";

            DB::table('notifications')->insert([
                'assignment_id' => $assignment->id,
                'recipient_type' => 'club',
                'recipient_email' => $clubEmail,
                'subject' => "Arbitri Assegnati: {$assignment->tournament->name}",
                'body' => $this->generateClubEmailBody($assignment),
                'template_used' => 'assignment_club',
                'status' => 'sent', // Club emails usually sent successfully
                'sent_at' => $this->generateSentDate($assignment),
                'error_message' => null,
                'retry_count' => 0,
                'attachments' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        } catch (\Exception $e) {
            $this->command->warn("⚠️ Errore creazione notifica circolo: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Notifica email istituzionale
     */
    private function createInstitutionalEmailNotification(Assignment $assignment): int
    {
        try {
            $institutionalEmails = [
                'crc@federgolf.it',
                'segreteria@federgolf.it',
                'arbitri@federgolf.it'
            ];

            DB::table('notifications')->insert([
                'assignment_id' => $assignment->id,
                'recipient_type' => 'institutional',
                'recipient_email' => $institutionalEmails[array_rand($institutionalEmails)],
                'subject' => "Report Assegnazione: {$assignment->tournament->name}",
                'body' => $this->generateInstitutionalEmailBody($assignment),
                'template_used' => 'assignment_institutional',
                'status' => 'sent',
                'sent_at' => $this->generateSentDate($assignment),
                'error_message' => null,
                'retry_count' => 0,
                'attachments' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        } catch (\Exception $e) {
            $this->command->warn("⚠️ Errore creazione notifica istituzionale: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Corpo email per arbitro
     */
    private function generateRefereeEmailBody(Assignment $assignment): string
    {
        return "Gentile {$assignment->user->name},\n\n" .
               "La confermiamo che è stato assegnato come {$assignment->role} per il torneo:\n\n" .
               "**{$assignment->tournament->name}**\n" .
               "Date: {$assignment->tournament->start_date->format('d/m/Y')} - {$assignment->tournament->end_date->format('d/m/Y')}\n" .
               "Circolo: {$assignment->tournament->club->name}\n" .
               "Ruolo: {$assignment->role}\n\n" .
               ($assignment->notes ? "Note: {$assignment->notes}\n\n" : "") .
               "La convocazione ufficiale verrà inviata dal circolo.\n\n" .
               "Cordiali saluti,\n" .
               "Sezione Zonale Regole";
    }

    /**
     * ✅ Corpo email per circolo
     */
    private function generateClubEmailBody(Assignment $assignment): string
    {
        return "Gentile Segreteria,\n\n" .
               "Vi confermiamo l'arbitro assegnato al vostro torneo:\n\n" .
               "**{$assignment->tournament->name}**\n" .
               "Arbitro: {$assignment->user->name} ({$assignment->role})\n" .
               "Email: {$assignment->user->email}\n" .
               "Telefono: {$assignment->user->phone}\n\n" .
               "Vi preghiamo di inviare la convocazione ufficiale.\n\n" .
               "Cordiali saluti,\n" .
               "Sezione Zonale Regole";
    }

    /**
     * ✅ Corpo email istituzionale
     */
    private function generateInstitutionalEmailBody(Assignment $assignment): string
    {
        return "REPORT ASSEGNAZIONE\n\n" .
               "Torneo: {$assignment->tournament->name}\n" .
               "Date: {$assignment->tournament->start_date->format('d/m/Y')} - {$assignment->tournament->end_date->format('d/m/Y')}\n" .
               "Circolo: {$assignment->tournament->club->name}\n" .
               "Zona: {$assignment->tournament->zone->name}\n\n" .
               "ARBITRO ASSEGNATO:\n" .
               "Nome: {$assignment->user->name}\n" .
               "Ruolo: {$assignment->role}\n" .
               "Livello: {$assignment->user->level}\n" .
               "Email: {$assignment->user->email}\n\n" .
               "Assegnato da: {$assignment->assignedBy->name}\n" .
               "Data assegnazione: {$assignment->assigned_at->format('d/m/Y H:i')}\n\n" .
               "---\n" .
               "Sistema Gestione Arbitri Golf";
    }

    /**
     * ✅ Status email realistico
     */
    private function getRandomEmailStatus(): string
    {
        $statuses = ['sent', 'sent', 'sent', 'pending', 'failed']; // 60% sent, 20% pending, 20% failed
        return $statuses[array_rand($statuses)];
    }

    /**
     * ✅ Data invio realistica
     */
    private function generateSentDate(Assignment $assignment): ?Carbon
    {
        $status = $this->getRandomEmailStatus();

        if ($status === 'pending') {
            return null; // Non ancora inviata
        }

        // Email inviate 0-3 giorni dopo l'assegnazione
        return $assignment->assigned_at->copy()->addDays(rand(0, 3))->addHours(rand(0, 23));
    }
}
