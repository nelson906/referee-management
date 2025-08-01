<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\TournamentNotification;
use App\Models\Tournament;
use App\Models\User;

/**
 * 🏆 Factory per TournamentNotification
 */
class TournamentNotificationFactory extends Factory
{
    protected $model = TournamentNotification::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['sent', 'partial', 'failed', 'pending']);

        // Genera dettagli realistici basati sullo stato
        $details = $this->generateRealisticDetails($status);

        return [
            'tournament_id' => Tournament::factory(),
            'status' => $status,
            'total_recipients' => $details['total'],
            'sent_at' => $status === 'pending' ? null : $this->faker->dateTimeBetween('-30 days', 'now'),
            'sent_by' => User::factory(),
            'details' => $details['breakdown'],
            'templates_used' => [
                'club' => $this->faker->randomElement([
                    'club_assignment_standard',
                    'club_assignment_detailed',
                    'club_assignment_minimal'
                ]),
                'referee' => $this->faker->randomElement([
                    'referee_assignment_formal',
                    'referee_assignment_friendly',
                    'referee_assignment_detailed'
                ]),
                'institutional' => $this->faker->randomElement([
                    'institutional_report_standard',
                    'institutional_report_detailed',
                    'institutional_report_minimal'
                ])
            ],
            'error_message' => $status === 'failed' ? $this->faker->sentence() : null
        ];
    }

    /**
     * 📊 Genera dettagli realistici
     */
    private function generateRealisticDetails(string $status): array
    {
        $clubSent = $this->faker->numberBetween(0, 1);
        $clubFailed = $status === 'failed' && $clubSent === 0 ? 1 : 0;

        $refereesSent = $this->faker->numberBetween(0, 5);
        $refereesFailed = $status === 'failed' ? $this->faker->numberBetween(0, 2) : 0;

        $institutionalSent = $this->faker->numberBetween(2, 3);
        $institutionalFailed = $status === 'failed' ? $this->faker->numberBetween(0, 1) : 0;

        if ($status === 'sent') {
            $refereesFailed = 0;
            $clubFailed = 0;
            $institutionalFailed = 0;
        } elseif ($status === 'partial') {
            // Almeno alcuni successi e alcuni fallimenti
            if ($refereesFailed === 0 && $clubFailed === 0 && $institutionalFailed === 0) {
                $refereesFailed = 1;
            }
        }

        $total = $clubSent + $clubFailed + $refereesSent + $refereesFailed + $institutionalSent + $institutionalFailed;

        return [
            'total' => $total,
            'breakdown' => [
                'club' => [
                    'sent' => $clubSent,
                    'failed' => $clubFailed,
                    'errors' => $clubFailed > 0 ? [$this->faker->sentence()] : []
                ],
                'referees' => [
                    'sent' => $refereesSent,
                    'failed' => $refereesFailed,
                    'errors' => $refereesFailed > 0 ? array_fill(0, $refereesFailed, $this->faker->sentence()) : []
                ],
                'institutional' => [
                    'sent' => $institutionalSent,
                    'failed' => $institutionalFailed,
                    'errors' => $institutionalFailed > 0 ? [$this->faker->sentence()] : []
                ]
            ]
        ];
    }

    /**
     * ✅ State: Notifica inviata con successo
     */
    public function sent(): static
    {
        return $this->state(function (array $attributes) {
            $details = $this->generateRealisticDetails('sent');

            return [
                'status' => 'sent',
                'total_recipients' => $details['total'],
                'details' => $details['breakdown'],
                'sent_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
                'error_message' => null
            ];
        });
    }

    /**
     * ❌ State: Notifica fallita
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $details = $this->generateRealisticDetails('failed');

            return [
                'status' => 'failed',
                'total_recipients' => $details['total'],
                'details' => $details['breakdown'],
                'sent_at' => $this->faker->dateTimeBetween('-3 days', 'now'),
                'error_message' => $this->faker->randomElement([
                    'SMTP connection failed',
                    'Invalid email address',
                    'Rate limit exceeded',
                    'Template rendering error',
                    'Database connection timeout'
                ])
            ];
        });
    }

    /**
     * ⚠️ State: Notifica parzialmente inviata
     */
    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            $details = $this->generateRealisticDetails('partial');

            return [
                'status' => 'partial',
                'total_recipients' => $details['total'],
                'details' => $details['breakdown'],
                'sent_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
                'error_message' => null
            ];
        });
    }

    /**
     * ⏳ State: Notifica in attesa
     */
    public function pending(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'total_recipients' => 0,
                'details' => null,
                'sent_at' => null,
                'error_message' => null
            ];
        });
    }

    /**
     * 📅 State: Notifica di oggi
     */
    public function today(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'sent_at' => $this->faker->dateTimeBetween('today', 'now')
            ];
        });
    }

    /**
     * 📅 State: Notifica di questa settimana
     */
    public function thisWeek(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'sent_at' => $this->faker->dateTimeBetween('monday this week', 'now')
            ];
        });
    }

    /**
     * 📊 State: Con statistiche realistiche
     */
    public function withRealisticStats(): static
    {
        return $this->state(function (array $attributes) {
            // Crea statistiche che sembrano reali
            $clubSent = 1;
            $refereesSent = $this->faker->numberBetween(2, 6);
            $institutionalSent = 3;
            $total = $clubSent + $refereesSent + $institutionalSent;

            return [
                'total_recipients' => $total,
                'details' => [
                    'club' => ['sent' => $clubSent, 'failed' => 0, 'errors' => []],
                    'referees' => ['sent' => $refereesSent, 'failed' => 0, 'errors' => []],
                    'institutional' => ['sent' => $institutionalSent, 'failed' => 0, 'errors' => []]
                ]
            ];
        });
    }
}

