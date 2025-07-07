<?php

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
                'value' => '1.0.0',
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
                'key' => 'session_lifetime',
                'value' => '120',
                'type' => 'integer',
                'description' => 'Durata della sessione in minuti',
                'group' => 'application',
                'is_public' => false,
                'is_editable' => true,
            ],

            // Mail Settings
            [
                'key' => 'mail_driver',
                'value' => 'smtp',
                'type' => 'string',
                'description' => 'Driver per l\'invio email',
                'group' => 'mail',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'mail_host',
                'value' => 'localhost',
                'type' => 'string',
                'description' => 'Host SMTP',
                'group' => 'mail',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'mail_port',
                'value' => '587',
                'type' => 'integer',
                'description' => 'Porta SMTP',
                'group' => 'mail',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'mail_username',
                'value' => '',
                'type' => 'string',
                'description' => 'Username SMTP',
                'group' => 'mail',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'mail_encryption',
                'value' => 'tls',
                'type' => 'string',
                'description' => 'Tipo di crittografia SMTP',
                'group' => 'mail',
                'is_public' => false,
                'is_editable' => true,
            ],
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

            // System Settings
            [
                'key' => 'system_debug',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Modalità debug',
                'group' => 'system',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'system_maintenance',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Modalità manutenzione',
                'group' => 'system',
                'is_public' => true,
                'is_editable' => true,
            ],
            [
                'key' => 'cache_driver',
                'value' => 'file',
                'type' => 'string',
                'description' => 'Driver cache',
                'group' => 'system',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'log_level',
                'value' => 'info',
                'type' => 'string',
                'description' => 'Livello di logging',
                'group' => 'system',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'api_rate_limit',
                'value' => '1000',
                'type' => 'integer',
                'description' => 'Limite richieste API per ora',
                'group' => 'system',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'max_upload_size',
                'value' => '10',
                'type' => 'integer',
                'description' => 'Dimensione massima upload in MB',
                'group' => 'system',
                'is_public' => false,
                'is_editable' => true,
            ],

            // Backup Settings
            [
                'key' => 'backup_enabled',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Backup automatici abilitati',
                'group' => 'backup',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'backup_frequency',
                'value' => 'daily',
                'type' => 'string',
                'description' => 'Frequenza backup automatici',
                'group' => 'backup',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'backup_retention_days',
                'value' => '30',
                'type' => 'integer',
                'description' => 'Giorni di conservazione backup',
                'group' => 'backup',
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
                'key' => 'tournament_notification_days',
                'value' => '3',
                'type' => 'integer',
                'description' => 'Giorni prima per notifica reminder arbitri',
                'group' => 'tournaments',
                'is_public' => false,
                'is_editable' => true,
            ],

            // Notification Settings
            [
                'key' => 'notifications_email_enabled',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Notifiche email abilitate',
                'group' => 'notifications',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'notifications_sms_enabled',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Notifiche SMS abilitate',
                'group' => 'notifications',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'notifications_auto_assignment',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Notifica automatica assegnazioni',
                'group' => 'notifications',
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
                'key' => 'security_lockout_duration',
                'value' => '15',
                'type' => 'integer',
                'description' => 'Durata blocco account in minuti',
                'group' => 'security',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'security_2fa_enabled',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Autenticazione a due fattori',
                'group' => 'security',
                'is_public' => false,
                'is_editable' => true,
            ],

            // Integration Settings
            [
                'key' => 'integration_calendar_sync',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Sincronizzazione calendario esterno',
                'group' => 'integrations',
                'is_public' => false,
                'is_editable' => true,
            ],
            [
                'key' => 'integration_handicap_system',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Integrazione sistema handicap',
                'group' => 'integrations',
                'is_public' => false,
                'is_editable' => true,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
