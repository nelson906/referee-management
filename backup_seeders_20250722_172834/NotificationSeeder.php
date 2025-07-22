<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔔 Creando Notifiche Sistema...');

        if (Schema::hasTable('notifications')) {
            Notification::truncate();
        } else {
            $this->command->warn('⚠️ Tabella notifications non trovata - saltando seeder');
            return;
        }

        $totalNotifications = 0;

        // Crea notifiche per assegnazioni
        $totalNotifications += $this->createAssignmentNotifications();

        // Crea notifiche per scadenze
        $totalNotifications += $this->createDeadlineNotifications();

        // Crea notifiche di sistema
        $totalNotifications += $this->createSystemNotifications();

        $this->command->info("🏆 Notifiche create con successo: {$totalNotifications} notifiche totali");
    }

    /**
     * Crea notifiche per assegnazioni arbitri
     */
    private function createAssignmentNotifications(): int
    {
        $this->command->info("📋 Creando notifiche assegnazioni...");

        $assignments = Assignment::with(['referee', 'tournament'])
                                ->where('is_confirmed', false)
                                ->get();

        $created = 0;

        foreach ($assignments as $assignment) {
            $notification = $this->createNotification([
                'user_id' => $assignment->referee_id,
                'type' => 'assignment_received',
                'title' => 'Nuova Assegnazione Torneo',
                'message' => "Sei stato assegnato come {$assignment->role} per il torneo '{$assignment->tournament->name}'",
                'data' => [
                    'tournament_id' => $assignment->tournament_id,
                    'assignment_id' => $assignment->id,
                    'role' => $assignment->role,
                    'tournament_name' => $assignment->tournament->name,
                    'tournament_date' => $assignment->tournament->start_date
                ],
                'action_url' => "/assignments/{$assignment->id}",
                'priority' => 'high',
                'expires_at' => Carbon::parse($assignment->tournament->start_date)->subDays(1)
            ]);

            if ($notification) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Crea notifiche per scadenze disponibilità
     */
    private function createDeadlineNotifications(): int
    {
        $this->command->info("⏰ Creando notifiche scadenze...");

        $openTournaments = Tournament::where('status', 'open')
                                   ->where('availability_deadline', '>', now())
                                   ->where('availability_deadline', '<', now()->addDays(7))
                                   ->get();

        $created = 0;

        foreach ($openTournaments as $tournament) {
            // Notifica a tutti gli arbitri della zona
            $eligibleReferees = $this->getEligibleRefereesForTournament($tournament);

            foreach ($eligibleReferees as $referee) {
                // Controlla se ha già dichiarato disponibilità
                $hasAvailability = $tournament->availabilities()
                                            ->where('referee_id', $referee->id)
                                            ->exists();

                if (!$hasAvailability) {
                    $notification = $this->createNotification([
                        'user_id' => $referee->id,
                        'type' => 'deadline_reminder',
                        'title' => 'Scadenza Disponibilità',
                        'message' => "Ricorda di dichiarare la tua disponibilità per '{$tournament->name}' entro il " .
                                   Carbon::parse($tournament->availability_deadline)->format('d/m/Y'),
                        'data' => [
                            'tournament_id' => $tournament->id,
                            'tournament_name' => $tournament->name,
                            'deadline' => $tournament->availability_deadline
                        ],
                        'action_url' => "/tournaments/{$tournament->id}/availability",
                        'priority' => 'medium',
                        'expires_at' => Carbon::parse($tournament->availability_deadline)
                    ]);

                    if ($notification) {
                        $created++;
                    }
                }
            }
        }

        return $created;
    }

    /**
     * Crea notifiche di sistema
     */
    private function createSystemNotifications(): int
    {
        $this->command->info("⚙️ Creando notifiche sistema...");

        $created = 0;

        // Notifica benvenuto per nuovi arbitri
        $newReferees = User::where('user_type', 'referee')
                          ->where('created_at', '>', now()->subDays(30))
                          ->get();

        foreach ($newReferees as $referee) {
            $notification = $this->createNotification([
                'user_id' => $referee->id,
                'type' => 'welcome',
                'title' => 'Benvenuto nel Sistema Golf',
                'message' => "Benvenuto {$referee->name}! Completa il tuo profilo e inizia a dichiarare le tue disponibilità.",
                'data' => [
                    'referee_level' => $referee->level,
                    'zone' => $referee->zone ? $referee->zone->name : null
                ],
                'action_url' => '/profile/complete',
                'priority' => 'low',
                'expires_at' => now()->addDays(30)
            ]);

            if ($notification) {
                $created++;
            }
        }

        // Notifiche per admin su tornei senza assegnazioni
        $problematicTournaments = Tournament::where('status', 'closed')
                                           ->whereDoesntHave('assignments')
                                           ->where('start_date', '>', now())
                                           ->get();

        foreach ($problematicTournaments as $tournament) {
            $admin = $tournament->zone_id
                ? User::where('user_type', 'admin')->where('zone_id', $tournament->zone_id)->first()
                : User::where('user_type', 'national_admin')->first();

            if ($admin) {
                $notification = $this->createNotification([
                    'user_id' => $admin->id,
                    'type' => 'admin_alert',
                    'title' => 'Torneo Senza Assegnazioni',
                    'message' => "Il torneo '{$tournament->name}' è chiuso ma non ha ancora assegnazioni.",
                    'data' => [
                        'tournament_id' => $tournament->id,
                        'tournament_name' => $tournament->name,
                        'tournament_date' => $tournament->start_date
                    ],
                    'action_url' => "/admin/tournaments/{$tournament->id}/assignments",
                    'priority' => 'high',
                    'expires_at' => Carbon::parse($tournament->start_date)->subDays(3)
                ]);

                if ($notification) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Crea una notifica
     */
    private function createNotification(array $data): ?Notification
    {
        try {
            return Notification::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => $data['type'],
                'notifiable_type' => User::class,
                'notifiable_id' => $data['user_id'],
                'data' => json_encode([
                    'title' => $data['title'],
                    'message' => $data['message'],
                    'action_url' => $data['action_url'] ?? null,
                    'priority' => $data['priority'] ?? 'medium',
                    'additional_data' => $data['data'] ?? []
                ]),
                'read_at' => rand(0, 1) ? now()->subDays(rand(1, 5)) : null, // Alcune già lette
                'created_at' => now()->subDays(rand(0, 7)),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            $this->command->warn("⚠️ Errore creazione notifica: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Ottieni arbitri eleggibili per torneo
     */
    private function getEligibleRefereesForTournament(Tournament $tournament): \Illuminate\Database\Eloquent\Collection
    {
        if ($tournament->zone_id) {
            // Torneo zonale: arbitri zona + nazionali
            return User::where('user_type', 'referee')
                      ->where('is_active', true)
                      ->where(function($query) use ($tournament) {
                          $query->where('zone_id', $tournament->zone_id)
                                ->orWhereIn('level', ['nazionale', 'internazionale']);
                      })
                      ->get();
        } else {
            // Torneo nazionale: solo arbitri nazionali
            return User::where('user_type', 'referee')
                      ->where('is_active', true)
                      ->whereIn('level', ['nazionale', 'internazionale'])
                      ->get();
        }
    }
}