/**
 * 📧 Factory aggiornata per Notification (integra nuovo sistema)
 */
class NotificationFactory extends Factory
{
    protected $model = \App\Models\Notification::class;

    public function definition(): array
    {
        $recipientType = $this->faker->randomElement(['club', 'referee', 'institutional']);

        return [
            'assignment_id' => \App\Models\Assignment::factory(),
            'tournament_id' => \App\Models\Tournament::factory(), // NUOVO: supporto nuovo sistema
            'recipient_type' => $recipientType,
            'recipient_email' => $this->faker->safeEmail(),
            'recipient_name' => $this->getRecipientName($recipientType), // NUOVO
            'subject' => $this->getRealisticSubject($recipientType),
            'body' => $this->getRealisticBody($recipientType),
            'template_used' => $this->getTemplateForType($recipientType),
            'status' => $this->faker->randomElement(['sent', 'failed', 'pending']),
            'sent_at' => $this->faker->optional(0.8)->dateTimeBetween('-30 days', 'now'),
            'error_message' => $this->faker->optional(0.2)->sentence(),
            'retry_count' => $this->faker->numberBetween(0, 3),
            'attachments' => $this->getAttachmentsForType($recipientType) // NUOVO
        ];
    }

    private function getRecipientName(string $type): string
    {
        return match($type) {
            'club' => $this->faker->company() . ' Golf Club',
            'referee' => $this->faker->name(),
            'institutional' => $this->faker->randomElement([
                'CRC Nazionale',
                'Sezione Zonale Regole',
                'Delegato Zona',
                'Ufficio Campionati'
            ]),
            default => $this->faker->name()
        };
    }

    private function getRealisticSubject(string $type): string
    {
        $tournamentName = $this->faker->randomElement([
            'Trofeo Regionale 2025',
            'Campionato Zonale',
            'Gara Nazionale 36/36',
            'Torneo di Primavera',
            'Memorial John Smith'
        ]);

        return match($type) {
            'club' => "Assegnazione Arbitri - {$tournamentName}",
            'referee' => "Convocazione Ufficiale - {$tournamentName}",
            'institutional' => "Nuova Assegnazione - {$tournamentName}",
            default => "Notifica - {$tournamentName}"
        };
    }

