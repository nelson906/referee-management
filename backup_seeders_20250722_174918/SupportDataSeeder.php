<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Zone;
use App\Models\TournamentType;
use App\Models\InstitutionalEmail;
use App\Models\LetterTemplate;
use App\Models\Letterhead;

class SupportDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔧 Creazione dati di supporto...');

        $this->createInstitutionalEmails();
        $this->createLetterTemplates();
        $this->createLetterheads();

        $this->command->info('✅ Dati di supporto creati con successo');
    }

    /**
     * Create institutional emails
     */
    private function createInstitutionalEmails()
    {
        $zones = Zone::all();

        // Federazione nazionale
        InstitutionalEmail::updateOrCreate([
            'email' => 'segreteria@figc.it'
        ], [
            'name' => 'Segreteria Federale',
            'email' => 'segreteria@figc.it',
            'description' => 'Segreteria generale della federazione',
            'is_active' => true,
            'zone_id' => null, // Nazionale
            'category' => 'federazione',
            'receive_all_notifications' => true,
            'notification_types' => json_encode([]),
        ]);

        InstitutionalEmail::updateOrCreate([
            'email' => 'crc@figc.it'
        ], [
            'name' => 'Commissione Regole e Competizioni',
            'email' => 'crc@figc.it',
            'description' => 'Commissione tecnica nazionale',
            'is_active' => true,
            'zone_id' => null,
            'category' => 'committee',
            'receive_all_notifications' => true,
            'notification_types' => json_encode([]),
        ]);

        // Email di zona
        foreach ($zones as $zone) {
            InstitutionalEmail::updateOrCreate([
                'email' => strtolower($zone->code) . '@figc.it'
            ], [
                'name' => 'Segreteria ' . $zone->name,
                'email' => strtolower($zone->code) . '@figc.it',
                'description' => 'Segreteria territoriale ' . $zone->name,
                'is_active' => true,
                'zone_id' => $zone->id,
                'category' => 'zona',
                'receive_all_notifications' => false,
                'notification_types' => json_encode(['assignment', 'availability', 'tournament_created']),
            ]);
        }
    }

    /**
     * Create letter templates
     */
    private function createLetterTemplates()
    {
        $zones = Zone::all();
        $tournamentTypes = TournamentType::all();

        // Template nazionale per convocazione
        LetterTemplate::updateOrCreate([
            'name' => 'Convocazione Arbitro - Template Nazionale'
        ], [
            'name' => 'Convocazione Arbitro - Template Nazionale',
            'type' => 'convocation',
            'subject' => 'Convocazione per {{tournament_name}}',
            'body' => $this->getConvocationTemplate(),
            'zone_id' => null, // Template nazionale
            'tournament_type_id' => null, // Per tutti i tipi
            'is_active' => true,
            'variables' => json_encode([
                'referee_name', 'tournament_name', 'tournament_dates',
                'club_name', 'role', 'zone_name'
            ]),
            'description' => 'Template standard per convocazione arbitri',
            'settings' => json_encode([
                'auto_send' => false,
                'require_confirmation' => true,
            ]),
        ]);

        // Template per comunicazione al circolo
        LetterTemplate::updateOrCreate([
            'name' => 'Comunicazione Circolo - Arbitri Assegnati'
        ], [
            'name' => 'Comunicazione Circolo - Arbitri Assegnati',
            'type' => 'club',
            'subject' => 'Arbitri assegnati per {{tournament_name}}',
            'body' => $this->getClubTemplate(),
            'zone_id' => null,
            'tournament_type_id' => null,
            'is_active' => true,
            'variables' => json_encode([
                'tournament_name', 'tournament_dates', 'club_name',
                'referee_list', 'total_referees', 'contact_person'
            ]),
            'description' => 'Template per comunicare al circolo gli arbitri assegnati',
            'settings' => json_encode([
                'auto_send' => true,
                'include_referee_contacts' => true,
            ]),
        ]);

        // Template per notifica assegnazione
        LetterTemplate::updateOrCreate([
            'name' => 'Notifica Assegnazione - Template Standard'
        ], [
            'name' => 'Notifica Assegnazione - Template Standard',
            'type' => 'assignment',
            'subject' => 'Nuova assegnazione: {{tournament_name}}',
            'body' => $this->getAssignmentTemplate(),
            'zone_id' => null,
            'tournament_type_id' => null,
            'is_active' => true,
            'variables' => json_encode([
                'referee_name', 'tournament_name', 'tournament_dates',
                'club_name', 'role', 'assigned_by', 'assignment_notes'
            ]),
            'description' => 'Template per notificare nuove assegnazioni',
            'settings' => json_encode([
                'auto_send' => true,
                'priority' => 'high',
            ]),
        ]);

        // Template zonali personalizzati
        foreach ($zones->take(2) as $zone) { // Solo alcuni esempi
            LetterTemplate::updateOrCreate([
                'name' => 'Convocazione ' . $zone->name . ' - Personalizzata'
            ], [
                'name' => 'Convocazione ' . $zone->name . ' - Personalizzata',
                'type' => 'convocation',
                'subject' => 'Convocazione Zona ' . $zone->name . ' - {{tournament_name}}',
                'body' => $this->getZoneSpecificTemplate($zone),
                'zone_id' => $zone->id,
                'tournament_type_id' => null,
                'is_active' => true,
                'variables' => json_encode([
                    'referee_name', 'tournament_name', 'tournament_dates',
                    'club_name', 'role', 'zone_name', 'zone_contact'
                ]),
                'description' => 'Template personalizzato per la zona ' . $zone->name,
                'settings' => json_encode([
                    'auto_send' => false,
                    'zone_branding' => true,
                ]),
            ]);
        }
    }

    /**
     * Create letterheads
     */
    private function createLetterheads()
    {
        $zones = Zone::all();

        // Letterhead nazionale
        Letterhead::updateOrCreate([
            'title' => 'Federazione Italiana Golf - Nazionale'
        ], [
            'zone_id' => null,
            'title' => 'Federazione Italiana Golf - Nazionale',
            'logo_path' => null, // Da configurare
            'header_text' => 'FEDERAZIONE ITALIANA GOLF',
            'header_content' => 'Commissione Regole e Competizioni',
            'footer_text' => 'Via del Golf, 123 - 00100 Roma',
            'footer_content' => 'Tel: +39 06 12345678 | Email: segreteria@figc.it | Web: www.figc.it',
            'contact_info' => json_encode([
                'address' => 'Via del Golf, 123 - 00100 Roma',
                'phone' => '+39 06 12345678',
                'email' => 'segreteria@figc.it',
                'website' => 'www.figc.it',
            ]),
            'is_active' => true,
            'is_default' => true,
            'settings' => json_encode([
                'margins' => ['top' => 25, 'bottom' => 20, 'left' => 25, 'right' => 25],
                'font' => ['family' => 'Arial', 'size' => 11, 'color' => '#000000'],
            ]),
        ]);

        // Letterheads zonali
        foreach ($zones->take(3) as $index => $zone) { // Solo alcuni esempi
            Letterhead::updateOrCreate([
                'title' => 'Federazione Italiana Golf - ' . $zone->name
            ], [
                'zone_id' => $zone->id,
                'title' => 'Federazione Italiana Golf - ' . $zone->name,
                'logo_path' => null,
                'header_text' => 'FEDERAZIONE ITALIANA GOLF',
                'header_content' => $zone->name,
                'footer_text' => $zone->address ?? ('Via del Golf ' . $zone->name),
                'footer_content' => ($zone->contact_phone ?? '+39 0X XXXXXXX') . ' | ' .
                                   ($zone->contact_email ?? strtolower($zone->code) . '@figc.it'),
                'contact_info' => json_encode([
                    'address' => $zone->address ?? 'Via del Golf ' . $zone->name,
                    'phone' => $zone->contact_phone ?? '+39 0X XXXXXXX',
                    'email' => $zone->contact_email ?? strtolower($zone->code) . '@figc.it',
                    'website' => $zone->website ?? 'www.figc.it',
                ]),
                'is_active' => true,
                'is_default' => $index === 0, // Prima zona come default zonale
                'settings' => json_encode([
                    'margins' => ['top' => 25, 'bottom' => 20, 'left' => 25, 'right' => 25],
                    'font' => ['family' => 'Arial', 'size' => 11, 'color' => '#000000'],
                ]),
            ]);
        }
    }

    /**
     * Get convocation template content
     */
    private function getConvocationTemplate(): string
    {
        return "Gentile {{referee_name}},

La informiamo che è stato convocato per svolgere il ruolo di {{role}} in occasione del torneo:

**{{tournament_name}}**
Date: {{tournament_dates}}
Circolo: {{club_name}}
Zona: {{zone_name}}

La preghiamo di confermare la Sua disponibilità entro 48 ore dalla ricezione della presente.

Per ulteriori informazioni può contattare la segreteria di zona.

Cordiali saluti,
La Segreteria";
    }

    /**
     * Get club template content
     */
    private function getClubTemplate(): string
    {
        return "Gentile {{contact_person}},

Con la presente comunichiamo gli arbitri assegnati per il torneo:

**{{tournament_name}}**
Date: {{tournament_dates}}

**Arbitri assegnati ({{total_referees}}):**
{{referee_list}}

Gli arbitri sono stati informati della loro assegnazione.

Cordiali saluti,
La Segreteria di Zona";
    }

    /**
     * Get assignment template content
     */
    private function getAssignmentTemplate(): string
    {
        return "Gentile {{referee_name}},

La informiamo di essere stato assegnato al seguente torneo:

**{{tournament_name}}**
Date: {{tournament_dates}}
Circolo: {{club_name}}
Ruolo: {{role}}
Assegnato da: {{assigned_by}}

{{assignment_notes}}

La assegnazione è stata confermata automaticamente.

Cordiali saluti";
    }

    /**
     * Get zone specific template
     */
    private function getZoneSpecificTemplate(Zone $zone): string
    {
        return "Gentile {{referee_name}},

A nome della {$zone->name}, La convoco per il torneo:

**{{tournament_name}}**
Date: {{tournament_dates}}
Circolo: {{club_name}}
Ruolo: {{role}}

Per informazioni specifiche della zona, può contattare:
{{zone_contact}}

Grazie per la Sua disponibilità.

Cordiali saluti,
La Segreteria {$zone->name}";
    }
}
