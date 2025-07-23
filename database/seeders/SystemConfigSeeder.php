<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SystemConfig;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SystemConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('⚙️ Creando Configurazioni Sistema Golf...');

        // Elimina configurazioni esistenti per evitare duplicati
        if (Schema::hasTable('system_configs')) {
            SystemConfig::truncate();
        } else {
            $this->command->warn('⚠️ Tabella system_configs non trovata - saltando seeder');
            return;
        }

        $configs = $this->getSystemConfigurations();
        $created = 0;

        foreach ($configs as $category => $categoryConfigs) {
            $this->command->info("📂 Categoria: {$category}");

            foreach ($categoryConfigs as $configData) {
                $config = SystemConfig::create([
                    'category' => $category,
                    'key' => $configData['key'],
                    'value' => $configData['value'],
                    'description' => $configData['description'],
                    'type' => $configData['type'],
                    'is_public' => $configData['is_public'] ?? false,
                    'is_editable' => $configData['is_editable'] ?? true,
                    'validation_rules' => json_encode($configData['validation'] ?? []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->command->info("  ✅ {$config->key}: {$config->value}");
                $created++;
            }
        }

        $this->command->info("🏆 Configurazioni create con successo: {$created} configurazioni totali");
    }

    /**
     * Definizioni configurazioni sistema
     */
    private function getSystemConfigurations(): array
    {
        return [
            'general' => [
                [
                    'key' => 'system_name',
                    'value' => 'Sistema Gestione Arbitri Golf',
                    'description' => 'Nome del sistema visualizzato nell\'interfaccia',
                    'type' => 'string',
                    'is_public' => true,
                    'is_editable' => true,
                    'validation' => ['required', 'string', 'max:255']
                ],
                [
                    'key' => 'system_version',
                    'value' => '1.0.0',
                    'description' => 'Versione corrente del sistema',
                    'type' => 'string',
                    'is_public' => true,
                    'is_editable' => false,
                    'validation' => ['required', 'regex:/^\d+\.\d+\.\d+$/']
                ],
                [
                    'key' => 'maintenance_mode',
                    'value' => false,
                    'description' => 'Modalità manutenzione del sistema',
                    'type' => 'boolean',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ],
                [
                    'key' => 'default_timezone',
                    'value' => 'Europe/Rome',
                    'description' => 'Fuso orario predefinito del sistema',
                    'type' => 'string',
                    'is_public' => true,
                    'is_editable' => true,
                    'validation' => ['required', 'string', 'timezone']
                ],
                [
                    'key' => 'default_language',
                    'value' => 'it',
                    'description' => 'Lingua predefinita del sistema',
                    'type' => 'string',
                    'is_public' => true,
                    'is_editable' => true,
                    'validation' => ['required', 'string', 'in:it,en']
                ]
            ],

            'tournaments' => [
                [
                    'key' => 'default_availability_deadline_days',
                    'value' => 14,
                    'description' => 'Giorni predefiniti per scadenza disponibilità',
                    'type' => 'integer',
                    'is_public' => true,
                    'is_editable' => true,
                    'validation' => ['required', 'integer', 'min:1', 'max:60']
                ],
                [
                    'key' => 'auto_close_tournaments',
                    'value' => true,
                    'description' => 'Chiusura automatica tornei dopo scadenza',
                    'type' => 'boolean',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ],
                [
                    'key' => 'min_referees_warning_threshold',
                    'value' => 2,
                    'description' => 'Soglia minima arbitri per warning',
                    'type' => 'integer',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'integer', 'min:1']
                ],
                [
                    'key' => 'allow_late_availability',
                    'value' => false,
                    'description' => 'Permetti disponibilità dopo scadenza',
                    'type' => 'boolean',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ],
                [
                    'key' => 'tournament_status_colors',
                    'value' => json_encode([
                        'draft' => '#6B7280',
                        'scheduled' => '#3B82F6',
                        'open' => '#10B981',
                        'closed' => '#F59E0B',
                        'assigned' => '#8B5CF6',
                        'completed' => '#6B7280',
                        'cancelled' => '#EF4444'
                    ]),
                    'description' => 'Colori per stati tornei nell\'interfaccia',
                    'type' => 'json',
                    'is_public' => true,
                    'is_editable' => true,
                    'validation' => ['required', 'json']
                ]
            ],

            'referees' => [
                [
                    'key' => 'referee_levels',
                    'value' => json_encode([
                        'aspirante' => 'Aspirante',
                        'primo_livello' => 'Primo Livello',
                        'regionale' => 'Regionale',
                        'nazionale' => 'Nazionale',
                        'internazionale' => 'Internazionale'
                    ]),
                    'description' => 'Livelli arbitro disponibili nel sistema',
                    'type' => 'json',
                    'is_public' => true,
                    'is_editable' => true,
                    'validation' => ['required', 'json']
                ],
                [
                    'key' => 'auto_generate_referee_codes',
                    'value' => true,
                    'description' => 'Generazione automatica codici arbitro',
                    'type' => 'boolean',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ],
                [
                    'key' => 'referee_code_format',
                    'value' => '{zone}-REF-{sequence:3}',
                    'description' => 'Formato per codici arbitro generati',
                    'type' => 'string',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'string']
                ],
                [
                    'key' => 'require_annual_course',
                    'value' => true,
                    'description' => 'Richiedi corso annuale per arbitri',
                    'type' => 'boolean',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ],
                [
                    'key' => 'max_assignments_per_month',
                    'value' => 8,
                    'description' => 'Massimo assegnazioni per arbitro al mese',
                    'type' => 'integer',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'integer', 'min:1', 'max:31']
                ]
            ],

            'notifications' => [
                [
                    'key' => 'send_assignment_emails',
                    'value' => true,
                    'description' => 'Invio email per nuove assegnazioni',
                    'type' => 'boolean',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ],
                [
                    'key' => 'send_deadline_reminders',
                    'value' => true,
                    'description' => 'Invio promemoria scadenze',
                    'type' => 'boolean',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ],
                [
                    'key' => 'reminder_days_before_deadline',
                    'value' => json_encode([7, 3, 1]),
                    'description' => 'Giorni prima della scadenza per promemoria',
                    'type' => 'json',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'json']
                ],
                [
                    'key' => 'notification_from_email',
                    'value' => 'noreply@golf.it',
                    'description' => 'Email mittente per notifiche',
                    'type' => 'string',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'email']
                ],
                [
                    'key' => 'notification_from_name',
                    'value' => 'Sistema Golf',
                    'description' => 'Nome mittente per notifiche',
                    'type' => 'string',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'string', 'max:100']
                ]
            ],

            'security' => [
                [
                    'key' => 'max_login_attempts',
                    'value' => 5,
                    'description' => 'Tentativi login massimi prima del blocco',
                    'type' => 'integer',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'integer', 'min:3', 'max:10']
                ],
                [
                    'key' => 'lockout_duration_minutes',
                    'value' => 30,
                    'description' => 'Durata blocco account in minuti',
                    'type' => 'integer',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'integer', 'min:5', 'max:1440']
                ],
                [
                    'key' => 'require_2fa_for_admins',
                    'value' => false,
                    'description' => 'Richiedi 2FA per amministratori',
                    'type' => 'boolean',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ],
                [
                    'key' => 'session_lifetime_minutes',
                    'value' => 480,
                    'description' => 'Durata sessione in minuti (8 ore)',
                    'type' => 'integer',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'integer', 'min:60', 'max:1440']
                ],
                [
                    'key' => 'audit_sensitive_actions',
                    'value' => true,
                    'description' => 'Audit log per azioni sensibili',
                    'type' => 'boolean',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ]
            ],

            'ui' => [
                [
                    'key' => 'items_per_page',
                    'value' => 25,
                    'description' => 'Elementi per pagina nelle liste',
                    'type' => 'integer',
                    'is_public' => true,
                    'is_editable' => true,
                    'validation' => ['required', 'integer', 'min:10', 'max:100']
                ],
                [
                    'key' => 'date_format',
                    'value' => 'd/m/Y',
                    'description' => 'Formato data nell\'interfaccia',
                    'type' => 'string',
                    'is_public' => true,
                    'is_editable' => true,
                    'validation' => ['required', 'string']
                ],
                [
                    'key' => 'datetime_format',
                    'value' => 'd/m/Y H:i',
                    'description' => 'Formato data/ora nell\'interfaccia',
                    'type' => 'string',
                    'is_public' => true,
                    'is_editable' => true,
                    'validation' => ['required', 'string']
                ],
                [
                    'key' => 'enable_dark_mode',
                    'value' => true,
                    'description' => 'Abilita modalità scura',
                    'type' => 'boolean',
                    'is_public' => true,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ],
                [
                    'key' => 'show_zone_in_title',
                    'value' => true,
                    'description' => 'Mostra zona nel titolo pagina',
                    'type' => 'boolean',
                    'is_public' => true,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ]
            ],

            'api' => [
                [
                    'key' => 'api_rate_limit_per_minute',
                    'value' => 60,
                    'description' => 'Limite richieste API per minuto',
                    'type' => 'integer',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'integer', 'min:10', 'max:1000']
                ],
                [
                    'key' => 'api_timeout_seconds',
                    'value' => 30,
                    'description' => 'Timeout per richieste API',
                    'type' => 'integer',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'integer', 'min:5', 'max:300']
                ],
                [
                    'key' => 'enable_api_documentation',
                    'value' => true,
                    'description' => 'Abilita documentazione API pubblica',
                    'type' => 'boolean',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ],
                [
                    'key' => 'api_version',
                    'value' => 'v1',
                    'description' => 'Versione corrente API',
                    'type' => 'string',
                    'is_public' => true,
                    'is_editable' => false,
                    'validation' => ['required', 'string']
                ]
            ],

            'backup' => [
                [
                    'key' => 'auto_backup_enabled',
                    'value' => true,
                    'description' => 'Backup automatico abilitato',
                    'type' => 'boolean',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ],
                [
                    'key' => 'backup_frequency_hours',
                    'value' => 24,
                    'description' => 'Frequenza backup in ore',
                    'type' => 'integer',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'integer', 'min:1', 'max:168']
                ],
                [
                    'key' => 'backup_retention_days',
                    'value' => 30,
                    'description' => 'Giorni di conservazione backup',
                    'type' => 'integer',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['required', 'integer', 'min:7', 'max:365']
                ],
                [
                    'key' => 'backup_compress',
                    'value' => true,
                    'description' => 'Comprimi file di backup',
                    'type' => 'boolean',
                    'is_public' => false,
                    'is_editable' => true,
                    'validation' => ['boolean']
                ]
            ]
        ];
    }
}