    private function getRealisticBody(string $type): string
    {
        return match($type) {
            'club' => "Gentile Circolo,\n\nVi comunichiamo gli arbitri assegnati per il torneo...\n\nCordiali saluti,\nSezione Zonale Regole",
            'referee' => "Gentile Arbitro,\n\nÈ convocato come Direttore di Torneo per...\n\nCordiali saluti,\nSZR",
            'institutional' => "Nuova assegnazione torneo comunicata:\n\nTorneo: ...\nArbitri: ...\n\n---\nSistema automatico",
            default => $this->faker->paragraphs(2, true)
        };
    }

    private function getTemplateForType(string $type): string
    {
        return match($type) {
            'club' => $this->faker->randomElement([
                'club_assignment_standard',
                'club_assignment_detailed',
                'club_assignment_minimal'
            ]),
            'referee' => $this->faker->randomElement([
                'referee_assignment_formal',
                'referee_assignment_friendly',
                'referee_assignment_detailed'
            ]),
            'institutional' => $this->faker->randomElement([
                'institutional_report_standard',
                'institutional_report_detailed',
                'institutional_report_minimal'
            ]),
            default => 'default_template'
        };
    }

    private function getAttachmentsForType(string $type): ?array
    {
        return match($type) {
            'club' => [
                ['name' => 'Convocazione_SZR.pdf', 'path' => 'attachments/convocazioni/szr.pdf', 'size' => 245760],
                ['name' => 'Facsimile_Convocazione.pdf', 'path' => 'attachments/templates/facsimile.pdf', 'size' => 189440]
            ],
            'referee' => [
                ['name' => 'Convocazione_Ufficiale.pdf', 'path' => 'attachments/convocazioni/referee_123.pdf', 'size' => 156672]
            ],
            'institutional' => null,
            default => null
        };
    }

    /**
     * 🏌️ State: Notifica per circolo
     */
    public function forClub(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'recipient_type' => 'club',
                'recipient_email' => $this->faker->companyEmail(),
                'recipient_name' => $this->faker->company() . ' Golf Club',
                'subject' => 'Assegnazione Arbitri - ' . $this->faker->words(3, true),
                'template_used' => 'club_assignment_standard',
                'attachments' => [
                    ['name' => 'Convocazione_SZR.pdf', 'path' => 'attachments/convocazioni/szr.pdf'],
                    ['name' => 'Facsimile_Convocazione.pdf', 'path' => 'attachments/templates/facsimile.pdf']
                ]
            ];
        });
    }

    /**
     * ⚖️ State: Notifica per arbitro
     */
    public function forReferee(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'recipient_type' => 'referee',
                'recipient_email' => $this->faker->safeEmail(),
                'recipient_name' => $this->faker->name(),
                'subject' => 'Convocazione Ufficiale - ' . $this->faker->words(3, true),
                'template_used' => 'referee_assignment_formal',
                'attachments' => [
                    ['name' => 'Convocazione_Ufficiale.pdf', 'path' => 'attachments/convocazioni/referee.pdf']
                ]
            ];
        });
    }

    /**
     * 🏛️ State: Notifica istituzionale
     */
    public function institutional(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'recipient_type' => 'institutional',
                'recipient_email' => $this->faker->randomElement([
                    'crc@federgolf.it',
                    'szr@federgolf.it',
                    'delegato@federgolf.it'
                ]),
                'recipient_name' => $this->faker->randomElement([
                    'CRC Nazionale',
                    'Sezione Zonale Regole',
                    'Delegato Zona'
                ]),
                'subject' => 'Nuova Assegnazione - ' . $this->faker->words(3, true),
                'template_used' => 'institutional_report_standard',
                'attachments' => null
            ];
        });
    }

    /**
     * ✅ State: Notifica inviata
     */
    public function sent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'sent',
                'sent_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
                'error_message' => null,
                'retry_count' => 0
            ];
        });
    }

    /**
     * ❌ State: Notifica fallita
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'failed',
                'sent_at' => null,
                'error_message' => $this->faker->randomElement([
                    'SMTP Error: Could not connect to server',
                    'Invalid email address format',
                    'Message rejected: spam detected',
                    'Connection timeout',
                    'Rate limit exceeded'
                ]),
                'retry_count' => $this->faker->numberBetween(1, 3)
            ];
        });
    }

    /**
     * 🆕 State: Nuovo sistema (con tournament_id)
     */
    public function newSystem(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'tournament_id' => \App\Models\Tournament::factory(),
                'recipient_name' => $this->faker->name()
            ];
        });
    }

    /**
     * 🕰️ State: Sistema legacy (senza tournament_id)
     */
    public function legacy(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'tournament_id' => null,
                'recipient_name' => null,
                'attachments' => null
            ];
        });
    }
}

