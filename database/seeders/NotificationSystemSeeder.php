<?php
// File: database/seeders/NotificationSystemSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InstitutionalEmail;
use App\Models\LetterTemplate;
use App\Models\Zone;

class NotificationSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding Notification System...');

        // Seed Institutional Emails
        $this->seedInstitutionalEmails();

        // Seed Letter Templates
        $this->seedLetterTemplates();

        $this->command->info('✅ Notification System seeded successfully!');
    }

    /**
     * Seed institutional emails
     */
    private function seedInstitutionalEmails(): void
    {
        $this->command->info('📮 Seeding Institutional Emails...');

        $institutionalEmails = [
            // Federazione
            [
                'name' => 'Federazione Italiana Golf',
                'email' => 'info@federgolf.it',
                'description' => 'Email principale della Federazione Italiana Golf',
                'category' => 'federazione',
                'zone_id' => null,
                'receive_all_notifications' => true,
                'notification_types' => ['assignment', 'convocation', 'institutional'],
                'is_active' => true,
            ],
            [
                'name' => 'Ufficio Campionati FIG',
                'email' => 'campionati@federgolf.it',
                'description' => 'Ufficio Campionati per comunicazioni sui tornei',
                'category' => 'federazione',
                'zone_id' => null,
                'receive_all_notifications' => true,
                'notification_types' => ['assignment', 'convocation'],
                'is_active' => true,
            ],

            // Comitati Regionali
            [
                'name' => 'Comitato Regionale Lazio',
                'email' => 'lazio@federgolf.it',
                'description' => 'Comitato Regionale del Lazio',
                'category' => 'comitati',
                'zone_id' => 1, // Assumendo che esista zona 1
                'receive_all_notifications' => false,
                'notification_types' => ['assignment', 'institutional'],
                'is_active' => true,
            ],
            [
                'name' => 'Comitato Regionale Lombardia',
                'email' => 'lombardia@federgolf.it',
                'description' => 'Comitato Regionale della Lombardia',
                'category' => 'comitati',
                'zone_id' => 2, // Assumendo che esista zona 2
                'receive_all_notifications' => false,
                'notification_types' => ['assignment', 'institutional'],
                'is_active' => true,
            ],

            // Zone
            [
                'name' => 'SZR Zona 1',
                'email' => 'szr1@federgolf.it',
                'description' => 'Sezione Zonale Regole Zona 1',
                'category' => 'zone',
                'zone_id' => 1,
                'receive_all_notifications' => false,
                'notification_types' => ['assignment', 'convocation', 'club'],
                'is_active' => true,
            ],
            [
                'name' => 'SZR Zona 2',
                'email' => 'szr2@federgolf.it',
                'description' => 'Sezione Zonale Regole Zona 2',
                'category' => 'zone',
                'zone_id' => 2,
                'receive_all_notifications' => false,
                'notification_types' => ['assignment', 'convocation', 'club'],
                'is_active' => true,
            ],

            // Convocazioni
            [
                'name' => 'Convocazioni Nazionali',
                'email' => 'convocazioni@federgolf.it',
                'description' => 'Email dedicata alle convocazioni nazionali',
                'category' => 'convocazioni',
                'zone_id' => null,
                'receive_all_notifications' => false,
                'notification_types' => ['convocation'],
                'is_active' => true,
            ],

            // Arbitri
            [
                'name' => 'Coordinamento Arbitri',
                'email' => 'arbitri@federgolf.it',
                'description' => 'Coordinamento nazionale arbitri',
                'category' => 'arbitri',
                'zone_id' => null,
                'receive_all_notifications' => true,
                'notification_types' => ['assignment', 'convocation'],
                'is_active' => true,
            ],
        ];

        foreach ($institutionalEmails as $emailData) {
            // Check if zone exists, otherwise set to null
            if ($emailData['zone_id'] && !Zone::find($emailData['zone_id'])) {
                $emailData['zone_id'] = null;
            }

            InstitutionalEmail::updateOrCreate(
                ['email' => $emailData['email']],
                $emailData
            );
        }

        $this->command->info('✅ Institutional Emails seeded: ' . count($institutionalEmails));
    }

    /**
     * Seed letter templates
     */
    private function seedLetterTemplates(): void
    {
        $this->command->info('📝 Seeding Letter Templates...');

        $templates = [
            // Assignment Templates
            [
                'name' => 'Notifica Assegnazione Standard',
                'type' => 'assignment',
                'subject' => 'Assegnazione Arbitri - {{tournament_name}}',
                'body' => "Gentile {{referee_name}},\n\nLa informiamo che è stato assegnato come {{assignment_role}} per il torneo:\n\n**{{tournament_name}}**\nDate: {{tournament_dates}}\nCircolo: {{club_name}}\nIndirizzo: {{club_address}}\n\nLa convocazione ufficiale verrà inviata dal circolo organizzatore.\n\nPer qualsiasi comunicazione può contattare:\n- Circolo: {{club_email}}\n- Sezione Zonale Regole: szr@federgolf.it\n\nCordiali saluti,\nSezione Zonale Regole",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => true,
                'variables' => ['tournament_name', 'referee_name', 'assignment_role', 'tournament_dates', 'club_name', 'club_address', 'club_email'],
            ],
            [
                'name' => 'Assegnazione Torneo Nazionale',
                'type' => 'assignment',
                'subject' => 'Assegnazione Comitato di Gara - {{tournament_name}} (Nazionale)',
                'body' => "Gentile {{referee_name}},\n\nÈ convocato come {{assignment_role}} per il torneo nazionale:\n\n**{{tournament_name}}**\nCategoria: {{tournament_category}}\nDate: {{tournament_dates}}\nCircolo: {{club_name}}\nZona: {{zone_name}}\n\nLa presente costituisce convocazione ufficiale. È richiesta conferma di partecipazione entro 48 ore.\n\nDettagli logistici:\n- Indirizzo: {{club_address}}\n- Telefono circolo: {{club_phone}}\n- Email circolo: {{club_email}}\n\nPer informazioni:\n- Ufficio Campionati: campionati@federgolf.it\n- Coordinamento Arbitri: arbitri@federgolf.it\n\nDistinti saluti,\nFederazione Italiana Golf",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => false,
                'variables' => ['tournament_name', 'referee_name', 'assignment_role', 'tournament_category', 'tournament_dates', 'club_name', 'zone_name', 'club_address', 'club_phone', 'club_email'],
            ],

            // Club Templates
            [
                'name' => 'Comunicazione Circolo Standard',
                'type' => 'club',
                'subject' => 'Comitato di Gara Assegnato - {{tournament_name}}',
                'body' => "Spett.le {{club_name}},\n\nCon la presente si comunica che per il torneo **{{tournament_name}}** ({{tournament_dates}}) è stato assegnato il seguente Comitato di Gara:\n\n{{assignments_list}}\n\nIl circolo è tenuto a:\n1. Inviare convocazione ufficiale agli arbitri entro 7 giorni dal torneo\n2. Fornire dettagli logistici (orari, dress code, pasti, etc.)\n3. Confermare la ricezione della presente comunicazione\n\nPer conoscenza inviare comunicazioni a:\n- Sezione Zonale Regole: {{zone_email}}\n- Ufficio Campionati: campionati@federgolf.it\n\nRestando a disposizione per chiarimenti.\n\nCordiali saluti,\nSezione Zonale Regole",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => true,
                'variables' => ['club_name', 'tournament_name', 'tournament_dates', 'assignments_list', 'zone_email'],
            ],

            // Institutional Templates
            [
                'name' => 'Comunicazione Istituzionale',
                'type' => 'institutional',
                'subject' => 'Nuova Assegnazione - {{tournament_name}}',
                'body' => "Nuova assegnazione torneo comunicata:\n\n**Torneo:** {{tournament_name}}\n**Date:** {{tournament_dates}}\n**Circolo:** {{club_name}}\n**Zona:** {{zone_name}}\n**Categoria:** {{tournament_category}}\n\n**Comitato di Gara:**\n{{assignments_list}}\n\n**Data assegnazione:** {{assigned_date}}\n\n---\nComunicazione automatica del sistema di gestione arbitri",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => true,
                'variables' => ['tournament_name', 'tournament_dates', 'club_name', 'zone_name', 'tournament_category', 'assignments_list', 'assigned_date'],
            ],

            // Convocation Template
            [
                'name' => 'Convocazione Ufficiale',
                'type' => 'convocation',
                'subject' => 'Convocazione Ufficiale - {{tournament_name}}',
                'body' => "CONVOCAZIONE UFFICIALE\n\nGentile {{referee_name}},\n\nÈ ufficialmente convocato come {{assignment_role}} per:\n\n**{{tournament_name}}**\nDate: {{tournament_dates}}\nCircolo: {{club_name}}\nIndirizzo: {{club_address}}\n\nDETTAGLI CONVOCAZIONE:\n- Presentazione: ore 07:30\n- Dress code: Ufficiale FIG\n- Documenti: Tessera FIG e documento identità\n\nLOGISTICA:\n- Pasti: A carico del circolo\n- Parcheggio: Disponibile presso il circolo\n- Contatto emergenze: {{club_phone}}\n\nCONFERMA:\nÈ richiesta conferma di partecipazione entro 24 ore.\n\nPer informazioni:\n- Circolo: {{club_email}}\n- SZR: szr@federgolf.it\n\nDistinti saluti,\n{{club_name}}",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => true,
                'variables' => ['referee_name', 'assignment_role', 'tournament_name', 'tournament_dates', 'club_name', 'club_address', 'club_phone', 'club_email'],
            ],
        ];

        foreach ($templates as $templateData) {
            LetterTemplate::updateOrCreate(
                [
                    'name' => $templateData['name'],
                    'type' => $templateData['type']
                ],
                $templateData
            );
        }

        $this->command->info('✅ Letter Templates seeded: ' . count($templates));
    }
}
