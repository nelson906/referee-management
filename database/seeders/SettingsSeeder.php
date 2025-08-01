<?php

/**
 * ========================================
 * SettingsSeeder.php - VERSIONE AGGIORNATA
 * ========================================
 * Aggiornato per coerenza con la nuova struttura
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('⚙️ Seeding application settings...');

        $settings = [
            // Application Settings
            [
                'key' => 'app_name',
                'value' => 'Golf Referee System',
                'type' => 'string',
                'description' => 'Nome dell\'applicazione',
                'group' => 'application',
                'is_public' => true,
                'is_editable' => true,
            ],
            [
                'key' => 'app_version',
                'value' => '2.0.0',
                'type' => 'string',
                'description' => 'Versione dell\'applicazione',
                'group' => 'application',
                'is_public' => true,
                'is_editable' => false,
            ],
            [
                'key' => 'app_timezone',
                'value' => 'Europe/Rome',
                'type' => 'string',
                'description' => 'Timezone dell\'applicazione',
                'group' => 'application',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'app_locale',
                'value' => 'it',
                'type' => 'string',
                'description' => 'Lingua dell\'applicazione',
                'group' => 'application',
                'is_public' => true,
                'is_editable' => true,
            ],
            [
                'key' => 'app_environment',
                'value' => 'production',
                'type' => 'string',
                'description' => 'Ambiente applicazione (development/production)',
                'group' => 'application',
                'is_public' => false,
                'is_editable' => true,
            ],

            // Mail Settings
            [
                'key' => 'mail_from_address',
                'value' => 'noreply@golfreferee.it',
                'type' => 'string',
                'description' => 'Indirizzo email mittente',
                'group' => 'mail',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'mail_from_name',
                'value' => 'Golf Referee System',
                'type' => 'string',
                'description' => 'Nome mittente email',
                'group' => 'mail',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'mail_reply_to',
                'value' => 'support@golfreferee.it',
                'type' => 'string',
                'description' => 'Indirizzo email per risposte',
                'group' => 'mail',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'mail_notifications_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Abilita notifiche email',
                'group' => 'mail',
                'is_public' => false,
                'is_editable' => true,
            ],

            // Tournament Settings
            [
                'key' => 'tournament_auto_close_days',
                'value' => '7',
                'type' => 'integer',
                'description' => 'Giorni prima dell\'evento per chiusura automatica disponibilità',
                'group' => 'tournaments',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'tournament_min_referees',
                'value' => '1',
                'type' => 'integer',
                'description' => 'Numero minimo di arbitri per torneo',
                'group' => 'tournaments',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'tournament_max_referees',
                'value' => '10',
                'type' => 'integer',
                'description' => 'Numero massimo di arbitri per torneo',
                'group' => 'tournaments',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'tournament_assignment_deadline_days',
                'value' => '3',
                'type' => 'integer',
                'description' => 'Giorni prima dell\'evento per completare assegnazioni',
                'group' => 'tournaments',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'tournament_reminder_days',
                'value' => '14,7,3',
                'type' => 'string',
                'description' => 'Giorni prima per invio promemoria (separati da virgola)',
                'group' => 'tournaments',
                'is_public' => false,
                'is_editable' => true,
            ],

            // Referee Settings
            [
                'key' => 'referee_levels',
                'value' => json_encode(['aspirante', 'primo_livello', 'regionale', 'nazionale', 'internazionale']),
                'type' => 'json',
                'description' => 'Livelli arbitri disponibili',
                'group' => 'referees',
                'is_public' => true,
                'is_editable' => false,
            ],
            [
                'key' => 'referee_categories',
                'value' => json_encode(['maschile', 'femminile', 'misto']),
                'type' => 'json',
                'description' => 'Categorie arbitri disponibili',
                'group' => 'referees',
                'is_public' => true,
                'is_editable' => false,
            ],
            [
                'key' => 'referee_code_prefix',
                'value' => 'ARB',
                'type' => 'string',
                'description' => 'Prefisso per codici arbitro',
                'group' => 'referees',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'referee_renewal_period_years',
                'value' => '3',
                'type' => 'integer',
                'description' => 'Anni di validità qualifica arbitro',
                'group' => 'referees',
                'is_public' => false,
                'is_editable' => true,
            ],

            // Security Settings
            [
                'key' => 'security_password_min_length',
                'value' => '8',
                'type' => 'integer',
                'description' => 'Lunghezza minima password',
                'group' => 'security',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'security_login_attempts',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Tentativi di login massimi',
                'group' => 'security',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'security_session_timeout',
                'value' => '120',
                'type' => 'integer',
                'description' => 'Timeout sessione in minuti',
                'group' => 'security',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'security_two_factor_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Abilita autenticazione a due fattori',
                'group' => 'security',
                'is_public' => false,
                'is_editable' => true,
            ],

            // System Settings
            [
                'key' => 'system_maintenance_mode',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Modalità manutenzione',
                'group' => 'system',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'system_debug_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Abilita modalità debug',
                'group' => 'system',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'system_backup_frequency',
                'value' => 'daily',
                'type' => 'string',
                'description' => 'Frequenza backup automatico',
                'group' => 'system',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'system_log_level',
                'value' => 'warning',
                'type' => 'string',
                'description' => 'Livello di logging (debug/info/warning/error)',
                'group' => 'system',
                'is_public' => false,
                'is_editable' => true,
            ],

            // UI Settings
            [
                'key' => 'ui_items_per_page',
                'value' => '25',
                'type' => 'integer',
                'description' => 'Elementi per pagina nelle liste',
                'group' => 'ui',
                'is_public' => true,
                'is_editable' => true,
            ],
            [
                'key' => 'ui_date_format',
                'value' => 'd/m/Y',
                'type' => 'string',
                'description' => 'Formato data visualizzazione',
                'group' => 'ui',
                'is_public' => true,
                'is_editable' => true,
            ],
            [
                'key' => 'ui_datetime_format',
                'value' => 'd/m/Y H:i',
                'type' => 'string',
                'description' => 'Formato data e ora visualizzazione',
                'group' => 'ui',
                'is_public' => true,
                'is_editable' => true,
            ],
            [
                'key' => 'ui_theme',
                'value' => 'default',
                'type' => 'string',
                'description' => 'Tema interfaccia utente',
                'group' => 'ui',
                'is_public' => true,
                'is_editable' => true,
            ],

            // Notification Settings
            [
                'key' => 'notifications_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Abilita sistema notifiche',
                'group' => 'notifications',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'notifications_channels',
                'value' => json_encode(['email', 'database']),
                'type' => 'json',
                'description' => 'Canali notifica disponibili',
                'group' => 'notifications',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'notifications_default_channel',
                'value' => 'email',
                'type' => 'string',
                'description' => 'Canale notifica predefinito',
                'group' => 'notifications',
                'is_public' => false,
                'is_editable' => true,
            ],

            // API Settings
            [
                'key' => 'api_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Abilita API REST',
                'group' => 'api',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'api_rate_limit',
                'value' => '60',
                'type' => 'integer',
                'description' => 'Limite richieste API per minuto',
                'group' => 'api',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'api_version',
                'value' => 'v1',
                'type' => 'string',
                'description' => 'Versione API attuale',
                'group' => 'api',
                'is_public' => false,
                'is_editable' => false,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('✅ Application settings created successfully (' . count($settings) . ' settings)');

        // Log dei gruppi creati
        $groups = collect($settings)->pluck('group')->unique()->sort();
        $this->command->info('📋 Setting groups: ' . $groups->implode(', '));
    }
}