/**
 * 🏆 Factory aggiornata per Tournament (con supporto notifiche)
 */
class TournamentFactory extends Factory
{
    protected $model = \App\Models\Tournament::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+6 months');
        $endDate = $this->faker->dateTimeBetween($startDate, $startDate->format('Y-m-d H:i:s') . ' +7 days');

        return [
            'name' => $this->generateTournamentName(),
            'description' => $this->faker->paragraphs(2, true),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'club_id' => \App\Models\Club::factory(),
            'zone_id' => \App\Models\Zone::factory(),
            'tournament_type_id' => \App\Models\TournamentType::factory(),
            'status' => $this->faker->randomElement(['draft', 'open', 'closed', 'assigned', 'completed']),
            'max_participants' => $this->faker->numberBetween(50, 200),
            'registration_deadline' => $this->faker->dateTimeBetween('now', $startDate),
            'notes' => $this->faker->optional(0.3)->paragraph()
        ];
    }

    private function generateTournamentName(): string
    {
        $prefixes = ['Trofeo', 'Memorial', 'Coppa', 'Campionato', 'Gara'];
        $suffixes = ['Regionale', 'Zonale', 'Nazionale', 'di Primavera', 'Estivo', 'Autunnale'];
        $years = ['2025', '2026'];

        $prefix = $this->faker->randomElement($prefixes);
        $suffix = $this->faker->randomElement($suffixes);
        $year = $this->faker->randomElement($years);

        return "{$prefix} {$suffix} {$year}";
    }

    /**
     * 📧 State: Pronto per notifiche
     */
    public function readyForNotification(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => $this->faker->randomElement(['closed', 'assigned']),
                'start_date' => $this->faker->dateTimeBetween('+1 week', '+2 months')
            ];
        })->has(\App\Models\Assignment::factory()->count(3), 'assignments')
          ->for(\App\Models\Club::factory()->state(['email' => $this->faker->companyEmail()]), 'club');
    }

    /**
     * 📧 State: Già notificato
     */
    public function alreadyNotified(): static
    {
        return $this->readyForNotification()
               ->has(TournamentNotification::factory()->sent(), 'notifications');
    }

    /**
     * ⚠️ State: Con problemi (non può essere notificato)
     */
    public function withIssues(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'draft' // Stato che impedisce notifiche
            ];
        })->for(\App\Models\Club::factory()->state(['email' => null]), 'club'); // Club senza email
    }

    /**
     * 📅 State: Prossimi tornei
     */
    public function upcoming(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'start_date' => $this->faker->dateTimeBetween('+1 day', '+3 months'),
                'end_date' => $this->faker->dateTimeBetween('+1 day', '+3 months'),
                'status' => $this->faker->randomElement(['open', 'closed', 'assigned'])
            ];
        });
    }

    /**
     * 📊 State: Con statistiche complete
     */
    public function withFullData(): static
    {
        return $this->has(\App\Models\Assignment::factory()->count(4), 'assignments')
               ->has(\App\Models\Availability::factory()->count(8), 'availabilities')
               ->has(TournamentNotification::factory()->sent(), 'notifications');
    }
}
